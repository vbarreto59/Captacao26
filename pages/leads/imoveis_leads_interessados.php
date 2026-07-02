<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// FILTRO POR NOME DO IMÓVEL (GET)
// ==========================================
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// ==========================================
// CONSULTA: APENAS IMÓVEIS COM LEADS INTERESSADOS
// ==========================================
$sql = "
    SELECT 
        i.id AS imovel_id,
        i.titulo,
        i.bairro,
        i.tipo,
        i.preco,
        i.status,
        GROUP_CONCAT(
            CONCAT(l.id, '|', l.nome, '|', l.telefone, '|', l.fase_funil) 
            SEPARATOR ';;'
        ) AS leads_info,
        COUNT(li.lead_id) AS total_leads
    FROM imoveis i
    INNER JOIN lead_imoveis li ON i.id = li.imovel_id
    INNER JOIN leads l ON li.lead_id = l.id AND l.deleted_at IS NULL
    WHERE i.deleted_at IS NULL
";

if (!empty($busca)) {
    $sql .= " AND i.titulo LIKE :busca";
}

$sql .= " GROUP BY i.id ORDER BY i.titulo ASC";

$stmt = $conn->prepare($sql);
if (!empty($busca)) {
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt->execute();
}
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Imóveis com Leads Interessados - CRM Imobiliário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; font-weight: 600; border: none; }
        .table-hover tbody tr:hover { background-color: #f8f9ff; }
        .badge-lead {
            background-color: #e9ecef;
            color: #0d6efd;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 2px 4px 2px 0;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-lead:hover { background-color: #0d6efd; color: white; transition: 0.2s; }
        .badge-lead a { color: inherit; text-decoration: none; }
        .badge-lead a:hover { color: white; }

        .search-box { border-radius: 12px; padding: 0.6rem 1rem; border: 1px solid #e0e3e9; }
        .search-box:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); }
        .table th { border-top: none; font-weight: 600; color: #4a5568; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .total-badge { background: rgba(255,255,255,0.2); border-radius: 30px; padding: 0.2rem 1rem; font-size: 0.9rem; }
        .lead-list { max-height: 120px; overflow-y: auto; scrollbar-width: thin; }
        .lead-list::-webkit-scrollbar { width: 4px; }
        .lead-list::-webkit-scrollbar-thumb { background: #0d6efd; border-radius: 10px; }

        /* ===== MOBILE FIRST ===== */
        @media (max-width: 768px) {
            /* Transforma a tabela em cartões */
            .table-responsive { border: 0; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table tr {
                background: #ffffff;
                border-radius: 16px;
                margin-bottom: 20px;
                padding: 16px 14px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                border: 1px solid #e9ecef !important;
            }
            .table td {
                padding: 6px 0 !important;
                text-align: left !important;
                border: none !important;
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
            }
            .table td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.7rem;
                text-transform: uppercase;
                font-weight: 700;
                color: #8896a8;
                letter-spacing: 0.5px;
                margin-bottom: 2px;
                width: 100%;
            }
            /* Ocultar o label de colunas que não precisam (ex: ações, #) */
            .table td:first-child::before { display: none; }
            .table td:first-child { font-weight: bold; font-size: 0.9rem; color: #6c757d; }

            .badge-lead {
                white-space: normal;
                font-size: 0.75rem;
                padding: 6px 10px;
                display: inline-flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 4px;
                margin: 2px 4px 2px 0;
            }
            .lead-list {
                max-height: none;
                overflow: visible;
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }
            .btn-group { display: flex; width: 100%; }
            .btn-group .btn { flex: 1; }

            /* Ajuste do cabeçalho e busca */
            .container { padding: 0 12px; }
            .d-flex.gap-2 { gap: 8px !important; }
            .btn { font-size: 0.9rem; padding: 8px 12px; }
            .search-box { font-size: 0.9rem; }
            .card-header .total-badge { font-size: 0.75rem; }
        }

        /* ========================================== */
        /* ===== ESTILOS DE IMPRESSÃO AQUI ===== */
        /* ========================================== */
        @media print {
            /* Oculta elementos não essenciais para impressão */
            .no-print,
            .btn,
            .search-box,
            form,
            .card-footer,
            .d-flex.gap-2,
            a[href*="imovel_form.php"],
            a[href*="index.php"] {
                display: none !important;
            }

            /* Remove sombras e fundos coloridos */
            body {
                background-color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 0 !important;
            }

            .card-header {
                background: #fff !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
                padding: 8px 12px !important;
            }

            .card-header .total-badge {
                background: none !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }

            /* Tabela otimizada para impressão */
            .table {
                font-size: 11pt !important;
                width: 100% !important;
            }

            .table th {
                background-color: #e9ecef !important;
                color: #000 !important;
                font-weight: bold !important;
                border: 1px solid #adb5bd !important;
                padding: 6px 8px !important;
            }

            .table td {
                border: 1px solid #dee2e6 !important;
                padding: 6px 8px !important;
                vertical-align: top !important;
            }

            .table tbody tr {
                page-break-inside: avoid;
            }

            /* Badges de leads em impressão */
            .badge-lead {
                background-color: #f8f9fa !important;
                color: #000 !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 4px !important;
                font-size: 9pt !important;
                padding: 2px 6px !important;
                margin: 1px 2px 1px 0 !important;
                display: inline-block !important;
            }

            .badge-lead a {
                color: #000 !important;
                text-decoration: none !important;
            }

            .badge-lead .bi-whatsapp,
            .badge-lead a[href^="tel:"] {
                display: none !important;
            }

            .lead-list {
                max-height: none !important;
                overflow: visible !important;
            }

            /* Badges de tipo e status */
            .badge {
                border: 1px solid #adb5bd !important;
                background-color: #f8f9fa !important;
                color: #000 !important;
                font-weight: normal !important;
            }

            /* Container ajustado */
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }

            /* Cabeçalho de impressão */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #000;
            }

            .print-header h2 {
                margin: 0;
                font-size: 16pt;
            }

            .print-header p {
                margin: 5px 0 0;
                font-size: 10pt;
                color: #666;
            }

            /* Quebra de página inteligente */
            .card {
                page-break-inside: avoid;
            }

            tr {
                page-break-inside: avoid;
            }

            /* Remove gradientes e efeitos visuais */
            * {
                box-shadow: none !important;
                text-shadow: none !important;
            }

            /* Ajuste de cores para preto e branco */
            .text-primary, .text-success, .text-warning, .text-info, .text-secondary, .text-dark {
                color: #000 !important;
            }

            .bg-white.rounded-circle.p-2.shadow-sm {
                display: none !important;
            }

            /* Ajuste do título principal */
            h1.display-6 {
                font-size: 14pt !important;
                margin-bottom: 5px !important;
            }

            .text-muted {
                color: #666 !important;
            }
        }

        /* Esconde o cabeçalho de impressão na tela */
        .print-header {
            display: none;
        }
    </style>
</head>
<body>

<!-- Cabeçalho visível apenas na impressão -->
<div class="print-header">
    <h2>Imóveis com Leads Interessados</h2>
    <p>Relatório gerado em <?= date('d/m/Y H:i') ?> | Total: <?= array_sum(array_column($imoveis, 'total_leads')) ?> leads em <?= count($imoveis) ?> imóvel(is)</p>
</div>

<div class="container py-4">

    <!-- CABEÇALHO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 no-print">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white rounded-circle p-2 shadow-sm">
                <i class="bi bi-building fs-4 text-primary"></i>
            </div>
            <div>
                <h1 class="display-6 fw-bold mb-0" style="font-size: 1.8rem;">
                    Imóveis com Leads Interessados
                </h1>
                <p class="text-muted mt-2 mb-0">
                    <i class="bi bi-houses"></i> <?= count($imoveis) ?> imóveis com interesse
                    <?php if(!empty($busca)): ?>
                        <span class="badge bg-secondary ms-2">Filtro: "<?= htmlspecialchars($busca) ?>"</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <a href="index.php" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
            <a href="../imoveis/imovel_form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Novo Imóvel
            </a>
            <button onclick="window.print()" class="btn btn-success shadow-sm">
                <i class="bi bi-printer me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- BUSCA POR IMÓVEL -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-8 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="busca" class="form-control search-box" 
                               placeholder="Filtrar imóveis por título..." 
                               value="<?= htmlspecialchars($busca) ?>">
                    </div>
                </div>
                <div class="col-md-4 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    <?php if(!empty($busca)): ?>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA DE IMÓVEIS E LEADS -->
    <div class="card shadow-sm">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2"></i> Relação de Imóveis x Leads</span>
            <span class="total-badge">
                <i class="bi bi-people"></i> Total: <?= array_sum(array_column($imoveis, 'total_leads')) ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Imóvel</th>
                            <th>Bairro</th>
                            <th>Tipo</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Leads Interessados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($imoveis)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    <?php if(!empty($busca)): ?>
                                        Nenhum imóvel com leads interessados encontrado para o filtro "<?= htmlspecialchars($busca) ?>".
                                        <br><small>Tente ajustar os termos da busca.</small>
                                    <?php else: ?>
                                        Nenhum imóvel possui leads interessados no momento.
                                        <br><small>Cadastre interesses na página de gerenciamento de leads.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($imoveis as $imovel): 
                                // Processa os leads
                                $leads = [];
                                if (!empty($imovel['leads_info'])) {
                                    $parts = explode(';;', $imovel['leads_info']);
                                    foreach ($parts as $part) {
                                        $data = explode('|', $part);
                                        if (count($data) >= 4) {
                                            $leads[] = [
                                                'id' => $data[0],
                                                'nome' => $data[1],
                                                'telefone' => $data[2],
                                                'fase' => $data[3]
                                            ];
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td data-label="#" class="fw-bold"><?= $imovel['imovel_id'] ?></td>
                                    <td data-label="Imóvel">
                                        <span class="fw-semibold"><?= htmlspecialchars($imovel['titulo']) ?></span>
                                    </td>
                                    <td data-label="Bairro"><?= htmlspecialchars($imovel['bairro']) ?></td>
                                    <td data-label="Tipo">
                                        <?php 
                                            $tipo = ucfirst($imovel['tipo'] ?? '—');
                                            $cor_tipo = match($tipo) {
                                                'Apartamento' => 'primary',
                                                'Casa' => 'success',
                                                'Terreno' => 'warning',
                                                'Comercial' => 'info',
                                                'Studio' => 'secondary',
                                                'Flat' => 'dark',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $cor_tipo ?>"><?= $tipo ?></span>
                                    </td>
                                    <td data-label="Preço">
                                        <?php if($imovel['preco'] > 0): ?>
                                            R$ <?= number_format($imovel['preco'], 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php 
                                            $status = $imovel['status'] ?? 'captado';
                                            $cor_status = match($status) {
                                                'captado' => 'info',
                                                'em_negociacao' => 'warning',
                                                'vendido' => 'success',
                                                'parceria' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $cor_status ?>"><?= ucfirst(str_replace('_', ' ', $status)) ?></span>
                                    </td>
                                    <td data-label="Leads Interessados">
                                        <div class="lead-list">
                                            <?php foreach($leads as $lead): ?>
                                                <div class="badge-lead d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-person-circle"></i>
                                                    <a href="../lead_form.php?id=<?= $lead['id'] ?>" 
                                                       class="text-decoration-none text-reset" 
                                                       title="Editar lead">
                                                        <?= htmlspecialchars($lead['nome']) ?>
                                                    </a>
                                                    <span class="text-muted small">(<?= $lead['fase'] ?>)</span>
                                                    <?php if(!empty($lead['telefone'])): ?>
                                                        <a href="tel:<?= $lead['telefone'] ?>" class="text-success">
                                                            <i class="bi bi-whatsapp"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if(!empty($imoveis)): ?>
            <div class="card-footer bg-white text-muted small py-2 px-4 border-0 no-print">
                Exibindo <?= count($imoveis) ?> imóvel(is) com leads interessados
                <?php if(!empty($busca)): ?>
                    (filtrados)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tooltips automáticos
    document.querySelectorAll('[title]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
</script>

<?php //require_once '../../includes/footer.php'; ?>
</body>
</html>
<!-- mobile ok -->