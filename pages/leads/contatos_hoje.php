<?php
// contatos_hoje.php - com DataTables, ordenação personalizada, contadores por temperatura e favorito
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==================== PROCESSAMENTO AJAX ====================
if (isset($_POST['action']) && $_POST['action'] == 'update_obs') {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $obs  = trim($_POST['observacoes'] ?? '');
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE leads SET observacoes = ?, ultima_interacao = NOW() WHERE id = ?");
        $success = $stmt->execute([$obs, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'update_obs_parceiros') {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $obs  = trim($_POST['obs_parceiros'] ?? '');
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE leads SET obs_parceiros = ?, ultima_interacao = NOW() WHERE id = ?");
        $success = $stmt->execute([$obs, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'update_temperatura') {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $temp = trim($_POST['temperatura'] ?? '');
    if ($id > 0 && in_array($temp, ['Quente', 'Morno', 'Frio'])) {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
        $success = $stmt->execute([$temp, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'toggle_hoje') {
    header('Content-Type: application/json');
    $id    = (int)($_POST['id'] ?? 0);
    $ativo = (int)($_POST['ativo'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = ?, ultima_interacao = NOW() WHERE id = ?");
        $success = $stmt->execute([$ativo, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'limpar_hoje') {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = 0 WHERE contatar_hoje = 1");
    echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
    exit;
}

// ==================== NOVO: Alternar favorito ====================
if (isset($_POST['action']) && $_POST['action'] == 'toggle_favorito') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Obtém o valor atual do favorito
        $stmt = $conn->prepare("SELECT favorito FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        $atual = (int)$stmt->fetchColumn();
        $novo = $atual ? 0 : 1;
        
        $stmt2 = $conn->prepare("UPDATE leads SET favorito = ?, ultima_interacao = NOW() WHERE id = ?");
        $success = $stmt2->execute([$novo, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error', 'novo_estado' => $novo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

// ====================== CONSULTA ======================
// Incluído o campo favorito na consulta
$sql = "SELECT l.*,
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado
        FROM leads l
        WHERE l.contatar_hoje = 1";
$stmt = $conn->query($sql);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contagem por temperatura
$total_quente = 0;
$total_morno  = 0;
$total_frio   = 0;
foreach ($leads as $l) {
    $temp = $l['temperatura'] ?: 'Morno';
    if ($temp == 'Quente') $total_quente++;
    elseif ($temp == 'Morno') $total_morno++;
    elseif ($temp == 'Frio') $total_frio++;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Contatos de Hoje - Gestão de Leads</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- DataTables CSS + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .table-lead-hoje { background-color: #ffffff; }
        .table-lead-hoje tbody tr { transition: background-color 0.3s ease, opacity 0.4s ease; }
        .table-lead-hoje tbody tr:hover { background-color: #fdfdfd; }
        .obs-text, .obs-parceiros-text {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .obs-text { color: #6c757d; }
        .obs-text:hover { color: #0d6efd; text-decoration: underline; }
        .obs-parceiros-text {
            color: #b65c00;
            background-color: #fff3e0;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .obs-parceiros-text:hover { color: #cc7b00; text-decoration: underline; background-color: #ffe6b3; }
        .temp-border { transition: border-left 0.3s ease; }
        .temp-Quente { border-left: 5px solid #dc3545 !important; }
        .temp-Morno { border-left: 5px solid #ffc107 !important; }
        .temp-Frio { border-left: 5px solid #0dcaf0 !important; }
        .item-lead-fila { transition: background-color 0.6s ease; }
        .form-check-input { cursor: pointer; width: 2.5em; height: 1.25em; }
        .select-temp {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.25rem 1.8rem 0.25rem 0.5rem;
            border-radius: 30px;
            width: 110px;
            margin: 0 auto;
        }
        .fav-star {
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.1s ease;
            display: inline-block;
            line-height: 1;
        }
        .fav-star:active { transform: scale(0.9); }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
        .dataTables_wrapper .dataTables_filter input { border-radius: 20px; padding: 0.375rem 0.75rem; }
        
        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {
            .table-lead-hoje thead { display: none; }
            .table-lead-hoje tbody, .table-lead-hoje tr, .table-lead-hoje td { display: block; width: 100%; }
            .table-lead-hoje tr {
                background: #ffffff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                border-radius: 12px;
                margin-bottom: 16px;
                padding: 16px;
                border: 1px solid #e9ecef !important;
                position: relative;
            }
            .table-lead-hoje td { padding: 6px 0 !important; text-align: left !important; border: none !important; }
            .table-lead-hoje td.index-col { display: none; }
            .table-lead-hoje td.temp-border {
                position: absolute;
                top: 0;
                left: 0;
                width: 6px !important;
                height: 100%;
                border-top-left-radius: 12px;
                border-bottom-left-radius: 12px;
                padding: 0 !important;
            }
            .table-lead-hoje td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.75rem;
                text-transform: uppercase;
                font-weight: 700;
                color: #a0aec0;
                margin-bottom: 4px;
            }
            /* Esconder o before em colunas que não precisam de rótulo explícito */
            .table-lead-hoje td:nth-child(3)::before,
            .table-lead-hoje td:nth-child(9)::before,
            .table-lead-hoje td:last-child::before { display: none; }
            .select-temp { margin: 0; width: 130px; }
            .obs-text, .obs-parceiros-text {
                max-width: 100%;
                white-space: normal;
                overflow: visible;
                background-color: #f8f9fa;
                padding: 8px 12px !important;
                border-radius: 8px;
                border: 1px dashed #dee2e6;
            }
            .obs-parceiros-text { background-color: #fff3e0; border-color: #ffe0b3; }
            .form-switch { padding-left: 2.5em; margin-top: 4px; }
            .table-lead-hoje td:last-child { padding-top: 12px !important; margin-top: 8px; border-top: 1px solid #f1f3f5 !important; }
            .btn-group { display: flex; width: 100%; box-shadow: none !important; }
            .btn-group .btn { flex: 1; padding: 10px 4px; font-size: 0.9rem; justify-content: center; }
            .dataTables_wrapper .dataTables_filter input { width: 100%; }
            .dataTables_wrapper .dataTables_length select { width: auto; margin-right: 5px; }
        }
    </style>
</head>
<body>

<div class="container my-3 my-md-5">
    <!-- Cabeçalho Principal -->
    <div class="card border-0 shadow-sm bg-primary bg-gradient text-white mb-4 rounded-3">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start">
            <div class="mb-3 mb-md-0">
                <h2 class="fw-bold mb-1 d-flex align-items-center justify-content-center justify-content-md-start gap-2 fs-3 fs-md-2">
                    <i class="bi bi-megaphone-fill"></i> Contatos de Hoje
                </h2>
                <p class="mb-0 opacity-75 fs-6">
                    Você tem <span id="contador-leads" class="badge bg-white text-primary fw-bold fs-6"><?= count($leads) ?></span> lead<?= count($leads) !== 1 ? 's' : '' ?> na fila.
                </p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                <?php if (count($leads) > 0): ?>
                    <button id="btnLimparTudo" class="btn btn-outline-light d-flex align-items-center justify-content-center gap-1 shadow-sm py-2">
                        <i class="bi bi-trash3-fill"></i> Limpar Lista
                    </button>
                <?php endif; ?>
    <!-- NOVO BOTÃO: Lista Compacta (abre em nova aba) -->
    <a href="contatos_hoje_print.php" target="_blank" class="btn btn-outline-light fw-semibold d-flex align-items-center justify-content-center gap-1 shadow-sm py-2">
        <i class="bi bi-file-text-fill"></i> Lista Compacta
    </a>                
                <a href="leads.php" class="btn btn-light fw-semibold text-primary d-flex align-items-center justify-content-center gap-1 shadow-sm py-2">
                    <i class="bi bi-arrow-left-short fs-5"></i> Voltar à Gestão
                </a>
            </div>
        </div>
    </div>

    <!-- Contadores por temperatura -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex gap-3 justify-content-center justify-content-md-start flex-wrap">
                <span class="badge bg-danger fs-6 p-2"><i class="bi bi-fire"></i> Quente: <span id="cont-quente"><?= $total_quente ?></span></span>
                <span class="badge bg-warning text-dark fs-6 p-2"><i class="bi bi-thermometer-half"></i> Morno: <span id="cont-morno"><?= $total_morno ?></span></span>
                <span class="badge bg-info fs-6 p-2"><i class="bi bi-snow"></i> Frio: <span id="cont-frio"><?= $total_frio ?></span></span>
            </div>
        </div>
    </div>

    <!-- Estado Vazio -->
    <div id="estado-vazio" class="card border-0 shadow-sm py-5 rounded-3 <?= empty($leads) ? '' : 'd-none' ?>">
        <div class="card-body text-center py-4">
            <div class="display-1 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="fw-bold text-dark">Tudo em dia!</h3>
            <p class="text-muted lead mb-4 px-3">Não há nenhum contato agendado ou na fila de execução para o dia de hoje.</p>
            <a href="leads.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> Selecionar Novos Leads
            </a>
        </div>
    </div>

    <?php if (!empty($leads)): ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-5">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaContatosHoje" class="table table-hover table-lead-hoje align-middle mb-0 w-100">
                        <thead class="table-light border-bottom">
                            <tr class="text-secondary small text-uppercase">
                                <th class="ps-3" style="width: 50px;">#</th>
                                <th style="width: 6px;"></th>
                                <th>Nome completo</th>
                                <th class="text-center">Temperatura</th>
                                <th>Status de Tempo</th>
                                <th>Observações</th>
                                <th>Obs Parceiros</th>
                                <th class="text-center">Fav</th>   <!-- NOVA COLUNA FAVORITO -->
                                <th class="text-center">Hoje?</th>
                                <th class="text-end pe-4">Ações rápidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $contador = 1;
                            foreach ($leads as $l):
                                $temp = $l['temperatura'] ?: 'Morno';
                                $classeTemp = 'temp-' . $temp;
                                $selectClass = 'bg-warning-subtle text-warning-emphasis border-warning';
                                if ($temp === 'Quente') $selectClass = 'bg-danger-subtle text-danger-emphasis border-danger';
                                if ($temp === 'Frio')   $selectClass = 'bg-info-subtle text-info-emphasis border-info';
                                $favorito = (bool)($l['favorito'] ?? 0);
                                $starIcon = $favorito ? 'bi-star-fill' : 'bi-star';
                                $starTitle = $favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
                            ?>
                            <tr id="linha-lead-<?= $l['id'] ?>" class="item-lead-fila border-bottom">
                                <td class="text-muted ps-3 fw-medium small index-col"><?= $contador++ ?></td>
                                <td id="borda-temp-<?= $l['id'] ?>" class="temp-border <?= $classeTemp ?>"></td>
                                <td data-label="Nome">
                                    <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($l['nome']) ?></div>
                                    <small class="text-muted d-block"><i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($l['telefone']) ?></small>
                                </td>
                                <td data-label="Temperatura" class="text-center">
                                    <select class="form-select select-temp change-temperatura <?= $selectClass ?>" data-id="<?= $l['id'] ?>">
                                        <option value="Quente" <?= $temp == 'Quente' ? 'selected' : '' ?>>Quente</option>
                                        <option value="Morno" <?= $temp == 'Morno' ? 'selected' : '' ?>>Morno</option>
                                        <option value="Frio" <?= $temp == 'Frio' ? 'selected' : '' ?>>Frio</option>
                                    </select>
                                </td>
                                <td data-label="Status de Tempo">
                                    <span class="badge bg-light text-secondary border fw-normal py-1.5 px-2">
                                        <i class="bi bi-clock-history me-1 text-primary"></i> Parado há <b><?= $l['dias_parado'] ?></b> dias
                                    </span>
                                </td>
                                <td data-label="Observações" 
                                    class="obs-text trigger-modal-obs" 
                                    id="celula-obs-<?= $l['id'] ?>"
                                    title="<?= htmlspecialchars($l['observacoes'] ?? '') ?>"
                                    data-id="<?= $l['id'] ?>"
                                    data-obs="<?= htmlspecialchars($l['observacoes'] ?? '') ?>">
                                    <?php if (!empty($l['observacoes'])): ?>
                                        <i class="bi bi-chat-left-text me-1 opacity-50"></i> <?= htmlspecialchars($l['observacoes']) ?>
                                    <?php else: ?>
                                        <em class="text-muted opacity-50">Sem observações...</em>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Obs Parceiros" 
                                    class="obs-parceiros-text trigger-modal-obs-parceiros" 
                                    id="celula-obs-parceiros-<?= $l['id'] ?>"
                                    title="<?= htmlspecialchars($l['obs_parceiros'] ?? '') ?>"
                                    data-id="<?= $l['id'] ?>"
                                    data-obs="<?= htmlspecialchars($l['obs_parceiros'] ?? '') ?>">
                                    <?php if (!empty($l['obs_parceiros'])): ?>
                                        <i class="bi bi-shield-shaded me-1"></i> <?= htmlspecialchars($l['obs_parceiros']) ?>
                                    <?php else: ?>
                                        <em class="text-muted opacity-50">Sem observações...</em>
                                    <?php endif; ?>
                                </td>
                                <!-- NOVA COLUNA FAVORITO -->
                                <td data-label="Favorito" class="text-center">
                                    <i class="fav-star bi <?= $starIcon ?> text-warning fs-3"
                                       data-id="<?= $l['id'] ?>"
                                       style="cursor: pointer;"
                                       title="<?= $starTitle ?>"></i>
                                </td>
                                <td data-label="Manter na fila de hoje?" class="text-md-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-hoje-switch" type="checkbox" checked data-id="<?= $l['id'] ?>">
                                    </div>
                                </td>
                                <td class="text-end pe-md-4">
                                    <div class="btn-group border rounded">
                                        <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1">
                                            <i class="bi bi-pencil-square"></i> <span>Editar</span>
                                        </a>
                                        <button class="btn btn-light btn-sm text-secondary border-start trigger-modal-obs" 
                                                data-id="<?= $l['id'] ?>" 
                                                data-obs="<?= htmlspecialchars($l['observacoes'] ?? '') ?>">
                                            <i class="bi bi-chat-text-fill"></i>
                                        </button>
                                        <button class="btn btn-light btn-sm text-warning border-start trigger-modal-obs-parceiros" 
                                                data-id="<?= $l['id'] ?>" 
                                                data-obs="<?= htmlspecialchars($l['obs_parceiros'] ?? '') ?>">
                                            <i class="bi bi-shield-shaded"></i>
                                        </button>
                                        <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-light btn-sm text-primary border-start">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modais (originais) -->
<div class="modal fade" id="modalObservacoes" tabindex="-1" aria-labelledby="modalObsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0 rounded-3">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalObsLabel">
                    <i class="bi bi-pencil-square text-primary"></i> Atualizar Notas do Lead
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="mb-3">
                    <label for="txtObservacoes" class="form-label text-secondary small fw-semibold">Inserir ou Modificar Histórico:</label>
                    <textarea id="txtObservacoes" class="form-control border-secondary-subtle" rows="6" placeholder="Escreva observações comerciais relevantes..."></textarea>
                </div>
                <input type="hidden" id="modalLeadId">
            </div>
            <div class="modal-footer bg-light border-top p-3">
                <button type="button" class="btn btn-outline-secondary fw-medium px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarObs" class="btn btn-primary fw-semibold px-4 shadow-sm">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalObsParceiros" tabindex="-1" aria-labelledby="modalObsParceirosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0 rounded-3">
            <div class="modal-header bg-warning bg-opacity-25 border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalObsParceirosLabel">
                    <i class="bi bi-shield-shaded text-warning"></i> Observações para Parceiros
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="mb-3">
                    <label for="txtObsParceiros" class="form-label text-secondary small fw-semibold">Informações internas para parceiros:</label>
                    <textarea id="txtObsParceiros" class="form-control border-secondary-subtle" rows="6" placeholder="Digite aqui as observações destinadas aos parceiros..."></textarea>
                </div>
                <input type="hidden" id="modalLeadIdParceiros">
            </div>
            <div class="modal-footer bg-light border-top p-3">
                <button type="button" class="btn btn-outline-secondary fw-medium px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarObsParceiros" class="btn btn-warning fw-semibold px-4 shadow-sm">
                    <i class="bi bi-save2 me-1"></i> Salvar para Parceiros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelaContatosHoje').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" },
        "order": [[2, 'asc']],
        "columnDefs": [
            { "orderable": true, "targets": [0,1,2,3,4,5,6,7,8,9] },
            { "searchable": true, "targets": [2,5,6] },
            { "type": "num", "targets": 0 },
            {
                "targets": 3,
                "render": function(data, type, row, meta) {
                    if (type === 'sort') {
                        let $select = $(data);
                        let tempVal = $select.val();
                        const map = { "Quente": 3, "Morno": 2, "Frio": 1 };
                        return map[tempVal] || 0;
                    }
                    return data;
                }
            }
        ],
        "pageLength": 100,
        "responsive": false,
        "drawCallback": function() {
            recalcularContadores();
            atualizarContadoresTemperatura();
        }
    });

    function recalcularContadores() {
        let totalLeads = table.rows({ search: 'applied' }).count();
        $('#contador-leads').text(totalLeads);
        if (totalLeads === 0) {
            $('#estado-vazio').removeClass('d-none');
            $('.card:has(#tabelaContatosHoje)').addClass('d-none');
            $('#btnLimparTudo').addClass('d-none');
        } else {
            $('#estado-vazio').addClass('d-none');
            $('.card:has(#tabelaContatosHoje)').removeClass('d-none');
            $('#btnLimparTudo').removeClass('d-none');
        }
    }

    function atualizarContadoresTemperatura() {
        let q = 0, m = 0, f = 0;
        table.rows({ search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
            let rowNode = this.node();
            let select = $(rowNode).find('.change-temperatura');
            let tempValue = select.val();
            if (tempValue === 'Quente') q++;
            else if (tempValue === 'Morno') m++;
            else if (tempValue === 'Frio') f++;
        });
        $('#cont-quente').text(q);
        $('#cont-morno').text(m);
        $('#cont-frio').text(f);
    }

    recalcularContadores();
    atualizarContadoresTemperatura();

    // ==================== FAVORITO ====================
    $(document).on('click', '.fav-star', function(e) {
        e.preventDefault();
        const star = $(this);
        const leadId = star.data('id');

        star.css('pointer-events', 'none').css('opacity', '0.6');

        $.post('contatos_hoje.php', {
            action: 'toggle_favorito',
            id: leadId
        }, function(res) {
            if (res.status === 'success') {
                const novoEstado = res.novo_estado;
                if (novoEstado === 1) {
                    star.removeClass('bi-star').addClass('bi-star-fill');
                    star.attr('title', 'Remover dos favoritos');
                } else {
                    star.removeClass('bi-star-fill').addClass('bi-star');
                    star.attr('title', 'Adicionar aos favoritos');
                }
                // Feedback visual na linha
                $(`#linha-lead-${leadId}`).addClass('table-info').delay(500).queue(function(next) {
                    $(this).removeClass('table-info');
                    next();
                });
            } else {
                alert('Erro ao alterar favorito. Tente novamente.');
            }
        }, 'json').fail(function() {
            alert('Erro de comunicação com o servidor.');
        }).always(function() {
            star.css('pointer-events', 'auto').css('opacity', '1');
        });
    });

    // ==================== EVENTOS EXISTENTES ====================
    // Alterar Temperatura
    $(document).on('change', '.change-temperatura', function() {
        const select = $(this);
        const leadId = select.data('id');
        const novaTemp = select.val();
        const celulaBorda = $(`#borda-temp-${leadId}`);

        select.prop('disabled', true);
        $.post('contatos_hoje.php', {
            action: 'update_temperatura',
            id: leadId,
            temperatura: novaTemp
        }, function(res) {
            if (res.status === 'success') {
                celulaBorda.removeClass('temp-Quente temp-Morno temp-Frio').addClass('temp-' + novaTemp);
                select.removeClass('bg-danger-subtle text-danger-emphasis border-danger bg-warning-subtle text-warning-emphasis border-warning bg-info-subtle text-info-emphasis border-info');
                if (novaTemp === 'Quente') select.addClass('bg-danger-subtle text-danger-emphasis border-danger');
                else if (novaTemp === 'Morno') select.addClass('bg-warning-subtle text-warning-emphasis border-warning');
                else if (novaTemp === 'Frio') select.addClass('bg-info-subtle text-info-emphasis border-info');
                
                const row = $(`#linha-lead-${leadId}`);
                row.addClass('table-primary');
                setTimeout(() => row.removeClass('table-primary'), 600);
                table.draw(false);
            } else {
                alert('Erro ao atualizar a temperatura.');
            }
        }, 'json')
        .fail(function() { alert('Erro de rede.'); })
        .always(() => select.prop('disabled', false));
    });

    // Remover da lista de hoje
    $(document).on('change', '.toggle-hoje-switch', function() {
        const switchBtn = $(this);
        const leadId = switchBtn.data('id');
        const estaAtivo = switchBtn.is(':checked') ? 1 : 0;
        const linha = $(`#linha-lead-${leadId}`);

        switchBtn.prop('disabled', true);
        $.post('contatos_hoje.php', {
            action: 'toggle_hoje',
            id: leadId,
            ativo: estaAtivo
        }, function(res) {
            if (res.status === 'success') {
                if (estaAtivo === 0) {
                    table.row(linha).remove().draw(false);
                }
            } else {
                alert('Erro ao modificar status.');
                switchBtn.prop('checked', !estaAtivo);
            }
        }, 'json')
        .fail(function() { alert('Erro de comunicação.'); switchBtn.prop('checked', !estaAtivo); })
        .always(() => switchBtn.prop('disabled', false));
    });
    
    // Modal Observações Comuns
    $(document).on('click', '.trigger-modal-obs', function() {
        if ($(this).closest('tr').css('opacity') < 1) return;
        const id = $(this).data('id');
        const obsAtual = $(`#celula-obs-${id}`).data('obs') || '';
        $('#modalLeadId').val(id);
        $('#txtObservacoes').val(obsAtual);
        $('#modalObservacoes').modal('show');
    });

    $('#btnSalvarObs').on('click', function() {
        const obs = $('#txtObservacoes').val().trim();
        const id = $('#modalLeadId').val();
        if (!id) return;
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>...');

        $.post('contatos_hoje.php', {
            action: 'update_obs',
            id: id,
            observacoes: obs
        }, function(res) {
            if (res.status === 'success') {
                const celula = $(`#celula-obs-${id}`);
                if (obs) celula.html('<i class="bi bi-chat-left-text me-1 opacity-50"></i> ' + obs);
                else celula.html('<em class="text-muted opacity-50">Sem observações...</em>');
                celula.data('obs', obs).attr('title', obs);
                $('#modalObservacoes').modal('hide');
                const row = $(`#linha-lead-${id}`);
                row.addClass('table-success');
                setTimeout(() => row.removeClass('table-success'), 1200);
            } else {
                alert('Erro ao salvar observações.');
            }
        }, 'json')
        .fail(() => alert('Falha de comunicação.'))
        .always(() => btn.prop('disabled', false).html(originalText));
    });

    // Modal Observações Parceiros
    $(document).on('click', '.trigger-modal-obs-parceiros', function() {
        if ($(this).closest('tr').css('opacity') < 1) return;
        const id = $(this).data('id');
        const obsAtual = $(`#celula-obs-parceiros-${id}`).data('obs') || '';
        $('#modalLeadIdParceiros').val(id);
        $('#txtObsParceiros').val(obsAtual);
        $('#modalObsParceiros').modal('show');
    });

    $('#btnSalvarObsParceiros').on('click', function() {
        const obs = $('#txtObsParceiros').val().trim();
        const id = $('#modalLeadIdParceiros').val();
        if (!id) return;
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>...');

        $.post('contatos_hoje.php', {
            action: 'update_obs_parceiros',
            id: id,
            obs_parceiros: obs
        }, function(res) {
            if (res.status === 'success') {
                const celula = $(`#celula-obs-parceiros-${id}`);
                if (obs) celula.html('<i class="bi bi-shield-shaded me-1"></i> ' + obs);
                else celula.html('<em class="text-muted opacity-50">Sem observações...</em>');
                celula.data('obs', obs).attr('title', obs);
                $('#modalObsParceiros').modal('hide');
                const row = $(`#linha-lead-${id}`);
                row.addClass('table-warning');
                setTimeout(() => row.removeClass('table-warning'), 1200);
            } else {
                alert('Erro ao salvar observações para parceiros.');
            }
        }, 'json')
        .fail(() => alert('Falha de comunicação.'))
        .always(() => btn.prop('disabled', false).html(originalText));
    });

    // Limpar lista completa
    $('#btnLimparTudo').on('click', function() {
        if (confirm('Deseja retirar TODOS os leads da lista de hoje?')) {
            $.post('contatos_hoje.php', { action: 'limpar_hoje' }, function(res) {
                if (res.status === 'success') location.reload();
            }, 'json');
        }
    });
});
</script>

</body>
</html>
<!--  -->