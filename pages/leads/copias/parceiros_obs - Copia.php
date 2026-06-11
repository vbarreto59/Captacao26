<?php
// listagem_obs_parceiros.php - Listagem com edição de nome, primeiro_nome, obs_parceiros, toggles de compartilhamento, favorito, contatar_hoje e exclusão lógica
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Garantir que as colunas necessárias existam
try {
    $check = $conn->query("SHOW COLUMNS FROM leads LIKE 'primeiro_nome'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN primeiro_nome VARCHAR(100) DEFAULT NULL");
    }
    $check2 = $conn->query("SHOW COLUMNS FROM leads LIKE 'compartilhado_parceiro'");
    if ($check2->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN compartilhado_parceiro TINYINT(1) DEFAULT 0");
    }
    $check3 = $conn->query("SHOW COLUMNS FROM leads LIKE 'favorito'");
    if ($check3->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN favorito TINYINT(1) DEFAULT 0");
    }
    $check4 = $conn->query("SHOW COLUMNS FROM leads LIKE 'deleted_at'");
    if ($check4->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN deleted_at DATETIME DEFAULT NULL");
    }
    $check5 = $conn->query("SHOW COLUMNS FROM leads LIKE 'contatar_hoje'");
    if ($check5->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN contatar_hoje TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    // Se não conseguir, prossegue (pode já existir)
}

// ==========================================
// LÓGICA AJAX
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Ação inválida'];

    // Atualizar nome completo
    if ($_POST['action'] == 'update_nome') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id > 0 && !empty($nome)) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET nome = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$nome, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Nome inválido ou vazio'];
        }
        echo json_encode($response);
        exit;
    }

    // Atualizar observações parceiros
    if ($_POST['action'] == 'update_obs_parceiros') {
        $id = (int)($_POST['id'] ?? 0);
        $obs = trim($_POST['obs_parceiros'] ?? '');
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET obs_parceiros = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$obs, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    // Atualizar primeiro nome
    if ($_POST['action'] == 'update_primeiro_nome') {
        $id = (int)($_POST['id'] ?? 0);
        $primeiro_nome = trim($_POST['primeiro_nome'] ?? '');
        if ($id > 0 && !empty($primeiro_nome)) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET primeiro_nome = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$primeiro_nome, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Nome inválido ou vazio'];
        }
        echo json_encode($response);
        exit;
    }

    // Toggle compartilhamento
    if ($_POST['action'] == 'toggle_share') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET compartilhado_parceiro = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$valor, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    // Toggle favorito (estrela)
    if ($_POST['action'] == 'toggle_favorito') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET favorito = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$valor, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    // Toggle contatar_hoje
    if ($_POST['action'] == 'toggle_contatar_hoje') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = ? WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$valor, $id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha na atualização'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    // SOFT DELETE (exclusão lógica)
    if ($_POST['action'] == 'soft_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $success = $stmt->execute([$id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha ao excluir'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    // RESTAURAR (desfazer soft delete)
    if ($_POST['action'] == 'restore') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE leads SET deleted_at = NULL WHERE id = ?");
                $success = $stmt->execute([$id]);
                $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? '' : 'Falha ao restaurar'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Erro no banco: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'ID inválido'];
        }
        echo json_encode($response);
        exit;
    }

    echo json_encode($response);
    exit;
}

