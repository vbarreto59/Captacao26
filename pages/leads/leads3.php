<?php
// leads3.php - Gestão de Leads com filtros avançados (incluindo varanda e obs_parceiros)
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// LÓGICA AJAX (PROCESSAMENTO)
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if ($_POST['action'] == 'update_temp') {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['temp'], $id]) ? 'success' : 'error']);
    } 
    elseif ($_POST['action'] == 'update_step') {
        $stmt = $conn->prepare("UPDATE leads SET proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['step'], $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'toggle_hoje') {
        $val = (int)$_POST['value'];
        $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = ? WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$val, $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'update_obs') {
        $obs = $_POST['observacoes'];
        $stmt = $conn->prepare("UPDATE leads SET observacoes = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$obs, $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'update_obs_parceiros') {
        $obs = $_POST['obs_parceiros'];
        $stmt = $conn->prepare("UPDATE leads SET obs_parceiros = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$obs, $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'toggle_share') {
        $val = (int)$_POST['value'];
        $stmt = $conn->prepare("UPDATE leads SET compartilhado_parceiro = ? WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$val, $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'add_agenda') {
        $lead_nome = $_POST['lead_nome'];
        $obs_extra = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
        $descricao_final = $lead_nome;
        if (!empty($obs_extra)) {
            $descricao_final .= " - " . $obs_extra;
        }
        $stmt = $conn->prepare("INSERT INTO agenda_geral (titulo, descricao, data_evento, categoria, status) VALUES (?, ?, ?, 'Lead', 'Pendente')");
        $ok = $stmt->execute(["Visita", $descricao_final, $_POST['data_evento']]);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }
    // Ação para zerar dias parados (atualizar última interação)
    elseif ($_POST['action'] == 'update_last_interaction') {
        $stmt = $conn->prepare("UPDATE leads SET ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$id]) ? 'success' : 'error']);
        exit;
    }
    // NOVA AÇÃO: Marcar lead como Perdido
    elseif ($_POST['action'] == 'marcar_perdido') {
        $stmt = $conn->prepare("UPDATE leads SET fase_funil = 'Perdido', contatar_hoje = 0, compartilhado_parceiro = 0 WHERE id = ?");
        $result = $stmt->execute([$id]);
        echo json_encode(['status' => $result ? 'success' : 'error']);
        exit;
    }
    exit; 
}

// ==========================================
// CONFIGURAÇÕES E FILTROS
// ==========================================
$opcoes_passos = ["Ligar para qualificar", "Agendar visita", "Enviar simulação", "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"];
$fases_lista = ['Novo', 'Tentativa de Contato', 'Contato Feito', 'Visita Agendada', 'Visita Realizada', 'Analisando', 'Proposta', 'Fechado', 'Perdido'];
$temps_lista = ['Quente' => '🔥 Quente', 'Morno' => '⚖️ Morno', 'Frio' => '❄️ Frio'];

$temp_ativa = $_GET['temperatura'] ?? '';
$fase_ativa = $_GET['fase'] ?? '';
$busca = $_GET['busca'] ?? '';

// Filtros avançados
$filtro_tipo_desejo = $_GET['tipo_desejo'] ?? '';
$filtro_tipologia = $_GET['tipologia'] ?? '';
$filtro_quartos_valor = isset($_GET['quartos_valor']) && is_numeric($_GET['quartos_valor']) ? (int)$_GET['quartos_valor'] : null;
$filtro_quartos_operador = $_GET['quartos_operador'] ?? 'minimo';
$filtro_valor_max_min = isset($_GET['valor_max_min']) && is_numeric(str_replace(['.', ','], ['', ''], $_GET['valor_max_min'])) ? (float)str_replace(['.', ','], ['', ''], $_GET['valor_max_min']) : null;
$filtro_valor_max_max = isset($_GET['valor_max_max']) && is_numeric(str_replace(['.', ','], ['', ''], $_GET['valor_max_max'])) ? (float)str_replace(['.', ','], ['', ''], $_GET['valor_max_max']) : null;
$filtro_vista_mar = $_GET['vista_mar'] ?? '';
$filtro_pe_na_areia = isset($_GET['pe_na_areia']) ? 1 : null;
$filtro_piscina = isset($_GET['piscina']) ? 1 : null;
$filtro_garagem = isset($_GET['garagem_coberta']) ? 1 : null;
$filtro_mobiliado = isset($_GET['mobiliado']) ? 1 : null;
$filtro_varanda = isset($_GET['varanda']) ? 1 : null;

$where = "WHERE 1=1  AND deleted_at IS NULL ";
$params = [];

// Filtros básicos
if ($fase_ativa) {
    $where .= " AND fase_funil = ?";
    $params[] = $fase_ativa;
}

if ($temp_ativa) { $where .= " AND temperatura = ?"; $params[] = $temp_ativa; }
if ($busca) { $where .= " AND (nome LIKE ? OR telefone LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

// Filtros avançados
if ($filtro_tipo_desejo) { $where .= " AND tipo_desejo = ?"; $params[] = $filtro_tipo_desejo; }
if ($filtro_tipologia) { $where .= " AND tipologia = ?"; $params[] = $filtro_tipologia; }

if ($filtro_quartos_valor !== null && $filtro_quartos_valor > 0) {
    if ($filtro_quartos_operador === 'exato') {
        $where .= " AND quartos_min = ?";
    } else {
        $where .= " AND quartos_min >= ?";
    }
    $params[] = $filtro_quartos_valor;
}

if ($filtro_valor_max_min !== null) { $where .= " AND valor_max >= ?"; $params[] = $filtro_valor_max_min; }
if ($filtro_valor_max_max !== null) { $where .= " AND valor_max <= ?"; $params[] = $filtro_valor_max_max; }
if ($filtro_vista_mar) { $where .= " AND vista_mar = ?"; $params[] = $filtro_vista_mar; }
if ($filtro_pe_na_areia !== null) { $where .= " AND pe_na_areia = ?"; $params[] = $filtro_pe_na_areia; }
if ($filtro_piscina !== null) { $where .= " AND piscina = ?"; $params[] = $filtro_piscina; }
if ($filtro_garagem !== null) { $where .= " AND garagem_coberta = ?"; $params[] = $filtro_garagem; }
if ($filtro_mobiliado !== null) { $where .= " AND mobiliado = ?"; $params[] = $filtro_mobiliado; }
if ($filtro_varanda !== null) { $where .= " AND varanda = ?"; $params[] = $filtro_varanda; }

function getFaseColor($fase) {
    $cores = ['Novo'=>'bg-info text-dark','Tentativa de Contato'=>'bg-warning text-dark','Contato Feito'=>'bg-primary text-white','Visita Agendada'=>'bg-success text-white','Visita Realizada'=>'bg-dark text-white','Analisando'=>'bg-secondary text-white','Proposta'=>'bg-danger text-white','Fechado'=>'bg-success text-white','Perdido'=>'bg-light text-muted'];
    return $cores[$fase] ?? 'bg-light text-dark';
}

function formatarCaracteristicas($lead) {
    $parts = [];
    if (!empty($lead['tipo_desejo'])) $parts[] = $lead['tipo_desejo'];
    if (!empty($lead['tipologia'])) $parts[] = $lead['tipologia'];
    if ($lead['valor_max'] > 0) $parts[] = 'R$ ' . number_format($lead['valor_max'], 0, ',', '.');
    if ($lead['quartos_min'] > 0) $parts[] = $lead['quartos_min'] . ' qts';
    if (!empty($lead['vista_mar']) && $lead['vista_mar'] != 'Nenhuma') $parts[] = '🌊 ' . $lead['vista_mar'];
    if ($lead['pe_na_areia']) $parts[] = '🏖️ Pé na areia';
    if ($lead['piscina']) $parts[] = '🏊 Piscina';
    if ($lead['garagem_coberta']) $parts[] = '🚗 Garagem';
    if ($lead['mobiliado']) $parts[] = '🛋️ Mobiliado';
    if ($lead['varanda']) $parts[] = '🏠 Varanda/Sacada';
    return implode(' • ', $parts);
}

/**
 * Retorna a classe CSS do badge de acordo com os dias parados
 */
function getDiasBadgeClass($dias) {
    if ($dias == 0) return 'bg-success';
    if ($dias <= 3) return 'bg-warning text-dark';
    return 'bg-danger';
}

/**
 * Função auxiliar para construir URLs de filtro, preservando todos os parâmetros GET
 * exceto o que se deseja alterar.
 */
function buildFilterUrl($keyToChange, $newValue) {
    $params = $_GET;
    if ($newValue === '') {
        unset($params[$keyToChange]);
    } else {
        $params[$keyToChange] = $newValue;
    }
    return '?' . http_build_query($params);
}

// ==========================================
// CONSULTA PRINCIPAL
// ==========================================
$sql = "SELECT l.*, 
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT GROUP_CONCAT(res ORDER BY data_registro DESC SEPARATOR '||') FROM (
            SELECT CONCAT(DATE_FORMAT(data_registro, '%d/%m'), ' - ', detalhes) as res, lead_id, data_registro 
            FROM lead_historico 
        ) as sub_hist WHERE sub_hist.lead_id = l.id) as resumo_historico,
        (SELECT GROUP_CONCAT(v_res ORDER BY dv DESC SEPARATOR '||') FROM (
            SELECT 
                CONCAT(
                    DATE_FORMAT(v.data_visita, '%d/%m %H:%i'),
                    ' - ',
                    COALESCE(i.titulo, 'Sem imóvel')
                ) as v_res,
                v.lead_id,
                v.data_visita as dv
            FROM visitas v
            LEFT JOIN imoveis i ON v.imovel_id = i.id
        ) as sub_v WHERE sub_v.lead_id = l.id) as ultimas_visitas_reais
        FROM leads l $where ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestão de Leads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, sans-serif; }
        .scroll-x { overflow-x: auto; display: flex; gap: 8px; padding-bottom: 10px; -webkit-overflow-scrolling: touch; }
        .name-row-container { padding: 12px; border-radius: 8px 8px 0 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .obs-preview { background: rgba(255,255,255,0.6); padding: 8px 12px; font-size: 0.88rem; color: #444; border-radius: 0 0 8px 8px; border: 1px solid rgba(0,0,0,0.03); cursor: pointer; line-height: 1.4; }
        .obs-parceiros-preview { background: #fef7e0; padding: 6px 10px; font-size: 0.8rem; color: #856404; border-radius: 6px; margin-top: 6px; cursor: pointer; border: 1px solid #ffeeba; }
        .hist-container { font-size: 0.75rem; background: #fff; border-radius: 6px; padding: 8px; border: 1px solid #eee; margin-top: 8px; }
        .hist-item { border-bottom: 1px solid #f1f1f1; padding: 3px 0; color: #666; }
        .btn-obs, .btn-obs-parceiros { cursor: pointer; color: #0d6efd; transition: 0.2s; }
        .temp-badge { cursor: pointer; font-size: 1.2rem; filter: grayscale(1); opacity: 0.6; transition: 0.2s; }
        .temp-badge.active { filter: grayscale(0); transform: scale(1.2); opacity: 1; }
        .row-shared { background-color: #f0f7ff !important; border-left: 6px solid #0d6efd !important; }
        .caracteristicas-cell { max-width: 280px; min-width: 200px; font-size: 0.75rem; line-height: 1.4; color: #2c3e50; }
        .caracteristica-badge { background: #e9ecef; padding: 3px 8px; border-radius: 20px; display: inline-block; margin: 2px 4px 2px 0; font-size: 0.7rem; white-space: nowrap; }
        .card-caracteristicas { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #dee2e6; }
        .filter-temp-link { text-decoration: none; color: #666; padding: 5px 12px; border-radius: 20px; border: 1px solid #ddd; background: #fff; font-size: 0.85rem; transition: 0.2s; white-space: nowrap; }
        .filter-temp-link:hover { background: #eee; }
        .filter-temp-link.active { background: #333; color: #fff; border-color: #333; }
        .btn-limpar-filtros { text-decoration: none; color: #dc3545; padding: 5px 12px; border-radius: 20px; border: 1px solid #dc3545; background: #fff; font-size: 0.85rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .btn-limpar-filtros:hover { background: #dc3545; color: #fff; }
        tr.lead-quente, .lead-card.lead-quente { background-color: #f8d7da !important; border-left: 5px solid #dc3545 !important; }
        tr.lead-quente:hover, .lead-card.lead-quente:hover { background-color: #f1b0b7 !important; }
        tr.lead-morno, .lead-card.lead-morno { background-color: #fff3cd !important; border-left: 5px solid #ffc107 !important; }
        tr.lead-morno:hover, .lead-card.lead-morno:hover { background-color: #ffe69c !important; }
        tr.lead-frio, .lead-card.lead-frio { background-color: #d1ecf1 !important; border-left: 5px solid #17a2b8 !important; }
        tr.lead-frio:hover, .lead-card.lead-frio:hover { background-color: #a6d5e0 !important; }
        .lead-quente .temp-badge[data-temp="Quente"], .lead-morno .temp-badge[data-temp="Morno"], .lead-frio .temp-badge[data-temp="Frio"] { transform: scale(1.25); filter: none; opacity: 1; }
        .lead-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,0.06); transition: background-color 0.2s; }
        .lead-card-header { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .lead-card-body { padding: 12px 16px; }
        .lead-card-footer { padding: 10px 16px; background: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
        .lead-card-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f1f1; }
        .lead-card-row:last-child { border-bottom: none; }
        .lead-card-label { font-size: 0.7rem; color: #888; text-transform: uppercase; font-weight: 600; }
        .lead-card-value { font-size: 0.85rem; color: #333; }
        .btn-filtro-mobile { width: 100%; padding: 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .filtro-card { border-radius: 16px; margin-bottom: 16px; }
        .form-group-mobile { margin-bottom: 16px; }
        .form-group-mobile label { font-weight: 600; margin-bottom: 6px; display: block; font-size: 0.85rem; }
        .form-group-mobile select, .form-group-mobile input { padding: 10px 12px; font-size: 0.95rem; border-radius: 12px; }
        .checkbox-group-mobile { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .checkbox-group-mobile .form-check { flex: 1 1 auto; min-width: 100px; background: #fff; padding: 8px 12px; border-radius: 40px; border: 1px solid #ddd; text-align: center; }
        .btn-aplicar-filtros { width: 100%; padding: 12px; font-weight: bold; border-radius: 40px; }
        .reset-dias-icon { cursor: pointer; font-size: 1.1rem; display: inline-block; transition: transform 0.1s; margin-left: 8px; }
        .reset-dias-icon:hover { transform: scale(1.1); }
        /* Estilos para os controles de ação (toggles + botão perdido) */
        .acoes-lead {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .acoes-lead .form-check {
            margin: 0;
            padding-left: 2em;
        }
        .btn-perdido {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-perdido:hover {
            background-color: #bb2d3b;
            transform: scale(1.02);
        }
        @media (max-width: 991px) { .table-responsive-desktop { display: none !important; } .cards-mobile { display: block !important; } }
        @media (min-width: 992px) { .table-responsive-desktop { display: block !important; } .cards-mobile { display: none !important; } }
        @media (max-width: 767px) { .filtros-container { flex-direction: column !important; align-items: stretch !important; } .scroll-x { padding-bottom: 6px; } .scroll-x .btn { font-size: 0.75rem; padding: 4px 10px; } .btn-limpar-filtros { margin-top: 8px; text-align: center; justify-content: center; } }
        @media (max-width: 575px) { .lead-card-footer .btn-group { width: 100%; } .lead-card-footer .btn-group .btn { flex: 1; } .checkbox-group-mobile .form-check { flex: 1 1 calc(50% - 12px); min-width: auto; } .acoes-lead { justify-content: space-between; } }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
        <p class="text-muted small mb-0"><?= count($lista) ?> leads na lista atual</p>
    </div>
    <div class="d-flex flex-column align-items-end gap-2 w-100 w-md-auto">
        <form action="" method="GET" class="d-flex gap-2 w-100">
            <input type="text" name="busca" class="form-control" placeholder="Pesquisar..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-light border"><i class="bi bi-search"></i></button>
        </form>
        <a href="lead_form.php" class="btn btn-primary shadow-sm w-100 w-md-auto"><i class="bi bi-plus-lg"></i> Novo</a>
    </div>
</div>

    <!-- Filtros de Fase -->
    <div class="scroll-x mb-2">
        <?php foreach ($fases_lista as $f): ?>
            <a href="?fase=<?= urlencode($f) . ($temp_ativa ? "&temperatura=$temp_ativa" : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="btn btn-sm <?= getFaseColor($f) ?> <?= ($fase_ativa == $f) ? 'active shadow border-dark' : 'border' ?> px-3"><?= $f ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Filtros de Temperatura + Limpar -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center filtros-container">
        <small class="text-muted fw-bold text-uppercase">Temperatura:</small>
        <a href="?fase=<?= urlencode($fase_ativa) . ($busca ? "&busca=$busca" : "") ?>" class="filter-temp-link <?= $temp_ativa == '' ? 'active' : '' ?>">Todos</a>
        <?php foreach ($temps_lista as $key => $label): ?>
            <a href="?temperatura=<?= $key . ($fase_ativa ? "&fase=".urlencode($fase_ativa) : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="filter-temp-link <?= $temp_ativa == $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <?php if ($fase_ativa || $temp_ativa || $busca): ?>
            <a href="leads3.php" class="btn-limpar-filtros ms-auto"><i class="bi bi-x-circle"></i> Limpar Filtros</a>
        <?php endif; ?>
    </div>

    <!-- FILTRO RÁPIDO PARA tipo_desejo (Compra / Aluguel) -->
    <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
        <small class="text-muted fw-bold text-uppercase">Intenção:</small>
        <a href="<?= buildFilterUrl('tipo_desejo', '') ?>" class="filter-temp-link <?= ($filtro_tipo_desejo == '') ? 'active' : '' ?>">Todos</a>
        <a href="<?= buildFilterUrl('tipo_desejo', 'Compra') ?>" class="filter-temp-link <?= ($filtro_tipo_desejo == 'Compra') ? 'active' : '' ?>">💰 Compra</a>
        <a href="<?= buildFilterUrl('tipo_desejo', 'Aluguel') ?>" class="filter-temp-link <?= ($filtro_tipo_desejo == 'Aluguel') ? 'active' : '' ?>">📄 Aluguel</a>
    </div>

    <!-- Filtros Avançados -->
    <?php 
    $filtros_ativos = ($filtro_tipo_desejo || $filtro_tipologia || $filtro_quartos_valor || $filtro_valor_max_min || $filtro_valor_max_max || $filtro_vista_mar || $filtro_pe_na_areia || $filtro_piscina || $filtro_garagem || $filtro_mobiliado || $filtro_varanda);
    $total_filtros_atributos = 0;
    if ($filtro_tipo_desejo) $total_filtros_atributos++;
    if ($filtro_tipologia) $total_filtros_atributos++;
    if ($filtro_quartos_valor) $total_filtros_atributos++;
    if ($filtro_valor_max_min || $filtro_valor_max_max) $total_filtros_atributos++;
    if ($filtro_vista_mar) $total_filtros_atributos++;
    if ($filtro_pe_na_areia) $total_filtros_atributos++;
    if ($filtro_piscina) $total_filtros_atributos++;
    if ($filtro_garagem) $total_filtros_atributos++;
    if ($filtro_mobiliado) $total_filtros_atributos++;
    if ($filtro_varanda) $total_filtros_atributos++;
    ?>
    <div class="mb-3">
        <button class="btn btn-outline-primary btn-filtro-mobile shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados" aria-expanded="<?= $filtros_ativos ? 'true' : 'false' ?>">
            <span><i class="bi bi-funnel"></i> Filtros Avançados</span>
            <?php if ($total_filtros_atributos > 0): ?>
                <span class="badge bg-primary rounded-pill"><?= $total_filtros_atributos ?></span>
            <?php else: ?>
                <i class="bi bi-chevron-down"></i>
            <?php endif; ?>
        </button>
    </div>

    <div class="collapse <?= $filtros_ativos ? 'show' : '' ?>" id="filtrosAvancados">
        <div class="card filtro-card shadow-sm border-0 mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="" id="formFiltrosAvancados">
                    <input type="hidden" name="fase" value="<?= htmlspecialchars($fase_ativa) ?>">
                    <input type="hidden" name="temperatura" value="<?= htmlspecialchars($temp_ativa) ?>">
                    <input type="hidden" name="busca" value="<?= htmlspecialchars($busca) ?>">
                    
                    <div class="row g-3">
                        <div class="col-12 col-md-3 form-group-mobile">
                            <label>Intenção</label>
                            <select name="tipo_desejo" class="form-select">
                                <option value="">Todos</option>
                                <option value="Compra" <?= $filtro_tipo_desejo=='Compra'?'selected':'' ?>>Compra</option>
                                <option value="Aluguel" <?= $filtro_tipo_desejo=='Aluguel'?'selected':'' ?>>Aluguel</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 form-group-mobile">
                            <label>Tipologia</label>
                            <select name="tipologia" class="form-select">
                                <option value="">Qualquer</option>
                                <option value="Apartamento" <?= $filtro_tipologia=='Apartamento'?'selected':'' ?>>Apartamento</option>
                                <option value="Casa" <?= $filtro_tipologia=='Casa'?'selected':'' ?>>Casa</option>
                                <option value="Cobertura" <?= $filtro_tipologia=='Cobertura'?'selected':'' ?>>Cobertura</option>
                                <option value="Flat" <?= $filtro_tipologia=='Flat'?'selected':'' ?>>Flat</option>
                                <option value="Studio" <?= $filtro_tipologia=='Studio'?'selected':'' ?>>Studio</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 form-group-mobile">
                            <label>Quartos</label>
                            <div class="d-flex gap-2">
                                <input type="number" name="quartos_valor" class="form-control" min="0" value="<?= htmlspecialchars($filtro_quartos_valor) ?>" placeholder="Número">
                                <select name="quartos_operador" class="form-select" style="width: auto;">
                                    <option value="minimo" <?= $filtro_quartos_operador == 'minimo' ? 'selected' : '' ?>>mínimo</option>
                                    <option value="exato" <?= $filtro_quartos_operador == 'exato' ? 'selected' : '' ?>>exato</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-3 form-group-mobile">
                            <label>Vista Mar</label>
                            <select name="vista_mar" class="form-select">
                                <option value="">Qualquer</option>
                                <option value="Nenhuma" <?= $filtro_vista_mar=='Nenhuma'?'selected':'' ?>>Sem vista</option>
                                <option value="Lateral" <?= $filtro_vista_mar=='Lateral'?'selected':'' ?>>Lateral</option>
                                <option value="Definitiva / Frente" <?= $filtro_vista_mar=='Definitiva / Frente'?'selected':'' ?>>Total / Frente</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 form-group-mobile">
                            <label>Valor máximo (R$) - de</label>
                            <input type="text" name="valor_max_min" class="form-control money-mask" placeholder="Mínimo" value="<?= $filtro_valor_max_min ? number_format($filtro_valor_max_min, 2, ',', '.') : '' ?>">
                        </div>
                        <div class="col-12 col-md-4 form-group-mobile">
                            <label>até</label>
                            <input type="text" name="valor_max_max" class="form-control money-mask" placeholder="Máximo" value="<?= $filtro_valor_max_max ? number_format($filtro_valor_max_max, 2, ',', '.') : '' ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label>Características</label>
                            <div class="checkbox-group-mobile">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pe_na_areia" id="filtro_pe" <?= $filtro_pe_na_areia ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtro_pe">Pé na areia</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="piscina" id="filtro_piscina" <?= $filtro_piscina ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtro_piscina">Piscina</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="garagem_coberta" id="filtro_garagem" <?= $filtro_garagem ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtro_garagem">Garagem</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="mobiliado" id="filtro_mobiliado" <?= $filtro_mobiliado ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtro_mobiliado">Mobiliado</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="varanda" id="filtro_varanda" <?= $filtro_varanda ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtro_varanda">Varanda/Sacada</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex flex-column flex-md-row justify-content-end gap-2">
                        <a href="leads3.php" class="btn btn-outline-danger w-100 w-md-auto"><i class="bi bi-x-circle"></i> Limpar todos os filtros</a>
                        <button type="submit" class="btn btn-primary btn-aplicar-filtros w-100 w-md-auto"><i class="bi bi-search"></i> Aplicar filtros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- VERSÃO DESKTOP (TABELA)                      -->
    <!-- ============================================ -->
    <div class="table-responsive-desktop">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light text-uppercase small fw-bold">
                            <tr>
                                <th class="ps-3">ID</th>
                                <!-- NOVO: Campo created_at na listagem (Desktop) -->
                                <th>Criado em</th>
                                <th>Fase / Inatividade</th>
                                <th>Lead / Observações / Histórico / Obs Parceiros</th>
                                <th>Perfil do Cliente</th>
                                <th class="text-center">Temperatura</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($lista as $l): 
        $temp = $l['temperatura'] ?: 'Morno';
        $is_shared = (int)($l['compartilhado_parceiro'] ?? 0);
        $contatar_hoje = (int)($l['contatar_hoje'] ?? 0);
        $v_max = ($l['valor_max'] > 0) ? 'R$ ' . number_format($l['valor_max'], 0, ',', '.') : 'N/I';
        $classe_temperatura = match($temp) {
            'Quente' => 'lead-quente',
            'Morno'  => 'lead-morno',
            'Frio'   => 'lead-frio',
            default  => 'lead-morno'
        };
        $caracteristicas_resumo = formatarCaracteristicas($l);
        $dias = $l['dias_parado'];
        $badgeDiasClass = getDiasBadgeClass($dias);
        // Formata a data de criação (created_at)
        $created_at_formatted = !empty($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : '—';
    ?>
    <tr id="row-lead-<?= $l['id'] ?>" class="<?= $is_shared ? 'row-shared' : '' ?> <?= $classe_temperatura ?>">
        <td class="ps-3 small text-muted">#<?= $l['id'] ?> </td>
        <!-- NOVO: Exibição do created_at -->
        <td class="small text-muted"><?= $created_at_formatted ?></td>
        <td style="width: 150px;">
            <span class="badge <?= getFaseColor($l['fase_funil']) ?> w-100 py-2 mb-1"><?= $l['fase_funil'] ?: 'Novo' ?></span>
            <!-- Badge colorido de dias parados -->
            <div class="text-center mt-1">
                <span class="badge <?= $badgeDiasClass ?> px-3 py-2" id="dias-parado-<?= $l['id'] ?>">
                    <?= $dias ?> dias parado
                </span>
            </div>
            <!-- Ícone de check para zerar dias parados -->
            <div class="text-center mt-1">
                <i class="bi bi-check-circle-fill text-success reset-dias-icon reset-dias"
                   data-id="<?= $l['id'] ?>"
                   title="Registrar ação (zerar dias parados)"></i>
            </div>
        </td>
        <td>
            <div class="name-row-container d-flex align-items-center gap-2">
                <i class="bi <?= $l['favorito'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> btn-favorito fs-5" data-id="<?= $l['id'] ?>" style="cursor: pointer;"></i>
                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($l['nome']) ?></span>
                <?php if (!empty($l['tipo_desejo'])): ?>
                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($l['tipo_desejo']) ?></span>
                <?php endif; ?>
                <i class="bi bi-journal-text btn-obs fs-4" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>"></i>
                <span class="ms-auto badge bg-white text-dark border shadow-sm py-2 px-3 small">Teto: <strong><?= $v_max ?></strong></span>
            </div>
            <div class="obs-preview btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>" id="obs-preview-<?= $l['id'] ?>">
                <?= !empty($l['observacoes']) ? nl2br(htmlspecialchars($l['observacoes'])) : '<span class="text-muted italic">Clique aqui para adicionar observações...</span>' ?>
            </div>
            <div class="obs-parceiros-preview btn-obs-parceiros" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['obs_parceiros'] ?? '') ?>" id="obs-parceiros-preview-<?= $l['id'] ?>">
                <i class="bi bi-shield-shaded"></i> 
                <?= !empty($l['obs_parceiros']) ? nl2br(htmlspecialchars($l['obs_parceiros'])) : '<span class="text-muted italic">Clique para adicionar observações para parceiros...</span>' ?>
            </div>
            <div class="hist-container">
                <?php if (!empty($l['resumo_historico'])): 
                    $hists = explode('||', $l['resumo_historico']);
                    foreach (array_slice($hists, 0, 3) as $h): ?>
                        <div class="hist-item"><i class="bi bi-record-fill text-primary me-1" style="font-size: 0.5rem;"></i> <?= htmlspecialchars($h) ?></div>
                <?php endforeach; else: ?>
                    <div class="text-muted small">Sem interações recentes.</div>
                <?php endif; ?>
            </div>
            <!-- Os toggles "Contatar hoje" e "Compartilhar" foram removidos daqui e movidos para a coluna de ações -->
        </td>
          <td class="caracteristicas-cell">
            <?php if (!empty($caracteristicas_resumo)): ?>
                <div class="d-flex flex-wrap gap-1">
                    <?php 
                    $itens = explode(' • ', $caracteristicas_resumo);
                    foreach ($itens as $item): 
                        $icone = '';
                        if (strpos($item, 'Compra') !== false) $icone = '💰 ';
                        elseif (strpos($item, 'Aluguel') !== false) $icone = '📄 ';
                        elseif (strpos($item, 'R$') !== false) $icone = '💰 ';
                        elseif (strpos($item, 'qts') !== false) $icone = '🛏️ ';
                        elseif (strpos($item, 'Piscina') !== false) $icone = '🏊 ';
                        elseif (strpos($item, 'Garagem') !== false) $icone = '🚗 ';
                        elseif (strpos($item, 'Mobiliado') !== false) $icone = '🛋️ ';
                        elseif (strpos($item, 'Varanda') !== false) $icone = '🏠 ';
                        ?>
                        <span class="caracteristica-badge"><?= $icone . $item ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <span class="text-muted small">Nenhuma preferência registrada</span>
            <?php endif; ?>
            <?php if (!empty($l['preferencia_localizacao'])): ?>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars(mb_strimwidth($l['preferencia_localizacao'], 0, 40, '...')) ?>
                </div>
            <?php endif; ?>
            </td>
          <td class="text-center">
            <div class="d-flex justify-content-center gap-2 mb-2">
                <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
            </div>
            <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>">
                <option value="">Próximo Passo...</option>
                <?php foreach ($opcoes_passos as $op): ?>
                    <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                <?php endforeach; ?>
            </select>
            </td>
          <td class="text-end pe-3">
            <!-- Agrupamento dos controles: Contatar Hoje, Compartilhar, Perdido -->
            <div class="acoes-lead d-flex justify-content-end mb-2">
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-hoje" type="checkbox" data-id="<?= $l['id'] ?>" <?= $contatar_hoje ? 'checked' : '' ?> id="toggleHojeDesktop<?= $l['id'] ?>">
                    <label class="form-check-label small" for="toggleHojeDesktop<?= $l['id'] ?>">Contatar hoje</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-share" type="checkbox" data-id="<?= $l['id'] ?>" <?= $is_shared ? 'checked' : '' ?> id="toggleShareDesktop<?= $l['id'] ?>">
                    <label class="form-check-label small" for="toggleShareDesktop<?= $l['id'] ?>">Compartilhar</label>
                </div>
                <button type="button" class="btn-perdido btn-sm" data-id="<?= $l['id'] ?>">
                    <i class="bi bi-x-octagon"></i> Perdido
                </button>
            </div>
            <div class="btn-group shadow-sm w-100 mb-2">
                <button class="btn btn-sm btn-outline-warning btn-agendar" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>"><i class="bi bi-calendar-plus"></i></button>
                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-eye-fill"></i></a>
                <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-pencil-square"></i></a>
            </div>
            <?php if (!empty($l['ultimas_visitas_reais'])): ?>
                <div class="text-start p-2 bg-white border rounded shadow-sm" style="font-size: 0.7rem;">
                    <div class="fw-bold text-muted border-bottom mb-1">ÚLTIMAS VISITAS</div>
                    <?php 
                    $visitas = explode('||', $l['ultimas_visitas_reais']);
                    foreach ($visitas as $v): 
                        $corStatus = 'text-primary';
                        if (strpos($v, 'concluido') !== false) $corStatus = 'text-success';
                        if (strpos($v, 'cancelado') !== false) $corStatus = 'text-danger';
                    ?>
                        <div class="mb-1 py-1 border-bottom <?= $corStatus ?>">
                            <i class="bi bi-geo-alt-fill" style="font-size: 0.65rem;"></i> <?= htmlspecialchars($v) ?>
                        </div>
                    <?php endforeach; ?>
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
    </div>

    <!-- ============================================ -->
    <!-- VERSÃO MOBILE (CARDS)                        -->
    <!-- ============================================ -->
    <div class="cards-mobile" style="display: none;">
        <?php foreach ($lista as $l): 
            $temp = $l['temperatura'] ?: 'Morno';
            $is_shared = (int)($l['compartilhado_parceiro'] ?? 0);
            $contatar_hoje = (int)($l['contatar_hoje'] ?? 0);
            $v_max = ($l['valor_max'] > 0) ? 'R$ ' . number_format($l['valor_max'], 0, ',', '.') : 'N/I';
            $classe_temperatura = match($temp) {
                'Quente' => 'lead-quente',
                'Morno'  => 'lead-morno',
                'Frio'   => 'lead-frio',
                default  => 'lead-morno'
            };
            $caracteristicas_resumo = formatarCaracteristicas($l);
            $dias = $l['dias_parado'];
            $badgeDiasClass = getDiasBadgeClass($dias);
            $created_at_formatted = !empty($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : '—';
        ?>
        <div class="lead-card <?= $is_shared ? 'row-shared' : '' ?> <?= $classe_temperatura ?>" id="card-lead-<?= $l['id'] ?>">
            <div class="lead-card-header <?= $classe_temperatura ?>">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $l['favorito'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> btn-favorito fs-5" data-id="<?= $l['id'] ?>" style="cursor: pointer;"></i>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($l['nome']) ?></span>
                        <?php if (!empty($l['tipo_desejo'])): ?>
                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($l['tipo_desejo']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted">#<?= $l['id'] ?> · Teto: <strong><?= $v_max ?></strong></div>
                    <!-- NOVO: Exibição do created_at no card mobile -->
                    <div class="small text-muted mt-1"><i class="bi bi-calendar3"></i> Criado em: <?= $created_at_formatted ?></div>
                </div>
                <span class="badge <?= getFaseColor($l['fase_funil']) ?> py-2 px-3"><?= $l['fase_funil'] ?: 'Novo' ?></span>
            </div>
            <div class="lead-card-body">
                <div class="lead-card-row">
                    <span class="lead-card-label">Inatividade</span>
                    <span class="lead-card-value">
                        <span class="badge <?= $badgeDiasClass ?> px-3 py-2" id="card-dias-parado-<?= $l['id'] ?>">
                            <?= $dias ?> dias parado
                        </span>
                        <i class="bi bi-check-circle-fill text-success reset-dias-icon reset-dias"
                           data-id="<?= $l['id'] ?>"
                           title="Registrar ação (zerar dias parados)"></i>
                    </span>
                </div>
                <div class="lead-card-row">
                    <span class="lead-card-label">Temperatura</span>
                    <div class="d-flex gap-2">
                        <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                        <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                        <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
                    </div>
                </div>
                <div class="lead-card-row">
                    <span class="lead-card-label">Próximo Passo</span>
                    <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>" style="width: auto; min-width: 160px;">
                        <option value="">Selecionar...</option>
                        <?php foreach ($opcoes_passos as $op): ?>
                            <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Toggles e botão perdido movidos para o footer (serão exibidos lá) -->
                <?php if (!empty($caracteristicas_resumo)): ?>
                <div class="mt-2">
                    <div class="lead-card-label mb-1">Preferências</div>
                    <div class="card-caracteristicas">
                        <?php 
                        $itens = explode(' • ', $caracteristicas_resumo);
                        foreach ($itens as $item): ?>
                            <span class="caracteristica-badge"><?= $item ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($l['preferencia_localizacao'])): ?>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($l['preferencia_localizacao']) ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <div class="lead-card-label mb-1">Observações</div>
                    <div class="obs-preview btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>" id="obs-preview-card-<?= $l['id'] ?>">
                        <?= !empty($l['observacoes']) ? nl2br(htmlspecialchars($l['observacoes'])) : '<span class="text-muted italic">Clique para adicionar observações...</span>' ?>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="lead-card-label mb-1">Observações para Parceiros</div>
                    <div class="obs-parceiros-preview btn-obs-parceiros" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['obs_parceiros'] ?? '') ?>" id="obs-parceiros-preview-card-<?= $l['id'] ?>">
                        <i class="bi bi-shield-shaded"></i>
                        <?= !empty($l['obs_parceiros']) ? nl2br(htmlspecialchars($l['obs_parceiros'])) : '<span class="text-muted italic">Clique para adicionar observações para parceiros...</span>' ?>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="lead-card-label mb-1">Histórico</div>
                    <div class="hist-container" style="margin-top: 0;">
                        <?php if (!empty($l['resumo_historico'])): 
                            $hists = explode('||', $l['resumo_historico']);
                            foreach (array_slice($hists, 0, 3) as $h): ?>
                                <div class="hist-item"><i class="bi bi-record-fill text-primary me-1" style="font-size: 0.5rem;"></i> <?= htmlspecialchars($h) ?></div>
                        <?php endforeach; else: ?>
                            <div class="text-muted small">Sem interações recentes.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="lead-card-footer">
                <div class="acoes-lead w-100 mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-hoje" type="checkbox" data-id="<?= $l['id'] ?>" <?= $contatar_hoje ? 'checked' : '' ?> id="toggleHojeMobile<?= $l['id'] ?>">
                        <label class="form-check-label small" for="toggleHojeMobile<?= $l['id'] ?>">Contatar hoje</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-share" type="checkbox" data-id="<?= $l['id'] ?>" <?= $is_shared ? 'checked' : '' ?> id="toggleShareMobile<?= $l['id'] ?>">
                        <label class="form-check-label small" for="toggleShareMobile<?= $l['id'] ?>">Compartilhar</label>
                    </div>
                    <button type="button" class="btn-perdido btn-sm" data-id="<?= $l['id'] ?>">
                        <i class="bi bi-x-octagon"></i> Perdido
                    </button>
                </div>
                <div class="btn-group shadow-sm w-100">
                    <button class="btn btn-sm btn-outline-warning btn-agendar" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>"><i class="bi bi-calendar-plus"></i> <span class="d-none d-sm-inline">Agendar</span></button>
                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye-fill"></i> <span class="d-none d-sm-inline">Ver</span></a>
                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i> <span class="d-none d-sm-inline">Editar</span></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modais (existentes) -->
<div class="modal fade" id="modalObs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Obs: <span id="nomeLeadObs"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formObs">
                <input type="hidden" id="obs_lead_id" name="id">
                <div class="modal-body bg-light">
                    <textarea class="form-control border-0 shadow-sm" id="obs_texto" name="observacoes" rows="8" placeholder="O que o cliente busca? Qual o perfil?"></textarea>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Salvar Notas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalObsParceiros" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-shaded me-2"></i>Observações para Parceiros: <span id="nomeLeadObsParceiros"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formObsParceiros">
                <input type="hidden" id="obs_parceiros_lead_id" name="id">
                <div class="modal-body bg-light">
                    <textarea class="form-control border-0 shadow-sm" id="obs_parceiros_texto" name="obs_parceiros" rows="8" placeholder="Informações importantes para parceiros (ex: condições especiais, restrições, etc.)"></textarea>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">Salvar Notas para Parceiros</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i>Agendar Visita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAgenda">
                <input type="hidden" id="agenda_lead_id" name="lead_id">
                <input type="hidden" id="agenda_lead_nome" name="lead_nome">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lead</label>
                        <input type="text" class="form-control bg-white" id="agenda_lead_nome_display" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Data e Hora da Visita *</label>
                        <input type="datetime-local" class="form-control" id="agenda_data" name="data_evento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observações (opcional)</label>
                        <textarea class="form-control" id="agenda_descricao" name="descricao" rows="2" placeholder="Ex: levar proposta, endereço, etc."></textarea>
                        <small class="text-muted">Estas informações serão adicionadas após o nome do lead na descrição do evento.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-success w-100 fw-bold py-2">Salvar Agendamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Máscara de dinheiro para os campos de filtro
document.querySelectorAll('.money-mask').forEach(input => {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value === '') return;
        let number = (parseInt(value) / 100).toFixed(2);
        let formatted = number.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        e.target.value = formatted;
    });
});

$(document).on('change', '.toggle-hoje', function() {
    const id = $(this).data('id');
    const val = $(this).is(':checked') ? 1 : 0;
    $.post('leads3.php', { action: 'toggle_hoje', id: id, value: val }, function(res) {
        if(res.status !== 'success') alert('Erro ao salvar seleção.');
    }, 'json');
});    

$(document).ready(function() {
    // Alternar compartilhamento
    $(document).on('change', '.toggle-share', function() {
        const id = $(this).data('id');
        const val = $(this).is(':checked') ? 1 : 0;
        const row = $(`#row-lead-${id}`);
        const card = $(`#card-lead-${id}`);
        $.post('leads3.php', { action: 'toggle_share', id: id, value: val }, function(res) {
            if(res.status === 'success') {
                if (val === 1) { row.addClass('row-shared'); card.addClass('row-shared'); }
                else { row.removeClass('row-shared'); card.removeClass('row-shared'); }
            }
        }, 'json');
    });

    // Observações padrão
    $(document).on('click', '.btn-obs', function() {
        const id = $(this).data('id');
        $('#obs_lead_id').val(id);
        $('#nomeLeadObs').text($(this).data('nome'));
        $('#obs_texto').val($(this).data('obs'));
        $('#modalObs').modal('show');
    });

    $('#formObs').on('submit', function(e) {
        e.preventDefault();
        const id = $('#obs_lead_id').val();
        const obs = $('#obs_texto').val();
        $.post('leads3.php', { action: 'update_obs', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') {
                $(`.btn-obs[data-id="${id}"]`).data('obs', obs);
                $(`#obs-preview-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique aqui para adicionar observações...</span>');
                $(`#obs-preview-card-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique para adicionar observações...</span>');
                $('#modalObs').modal('hide');
            }
        }, 'json');
    });

    // Observações para parceiros
    $(document).on('click', '.btn-obs-parceiros', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const obsAtual = $(this).data('obs');
        $('#obs_parceiros_lead_id').val(id);
        $('#nomeLeadObsParceiros').text(nome);
        $('#obs_parceiros_texto').val(obsAtual);
        $('#modalObsParceiros').modal('show');
    });

    $('#formObsParceiros').on('submit', function(e) {
        e.preventDefault();
        const id = $('#obs_parceiros_lead_id').val();
        const obs = $('#obs_parceiros_texto').val();
        $.post('leads3.php', { action: 'update_obs_parceiros', id: id, obs_parceiros: obs }, function(res) {
            if (res.status === 'success') {
                $(`.btn-obs-parceiros[data-id="${id}"]`).data('obs', obs);
                $(`#obs-parceiros-preview-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique para adicionar observações para parceiros...</span>');
                $(`#obs-parceiros-preview-card-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique para adicionar observações para parceiros...</span>');
                $('#modalObsParceiros').modal('hide');
            } else {
                alert('Erro ao salvar observações para parceiros.');
            }
        }, 'json');
    });

    // Temperatura
    $(document).on('click', '.temp-badge', function() {
        $.post('leads3.php', { action: 'update_temp', id: $(this).data('id'), temp: $(this).data('temp') }, function() { location.reload(); });
    });

    // Próximo passo
    $(document).on('change', '.select-step', function() {
        $.post('leads3.php', { action: 'update_step', id: $(this).data('id'), step: $(this).val() });
    });

    // Agendar visita
    $(document).on('click', '.btn-agendar', function() {
        const leadId = $(this).data('id');
        const leadNome = $(this).data('nome');
        $('#agenda_lead_id').val(leadId);
        $('#agenda_lead_nome').val(leadNome);
        $('#agenda_lead_nome_display').val(leadNome);
        $('#agenda_data').val('');
        $('#agenda_descricao').val('');
        $('#modalAgenda').modal('show');
    });

    $('#formAgenda').on('submit', function(e) {
        e.preventDefault();
        const leadId = $('#agenda_lead_id').val();
        const leadNome = $('#agenda_lead_nome').val();
        const dataEvento = $('#agenda_data').val();
        const descricaoExtra = $('#agenda_descricao').val();
        if (!dataEvento) { alert('Defina a data e hora da visita.'); return; }
        $.post('leads3.php', {
            action: 'add_agenda',
            lead_id: leadId,
            lead_nome: leadNome,
            data_evento: dataEvento,
            descricao: descricaoExtra
        }, function(res) {
            if (res.status === 'success') {
                $('#modalAgenda').modal('hide');
                alert('Visita agendada com sucesso!');
                location.reload();
            } else { alert('Erro ao salvar. Verifique os dados.'); }
        }, 'json').fail(function() { alert('Erro de comunicação com o servidor.'); });
    });
    
    // Favorito
    $(document).on('click', '.btn-favorito', function(e) {
        e.preventDefault();
        const icone = $(this);
        const leadId = icone.data('id');
        $.ajax({
            url: 'atualizar_favorito.php',
            type: 'POST',
            data: { id: leadId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (response.novo_status == 1) {
                        icone.removeClass('bi-star text-muted').addClass('bi-star-fill text-warning');
                    } else {
                        icone.removeClass('bi-star-fill text-warning').addClass('bi-star text-muted');
                    }
                }
            }
        });
    });

    // Resetar dias parados (ação)
    $(document).on('click', '.reset-dias', function() {
        const id = $(this).data('id');
        const $rowBadge = $(`#dias-parado-${id}`);
        const $cardBadge = $(`#card-dias-parado-${id}`);
        const $btn = $(this);
        
        $.post('leads3.php', { action: 'update_last_interaction', id: id }, function(res) {
            if (res.status === 'success') {
                const zeroText = '0 dias parado';
                if ($rowBadge.length) {
                    $rowBadge.text(zeroText).removeClass('bg-warning bg-danger').addClass('bg-success');
                }
                if ($cardBadge.length) {
                    $cardBadge.text(zeroText).removeClass('bg-warning bg-danger').addClass('bg-success');
                }
                $btn.css('transform', 'scale(1.2)');
                setTimeout(() => $btn.css('transform', ''), 200);
            } else {
                alert('Erro ao registrar ação. Tente novamente.');
            }
        }, 'json').fail(() => alert('Erro de comunicação com o servidor.'));
    });

    // ==========================================
    // BOTÃO "PERDIDO" - Nova funcionalidade
    // ==========================================
    $(document).on('click', '.btn-perdido', function() {
        const id = $(this).data('id');
        if (confirm('Tem certeza que deseja marcar este lead como PERDIDO? Ele será removido da lista atual.')) {
            const row = $(`#row-lead-${id}`);
            const card = $(`#card-lead-${id}`);
            // Animação de fade-out
            if (row.length) row.addClass('removendo').css('transition', 'opacity 0.3s');
            if (card.length) card.addClass('removendo').css('transition', 'opacity 0.3s');
            
            $.post('leads3.php', { action: 'marcar_perdido', id: id }, function(res) {
                if (res.status === 'success') {
                    // Remove a linha e o card após a animação
                    setTimeout(() => {
                        if (row.length) row.remove();
                        if (card.length) card.remove();
                        // Atualiza a contagem de leads (opcional)
                        const totalText = $('.text-muted small:contains("leads na lista atual")');
                        if (totalText.length) {
                            let count = parseInt(totalText.text().match(/\d+/)[0]) - 1;
                            totalText.text(count + ' leads na lista atual');
                        }
                    }, 300);
                } else {
                    alert('Erro ao marcar lead como perdido. Tente novamente.');
                    if (row.length) row.removeClass('removendo');
                    if (card.length) card.removeClass('removendo');
                }
            }).fail(() => {
                alert('Erro de comunicação com o servidor.');
                if (row.length) row.removeClass('removendo');
                if (card.length) card.removeClass('removendo');
            });
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
</body>
</html>