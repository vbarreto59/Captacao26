<?php
// listagem_obs_parceiros.php - Destaque para favoritos (azul), contatar hoje (verde) e dias parados
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// NOVAS FASES ADICIONADAS: Contato Feito e Procurar Imóvel
// ==========================================
$fases_lista = [
    'Novo',
    'Tentativa de Contato',
    'Contato Feito',
    'Procurar Imóvel',
    'Agendar Visita',
    'Visita Agendada',
    'Visita Realizada',
    'Analisando',
    'Proposta',
    'Fechado',
    'Perdido'
];

// Garantir colunas necessárias
try {
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS primeiro_nome VARCHAR(100) DEFAULT NULL");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS compartilhado_parceiro TINYINT(1) DEFAULT 0");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS favorito TINYINT(1) DEFAULT 0");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS contatar_hoje TINYINT(1) DEFAULT 0");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS ultima_interacao DATETIME DEFAULT NULL");
    $conn->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS tipo_pagamento VARCHAR(20) DEFAULT NULL");
} catch (PDOException $e) { /* colunas já existem */ }

// ==========================================
// LÓGICA AJAX (ações inline)
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Ação inválida'];

    if ($_POST['action'] == 'update_nome') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id > 0 && !empty($nome)) {
            $stmt = $conn->prepare("UPDATE leads SET nome = ? WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$nome, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'update_obs_parceiros') {
        $id = (int)($_POST['id'] ?? 0);
        $obs = trim($_POST['obs_parceiros'] ?? '');
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET obs_parceiros = ?, ultima_interacao = NOW() WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$obs, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'update_observacoes') {
        $id = (int)($_POST['id'] ?? 0);
        $obs = trim($_POST['observacoes'] ?? '');
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET observacoes = ?, ultima_interacao = NOW() WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$obs, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'update_primeiro_nome') {
        $id = (int)($_POST['id'] ?? 0);
        $primeiro_nome = trim($_POST['primeiro_nome'] ?? '');
        if ($id > 0 && !empty($primeiro_nome)) {
            $stmt = $conn->prepare("UPDATE leads SET primeiro_nome = ? WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$primeiro_nome, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'update_valor_max') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (float)str_replace(['.', ','], ['', '.'], trim($_POST['valor_max'] ?? '0'));
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET valor_max = ? WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$valor, $id]);
            $response = ['status' => $success ? 'success' : 'error', 'valor' => $valor];
        }
        echo json_encode($response);
        exit;
    }
    // Atualizar tipo_pagamento
    if ($_POST['action'] == 'update_tipo_pagamento') {
        $id = (int)($_POST['id'] ?? 0);
        $tipo = trim($_POST['tipo_pagamento'] ?? '');
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET tipo_pagamento = ? WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$tipo, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    // Atualizar fase
    if ($_POST['action'] == 'update_fase') {
        $id = (int)($_POST['id'] ?? 0);
        $fase = trim($_POST['fase'] ?? '');
        if ($id > 0 && !empty($fase)) {
            $stmt = $conn->prepare("UPDATE leads SET fase_funil = ?, ultima_interacao = NOW() WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$fase, $id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'toggle_share') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        $stmt = $conn->prepare("UPDATE leads SET compartilhado_parceiro = ? WHERE id = ? AND deleted_at IS NULL");
        $success = $stmt->execute([$valor, $id]);
        $response = ['status' => $success ? 'success' : 'error'];
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'toggle_favorito') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        $stmt = $conn->prepare("UPDATE leads SET favorito = ? WHERE id = ? AND deleted_at IS NULL");
        $success = $stmt->execute([$valor, $id]);
        $response = ['status' => $success ? 'success' : 'error'];
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'toggle_contatar_hoje') {
        $id = (int)($_POST['id'] ?? 0);
        $valor = (int)($_POST['value'] ?? 0);
        $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = ? WHERE id = ? AND deleted_at IS NULL");
        $success = $stmt->execute([$valor, $id]);
        $response = ['status' => $success ? 'success' : 'error'];
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'soft_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE leads SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $success = $stmt->execute([$id]);
        $response = ['status' => $success ? 'success' : 'error'];
        echo json_encode($response);
        exit;
    }
    if ($_POST['action'] == 'restore') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE leads SET deleted_at = NULL WHERE id = ?");
        $success = $stmt->execute([$id]);
        $response = ['status' => $success ? 'success' : 'error'];
        echo json_encode($response);
        exit;
    }
    // Resetar dias parados
    if ($_POST['action'] == 'update_last_interaction') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET ultima_interacao = NOW() WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }
    // Marcar como Perdido
    if ($_POST['action'] == 'marcar_perdido') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads SET fase_funil = 'Perdido', contatar_hoje = 0, compartilhado_parceiro = 0 WHERE id = ? AND deleted_at IS NULL");
            $success = $stmt->execute([$id]);
            $response = ['status' => $success ? 'success' : 'error'];
        }
        echo json_encode($response);
        exit;
    }

    // Duplicar lead
    if ($_POST['action'] == 'duplicate_lead') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($original) {
                unset($original['id']);
                unset($original['created_at']);
                unset($original['updated_at']);
                unset($original['ultima_interacao']);
                $original['nome'] = 'Cópia de ' . $original['nome'];
                $original['telefone'] = '';
                $original['email'] = '';
                $original['primeiro_nome'] = '';
                $original['contatar_hoje'] = 0;
                $original['compartilhado_parceiro'] = 0;
                $original['favorito'] = 0;
                $original['deleted_at'] = null;
                $columns = implode(", ", array_keys($original));
                $placeholders = ":" . implode(", :", array_keys($original));
                $stmtIns = $conn->prepare("INSERT INTO leads ($columns) VALUES ($placeholders)");
                $stmtIns->execute($original);
                $newId = $conn->lastInsertId();
                $conn->prepare("UPDATE leads SET nome = ? WHERE id = ?")->execute(["LD".str_pad($newId,3,'0',STR_PAD_LEFT)."-".$original['nome'], $newId]);
                $stmtIm = $conn->prepare("SELECT imovel_id FROM lead_imoveis WHERE lead_id = ?");
                $stmtIm->execute([$id]);
                $imoveis = $stmtIm->fetchAll(PDO::FETCH_COLUMN);
                foreach ($imoveis as $im_id) {
                    $conn->prepare("INSERT INTO lead_imoveis (lead_id, imovel_id) VALUES (?, ?)")->execute([$newId, $im_id]);
                }
                $response = ['status' => 'success', 'new_id' => $newId];
            } else {
                $response = ['status' => 'error', 'message' => 'Lead original não encontrado ou está deletado'];
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
// FUNÇÃO PARA CORES DA FASE (atualizada)
// ==========================================
function getFaseColor($fase) {
    $cores = [
        'Novo'                  => 'bg-info text-dark',
        'Tentativa de Contato'  => 'bg-warning text-dark',
        'Contato Feito'         => 'bg-primary text-white',
        'Procurar Imóvel'       => 'bg-teal text-white',
        'Agendar Visita'        => 'bg-purple text-white',
        'Visita Agendada'       => 'bg-success text-white',
        'Visita Realizada'      => 'bg-dark text-white',
        'Analisando'            => 'bg-secondary text-white',
        'Proposta'              => 'bg-danger text-white',
        'Fechado'               => 'bg-success text-white',
        'Perdido'               => 'bg-light text-muted'
    ];
    return $cores[$fase] ?? 'bg-light text-dark';
}

// ==========================================
// FILTROS
// ==========================================
$busca = $_GET['busca'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';
$filtro_valor_max = isset($_GET['valor_max_filtro']) && is_numeric($_GET['valor_max_filtro']) ? (float)$_GET['valor_max_filtro'] : null;
$filtro_favorito = isset($_GET['favorito']) && $_GET['favorito'] == '1';

$where = "WHERE 1=1 AND fase_funil <> 'Perdido' ";
$params = [];

if (!$show_deleted) {
    $where .= " AND deleted_at IS NULL";
}
if (!empty($busca)) {
    $where .= " AND (nome LIKE ? OR primeiro_nome LIKE ? OR obs_parceiros LIKE ? OR observacoes LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}
if ($filtro_valor_max !== null && $filtro_valor_max > 0) {
    $where .= " AND valor_max >= ?";
    $params[] = $filtro_valor_max;
}
if ($filtro_favorito) {
    $where .= " AND favorito = 1";
}

$sql = "SELECT id, nome, primeiro_nome, tipo_pagamento, quartos_min, valor_max, obs_parceiros, observacoes, 
               compartilhado_parceiro, favorito, contatar_hoje, deleted_at,
               fase_funil,
               COALESCE(DATEDIFF(NOW(), ultima_interacao), 0) as dias_parado,
               created_at
        FROM leads $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sqlDistinct = "SELECT DISTINCT valor_max FROM leads WHERE deleted_at IS NULL AND valor_max > 0 ORDER BY valor_max ASC";
$stmtDist = $conn->query($sqlDistinct);
$valores_distinct = $stmtDist->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';

function getDiasBadgeClass($dias) {
    if ($dias == 0) return 'bg-success';
    if ($dias <= 3) return 'bg-warning text-dark';
    return 'bg-danger';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Observações para Parceiros - Listagem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .obs-cell, .nome-cell, .nome-completo-cell, .valor-max-cell, .observacoes-cell, .tipo-pagamento-cell, .fase-cell { cursor: pointer; transition: background-color 0.2s; }
        .obs-cell:hover, .observacoes-cell:hover { background-color: #fff3cd; text-decoration: underline; }
        .nome-cell:hover, .nome-completo-cell:hover, .valor-max-cell:hover, .tipo-pagamento-cell:hover, .fase-cell:hover { background-color: #e2f0ff; text-decoration: underline; }
        .row-shared { background-color: #f0f7ff !important; border-left: 4px solid #0d6efd; }
        .row-deleted { background-color: #f8d7da !important; opacity: 0.7; text-decoration: line-through; color: #6c757d; }
        .row-favorito td { background-color: #e3f2fd !important; }
        .row-favorito:hover td { background-color: #d0e4ff !important; }
        .row-contatar-hoje td { background-color: #d4edda !important; }
        .row-contatar-hoje:hover td { background-color: #c8e6d2 !important; }
        .row-favorito.row-contatar-hoje td { background-color: #e3f2fd !important; }
        .favorito-star { cursor: pointer; font-size: 1.3rem; transition: transform 0.1s; }
        .favorito-star:hover { transform: scale(1.2); }
        .reset-dias-icon { cursor: pointer; font-size: 1.1rem; margin-left: 6px; transition: transform 0.1s; color: #28a745; }
        .reset-dias-icon:hover { transform: scale(1.15); }
        .btn-perdido { background-color: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; transition: all 0.2s; }
        .btn-perdido:hover { background-color: #bb2d3b; transform: scale(1.02); }
        .btn-favorito-filtro { background: #fff; border: 1px solid #ddd; border-radius: 20px; padding: 5px 14px; color: #6c757d; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem; }
        .btn-favorito-filtro:hover { background: #f0f0f0; }
        .btn-favorito-filtro.active { background: #ffc107; border-color: #ffc107; color: #212529; font-weight: 600; }
        /* Cores personalizadas para as novas fases */
        .bg-teal { background-color: #20c997 !important; color: white !important; }
        .bg-purple { background-color: #6f42c1 !important; color: white !important; }
        /* ... demais estilos existentes ... */
        #tabelaLeads {
            table-layout: fixed;
            width: 100%;
            min-width: 1200px;
        }
        #tabelaLeads th, #tabelaLeads td {
            padding: 6px 4px !important;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        #tabelaLeads th:nth-child(1), #tabelaLeads td:nth-child(1) { width: 4%; }
        #tabelaLeads th:nth-child(2), #tabelaLeads td:nth-child(2) { width: 7%; }
        #tabelaLeads th:nth-child(3), #tabelaLeads td:nth-child(3) { width: 9%; }
        #tabelaLeads th:nth-child(4), #tabelaLeads td:nth-child(4) { width: 8%; }
        #tabelaLeads th:nth-child(5), #tabelaLeads td:nth-child(5) { width: 7%; }
        #tabelaLeads th:nth-child(6), #tabelaLeads td:nth-child(6) { width: 4%; }
        #tabelaLeads th:nth-child(7), #tabelaLeads td:nth-child(7) { width: 12%; }
        #tabelaLeads th:nth-child(8), #tabelaLeads td:nth-child(8) { width: 8%; }
        #tabelaLeads th:nth-child(9), #tabelaLeads td:nth-child(9) { width: 11%; }
        #tabelaLeads th:nth-child(10), #tabelaLeads td:nth-child(10) { width: 11%; }
        #tabelaLeads th:nth-child(11), #tabelaLeads td:nth-child(11) { width: 7%; }
        #tabelaLeads th:nth-child(12), #tabelaLeads td:nth-child(12) { width: 7%; }
        #tabelaLeads th:nth-child(13), #tabelaLeads td:nth-child(13) { width: 10%; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #tabelaLeads td .badge { font-size: 0.7rem; padding: 4px 6px; }
        #tabelaLeads td .form-check { margin: 0; padding-left: 1.5em; }
        #tabelaLeads td .form-check-input { margin-left: -1.2em; }
        #tabelaLeads td .btn-sm { font-size: 0.65rem; padding: 2px 6px; }
        #tabelaLeads td .btn-perdido { font-size: 0.6rem; padding: 2px 6px; }
        #tabelaLeads td .btn-duplicate { font-size: 0.6rem; padding: 2px 6px; }
        .obs-cell, .observacoes-cell { font-size: 0.75rem; line-height: 1.3; }
        .tipo-pagamento-cell { display: inline-block; padding: 0 4px; border-radius: 4px; }
        @media (max-width: 768px) {
            #tabelaLeads { table-layout: auto; min-width: unset; }
            #tabelaLeads thead { display: none; }
            #tabelaLeads tbody, #tabelaLeads tr, #tabelaLeads td { display: block; width: 100%; }
            #tabelaLeads tr { background: #fff; border-radius: 12px; margin-bottom: 16px; padding: 12px; border: 1px solid #e9ecef; position: relative; }
            #tabelaLeads td { padding: 6px 0 !important; text-align: left !important; border: none !important; }
            #tabelaLeads td::before { content: attr(data-label); display: inline-block; font-weight: bold; width: 40%; font-size: 0.75rem; color: #6c757d; text-transform: uppercase; }
            #tabelaLeads td[data-label="Ações"]::before,
            #tabelaLeads td[data-label="Favorito"]::before,
            #tabelaLeads td[data-label="Compartilhar"]::before,
            #tabelaLeads td[data-label="Contatar Hoje"]::before { width: auto; margin-right: 8px; }
            .btn-group { display: inline-flex; gap: 4px; }
            .form-check { display: inline-block; }
            .favorito-star { font-size: 1.1rem; }
            .dataTables_filter input { width: 100%; }
            #tabelaLeads td .btn-sm { font-size: 0.7rem; padding: 4px 8px; }
        }
        .filter-bar { background: white; border-radius: 20px; padding: 12px 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-select { min-width: 180px; }
        @media (max-width: 768px) { .filter-select { width: 100%; margin-top: 8px; } }
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
            <p class="text-muted small mb-0">
                Clique nos campos destacados para editar | 
                <span class="badge bg-info">★ Favorito = fundo azul</span> 
                <span class="badge bg-success">📞 Contatar Hoje = fundo verde</span>
                <span class="badge bg-warning text-dark">🟠 1-3 dias</span>
                <span class="badge bg-danger">🔴 >3 dias parado</span>
                <i class="bi bi-check-circle-fill text-success"></i> clique para zerar |
                <i class="bi bi-files text-info"></i> Dupe = duplicar lead |
                <i class="bi bi-credit-card"></i> Clique no tipo de pagamento para editar |
                <i class="bi bi-arrow-repeat"></i> Clique na fase para alterar
            </p>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto">
            <a href="lead_form.php" class="btn btn-success shadow-sm"><i class="bi bi-plus-circle"></i> Novo Lead</a>
            <a href="leads3.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="filter-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center flex-grow-1">
            <div class="d-flex gap-2 flex-grow-1">
                <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, observações..." value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <label class="fw-semibold text-nowrap">Valor ≥</label>
                <select name="valor_max_filtro" class="form-select filter-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($valores_distinct as $valor): 
                        $valor_formatado = 'R$ ' . number_format($valor, 0, ',', '.');
                        $selected = ($filtro_valor_max !== null && (float)$filtro_valor_max == $valor) ? 'selected' : '';
                    ?>
                        <option value="<?= $valor ?>" <?= $selected ?>><?= $valor_formatado ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="show_deleted" value="1" id="showDeleted" <?= $show_deleted ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label" for="showDeleted">Mostrar excluídos</label>
            </div>
            <a href="<?= htmlspecialchars('?' . http_build_query(array_merge($_GET, ['favorito' => $filtro_favorito ? '0' : '1'], ['show_deleted' => $show_deleted ? '1' : '0']))) ?>" 
               class="btn-favorito-filtro <?= $filtro_favorito ? 'active' : '' ?>">
                <i class="bi <?= $filtro_favorito ? 'bi-star-fill' : 'bi-star' ?>"></i> 
                <?= $filtro_favorito ? 'Favoritos' : 'Todos' ?>
            </a>
            <?php if ($busca || $filtro_valor_max || $show_deleted || $filtro_favorito): ?>
                <a href="parceiros_obs.php" class="btn btn-outline-danger btn-sm">Limpar filtros</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabela -->
    <div class="card card-custom shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabelaLeads" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Dias Parado</th>
                            <th>Criado em</th>
                            <th>Valor / Pagamento</th>
                            <th>Fase</th>
                            <th><i class="bi bi-star-fill"></i></th>
                            <th>Nome Completo</th>
                            <th>Primeiro Nome</th>
                            <th>Obs Gerais</th>
                            <th>Obs Parceiros</th>
                            <th class="text-center">Compartilhar</th>
                            <th class="text-center">Contatar Hoje</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $index => $lead): 
                            $is_shared = (int)($lead['compartilhado_parceiro'] ?? 0);
                            $is_favorito = (int)($lead['favorito'] ?? 0);
                            $is_contatar_hoje = (int)($lead['contatar_hoje'] ?? 0);
                            $is_deleted = !is_null($lead['deleted_at']);
                            $dias_parado = (int)($lead['dias_parado'] ?? 0);
                            $badge_dias_class = getDiasBadgeClass($dias_parado);
                            $created_at_formatado = !empty($lead['created_at']) ? date('d/m/Y H:i', strtotime($lead['created_at'])) : '—';
                            
                            $row_class = '';
                            if ($is_deleted) {
                                $row_class = 'row-deleted';
                            } else {
                                if ($is_favorito) $row_class .= ' row-favorito';
                                if ($is_contatar_hoje) $row_class .= ' row-contatar-hoje';
                                if ($is_shared) $row_class .= ' row-shared';
                                $row_class = trim($row_class);
                            }
                            
                            $valor_max_raw = $lead['valor_max'] ?? 0;
                            $valor_max_formatado = $valor_max_raw > 0 ? 'R$ ' . number_format($valor_max_raw, 0, ',', '.') : '—';
                            $observacoes = $lead['observacoes'] ?? '';
                            $tipo_pagamento = $lead['tipo_pagamento'] ?? '';
                            $quartos_min = $lead['quartos_min'] ?? '';
                            
                            $fase = $lead['fase_funil'] ?? 'Novo';
                            $fase_badge_class = getFaseColor($fase);
                        ?>
                        <tr id="row-lead-<?= $lead['id'] ?>" class="<?= $row_class ?>">
                            <td data-label="#"><?= $index + 1 ?></td>
                            <td data-label="Dias Parado">
                                <span class="badge <?= $badge_dias_class ?> px-3 py-2" id="dias-parado-<?= $lead['id'] ?>">
                                    <?= $dias_parado ?> dias
                                </span>
                                <?php if (!$is_deleted): ?>
                                    <i class="bi bi-check-circle-fill reset-dias-icon" data-id="<?= $lead['id'] ?>" title="Registrar ação (zerar dias parados)"></i>
                                <?php endif; ?>
                            </td>
                            <td data-label="Criado em" class="text-muted small"><?= $created_at_formatado ?></td>
                            <td data-label="Valor / Pagamento" class="fw-semibold">
                                <div class="valor-max-cell" data-id="<?= $lead['id'] ?>" data-valor="<?= $valor_max_raw ?>" style="cursor: pointer;">
                                    <?= $valor_max_formatado ?>
                                </div>
                                <span class="tipo-pagamento-cell text-muted" 
                                      style="font-size: 0.65rem; cursor: pointer; display: inline-block; padding: 2px 4px; border-radius: 4px;"
                                      data-id="<?= $lead['id'] ?>" 
                                      data-tipo="<?= htmlspecialchars($tipo_pagamento) ?>">
                                    <?php if (!empty($tipo_pagamento)): ?>
                                        <i class="bi bi-credit-card"></i> <?= htmlspecialchars($tipo_pagamento) ?>
                                    <?php else: ?>
                                        <i class="bi bi-plus-circle text-muted"></i> Adicionar pagamento
                                    <?php endif; ?>
                                    <br>
                                    <?php if (!empty($quartos_min)): ?>
                                        <i class="bi bi-door-open"></i> <?= htmlspecialchars($quartos_min) ?> qtos
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Fase" class="fase-cell" data-id="<?= $lead['id'] ?>" data-fase="<?= htmlspecialchars($fase) ?>" style="cursor: pointer;">
                                <span class="badge <?= $fase_badge_class ?> px-3 py-2 w-100 text-center"><?= htmlspecialchars($fase) ?></span>
                            </td>
                            <td data-label="Favorito" class="text-center">
                                <i class="bi <?= $is_favorito ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> favorito-star" data-id="<?= $lead['id'] ?>"></i>
                            </td>
                            <td data-label="Nome Completo" class="fw-semibold nome-completo-cell" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                <i class="bi bi-person-circle text-primary me-1"></i><?= htmlspecialchars($lead['nome']) ?>
                            </td>
                            <td data-label="Primeiro Nome" class="nome-cell" data-id="<?= $lead['id'] ?>" data-nome-completo="<?= htmlspecialchars($lead['nome']) ?>" data-primeiro-nome="<?= htmlspecialchars($lead['primeiro_nome'] ?? '') ?>">
                                <i class="bi bi-person-bounding-box text-info me-1"></i><?= htmlspecialchars($lead['primeiro_nome'] ?: '—') ?>
                            </td>
                            <td data-label="Obs Gerais" class="observacoes-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($observacoes) ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                <small class="d-block text-muted fw-bold mb-1 text-uppercase" style="font-size: 0.65rem;">Obs Gerais</small>
                                <?php if (!empty($observacoes)): ?>
                                    <i class="bi bi-chat-text text-secondary me-1"></i><?= nl2br(htmlspecialchars($observacoes)) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Obs Parceiros" class="obs-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($lead['obs_parceiros'] ?? '') ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                <small class="d-block text-muted fw-bold mb-1 text-uppercase" style="font-size: 0.65rem;">Obs Parceiros</small>
                                <?php if (!empty($lead['obs_parceiros'])): ?>
                                    <i class="bi bi-shield-shaded text-warning me-1"></i><?= nl2br(htmlspecialchars($lead['obs_parceiros'])) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Compartilhar" class="text-center">
                                <div class="form-check form-switch d-inline-block align-middle">
                                    <input class="form-check-input toggle-share" type="checkbox" id="share-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_shared ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                                    <label class="form-check-label small text-muted ms-1" for="share-<?= $lead['id'] ?>">Comp.</label>
                                </div>
                            </td>
                            <td data-label="Contatar Hoje" class="text-center">
                                <div class="form-check form-switch d-inline-block align-middle">
                                    <input class="form-check-input toggle-contatar-hoje" type="checkbox" id="switch-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_contatar_hoje ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                                    <label class="form-check-label small text-muted ms-1" for="switch-<?= $lead['id'] ?>">Hoje</label>
                                </div>
                            </td>
                            <td data-label="Ações" class="text-center">
                                <?php if ($is_deleted): ?>
                                    <button class="btn btn-sm btn-outline-success btn-restore" data-id="<?= $lead['id'] ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-info btn-duplicate ms-1" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" title="Duplicar lead">
                                        <i class="bi bi-files"></i> Dupe
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-soft-delete" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>"><i class="bi bi-trash"></i></button>
                                    <button class="btn btn-sm btn-perdido ms-1" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                        <i class="bi bi-x-octagon"></i> Perdido
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-3 text-center">
        <i class="bi bi-info-circle"></i> Clique nos campos destacados para editar. 
        <i class="bi bi-star-fill text-warning"></i> Favorito = fundo azul claro. 
        <i class="bi bi-telephone-fill"></i> Contatar Hoje = fundo verde claro.
        <i class="bi bi-check-circle-fill text-success"></i> Clique no check para zerar dias parados.
        <i class="bi bi-x-octagon text-danger"></i> "Perdido" move o lead para a fase "Perdido".
        <i class="bi bi-files text-info"></i> "Dupe" duplica o lead.
        <i class="bi bi-credit-card"></i> Clique no tipo de pagamento para escolher "Financiamento" ou "À Vista".
        <i class="bi bi-arrow-repeat"></i> Clique na fase para alterar (inclui "Contato Feito" e "Procurar Imóvel").
    </div>
</div>

<!-- Modais (mantidos) -->
<div class="modal fade" id="modalNomeCompleto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar nome completo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="nomeCompletoInput" class="form-control"><input type="hidden" id="nomeCompletoLeadId"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="btnSalvarNomeCompleto" class="btn btn-primary">Salvar</button></div></div></div>
</div>
<div class="modal fade" id="modalPrimeiroNome" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Editar primeiro nome</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" class="form-control bg-light" id="nomeCompletoReadonly" readonly><input type="text" id="primeiroNomeInput" class="form-control mt-2"><input type="hidden" id="primeiroNomeLeadId"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="btnSalvarPrimeiroNome" class="btn btn-primary">Salvar</button></div></div></div>
</div>
<div class="modal fade" id="modalObsParceiros" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-warning text-dark"><h5 class="modal-title">Editar observações (parceiros)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" class="form-control bg-light" id="leadNome" readonly><textarea id="obsParceirosTexto" class="form-control mt-2" rows="5"></textarea><input type="hidden" id="leadId"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="btnSalvarObs" class="btn btn-warning">Salvar</button></div></div></div>
</div>
<div class="modal fade" id="modalObservacoes" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-info text-white"><h5 class="modal-title">Editar observações gerais</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" class="form-control bg-light" id="observacoesLeadNome" readonly><textarea id="observacoesTexto" class="form-control mt-2" rows="5"></textarea><input type="hidden" id="observacoesLeadId"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="btnSalvarObservacoes" class="btn btn-info">Salvar</button></div></div></div>
</div>
<div class="modal fade" id="modalValorMax" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title">Editar valor máximo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" class="form-control bg-light" id="valorMaxLeadNome" readonly><input type="number" id="valorMaxInput" class="form-control mt-2" step="1" min="0"><input type="hidden" id="valorMaxLeadId"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="btnSalvarValorMax" class="btn btn-success">Salvar</button></div></div></div>
</div>

<!-- Modal Tipo Pagamento -->
<div class="modal fade" id="modalTipoPagamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Editar Tipo de Pagamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control bg-light mb-3" id="tipoPagamentoLeadNome" readonly placeholder="Lead">
                <label class="fw-semibold mb-1">Selecione o tipo de pagamento:</label>
                <select id="tipoPagamentoSelect" class="form-select">
                    <option value="">Nenhum</option>
                    <option value="Financiamento">Financiamento</option>
                    <option value="À Vista">À Vista</option>
                </select>
                <input type="hidden" id="tipoPagamentoLeadId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="btnSalvarTipoPagamento" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fase (com todas as fases atualizadas) -->
<div class="modal fade" id="modalFase" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Alterar Fase do Lead</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control bg-light mb-3" id="faseLeadNome" readonly placeholder="Lead">
                <label class="fw-semibold mb-1">Selecione a nova fase:</label>
                <select id="faseSelect" class="form-select">
                    <?php foreach ($fases_lista as $f): ?>
                        <option value="<?= $f ?>"><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="faseLeadId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="btnSalvarFase" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    var currentScript = window.location.pathname.split('/').pop();
    var table = $('#tabelaLeads').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" },
        "order": [[0, 'asc']],
        "columnDefs": [{ "type": "num", "targets": 0 }],
        "pageLength": 100,
        "responsive": false,
        "scrollX": true
    });

    function formatMoney(value) {
        let num = parseFloat(value);
        if (isNaN(num)) num = 0;
        return 'R$ ' + num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Mapeamento de cores para as fases (incluindo as novas)
    const faseColors = {
        'Novo': 'bg-info text-dark',
        'Tentativa de Contato': 'bg-warning text-dark',
        'Contato Feito': 'bg-primary text-white',
        'Procurar Imóvel': 'bg-teal text-white',
        'Agendar Visita': 'bg-purple text-white',
        'Visita Agendada': 'bg-success text-white',
        'Visita Realizada': 'bg-dark text-white',
        'Analisando': 'bg-secondary text-white',
        'Proposta': 'bg-danger text-white',
        'Fechado': 'bg-success text-white',
        'Perdido': 'bg-light text-muted'
    };
    function getFaseColor(fase) {
        return faseColors[fase] || 'bg-light text-dark';
    }

    // ==================== Resetar dias parados ====================
    $(document).on('click', '.reset-dias-icon', function() {
        let icon = $(this);
        let id = icon.data('id');
        let row = icon.closest('tr');
        let badgeSpan = $('#dias-parado-' + id);
        
        icon.css('pointer-events', 'none');
        $.post(currentScript, { action: 'update_last_interaction', id: id }, function(res) {
            if (res.status === 'success') {
                badgeSpan.text('0 dias').removeClass('bg-warning bg-danger').addClass('bg-success');
                icon.css('transform', 'scale(1.2)');
                setTimeout(() => icon.css('transform', ''), 200);
                row.css('background-color', '#d4edda').delay(800).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else {
                alert('Erro ao registrar ação. Tente novamente.');
            }
        }).always(() => icon.css('pointer-events', ''));
    });

    // ==================== Editar valor máximo ====================
    $(document).on('click', '.valor-max-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        $('#valorMaxLeadId').val($(this).data('id'));
        $('#valorMaxLeadNome').val($(this).closest('tr').find('.nome-completo-cell').data('nome'));
        $('#valorMaxInput').val($(this).data('valor'));
        $('#modalValorMax').modal('show');
    });
    $('#btnSalvarValorMax').on('click', function() {
        let id = $('#valorMaxLeadId').val();
        let valor = parseFloat($('#valorMaxInput').val());
        if (isNaN(valor)) valor = 0;
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_valor_max', id: id, valor_max: valor }, function(res) {
            if (res.status === 'success') {
                let celula = $('.valor-max-cell[data-id="' + id + '"]');
                celula.html(valor > 0 ? formatMoney(valor) : '—').data('valor', valor);
                $('#modalValorMax').modal('hide');
                $('#row-lead-' + id).css('background-color', '#d4edda').delay(500).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro ao salvar.');
        }).always(() => btn.prop('disabled', false).html('Salvar'));
    });

    // ==================== Favorito ====================
    $(document).on('click', '.favorito-star', function() {
        let icon = $(this);
        let id = icon.data('id');
        let newState = icon.hasClass('bi-star-fill') ? 0 : 1;
        let row = icon.closest('tr');
        icon.css('pointer-events', 'none');
        $.post(currentScript, { action: 'toggle_favorito', id: id, value: newState }, function(res) {
            if (res.status === 'success') {
                if (newState) {
                    icon.removeClass('bi-star text-muted').addClass('bi-star-fill text-warning');
                    row.addClass('row-favorito');
                } else {
                    icon.removeClass('bi-star-fill text-warning').addClass('bi-star text-muted');
                    row.removeClass('row-favorito');
                }
                row.css('background-color', '').delay(200).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro ao alterar favorito');
        }).always(() => icon.css('pointer-events', ''));
    });

    // ==================== Contatar Hoje ====================
    $(document).on('change', '.toggle-contatar-hoje', function() {
        let cb = $(this);
        if (cb.prop('disabled')) return;
        let id = cb.data('id');
        let val = cb.is(':checked') ? 1 : 0;
        let row = $('#row-lead-' + id);
        $.post(currentScript, { action: 'toggle_contatar_hoje', id: id, value: val }, function(res) {
            if (res.status === 'success') {
                if (val) row.addClass('row-contatar-hoje');
                else row.removeClass('row-contatar-hoje');
                row.css('background-color', '#d4edda').delay(500).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else { alert('Erro'); cb.prop('checked', !val); }
        });
    });

    // ==================== Nome completo ====================
    $(document).on('click', '.nome-completo-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        $('#nomeCompletoLeadId').val($(this).data('id'));
        $('#nomeCompletoInput').val($(this).data('nome'));
        $('#modalNomeCompleto').modal('show');
    });
    $('#btnSalvarNomeCompleto').on('click', function() {
        let id = $('#nomeCompletoLeadId').val();
        let novoNome = $('#nomeCompletoInput').val().trim();
        if (!novoNome) { alert('Nome não pode ficar vazio'); return; }
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_nome', id: id, nome: novoNome }, function(res) {
            if (res.status === 'success') {
                let celula = $('.nome-completo-cell[data-id="' + id + '"]');
                celula.html('<i class="bi bi-person-circle text-primary me-1"></i> ' + novoNome).data('nome', novoNome);
                $('.nome-cell[data-id="' + id + '"]').data('nome-completo', novoNome);
                $('#modalNomeCompleto').modal('hide');
                $('#row-lead-' + id).css('background-color', '#e2f0ff').delay(800).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro');
        }).always(() => btn.prop('disabled', false).html('Salvar'));
    });

    // ==================== Primeiro nome ====================
    $(document).on('click', '.nome-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        $('#primeiroNomeLeadId').val($(this).data('id'));
        $('#primeiroNomeInput').val($(this).data('primeiro-nome') || '');
        $('#nomeCompletoReadonly').val($(this).data('nome-completo') || '');
        $('#modalPrimeiroNome').modal('show');
    });
    $('#btnSalvarPrimeiroNome').on('click', function() {
        let id = $('#primeiroNomeLeadId').val();
        let novoNome = $('#primeiroNomeInput').val().trim();
        if (!novoNome) { alert('Primeiro nome não pode ficar vazio'); return; }
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_primeiro_nome', id: id, primeiro_nome: novoNome }, function(res) {
            if (res.status === 'success') {
                let celula = $('.nome-cell[data-id="' + id + '"]');
                celula.html('<i class="bi bi-person-bounding-box text-info me-1"></i> ' + novoNome).data('primeiro-nome', novoNome);
                $('#modalPrimeiroNome').modal('hide');
                $('#row-lead-' + id).css('background-color', '#d1e7ff').delay(800).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro');
        }).always(() => btn.prop('disabled', false).html('Salvar'));
    });

    // ==================== Obs Parceiros ====================
    $(document).on('click', '.obs-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        $('#leadId').val($(this).data('id'));
        $('#leadNome').val($(this).data('nome'));
        $('#obsParceirosTexto').val($(this).data('obs') || '');
        $('#modalObsParceiros').modal('show');
    });
    $('#btnSalvarObs').on('click', function() {
        let id = $('#leadId').val();
        let obs = $('#obsParceirosTexto').val().trim();
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_obs_parceiros', id: id, obs_parceiros: obs }, function(res) {
            if (res.status === 'success') {
                let celula = $('.obs-cell[data-id="' + id + '"]');
                if (obs) celula.html('<i class="bi bi-shield-shaded text-warning me-1"></i> ' + obs.replace(/\n/g, '<br>'));
                else celula.html('<span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>');
                celula.data('obs', obs);
                $('#modalObsParceiros').modal('hide');
                $('#row-lead-' + id).css('background-color', '#fff9e6').delay(800).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro');
        }).always(() => btn.prop('disabled', false).html('Salvar'));
    });

    // ==================== Observações Gerais ====================
    $(document).on('click', '.observacoes-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        $('#observacoesLeadId').val($(this).data('id'));
        $('#observacoesLeadNome').val($(this).data('nome'));
        $('#observacoesTexto').val($(this).data('obs') || '');
        $('#modalObservacoes').modal('show');
    });
    $('#btnSalvarObservacoes').on('click', function() {
        let id = $('#observacoesLeadId').val();
        let obs = $('#observacoesTexto').val().trim();
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_observacoes', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') {
                let celula = $('.observacoes-cell[data-id="' + id + '"]');
                if (obs) celula.html('<i class="bi bi-chat-text text-secondary me-1"></i> ' + obs.replace(/\n/g, '<br>'));
                else celula.html('<span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>');
                celula.data('obs', obs);
                $('#modalObservacoes').modal('hide');
                $('#row-lead-' + id).css('background-color', '#cfe2ff').delay(800).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else alert('Erro ao salvar observações.');
        }).always(() => btn.prop('disabled', false).html('Salvar'));
    });

    // ==================== Toggle Compartilhar ====================
    $(document).on('change', '.toggle-share', function() {
        let cb = $(this);
        if (cb.prop('disabled')) return;
        let id = cb.data('id');
        let val = cb.is(':checked') ? 1 : 0;
        $.post(currentScript, { action: 'toggle_share', id: id, value: val }, function(res) {
            if (res.status === 'success') {
                if (val) $('#row-lead-' + id).addClass('row-shared');
                else $('#row-lead-' + id).removeClass('row-shared');
                $('#row-lead-' + id).css('background-color', '#d1e7ff').delay(500).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else { alert('Erro'); cb.prop('checked', !val); }
        });
    });

    // ==================== Soft Delete e Restore ====================
    $(document).on('click', '.btn-soft-delete', function() {
        if (!confirm('Excluir logicamente este lead?')) return;
        let btn = $(this);
        let id = btn.data('id');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'soft_delete', id: id }, res => { if (res.status === 'success') location.reload(); else alert('Erro'); });
    });
    $(document).on('click', '.btn-restore', function() {
        let btn = $(this);
        let id = btn.data('id');
        if (!confirm('Restaurar lead?')) return;
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'restore', id: id }, res => { if (res.status === 'success') location.reload(); else alert('Erro'); });
    });

    // ==================== MARCAR COMO PERDIDO ====================
    $(document).on('click', '.btn-perdido', function() {
        let id = $(this).data('id');
        let nome = $(this).data('nome');
        if (!confirm(`Tem certeza que deseja marcar o lead "${nome}" como PERDIDO? Ele será removido da lista de ativos.`)) return;
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'marcar_perdido', id: id }, function(res) {
            if (res.status === 'success') {
                let row = $('#row-lead-' + id);
                row.css('background-color', '#f8d7da').fadeOut(400, function() {
                    table.row(row).remove().draw();
                });
            } else {
                alert('Erro ao marcar lead como perdido. Tente novamente.');
                btn.prop('disabled', false).html('<i class="bi bi-x-octagon"></i> Perdido');
            }
        }).fail(() => {
            alert('Erro de comunicação.');
            btn.prop('disabled', false).html('<i class="bi bi-x-octagon"></i> Perdido');
        });
    });

    // ==================== DUPLICAR LEAD ====================
    $(document).on('click', '.btn-duplicate', function() {
        let id = $(this).data('id');
        let nome = $(this).data('nome');
        if (!confirm(`Duplicar o lead "${nome}"? As informações de perfil serão copiadas, mas telefone e e-mail serão zerados para edição posterior.`)) return;
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'duplicate_lead', id: id }, function(res) {
            if (res.status === 'success') {
                alert(`Lead duplicado com sucesso! Novo ID: ${res.new_id}. A página será recarregada.`);
                location.reload();
            } else {
                alert('Erro ao duplicar: ' + (res.message || 'Tente novamente.'));
                btn.prop('disabled', false).html('<i class="bi bi-files"></i> Dupe');
            }
        }).fail(() => {
            alert('Erro de comunicação com o servidor.');
            btn.prop('disabled', false).html('<i class="bi bi-files"></i> Dupe');
        });
    });

    // ==================== EDITAR TIPO DE PAGAMENTO ====================
    $(document).on('click', '.tipo-pagamento-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        let id = $(this).data('id');
        let tipo = $(this).data('tipo');
        let nome = $(this).closest('tr').find('.nome-completo-cell').data('nome') || 'Lead';
        
        $('#tipoPagamentoLeadId').val(id);
        $('#tipoPagamentoLeadNome').val(nome);
        $('#tipoPagamentoSelect').val(tipo || '');
        $('#modalTipoPagamento').modal('show');
    });

    $('#btnSalvarTipoPagamento').on('click', function() {
        let id = $('#tipoPagamentoLeadId').val();
        let tipo = $('#tipoPagamentoSelect').val().trim();
        let btn = $(this);
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_tipo_pagamento', id: id, tipo_pagamento: tipo }, function(res) {
            if (res.status === 'success') {
                let celula = $('.tipo-pagamento-cell[data-id="' + id + '"]');
                if (tipo) {
                    celula.html('<i class="bi bi-credit-card"></i> ' + tipo);
                } else {
                    celula.html('<i class="bi bi-plus-circle text-muted"></i> Adicionar pagamento');
                }
                celula.data('tipo', tipo);
                $('#modalTipoPagamento').modal('hide');
                $('#row-lead-' + id).css('background-color', '#d4edda').delay(500).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else {
                alert('Erro ao salvar tipo de pagamento.');
            }
        }).always(() => {
            btn.prop('disabled', false).html('Salvar');
        });
    });

    // ==================== EDITAR FASE (com suporte às novas fases) ====================
    $(document).on('click', '.fase-cell', function() {
        if ($(this).closest('tr').hasClass('row-deleted')) { alert('Lead excluído.'); return; }
        let id = $(this).data('id');
        let fase = $(this).data('fase');
        let nome = $(this).closest('tr').find('.nome-completo-cell').data('nome') || 'Lead';
        
        $('#faseLeadId').val(id);
        $('#faseLeadNome').val(nome);
        $('#faseSelect').val(fase);
        $('#modalFase').modal('show');
    });

    $('#btnSalvarFase').on('click', function() {
        let id = $('#faseLeadId').val();
        let fase = $('#faseSelect').val();
        let btn = $(this);
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post(currentScript, { action: 'update_fase', id: id, fase: fase }, function(res) {
            if (res.status === 'success') {
                let celula = $('.fase-cell[data-id="' + id + '"]');
                let badge = celula.find('.badge');
                let newClass = getFaseColor(fase);
                badge.text(fase).removeClass().addClass('badge px-3 py-2 w-100 text-center ' + newClass);
                celula.data('fase', fase);
                $('#modalFase').modal('hide');
                $('#row-lead-' + id).css('background-color', '#d4edda').delay(500).queue(function(n) { $(this).css('background-color', ''); n(); });
            } else {
                alert('Erro ao salvar fase.');
            }
        }).always(() => {
            btn.prop('disabled', false).html('Salvar');
        });
    });

});
</script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>