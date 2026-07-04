<?php
// contatos_hoje_print.php – Versão compacta para impressão (inclui valor_max, tipo_pagamento, fase_funil, imóveis e agenda)
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Buscar leads com contatar_hoje = 1, incluindo favorito, valor_max, tipo_pagamento, fase_funil, imóveis e próxima agenda ativa
$sql = "SELECT l.id, l.nome, l.telefone, l.temperatura, l.observacoes, l.favorito,
               l.valor_max, l.tipo_pagamento, l.fase_funil,
               COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
               GROUP_CONCAT(DISTINCT i.titulo ORDER BY i.titulo ASC SEPARATOR '||') AS imoveis_titulos,
               GROUP_CONCAT(DISTINCT i.id ORDER BY i.titulo ASC SEPARATOR ',') AS imoveis_ids,
               (SELECT DATE_FORMAT(data_evento, '%d/%m %H:%i') 
                FROM agenda_geral 
                WHERE lead_id = l.id 
                  AND data_evento >= NOW() 
                  AND status IN ('agendado', 'confirmado', 'pendente') 
                ORDER BY data_evento ASC LIMIT 1) AS proxima_visita
        FROM leads l
        LEFT JOIN lead_imoveis li ON l.id = li.lead_id
        LEFT JOIN imoveis i ON li.imovel_id = i.id AND i.deleted_at IS NULL
        WHERE l.contatar_hoje = 1 and fase_funil <> 'perdido'
        GROUP BY l.id";

$stmt = $conn->prepare($sql);
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== ORDENAÇÃO POR TEMPERATURA ==========
$ordemTemp = ['Quente' => 0, 'Morno' => 1, 'Frio' => 2];
usort($leads, function($a, $b) use ($ordemTemp) {
    $tempA = $ordemTemp[$a['temperatura'] ?? 'Morno'] ?? 1;
    $tempB = $ordemTemp[$b['temperatura'] ?? 'Morno'] ?? 1;
    if ($tempA != $tempB) return $tempA <=> $tempB;
    if ($a['favorito'] != $b['favorito']) return $b['favorito'] <=> $a['favorito'];
    return $b['id'] <=> $a['id'];
});

$temLeads = count($leads) > 0;

