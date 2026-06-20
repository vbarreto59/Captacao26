<?php
// valor_agrupado_print.php – Lista de leads compartilhados agrupados por valor (para impressão)
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Garantir coluna compartilhado_parceiro
try {
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS compartilhado_parceiro TINYINT(1) DEFAULT 0");
} catch (PDOException $e) { /* ignora */ }

// ==========================================
// CONSULTA – somente compartilhados e não perdidos
// ==========================================
$busca = $_GET['busca'] ?? '';

$where = "WHERE compartilhado_parceiro = 1 AND (fase_funil IS NULL OR fase_funil != 'Perdido')";
$params = [];

if (!empty($busca)) {
    $where .= " AND (primeiro_nome LIKE ? OR obs_parceiros LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql = "SELECT id, nome, primeiro_nome, obs_parceiros, tipo_desejo,
               valor_max, tipo_pagamento, quartos_min, created_at
        FROM leads $where 
        ORDER BY valor_max ASC, id ASC"; // ordena do menor para o maior valor
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// AGRUPAR POR VALOR EXATO
// ==========================================
$grupos = [];
foreach ($leads as $lead) {
    $valor = (float)($lead['valor_max'] ?? 0);
    $chave = number_format($valor, 2, ',', '.');
    if (!isset($grupos[$chave])) {
        $grupos[$chave] = [];
    }
    $grupos[$chave][] = $lead;
}
// Ordenar os grupos por valor (chave numérica)
ksort($grupos, SORT_NUMERIC);

// ==========================================
// INÍCIO DA PÁGINA (SEM HEADER/FOOTER DO SISTEMA PARA IMPRESSÃO)
// ==========================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads_Valter_Barreto_CRECI_PE_22003</title>
    <style>
        /* RESET e configurações para impressão */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: white;
            color: #1a1a1a;
            padding: 0.4in;
            font-size: 10pt;
            line-height: 1.5;
        }
        .container {
            max-width: 100%;
        }

        /* Cabeçalho da página */
        .header {
            text-align: center;
            margin-bottom: 20pt;
            border-bottom: 2px solid #333;
            padding-bottom: 8pt;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header .sub {
            font-size: 9pt;
            color: #555;
            margin-top: 4pt;
        }

        /* Cada grupo de valor */
        .grupo-valor {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 18pt;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10pt 12pt;
            background: #fafafa;
        }
        .grupo-valor .cabecalho {
            font-weight: 700;
            font-size: 12pt;
            background: #e9ecef;
            padding: 6pt 10pt;
            margin: -10pt -12pt 10pt -12pt;
            border-radius: 6px 6px 0 0;
            border-bottom: 1px solid #ccc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .grupo-valor .cabecalho .qtde {
            font-size: 9pt;
            font-weight: 400;
            color: #555;
        }

        /* Cada lead dentro do grupo */
        .lead-item {
            padding: 4pt 0;
            border-bottom: 0.5px dashed #ddd;
            display: flex;
            flex-wrap: wrap;
            gap: 4pt 12pt;
        }
        .lead-item:last-child {
            border-bottom: none;
        }
        .lead-item .id {
            font-weight: 600;
            color: #0d6efd;
            min-width: 50pt;
        }
        .lead-item .nome {
            font-weight: 600;
            min-width: 140pt;
        }
        .lead-item .nome small {
            font-weight: 400;
            color: #888;
            font-size: 8pt;
        }
        .lead-item .quartos {
            color: #2c3e50;
            min-width: 70pt;
        }
        .lead-item .pagamento {
            background: #e9ecef;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 8pt;
            color: #495057;
            white-space: nowrap;
        }
        .lead-item .obs {
            flex: 1 1 100%;
            color: #444;
            font-size: 9pt;
            padding-left: 50pt;
            margin-top: 2pt;
            font-style: italic;
        }
        .lead-item .obs::before {
            content: "📝 ";
        }

        /* Rodapé com data/hora */
        .footer {
            margin-top: 24pt;
            font-size: 7.5pt;
            text-align: center;
            color: #777;
            border-top: 1px solid #ccc;
            padding-top: 8pt;
        }

        /* Filtro de busca (visível na tela, mas escondido na impressão) */
        .filtro-busca {
            background: #f8f9fa;
            padding: 10pt;
            border-radius: 6px;
            margin-bottom: 16pt;
            display: flex;
            gap: 10pt;
            align-items: center;
            flex-wrap: wrap;
        }
        .filtro-busca input, .filtro-busca button {
            padding: 6pt 12pt;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 10pt;
        }
        .filtro-busca button {
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
        }
        .filtro-busca a {
            color: #dc3545;
            text-decoration: none;
            font-size: 9pt;
        }

        /* Ocultar elementos interativos na impressão */
        @media print {
            .filtro-busca {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
            body {
                padding: 0.2in;
                font-size: 9pt;
            }
            .grupo-valor {
                page-break-inside: avoid;
                break-inside: avoid;
                border-color: #aaa;
                background: white;
            }
            .grupo-valor .cabecalho {
                background: #f1f3f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .lead-item .pagamento {
                background: #e9ecef !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .lead-item {
                border-bottom-color: #ddd;
            }
        }

        /* Tela (pré-visualização) */
        @media screen {
            body {
                background: #f4f6f9;
                padding: 20px;
            }
            .container {
                max-width: 1100px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 16px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            }
            .grupo-valor {
                background: white;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Cabeçalho da página -->
    <div class="header">
        <h1>📋 Leads de Valter Barreto - CRECI-PE: 22003</h1>
        <div class="sub">
            Gerado em <?= date('d/m/Y H:i') ?> &bull; Total: <?= count($leads) ?> leads
            <?php if (!empty($busca)): ?>
                &bull; Filtro: "<?= htmlspecialchars($busca) ?>"
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtro (visível apenas na tela) -->
    <div class="filtro-busca no-print">
        <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="busca" placeholder="Filtrar nome ou observação..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit"><i class="bi bi-search"></i> Filtrar</button>
            <a href="?">Limpar</a>
        </form>
        <div style="margin-left:auto;">
            <button onclick="window.print()" style="background:#28a745; color:white; border:none; padding:6pt 16pt; border-radius:4px; cursor:pointer;">
                🖨️ Imprimir
            </button>
        </div>
    </div>

    <?php if (empty($grupos)): ?>
        <p style="text-align:center; padding:40pt 0; color:#888;">Nenhum lead compartilhado ativo encontrado.</p>
    <?php else: ?>
        <?php foreach ($grupos as $valor_formatado => $lista): ?>
            <div class="grupo-valor">
                <div class="cabecalho">
                    <span>💰 Valor: R$ <?= $valor_formatado ?></span>
                    <span class="qtde"><?= count($lista) ?> lead(s)</span>
                </div>

                <?php foreach ($lista as $lead): 
                    $id = 'L' . str_pad($lead['id'], 3, '0', STR_PAD_LEFT);
                    $nome = htmlspecialchars($lead['primeiro_nome'] ?: $lead['nome'] ?: '(nome não informado)');
                    $obs = htmlspecialchars($lead['obs_parceiros'] ?? '');
                    $quartos = (int)($lead['quartos_min'] ?? 0);
                    $tipo_pag = htmlspecialchars($lead['tipo_pagamento'] ?? '');
                    $tipo_desejo = htmlspecialchars($lead['tipo_desejo'] ?? '');

                    // Dias desde o cadastro
                    $dias = '';
                    if (!empty($lead['created_at'])) {
                        $criacao = new DateTime($lead['created_at']);
                        $hoje = new DateTime('now');
                        $diff = $criacao->diff($hoje);
                        $dias = $diff->days;
                    }
                ?>
                <div class="lead-item">
                    <span class="id"><?= $id ?></span>
                    <span class="nome">
                        <?= $nome ?>
                        <?php if ($dias > 0): ?>
                            <small>(<?= $dias ?> dias)</small>
                        <?php endif; ?>
                    </span>
                    <?php if ($quartos > 0): ?>
                        <span class="quartos">🏠 <?= $quartos ?> qts</span>
                    <?php endif; ?>
                    <?php if (!empty($tipo_desejo)): ?>
                        <span style="background:#e2e3e5; padding:0 6px; border-radius:10px; font-size:8pt;"><?= $tipo_desejo ?></span>
                    <?php endif; ?>
                    <?php if (!empty($tipo_pag)): ?>
                        <span class="pagamento">💳 <?= $tipo_pag ?></span>
                    <?php endif; ?>
                    <?php if (!empty($obs)): ?>
                        <div class="obs"><?= $obs ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Rodapé -->
    <div class="footer">
        Documento gerado automaticamente – Lista de leads compartilhados agrupados por valor.
        <?php if (!empty($busca)): ?> Filtro aplicado: "<?= htmlspecialchars($busca) ?>" <?php endif; ?>
    </div>
</div>

<!-- Ícones Bootstrap (para visualização) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
    // Melhorar a experiência de impressão: ajustar margens
    document.querySelector('.filtro-busca button[onclick]')?.addEventListener('click', function(e) {
        if (e.target.textContent.includes('Imprimir')) {
            window.print();
        }
    });
</script>
</body>
</html>