// ==========================================
// CONSULTA PRINCIPAL (incluindo contatar_hoje)
// ==========================================
$busca = $_GET['busca'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

$where = "WHERE 1=1";
$params = [];

if (!$show_deleted) {
    $where .= " AND deleted_at IS NULL";
}

if (!empty($busca)) {
    $where .= " AND (nome LIKE ? OR primeiro_nome LIKE ? OR obs_parceiros LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql = "SELECT id, nome, primeiro_nome, obs_parceiros, compartilhado_parceiro, favorito, contatar_hoje, deleted_at FROM leads $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Observações para Parceiros - Listagem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .card-custom {
            border-radius: 16px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .obs-cell, .nome-cell, .nome-completo-cell {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .obs-cell:hover {
            background-color: #fff3cd;
            text-decoration: underline;
        }
        .nome-cell:hover, .nome-completo-cell:hover {
            background-color: #e2f0ff;
            text-decoration: underline;
        }
        .row-shared {
            background-color: #f0f7ff !important;
            border-left: 4px solid #0d6efd;
        }
        .row-deleted {
            background-color: #f8d7da !important;
            opacity: 0.7;
            text-decoration: line-through;
            color: #6c757d;
        }
        .favorito-star {
            cursor: pointer;
            font-size: 1.3rem;
            transition: transform 0.1s ease, color 0.2s;
        }
        .favorito-star:hover {
            transform: scale(1.2);
        }
        .toggle-contatar-hoje {
            cursor: pointer;
            width: 2.5em;
            height: 1.25em;
        }
        .btn-restore, .btn-delete {
            font-size: 0.9rem;
            padding: 4px 8px;
        }
        @media (max-width: 768px) {
            .obs-cell, .nome-cell, .nome-completo-cell {
                max-width: 100%;
                white-space: normal;
                word-break: break-word;
            }
            .table thead {
                display: none;
            }
            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 12px;
                padding: 12px;
                position: relative;
            }
            .table tbody td {
                display: block;
                width: 100%;
                text-align: left;
                padding: 6px 0;
                border: none;
            }
            .table tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                display: block;
                font-size: 0.75rem;
                color: #6c757d;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
            .table tbody td:first-child {
                padding-top: 0;
            }
            .table tbody td:last-child {
                padding-bottom: 0;
            }
            .form-check.form-switch {
                display: inline-block;
            }
            .favorito-star {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">
                <i class="bi bi-shield-shaded me-2"></i>Observações para Parceiros
            </h2>
            <p class="text-muted small mb-0">Clique sobre o nome ou observação para editar | <i class="bi bi-star-fill text-warning"></i> Favoritos | <i class="bi bi-telephone-fill"></i> Contatar hoje</p>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto">
            <form method="GET" class="d-flex gap-2 flex-grow-1 flex-md-grow-0">
                <input type="text" name="busca" class="form-control" placeholder="Pesquisar nome ou observação..." value="<?= htmlspecialchars($busca) ?>">
                <?php if ($show_deleted): ?>
                    <input type="hidden" name="show_deleted" value="1">
                <?php endif; ?>
                <button type="submit" class="btn btn-light border"><i class="bi bi-search"></i></button>
            </form>
            <div class="btn-group">
                <a href="?<?= $show_deleted ? '' : 'show_deleted=1' ?>" class="btn btn-outline-secondary <?= $show_deleted ? 'active' : '' ?>">
                    <i class="bi bi-trash"></i> <?= $show_deleted ? 'Ocultar excluídos' : 'Mostrar excluídos' ?>
                </a>
                <a href="leads3.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <!-- Listagem -->
    <div class="card card-custom shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="width: 50px;"><i class="bi bi-star-fill"></i></th>
                            <th>Nome Completo</th>
                            <th>Primeiro Nome</th>
                            <th>Obs Parceiros</th>
                            <th class="text-center" style="width: 100px;">Compartilhar</th>
                            <th class="text-center" style="width: 100px;">Contatar Hoje</th>
                            <th class="text-center" style="width: 80px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($leads) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Nenhum lead encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $index => $lead): 
                                $is_shared = (int)($lead['compartilhado_parceiro'] ?? 0);
                                $is_favorito = (int)($lead['favorito'] ?? 0);
                                $is_contatar_hoje = (int)($lead['contatar_hoje'] ?? 0);
                                $is_deleted = !is_null($lead['deleted_at']);
                                $row_class = $is_deleted ? 'row-deleted' : ($is_shared ? 'row-shared' : '');
                            ?>
                            <tr id="row-lead-<?= $lead['id'] ?>" class="<?= $row_class ?>">
                                <td data-label="#"><?= $index + 1 ?></td>
                                <!-- Coluna da estrela -->
                                <td data-label="Favorito" class="text-center">
                                    <i class="bi <?= $is_favorito ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> favorito-star" 
                                       data-id="<?= $lead['id'] ?>" 
                                       data-favorito="<?= $is_favorito ?>"></i>
                                </td>
                                <td data-label="Nome Completo" 
                                    class="fw-semibold nome-completo-cell <?= $is_deleted ? 'text-muted' : '' ?>" 
                                    data-id="<?= $lead['id'] ?>"
                                    data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                    <i class="bi bi-person-circle text-primary me-1"></i>
                                    <?= htmlspecialchars($lead['nome']) ?>
                                 </td>
                                <td data-label="Primeiro Nome" 
                                    class="nome-cell <?= $is_deleted ? 'text-muted' : '' ?>" 
                                    data-id="<?= $lead['id'] ?>"
                                    data-nome-completo="<?= htmlspecialchars($lead['nome']) ?>"
                                    data-primeiro-nome="<?= htmlspecialchars($lead['primeiro_nome'] ?? '') ?>">
                                    <i class="bi bi-person-bounding-box text-info me-1"></i>
                                    <?= htmlspecialchars($lead['primeiro_nome'] ?: '—') ?>
                                 </td>
                                <td data-label="Obs Parceiros" class="obs-cell <?= $is_deleted ? 'text-muted' : '' ?>" 
                                    data-id="<?= $lead['id'] ?>" 
                                    data-obs="<?= htmlspecialchars($lead['obs_parceiros'] ?? '') ?>"
                                    data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                    <?php if (!empty($lead['obs_parceiros'])): ?>
                                        <i class="bi bi-shield-shaded text-warning me-1"></i>
                                        <?= nl2br(htmlspecialchars($lead['obs_parceiros'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">
                                            <i class="bi bi-pencil-square"></i> Adicionar...
                                        </span>
                                    <?php endif; ?>
                                 </td>
                                <td data-label="Compartilhar" class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-share" type="checkbox" 
                                               data-id="<?= $lead['id'] ?>" <?= $is_shared ? 'checked' : '' ?>
                                               <?= $is_deleted ? 'disabled' : '' ?>>
                                    </div>
                                 </td>
                                <!-- Contatar Hoje toggle -->
                                <td data-label="Contatar Hoje" class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-contatar-hoje" type="checkbox" 
                                               data-id="<?= $lead['id'] ?>" <?= $is_contatar_hoje ? 'checked' : '' ?>
                                               <?= $is_deleted ? 'disabled' : '' ?>>
                                    </div>
                                 </td>
                                <td data-label="Ações" class="text-center">
                                    <?php if ($is_deleted): ?>
                                        <button class="btn btn-sm btn-outline-success btn-restore" data-id="<?= $lead['id'] ?>" title="Restaurar lead">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-danger btn-soft-delete" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" title="Excluir logicamente">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-3 text-center">
        <i class="bi bi-info-circle"></i> Clique sobre qualquer campo destacado para editar. 
        O toggle "Compartilhar" indica leads visíveis para parceiros. 
        <i class="bi bi-star-fill text-warning"></i> Estrela = favorito.
        <i class="bi bi-telephone-fill"></i> "Contatar Hoje" marca o lead para contato no dia atual.
        Leads excluídos aparecem com linha riscada e podem ser restaurados.
    </div>
</div>

<!-- Modal Editar Nome Completo -->
<div class="modal fade" id="modalNomeCompleto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary bg-opacity-25 border-bottom">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-circle me-2"></i>Editar nome completo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome completo do lead</label>
                    <input type="text" id="nomeCompletoInput" class="form-control" placeholder="Ex: João Silva Santos" maxlength="255">
                </div>
                <input type="hidden" id="nomeCompletoLeadId">
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarNomeCompleto" class="btn btn-primary fw-semibold">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Primeiro Nome -->
<div class="modal fade" id="modalPrimeiroNome" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary bg-opacity-25 border-bottom">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-bounding-box me-2"></i>Editar primeiro nome
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome completo (apenas leitura)</label>
                    <input type="text" class="form-control bg-light" id="nomeCompletoReadonly" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Primeiro nome</label>
                    <input type="text" id="primeiroNomeInput" class="form-control" placeholder="Ex: João, Maria, etc." maxlength="100">
                </div>
                <input type="hidden" id="primeiroNomeLeadId">
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarPrimeiroNome" class="btn btn-primary fw-semibold">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Observações Parceiros -->
<div class="modal fade" id="modalObsParceiros" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning bg-opacity-25 border-bottom">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-shield-shaded me-2"></i>Editar observações para parceiros
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Lead</label>
                    <input type="text" class="form-control bg-light" id="leadNome" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observações</label>
                    <textarea id="obsParceirosTexto" class="form-control" rows="5" placeholder="Ex: condição especial de comissão, restrições..."></textarea>
                </div>
                <input type="hidden" id="leadId">
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarObs" class="btn btn-warning fw-semibold">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var currentScript = window.location.pathname.split('/').pop();

    // ==================== Favorito (Estrela) ====================
    $(document).on('click', '.favorito-star', function() {
        var icone = $(this);
        var id = icone.data('id');
        var estadoAtual = icone.hasClass('bi-star-fill') ? 1 : 0;
        var novoEstado = estadoAtual === 1 ? 0 : 1;
        
        icone.css('pointer-events', 'none');
        icone.addClass('opacity-50');
        
        $.post(currentScript, {
            action: 'toggle_favorito',
            id: id,
            value: novoEstado
        }, function(res) {
            if (res.status === 'success') {
                if (novoEstado === 1) {
                    icone.removeClass('bi-star text-muted').addClass('bi-star-fill text-warning');
                } else {
                    icone.removeClass('bi-star-fill text-warning').addClass('bi-star text-muted');
                }
                icone.data('favorito', novoEstado);
                $('#row-lead-' + id).css('background-color', '#fff3cd').delay(500).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro ao alterar favorito: ' + (res.message || 'Tente novamente.'));
                if (estadoAtual === 1) {
                    icone.addClass('bi-star-fill text-warning').removeClass('bi-star text-muted');
                } else {
                    icone.addClass('bi-star text-muted').removeClass('bi-star-fill text-warning');
                }
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
            if (estadoAtual === 1) {
                icone.addClass('bi-star-fill text-warning').removeClass('bi-star text-muted');
            } else {
                icone.addClass('bi-star text-muted').removeClass('bi-star-fill text-warning');
            }
        }).always(function() {
            icone.css('pointer-events', '');
            icone.removeClass('opacity-50');
        });
    });

    // ==================== Editar Nome Completo ====================
    $('.nome-completo-cell').on('click', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) {
            alert('Lead excluído. Não é possível editar. Restaure-o primeiro.');
            return;
        }
        $('#nomeCompletoLeadId').val($(this).data('id'));
        $('#nomeCompletoInput').val($(this).data('nome'));
        $('#modalNomeCompleto').modal('show');
    });

    $('#btnSalvarNomeCompleto').on('click', function() {
        var id = $('#nomeCompletoLeadId').val();
        var novoNome = $('#nomeCompletoInput').val().trim();
        if (novoNome === '') {
            alert('Por favor, informe o nome completo.');
            return;
        }
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');

        $.post(currentScript, {
            action: 'update_nome',
            id: id,
            nome: novoNome
        }, function(res) {
            if (res.status === 'success') {
                var celulaNome = $('.nome-completo-cell[data-id="' + id + '"]');
                celulaNome.html('<i class="bi bi-person-circle text-primary me-1"></i> ' + novoNome);
                celulaNome.data('nome', novoNome);
                $('.nome-cell[data-id="' + id + '"]').data('nome-completo', novoNome);
                $('#modalNomeCompleto').modal('hide');
                $('#row-lead-' + id).css('background-color', '#e2f0ff').delay(800).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro: ' + (res.message || 'Falha ao salvar.'));
                console.error(res);
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
        }).always(function() {
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // ==================== Editar Primeiro Nome ====================
    $('.nome-cell').on('click', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) {
            alert('Lead excluído. Não é possível editar. Restaure-o primeiro.');
            return;
        }
        $('#primeiroNomeLeadId').val($(this).data('id'));
        $('#primeiroNomeInput').val($(this).data('primeiro-nome') || '');
        $('#nomeCompletoReadonly').val($(this).data('nome-completo') || '');
        $('#modalPrimeiroNome').modal('show');
    });

    $('#btnSalvarPrimeiroNome').on('click', function() {
        var id = $('#primeiroNomeLeadId').val();
        var novoNome = $('#primeiroNomeInput').val().trim();
        if (novoNome === '') {
            alert('Por favor, informe o primeiro nome.');
            return;
        }
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');

        $.post(currentScript, {
            action: 'update_primeiro_nome',
            id: id,
            primeiro_nome: novoNome
        }, function(res) {
            if (res.status === 'success') {
                var celula = $('.nome-cell[data-id="' + id + '"]');
                celula.html('<i class="bi bi-person-bounding-box text-info me-1"></i> ' + novoNome);
                celula.data('primeiro-nome', novoNome);
                $('#modalPrimeiroNome').modal('hide');
                $('#row-lead-' + id).css('background-color', '#d1e7ff').delay(800).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro: ' + (res.message || 'Falha ao salvar.'));
                console.error(res);
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
        }).always(function() {
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // ==================== Editar Observações Parceiros ====================
    $('.obs-cell').on('click', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) {
            alert('Lead excluído. Não é possível editar. Restaure-o primeiro.');
            return;
        }
        $('#leadId').val($(this).data('id'));
        $('#leadNome').val($(this).data('nome'));
        $('#obsParceirosTexto').val($(this).data('obs') || '');
        $('#modalObsParceiros').modal('show');
    });

    $('#btnSalvarObs').on('click', function() {
        var id = $('#leadId').val();
        var obs = $('#obsParceirosTexto').val().trim();
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');

        $.post(currentScript, {
            action: 'update_obs_parceiros',
            id: id,
            obs_parceiros: obs
        }, function(res) {
            if (res.status === 'success') {
                var celula = $('.obs-cell[data-id="' + id + '"]');
                if (obs) {
                    celula.html('<i class="bi bi-shield-shaded text-warning me-1"></i> ' + obs.replace(/\n/g, '<br>'));
                } else {
                    celula.html('<span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>');
                }
                celula.data('obs', obs);
                $('#modalObsParceiros').modal('hide');
                $('#row-lead-' + id).css('background-color', '#fff9e6').delay(800).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro: ' + (res.message || 'Falha ao salvar.'));
                console.error(res);
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
        }).always(function() {
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // ==================== Toggle Compartilhar ====================
    $(document).on('change', '.toggle-share', function() {
        var checkbox = $(this);
        if (checkbox.prop('disabled')) return;
        var id = checkbox.data('id');
        var valor = checkbox.is(':checked') ? 1 : 0;
        var row = $('#row-lead-' + id);
        
        checkbox.prop('disabled', true);
        
        $.post(currentScript, {
            action: 'toggle_share',
            id: id,
            value: valor
        }, function(res) {
            if (res.status === 'success') {
                if (valor === 1) {
                    row.addClass('row-shared');
                } else {
                    row.removeClass('row-shared');
                }
                row.css('background-color', '#d1e7ff').delay(500).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro ao alterar compartilhamento: ' + (res.message || 'Tente novamente.'));
                checkbox.prop('checked', !valor);
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
            checkbox.prop('checked', !valor);
        }).always(function() {
            checkbox.prop('disabled', false);
        });
    });

    // ==================== Toggle Contatar Hoje ====================
    $(document).on('change', '.toggle-contatar-hoje', function() {
        var checkbox = $(this);
        if (checkbox.prop('disabled')) return;
        var id = checkbox.data('id');
        var valor = checkbox.is(':checked') ? 1 : 0;
        
        checkbox.prop('disabled', true);
        
        $.post(currentScript, {
            action: 'toggle_contatar_hoje',
            id: id,
            value: valor
        }, function(res) {
            if (res.status === 'success') {
                $('#row-lead-' + id).css('background-color', '#d4edda').delay(500).queue(function(n) {
                    $(this).css('background-color', ''); n();
                });
            } else {
                alert('Erro ao alterar status "Contatar Hoje": ' + (res.message || 'Tente novamente.'));
                checkbox.prop('checked', !valor);
            }
        }, 'json').fail(function(xhr) {
            alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
            checkbox.prop('checked', !valor);
        }).always(function() {
            checkbox.prop('disabled', false);
        });
    });

    // ==================== Soft Delete ====================
    $(document).on('click', '.btn-soft-delete', function() {
        var btn = $(this);
        var id = btn.data('id');
        var nome = btn.data('nome');
        
        if (confirm(`Tem certeza que deseja excluir logicamente o lead "${nome}"?\nO lead ficará oculto da listagem principal, mas poderá ser restaurado posteriormente.`)) {
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.post(currentScript, {
                action: 'soft_delete',
                id: id
            }, function(res) {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + (res.message || 'Tente novamente.'));
                    btn.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                }
            }, 'json').fail(function(xhr) {
                alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
                btn.prop('disabled', false).html('<i class="bi bi-trash"></i>');
            });
        }
    });

    // ==================== Restaurar ====================
    $(document).on('click', '.btn-restore', function() {
        var btn = $(this);
        var id = btn.data('id');
        
        if (confirm('Restaurar este lead? Ele voltará a aparecer na listagem principal.')) {
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.post(currentScript, {
                action: 'restore',
                id: id
            }, function(res) {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert('Erro ao restaurar: ' + (res.message || 'Tente novamente.'));
                    btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise"></i>');
                }
            }, 'json').fail(function(xhr) {
                alert('Erro de comunicação: ' + xhr.status + ' - ' + xhr.statusText);
                btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise"></i>');
            });
        }
    });
});
</script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>