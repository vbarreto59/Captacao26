<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$ids = $_GET['ids'] ?? '';
if (!preg_match('/^[0-9,]+$/', $ids)) {
    die("Selecione ao menos um imóvel.");
}

$sql = "SELECT i.*, p.nome as nome_proprietario,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id ORDER BY capa DESC, id ASC LIMIT 1) AS foto_capa,
        (SELECT GROUP_CONCAT(c.nome SEPARATOR ' / ') 
         FROM imovel_parceiros ip 
         JOIN corretores c ON ip.corretor_id = c.id 
         WHERE ip.imovel_id = i.id) AS nomes_parceiros
        FROM imoveis i 
        LEFT JOIN proprietarios p ON i.proprietario_id = p.id 
        WHERE i.id IN ($ids)
        ORDER BY FIELD(i.id, $ids)";

$stmt = $conn->query($sql);
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lista de campos booleanos para checagem dinâmica
$comodidades_labels = [
    'mobiliado' => 'Mobiliado',
    'gas_encanado' => 'Gás Encanado',
    'tem_piscina' => 'Piscina',
    'tem_academia' => 'Academia',
    'tem_salao_festas' => 'Salão de Festas',
    'tem_espaco_gourmet' => 'Espaço Gourmet',
    'tem_playground' => 'Playground',
    'possui_elevador' => 'Elevador',
    'possui_moveis_planejados' => 'Móveis Planejados',
    'aceita_financiamento' => 'Aceita Financiamento',
    'aceita_permuta' => 'Aceita Permuta'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Estudo - <?= date('d/m/Y') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; color: #333; }
        .no-print { background: #1a1d20; color: white; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 100; }
        .btn-print { background: #198754; color: white; border: none; padding: 10px 25px; font-weight: bold; border-radius: 5px; cursor: pointer; }

        .pagina-a4 { 
            background: white; width: 210mm; min-height: 297mm; 
            margin: 20px auto; padding: 12mm; 
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            page-break-after: always;
            position: relative;
        }

        .header-estudo { border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 15px; }
        .titulo-linha { display: flex; justify-content: space-between; align-items: flex-start; }
        
        .caracteristicas-bar { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .caract-item { display: flex; align-items: center; gap: 5px; font-size: 0.85rem; background: #f0f7ff; padding: 4px 10px; border-radius: 4px; border: 1px solid #d0e3ff; }
        .caract-item i { color: #0d6efd; }

        .bloco-topo { display: flex; gap: 20px; margin-bottom: 20px; align-items: flex-start; }
        .foto-quadrada { width: 185px; height: 185px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; flex-shrink: 0; }

        .info-topo-lateral { flex-grow: 1; }
        .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        
        .secao-titulo { 
            background: #f8f9fa; padding: 4px 8px; font-weight: bold; 
            border-left: 4px solid #0d6efd; margin: 10px 0 8px 0; 
            font-size: 0.8rem; text-transform: uppercase;
        }

        .texto-caracteristicas { font-size: 0.85rem; line-height: 1.4; color: #444; white-space: pre-wrap; }
        .info-item { margin-bottom: 3px; font-size: 0.82rem; }
        .info-label { font-weight: bold; color: #666; width: 100px; display: inline-block; }

        .item-true { color: #155724; background: #d4edda; padding: 2px 6px; border-radius: 3px; display: inline-block; margin: 2px; font-size: 0.75rem; }

        .campo-notas { border: 1px dashed #adb5bd; height: 340px; margin-top: 10px; border-radius: 4px; background: #fffefb; }

        @media print {
            body { background: white; }
            .no-print { display: none; }
            .pagina-a4 { margin: 0; box-shadow: none; width: 100%; padding: 10mm; }
            .campo-notas { border: 1px solid #eee; background: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> IMPRIMIR FICHAS (<?= count($imoveis) ?>)
        </button>
    </div>

    <?php foreach ($imoveis as $im): 
        $foto = $im['foto_capa'] ? '../../uploads/fotos_imoveis/' . $im['foto_capa'] : 'https://via.placeholder.com/200x200?text=Sem+Foto';
    ?>
    <div class="pagina-a4">
        <div class="header-estudo">
            <div class="titulo-linha">
                <div>
                    <h1 style="margin:0; font-size: 1.5rem; color: #0d6efd;"><?= htmlspecialchars($im['titulo']) ?></h1>
                    <div style="font-size: 0.9rem; color: #666;">
                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($im['bairro']) ?> - <?= htmlspecialchars($im['cidade']) ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.3rem; font-weight: bold; color: #d32f2f;">R$ <?= number_format($im['preco'], 2, ',', '.') ?></div>
                    <small class="text-muted">ID: <?= $im['id'] ?> | <?= ucfirst($im['tipo']) ?></small>
                </div>
            </div>

            <div class="caracteristicas-bar">
                <div class="caract-item"><i class="bi bi-arrows-fullscreen"></i> <?= number_format($im['area'], 0) ?> m²</div>
                <div class="caract-item"><i class="bi bi-door-open"></i> <?= $im['quartos'] ?> Qts</div>
                <?php if($im['suites'] > 0): ?>
                    <div class="caract-item"><i class="bi bi-bookmark-star"></i> <?= $im['suites'] ?> Suítes</div>
                <?php endif; ?>
                <div class="caract-item"><i class="bi bi-car-front"></i> <?= $im['vagas_garagem'] ?> Vagas</div>
                <?php if($im['andar'] > 0): ?>
                    <div class="caract-item"><i class="bi bi-layers"></i> <?= $im['andar'] ?>º Andar</div>
                <?php endif; ?>
                <?php if($im['face'] && $im['face'] !== ''): ?>
                    <div class="caract-item"><i class="bi bi-brightness-high"></i> Face <?= ucfirst($im['face']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bloco-topo">
            <img src="<?= $foto ?>" class="foto-quadrada">
            
            <div class="info-topo-lateral">
                <div class="secao-titulo" style="margin-top:0;">Logística e Custos</div>
                <div class="info-item"><span class="info-label">Proprietário:</span> <?= htmlspecialchars($im['nome_proprietario'] ?? 'N/I') ?></div>
                <!-- EXIBIÇÃO DOS CORRETORES PARCEIROS -->
                <?php if (!empty($im['nomes_parceiros'])): ?>
                <div class="info-item"><span class="info-label">Corretores Parceiros:</span> <strong><?= htmlspecialchars($im['nomes_parceiros']) ?></strong></div>
                <?php endif; ?>
                <div class="info-item"><span class="info-label">Endereço:</span> <?= htmlspecialchars($im['endereco'] ?? 'Ver no Sistema') ?></div>
                <div class="info-item"><span class="info-label">Condomínio:</span> R$ <?= number_format($im['valor_condominio'], 2, ',', '.') ?></div>
                <div class="info-item"><span class="info-label">IPTU:</span> R$ <?= number_format($im['valor_iptu'], 2, ',', '.') ?></div>
                
                <?php if($im['rip_marinha'] && $im['rip_marinha'] != ''): ?>
                    <div class="info-item" style="color: #055160; background: #e2f3f5; padding: 4px 8px; border-radius: 4px; margin-top: 5px; font-size: 0.75rem;">
                        <strong>MARINHA:</strong> RIP <?= $im['rip_marinha'] ?> (<?= ucfirst($im['regime_marinha']) ?>)
                        <?php if($im['valor_foro_anual'] > 0): ?> | Foro: R$ <?= number_format($im['valor_foro_anual'], 2, ',', '.') ?><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-info">
            <div>
                <div class="secao-titulo">Descrição / Diferenciais</div>
                <div class="texto-caracteristicas" style="margin-bottom: 15px;"><?= htmlspecialchars($im['descricao'] ?: $im['observacoes_gerais']) ?></div>
                
                <div class="secao-titulo">O que o imóvel possui</div>
                <div style="line-height: 1.8;">
                    <?php 
                    $tem_algo = false;
                    foreach ($comodidades_labels as $campo => $label) {
                        if (isset($im[$campo]) && $im[$campo] == 1) {
                            echo "<span class='item-true'><i class='bi bi-check2'></i> $label</span> ";
                            $tem_algo = true;
                        }
                    }
                    if (!$tem_algo) echo "<small class='text-muted'>Nenhum item marcado.</small>";
                    ?>
                </div>

                <div class="secao-titulo" style="margin-top:20px;">Checklist de Visita</div>
                <div style="font-size: 0.8rem; color: #666;">
                    <i class="bi bi-square"></i> Posição Solar exata<br>
                    <i class="bi bi-square"></i> Estado das instalações elétricas/hidráulicas<br>
                    <i class="bi bi-square"></i> Itens que sairão do imóvel (se mobiliado)<br>
                    <i class="bi bi-square"></i> Vizinhança e ruídos externos
                </div>
            </div>

            <div>
                <div class="secao-titulo">Anotações e Objeções (Lead)</div>
                <div class="campo-notas"></div>
            </div>
        </div>

        <div style="position: absolute; bottom: 8mm; left: 12mm; right: 12mm; border-top: 1px solid #eee; padding-top: 5px; text-align: center; font-size: 0.7rem; color: #bbb;">
            Ficha de Estudo - CRECI-PE 22003 | Gerado em <?= date('d/m/Y H:i') ?>
        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>