// ========== FUNÇÃO PARA ÍCONE DE TEMPERATURA ==========
function tempIcon($temp) {
    $map = ['Quente' => '🔥', 'Morno' => '🌤️', 'Frio' => '❄️'];
    return $map[$temp] ?? '🌤️';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Contatos de Hoje – Versão Impressão</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;
            background: white;
            color: black;
            margin: 0;
            padding: 0.5in;
            font-size: 9pt;
            line-height: 1.2;
        }
        h1 {
            font-size: 14pt;
            margin-bottom: 6pt;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .subtitle {
            font-size: 8pt;
            color: #555;
            border-bottom: 1px solid #ccc;
            margin-bottom: 12pt;
            padding-bottom: 4pt;
        }
        .tabela-impressao {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        .tabela-impressao th,
        .tabela-impressao td {
            border: 0.5px solid #aaa;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
        }
        .tabela-impressao th {
            background-color: #f1f3f5;
            font-weight: 600;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .tabela-impressao thead {
            display: table-header-group;
        }
        .tabela-impressao tbody tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .temp-quente { background-color: #fff0f0; }
        .temp-morno { background-color: #fff8e7; }
        .temp-frio { background-color: #eef7ff; }
        .obs-cell {
            max-width: 220px;
            word-wrap: break-word;
            white-space: normal;
        }
        .footer {
            margin-top: 16pt;
            font-size: 7pt;
            text-align: center;
            color: #777;
            border-top: 0.5px dashed #ccc;
            padding-top: 8pt;
        }
        .favorito-star {
            font-size: 10pt;
            margin-left: 4px;
        }
        .favorito-star.ativo {
            color: #f1c40f;
        }
        .favorito-star.inativo {
            color: #ccc;
        }
        .temp-icon {
            margin-left: 4px;
            font-size: 10pt;
        }
        .valor-pagamento-cell {
            font-size: 8pt;
            white-space: normal;  /* permite quebra de linha */
        }
        .valor-pagamento-cell .valor {
            font-weight: 600;
            color: #1a1a1a;
        }
        .valor-pagamento-cell .pagamento {
            display: inline-block;
            margin-left: 6px;
            background: #e9ecef;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 7pt;
            color: #495057;
        }
        .agenda-info {
            display: block;
            margin-top: 4px;
            font-size: 7.5pt;
            color: #92400e;
            background: #fef3c7;
            padding: 1px 6px;
            border-radius: 10px;
            width: fit-content;
            font-weight: 600;
        }
        .fase-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 7pt;
            color: #495057;
            margin-top: 2px;
        }
        .imovel-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 7pt;
            margin: 1px 2px 0 0;
            white-space: nowrap;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .imoveis-container {
            margin-top: 4px;
        }
        @media screen {
            body { margin: 20px; background: #f4f6f9; }
            .tabela-impressao { background: white; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
            .tabela-impressao th { background-color: #e9ecef; }
        }
        @media print {
            body { margin: 0.4in; padding: 0; }
            .tabela-impressao td, .tabela-impressao th { padding: 3px 4px; }
            .obs-cell { max-width: 180px; }
            .footer { position: fixed; bottom: 0; width: 100%; }
            .valor-pagamento-cell .pagamento { background: #eee; }
            .agenda-info { background: #fef3c7; }
            .fase-badge { background: #eee; }
            .imovel-badge { background: #dbeafe; }
        }
    </style>
</head>
<body>

<h1>📞 Contatos de Hoje – Lista de Trabalho</h1>
<div class="subtitle">
    Gerado em <?= date('d/m/Y H:i') ?> | <?= count($leads) ?> lead(s) agendado(s)
    <?php 
    $favoritos = array_filter($leads, fn($l) => $l['favorito'] == 1);
    if (count($favoritos) > 0) {
        echo ' | ⭐ ' . count($favoritos) . ' favorito(s)';
    }
    ?>
</div>

<?php if (!$temLeads): ?>
    <p style="margin-top: 20pt; font-style: italic;">Nenhum lead com contato agendado para hoje.</p>
<?php else: ?>
    <table class="tabela-impressao">
        <thead>
            <tr>
                <th style="width: 45px;">#</th>
                <th style="width: 280px;">Nome / Fase / Imóveis</th>
                <th style="width: 130px;">Valor / Pagamento / Agenda</th>
                <th>Observações (Lead)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $contador = 1;
            foreach ($leads as $lead):
                $temp = $lead['temperatura'] ?: 'Morno';
                $classeTemp = '';
                if ($temp == 'Quente') $classeTemp = 'temp-quente';
                elseif ($temp == 'Frio') $classeTemp = 'temp-frio';
                else $classeTemp = 'temp-morno';
                
                $telefone = htmlspecialchars($lead['telefone']);
                $apenas_numeros = preg_replace('/\D/', '', $lead['telefone']);
                $telefone = '******' . substr($apenas_numeros, -4);

                $nome = htmlspecialchars($lead['nome']);
                $observacoes = htmlspecialchars($lead['observacoes'] ?? '');
                $favorito = (int)($lead['favorito'] ?? 0);
                $estrela = $favorito ? '★' : '☆';
                $classeEstrela = $favorito ? 'ativo' : 'inativo';
                
                $valor_max = (float)($lead['valor_max'] ?? 0);
                $valor_formatado = $valor_max > 0 ? 'R$ ' . number_format($valor_max, 0, ',', '.') : '—';
                $tipo_pagamento = htmlspecialchars($lead['tipo_pagamento'] ?? '');
                
                $fase = htmlspecialchars($lead['fase_funil'] ?? '');
                
                // ====== IMÓVEIS ======
                $imoveis_titulos = !empty($lead['imoveis_titulos']) ? explode('||', $lead['imoveis_titulos']) : [];
                $total_imoveis = count($imoveis_titulos);
                
                $iconTemp = tempIcon($temp);

                // ====== PRÓXIMA AGENDA (tabela agenda_geral) ======
                $proxima_agenda = $lead['proxima_visita'] ?? null;
                $info_agenda = '';
                if (!empty($proxima_agenda)) {
                    $info_agenda = '📅 ' . $proxima_agenda;
                }
            ?>
            <tr class="<?= $classeTemp ?>">
                <td style="text-align: center;">
                    <?= $contador++ ?>
                    <span class="favorito-star <?= $classeEstrela ?>"><?= $estrela ?></span>
                    <span class="temp-icon"><?= $iconTemp ?></span>
                </td>
                <td>
                    <strong><?= $nome ?></strong><br>
                    <span style="font-size: 8pt; color: #2c3e50;">📞 <?= $telefone ?></span>
                    <?php if (!empty($fase)): ?>
                        <span class="fase-badge">📌 Fase: <?= $fase ?></span>
                    <?php endif; ?>
                    <!-- IMÓVEIS ABAIXO DA FASE -->
                    <?php if ($total_imoveis > 0): ?>
                        <div class="imoveis-container">
                            <?php foreach ($imoveis_titulos as $titulo): ?>
                                <span class="imovel-badge">
                                    <i style="font-style:normal;">🏠</i> <?= htmlspecialchars($titulo) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="valor-pagamento-cell">
                    <span class="valor"><?= $valor_formatado ?></span>
                    <?php if (!empty($tipo_pagamento)): ?>
                        <span class="pagamento"><?= $tipo_pagamento ?></span>
                    <?php endif; ?>
                    <?php if (!empty($info_agenda)): ?>
                        <span class="agenda-info"><?= $info_agenda ?></span>
                    <?php endif; ?>
                </td>
                <td class="obs-cell">
                    <?= empty($observacoes) ? '—' : $observacoes ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="footer">
    Documento gerado automaticamente – Lista de contatos prioritários do dia.
</div>

</body>
</html>