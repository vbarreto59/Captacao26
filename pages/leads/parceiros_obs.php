<?php
// listagem_obs_parceiros.php - Destaque para favoritos (azul), contatar hoje (verde) e dias parados
// INCLUI IMÓVEIS DE INTERESSE DE CADA LEAD
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
// LÓGICA AJAX (ações inline) - MANTIDA IGUAL
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

// ==========================================
// CONSTRUÇÃO DA WHERE COM ALIAS l.
// ==========================================
$where = "WHERE 1=1 AND l.fase_funil <> 'Perdido' ";
$params = [];

if (!$show_deleted) {
    $where .= " AND l.deleted_at IS NULL";
}
if (!empty($busca)) {
    $where .= " AND (l.nome LIKE ? OR l.primeiro_nome LIKE ? OR l.obs_parceiros LIKE ? OR l.observacoes LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}
if ($filtro_valor_max !== null && $filtro_valor_max > 0) {
    $where .= " AND l.valor_max >= ?";
    $params[] = $filtro_valor_max;
}
if ($filtro_favorito) {
    $where .= " AND l.favorito = 1";
}

// ==========================================
// CONSULTA PRINCIPAL COM IMÓVEIS DE INTERESSE (CORRIGIDA)
// ==========================================
$sql = "SELECT 
            l.id, 
            l.nome, 
            l.primeiro_nome, 
            l.tipo_pagamento, 
            l.quartos_min, 
            l.valor_max, 
            l.obs_parceiros, 
            l.observacoes, 
            l.compartilhado_parceiro, 
            l.favorito, 
            l.contatar_hoje, 
            l.deleted_at,
            l.fase_funil,
            COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
            l.created_at,
            GROUP_CONCAT(DISTINCT i.titulo ORDER BY i.titulo ASC SEPARATOR '||') AS imoveis_titulos,
            GROUP_CONCAT(DISTINCT i.id ORDER BY i.titulo ASC SEPARATOR ',') AS imoveis_ids
        FROM leads l
        LEFT JOIN lead_imoveis li ON l.id = li.lead_id
        LEFT JOIN imoveis i ON li.imovel_id = i.id AND i.deleted_at IS NULL
        $where
        GROUP BY l.id
        ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Valores para o filtro de valor máximo (com alias l. para evitar ambiguidade)
$sqlDistinct = "SELECT DISTINCT l.valor_max FROM leads l WHERE l.deleted_at IS NULL AND l.valor_max > 0 ORDER BY l.valor_max ASC";
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
        .bg-teal { background-color: #20c997 !important; color: white !important; }
        .bg-purple { background-color: #6f42c1 !important; color: white !important; }
        
        /* Badge de imóveis */
        .badge-imovel-interesse {
            background-color: #e9ecef;
            color: #0d6efd;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin: 1px 2px;
            display: inline-block;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-imovel-interesse:hover {
            background-color: #0d6efd;
            color: white;
            transition: 0.2s;
        }
        .imoveis-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            margin-top: 4px;
        }

        /* ====== TABELA DESKTOP ====== */
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
        /* Larguras das colunas */
        #tabelaLeads th:nth-child(1), #tabelaLeads td:nth-child(1) { width: 3%; }
        #tabelaLeads th:nth-child(2), #tabelaLeads td:nth-child(2) { width: 6%; }
        #tabelaLeads th:nth-child(3), #tabelaLeads td:nth-child(3) { width: 7%; }
        #tabelaLeads th:nth-child(4), #tabelaLeads td:nth-child(4) { width: 7%; }
        #tabelaLeads th:nth-child(5), #tabelaLeads td:nth-child(5) { width: 5%; }
        #tabelaLeads th:nth-child(6), #tabelaLeads td:nth-child(6) { width: 4%; }
        #tabelaLeads th:nth-child(7), #tabelaLeads td:nth-child(7) { width: 12%; }
        #tabelaLeads th:nth-child(8), #tabelaLeads td:nth-child(8) { width: 7%; }
        #tabelaLeads th:nth-child(9), #tabelaLeads td:nth-child(9) { width: 11%; }
        #tabelaLeads th:nth-child(10), #tabelaLeads td:nth-child(10) { width: 10%; }
        #tabelaLeads th:nth-child(11), #tabelaLeads td:nth-child(11) { width: 10%; }
        #tabelaLeads th:nth-child(12), #tabelaLeads td:nth-child(12) { width: 6%; }
        #tabelaLeads th:nth-child(13), #tabelaLeads td:nth-child(13) { width: 6%; }
        #tabelaLeads th:nth-child(14), #tabelaLeads td:nth-child(14) { width: 10%; }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #tabelaLeads td .badge { font-size: 0.7rem; padding: 4px 6px; }
        #tabelaLeads td .form-check { margin: 0; padding-left: 1.5em; }
        #tabelaLeads td .form-check-input { margin-left: -1.2em; }
        #tabelaLeads td .btn-sm { font-size: 0.65rem; padding: 2px 6px; }
        #tabelaLeads td .btn-perdido { font-size: 0.6rem; padding: 2px 6px; }
        #tabelaLeads td .btn-duplicate { font-size: 0.6rem; padding: 2px 6px; }
        .obs-cell, .observacoes-cell { font-size: 0.75rem; line-height: 1.3; }
        .tipo-pagamento-cell { display: inline-block; padding: 0 4px; border-radius: 4px; }

        /* ====== FILTROS ====== */
        .filter-bar { background: white; border-radius: 20px; padding: 12px 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-select { min-width: 180px; }

        /* ====== CARDS MOBILE ====== */
        .lead-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: background 0.2s;
        }
        .lead-card.row-favorito {
            background-color: #e3f2fd !important;
        }
        .lead-card.row-contatar-hoje {
            background-color: #d4edda !important;
        }
        .lead-card.row-shared {
            border-left-color: #0d6efd !important;
        }
        .lead-card.row-deleted {
            opacity: 0.6;
            text-decoration: line-through;
            background-color: #f8d7da !important;
        }
        .lead-card .badge-imovel-interesse {
            background-color: #e9ecef;
            color: #0d6efd;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lead-card .valor-max-cell,
        .lead-card .nome-completo-cell,
        .lead-card .nome-cell,
        .lead-card .tipo-pagamento-cell,
        .lead-card .fase-cell,
        .lead-card .obs-cell,
        .lead-card .observacoes-cell {
            cursor: pointer;
        }
        .lead-card .reset-dias-icon {
            font-size: 1.4rem;
            color: #28a745;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .lead-card .reset-dias-icon:hover {
            transform: scale(1.15);
        }
        .lead-card .favorito-star {
            font-size: 1.6rem;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .lead-card .favorito-star:hover {
            transform: scale(1.2);
        }
        .lead-card .btn-perdido {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .lead-card .btn-duplicate {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .lead-card .btn-soft-delete {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .lead-card .btn-restore {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .lead-card .form-check-label {
            font-size: 0.8rem;
        }
        .lead-card .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
        }

        @media (max-width: 768px) {
            .filter-bar .d-flex.gap-2 {
                flex-wrap: wrap;
            }
            .filter-select {
                min-width: 120px;
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

    <!-- ======================================== -->
    <!-- TABELA (DESKTOP) -->
    <!-- ======================================== -->
    <div class="card card-custom shadow-sm d-none d-md-block">
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
                            <th>Nome Completo / Imóveis</th>
                            <th>Primeiro Nome</th>
                            <th>Obs Gerais</th>
                            <th>Obs Parceiros</th>
                            <th class="text-center">Compartilhar</th>
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

                            $imoveis_titulos = !empty($lead['imoveis_titulos']) ? explode('||', $lead['imoveis_titulos']) : [];
                            $imoveis_ids = !empty($lead['imoveis_ids']) ? explode(',', $lead['imoveis_ids']) : [];
                            $total_imoveis = count($imoveis_titulos);
                        ?>
                        <tr id="row-lead-<?= $lead['id'] ?>" class="<?= $row_class ?>">
                            <!-- Coluna 1: # -->
                            <td data-label="#" class="col-1"><?= $index + 1 ?></td>
                            
                            <!-- Coluna 2: Dias Parado -->
                            <td data-label="Dias Parado" class="col-2">
                                <span class="badge <?= $badge_dias_class ?> px-3 py-2" id="dias-parado-<?= $lead['id'] ?>">
                                    <?= $dias_parado ?> dias
                                </span>
                                <?php if (!$is_deleted): ?>
                                    <i class="bi bi-check-circle-fill reset-dias-icon" data-id="<?= $lead['id'] ?>" title="Registrar ação (zerar dias parados)"></i>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Coluna 3: Criado em -->
                            <td data-label="Criado em" class="col-3 text-muted small"><?= $created_at_formatado ?></td>
                            
                            <!-- Coluna 4: Valor / Pagamento -->
                            <td data-label="Valor / Pagamento" class="col-4 fw-semibold">
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
                            
                            <!-- Coluna 5: Fase -->
                            <td data-label="Fase" class="col-5 fase-cell" data-id="<?= $lead['id'] ?>" data-fase="<?= htmlspecialchars($fase) ?>" style="cursor: pointer;">
                                <span class="badge <?= $fase_badge_class ?> px-3 py-2 w-100 text-center"><?= htmlspecialchars($fase) ?></span>
                            </td>
                            
                            <!-- Coluna 6: Favorito -->
                            <td data-label="Favorito" class="col-6 text-center">
                                <i class="bi <?= $is_favorito ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> favorito-star" data-id="<?= $lead['id'] ?>"></i>
                            </td>
                            
                            <!-- Coluna 7: Nome Completo -->
                            <td data-label="Nome Completo" class="col-7 fw-semibold">
                                <div class="nome-completo-cell" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" style="cursor: pointer;">
                                    <i class="bi bi-person-circle text-primary me-1"></i><?= htmlspecialchars($lead['nome']) ?>
                                </div>
                            </td>
                            
                            <!-- Coluna 8: Primeiro Nome + Imóveis -->
                            <td data-label="Primeiro Nome / Imóveis" class="col-8 nome-cell" data-id="<?= $lead['id'] ?>" data-nome-completo="<?= htmlspecialchars($lead['nome']) ?>" data-primeiro-nome="<?= htmlspecialchars($lead['primeiro_nome'] ?? '') ?>">
                                <i class="bi bi-person-bounding-box text-info me-1"></i><?= htmlspecialchars($lead['primeiro_nome'] ?: '—') ?>
                                <?php if ($total_imoveis > 0): ?>
                                    <div class="imoveis-container">
                                        <?php foreach ($imoveis_titulos as $i => $titulo): 
                                            $id_imovel = isset($imoveis_ids[$i]) ? $imoveis_ids[$i] : '';
                                        ?>
                                            <span class="badge-imovel-interesse" title="ID: <?= $id_imovel ?>">
                                                <i class="bi bi-house-fill me-1"></i><?= htmlspecialchars($titulo) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">Nenhum</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Coluna 9: Obs Gerais -->
                            <td data-label="Obs Gerais" class="col-9 observacoes-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($observacoes) ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                <small class="d-block text-muted fw-bold mb-1 text-uppercase" style="font-size: 0.65rem;">Obs Gerais</small>
                                <?php if (!empty($observacoes)): ?>
                                    <i class="bi bi-chat-text text-secondary me-1"></i><?= nl2br(htmlspecialchars($observacoes)) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Coluna 10: Obs Parceiros -->
                            <td data-label="Obs Parceiros" class="col-10 obs-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($lead['obs_parceiros'] ?? '') ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                <small class="d-block text-muted fw-bold mb-1 text-uppercase" style="font-size: 0.65rem;">Obs Parceiros</small>
                                <?php if (!empty($lead['obs_parceiros'])): ?>
                                    <i class="bi bi-shield-shaded text-warning me-1"></i><?= nl2br(htmlspecialchars($lead['obs_parceiros'])) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Coluna 11: Compartilhar -->
                            <td data-label="Compartilhar" class="col-11 text-start">
                                <div class="form-check form-switch d-inline-block align-middle me-3">
                                    <input class="form-check-input toggle-share" type="checkbox" id="share-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_shared ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                                    <label class="form-check-label small text-muted ms-1" for="share-<?= $lead['id'] ?>">Comp.</label>
                                </div>
                                <div class="form-check form-switch d-inline-block align-middle">
                                    <input class="form-check-input toggle-contatar-hoje" type="checkbox" id="switch-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_contatar_hoje ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                                    <label class="form-check-label small text-muted ms-1" for="switch-<?= $lead['id'] ?>">Hoje</label>
                                </div>
                            </td>
                            
                            <!-- Coluna 12: Ações -->
                            <td data-label="Ações" class="col-12 text-center">
                                <?php if ($is_deleted): ?>
                                    <button class="btn btn-sm btn-outline-success btn-restore" data-id="<?= $lead['id'] ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap justify-content-center gap-1">
                                        <button class="btn btn-sm btn-outline-info btn-duplicate" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" title="Duplicar lead">
                                            <i class="bi bi-files"></i> Dupe
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-soft-delete" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-perdido" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>">
                                            <i class="bi bi-x-octagon"></i> Perdido
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- CARDS (MOBILE) -->
    <!-- ======================================== -->
    <div class="d-block d-md-none">
        <?php foreach ($leads as $index => $lead): 
            $is_shared = (int)($lead['compartilhado_parceiro'] ?? 0);
            $is_favorito = (int)($lead['favorito'] ?? 0);
            $is_contatar_hoje = (int)($lead['contatar_hoje'] ?? 0);
            $is_deleted = !is_null($lead['deleted_at']);
            $dias_parado = (int)($lead['dias_parado'] ?? 0);
            $badge_dias_class = getDiasBadgeClass($dias_parado);
            $created_at_formatado = !empty($lead['created_at']) ? date('d/m/Y H:i', strtotime($lead['created_at'])) : '—';
            $valor_max_raw = $lead['valor_max'] ?? 0;
            $valor_max_formatado = $valor_max_raw > 0 ? 'R$ ' . number_format($valor_max_raw, 0, ',', '.') : '—';
            $observacoes = $lead['observacoes'] ?? '';
            $tipo_pagamento = $lead['tipo_pagamento'] ?? '';
            $quartos_min = $lead['quartos_min'] ?? '';
            $fase = $lead['fase_funil'] ?? 'Novo';
            $fase_badge_class = getFaseColor($fase);
            $imoveis_titulos = !empty($lead['imoveis_titulos']) ? explode('||', $lead['imoveis_titulos']) : [];
            $imoveis_ids = !empty($lead['imoveis_ids']) ? explode(',', $lead['imoveis_ids']) : [];
            $total_imoveis = count($imoveis_titulos);

            $card_class = 'lead-card mb-3 p-3 bg-white rounded-3 shadow-sm border';
            if ($is_deleted) {
                $card_class .= ' row-deleted';
            } else {
                if ($is_favorito) $card_class .= ' row-favorito';
                if ($is_contatar_hoje) $card_class .= ' row-contatar-hoje';
                if ($is_shared) $card_class .= ' row-shared';
            }
        ?>
        <div id="row-lead-<?= $lead['id'] ?>" class="<?= $card_class ?>" style="border-left: 4px solid <?= $is_favorito ? '#0d6efd' : ($is_contatar_hoje ? '#28a745' : '#dee2e6') ?>;">
            <!-- Cabeçalho do card -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold nome-completo-cell" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" style="cursor:pointer; font-size:1.1rem;">
                        <i class="bi bi-person-circle text-primary me-1"></i><?= htmlspecialchars($lead['nome']) ?>
                    </span>
                    <span class="fase-cell badge <?= $fase_badge_class ?> px-3 py-2" data-id="<?= $lead['id'] ?>" data-fase="<?= htmlspecialchars($fase) ?>" style="cursor:pointer; font-size:0.75rem;">
                        <?= htmlspecialchars($fase) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi <?= $is_favorito ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> favorito-star" data-id="<?= $lead['id'] ?>" style="font-size:1.6rem; cursor:pointer;"></i>
                    <span class="badge <?= $badge_dias_class ?> px-3 py-2" id="dias-parado-<?= $lead['id'] ?>">
                        <?= $dias_parado ?>d
                    </span>
                    <?php if (!$is_deleted): ?>
                        <i class="bi bi-check-circle-fill reset-dias-icon" data-id="<?= $lead['id'] ?>" title="Zerar dias" style="font-size:1.4rem; color:#28a745; cursor:pointer;"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Corpo do card -->
            <div class="row g-2 small">
                <div class="col-6">
                    <span class="text-muted">Criado:</span> <?= $created_at_formatado ?>
                </div>
                <div class="col-6">
                    <span class="text-muted">Valor:</span>
                    <span class="valor-max-cell" data-id="<?= $lead['id'] ?>" data-valor="<?= $valor_max_raw ?>" style="cursor:pointer; font-weight:600;">
                        <?= $valor_max_formatado ?>
                    </span>
                </div>
                <div class="col-6">
                    <span class="text-muted">Pagamento:</span>
                    <span class="tipo-pagamento-cell" data-id="<?= $lead['id'] ?>" data-tipo="<?= htmlspecialchars($tipo_pagamento) ?>" style="cursor:pointer;">
                        <?php if (!empty($tipo_pagamento)): ?>
                            <i class="bi bi-credit-card"></i> <?= htmlspecialchars($tipo_pagamento) ?>
                        <?php else: ?>
                            <i class="bi bi-plus-circle text-muted"></i> Adicionar
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($quartos_min)): ?>
                        <span class="ms-2"><i class="bi bi-door-open"></i> <?= htmlspecialchars($quartos_min) ?> qtos</span>
                    <?php endif; ?>
                </div>
                <div class="col-6">
                    <span class="text-muted">Primeiro nome:</span>
                    <span class="nome-cell" data-id="<?= $lead['id'] ?>" data-nome-completo="<?= htmlspecialchars($lead['nome']) ?>" data-primeiro-nome="<?= htmlspecialchars($lead['primeiro_nome'] ?? '') ?>" style="cursor:pointer;">
                        <i class="bi bi-person-bounding-box text-info me-1"></i><?= htmlspecialchars($lead['primeiro_nome'] ?: '—') ?>
                    </span>
                </div>
            </div>

            <!-- Obs Gerais -->
            <div class="mt-2 observacoes-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($observacoes) ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" style="cursor:pointer; background:#f8f9fa; padding:6px 10px; border-radius:8px;">
                <span class="text-muted fw-bold text-uppercase" style="font-size:0.65rem;">Obs Gerais</span>
                <?php if (!empty($observacoes)): ?>
                    <div><i class="bi bi-chat-text text-secondary me-1"></i><?= nl2br(htmlspecialchars($observacoes)) ?></div>
                <?php else: ?>
                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                <?php endif; ?>
            </div>

            <!-- Obs Parceiros -->
            <div class="mt-1 obs-cell" data-id="<?= $lead['id'] ?>" data-obs="<?= htmlspecialchars($lead['obs_parceiros'] ?? '') ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" style="cursor:pointer; background:#fff9e6; padding:6px 10px; border-radius:8px;">
                <span class="text-muted fw-bold text-uppercase" style="font-size:0.65rem;">Obs Parceiros</span>
                <?php if (!empty($lead['obs_parceiros'])): ?>
                    <div><i class="bi bi-shield-shaded text-warning me-1"></i><?= nl2br(htmlspecialchars($lead['obs_parceiros'])) ?></div>
                <?php else: ?>
                    <span class="text-muted fst-italic"><i class="bi bi-pencil-square"></i> Adicionar...</span>
                <?php endif; ?>
            </div>

            <!-- Imóveis de interesse -->
            <?php if ($total_imoveis > 0): ?>
                <div class="mt-2">
                    <span class="text-muted fw-bold text-uppercase" style="font-size:0.65rem;">Imóveis de interesse</span>
                    <div class="imoveis-container d-flex flex-wrap gap-1 mt-1">
                        <?php foreach ($imoveis_titulos as $i => $titulo): 
                            $id_imovel = isset($imoveis_ids[$i]) ? $imoveis_ids[$i] : '';
                        ?>
                            <span class="badge-imovel-interesse" title="ID: <?= $id_imovel ?>">
                                <i class="bi bi-house-fill me-1"></i><?= htmlspecialchars($titulo) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ações e toggles -->
            <div class="mt-3 d-flex flex-wrap align-items-center gap-3">
                <!-- Compartilhar -->
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-share" type="checkbox" id="share-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_shared ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                    <label class="form-check-label small text-muted" for="share-<?= $lead['id'] ?>">Compartilhar</label>
                </div>
                <!-- Contatar Hoje -->
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-contatar-hoje" type="checkbox" id="switch-<?= $lead['id'] ?>" data-id="<?= $lead['id'] ?>" <?= $is_contatar_hoje ? 'checked' : '' ?> <?= $is_deleted ? 'disabled' : '' ?>>
                    <label class="form-check-label small text-muted" for="switch-<?= $lead['id'] ?>">Contatar hoje</label>
                </div>
                <!-- Botões de ação -->
                <?php if ($is_deleted): ?>
                    <button class="btn btn-sm btn-outline-success btn-restore" data-id="<?= $lead['id'] ?>"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-info btn-duplicate" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>" title="Duplicar"><i class="bi bi-files"></i> Dupe</button>
                    <button class="btn btn-sm btn-outline-danger btn-soft-delete" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>"><i class="bi bi-trash"></i></button>
                    <button class="btn btn-sm btn-perdido" data-id="<?= $lead['id'] ?>" data-nome="<?= htmlspecialchars($lead['nome']) ?>"><i class="bi bi-x-octagon"></i> Perdido</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
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
        <i class="bi bi-house-heart"></i> Os imóveis de interesse aparecem abaixo do nome.
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

<!-- Modal Fase -->
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
    var isMobile = window.innerWidth < 768;
    var table = $('#tabelaLeads').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" },
        "order": [[0, 'asc']],
        "columnDefs": [{ "type": "num", "targets": 0 }],
        "pageLength": 100,
        "responsive": false,
        "scrollX": !isMobile
    });

    function formatMoney(value) {
        let num = parseFloat(value);
        if (isNaN(num)) num = 0;
        return 'R$ ' + num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

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

    // Resetar dias parados
    $(document).on('click', '.reset-dias-icon', function() {
        let icon = $(this);
        let id = icon.data('id');
        let row = icon.closest('tr') || icon.closest('.lead-card');
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

    // Editar valor máximo
    $(document).on('click', '.valor-max-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
        $('#valorMaxLeadId').val($(this).data('id'));
        $('#valorMaxLeadNome').val($(this).closest('tr')?.find('.nome-completo-cell')?.data('nome') || $(this).closest('.lead-card')?.find('.nome-completo-cell')?.data('nome') || 'Lead');
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

    // Favorito
    $(document).on('click', '.favorito-star', function() {
        let icon = $(this);
        let id = icon.data('id');
        let newState = icon.hasClass('bi-star-fill') ? 0 : 1;
        let row = icon.closest('tr') || icon.closest('.lead-card');
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

    // Contatar Hoje
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

    // Nome completo
    $(document).on('click', '.nome-completo-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
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

    // Primeiro nome
    $(document).on('click', '.nome-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
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

    // Obs Parceiros
    $(document).on('click', '.obs-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
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

    // Observações Gerais
    $(document).on('click', '.observacoes-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
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

    // Toggle Compartilhar
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

    // Soft Delete e Restore
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

    // Marcar como Perdido
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
                    if ($.fn.DataTable.isDataTable('#tabelaLeads')) {
                        table.row(row).remove().draw();
                    } else {
                        row.remove();
                    }
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

    // Duplicar lead
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

    // Editar tipo de pagamento
    $(document).on('click', '.tipo-pagamento-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
        let id = $(this).data('id');
        let tipo = $(this).data('tipo');
        let nome = $(this).closest('tr')?.find('.nome-completo-cell')?.data('nome') || $(this).closest('.lead-card')?.find('.nome-completo-cell')?.data('nome') || 'Lead';
        
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

    // Editar fase
    $(document).on('click', '.fase-cell', function() {
        if ($(this).closest('tr')?.hasClass('row-deleted') || $(this).closest('.lead-card')?.hasClass('row-deleted')) {
            alert('Lead excluído.');
            return;
        }
        let id = $(this).data('id');
        let fase = $(this).data('fase');
        let nome = $(this).closest('tr')?.find('.nome-completo-cell')?.data('nome') || $(this).closest('.lead-card')?.find('.nome-completo-cell')?.data('nome') || 'Lead';
        
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