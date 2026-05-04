<?php
// leads.php
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
    elseif ($_POST['action'] == 'update_obs') {
        $obs = $_POST['observacoes'];
        $stmt = $conn->prepare("UPDATE leads SET observacoes = ?, ultima_interacao = NOW() WHERE id = ?");
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

$where = "WHERE 1=1";
$params = [];

if (!$fase_ativa) { $where .= " AND (fase_funil != 'Perdido' OR fase_funil IS NULL)"; } 
else { $where .= " AND fase_funil = ?"; $params[] = $fase_ativa; }

if ($temp_ativa) { $where .= " AND temperatura = ?"; $params[] = $temp_ativa; }
if ($busca) { $where .= " AND (nome LIKE ? OR telefone LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

function getFaseColor($fase) {
    $cores = ['Novo'=>'bg-info text-dark','Tentativa de Contato'=>'bg-warning text-dark','Contato Feito'=>'bg-primary text-white','Visita Agendada'=>'bg-success text-white','Visita Realizada'=>'bg-dark text-white','Analisando'=>'bg-secondary text-white','Proposta'=>'bg-danger text-white','Fechado'=>'bg-success text-white','Perdido'=>'bg-light text-muted'];
    return $cores[$fase] ?? 'bg-light text-dark';
}

// ==========================================
// CONSULTA PRINCIPAL (com visitas e nome do imóvel, sem status)
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

<style>
    body { background-color: #f8f9fa; }
    .scroll-x { overflow-x: auto; display: flex; gap: 8px; padding-bottom: 10px; -webkit-overflow-scrolling: touch; }
    .name-row-container { padding: 12px; border-radius: 8px 8px 0 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .obs-preview { background: rgba(255,255,255,0.6); padding: 8px 12px; font-size: 0.88rem; color: #444; border-radius: 0 0 8px 8px; border: 1px solid rgba(0,0,0,0.03); cursor: pointer; line-height: 1.4; }
    .hist-container { font-size: 0.75rem; background: #fff; border-radius: 6px; padding: 8px; border: 1px solid #eee; margin-top: 8px; }
    .hist-item { border-bottom: 1px solid #f1f1f1; padding: 3px 0; color: #666; }
    .btn-obs { cursor: pointer; color: #0d6efd; transition: 0.2s; }
    .temp-badge { cursor: pointer; font-size: 1.2rem; filter: grayscale(1); opacity: 0.6; transition: 0.2s; }
    .temp-badge.active { filter: grayscale(0); transform: scale(1.2); opacity: 1; }
    .row-shared { background-color: #f0f7ff !important; border-left: 6px solid #0d6efd !important; }

    /* Filtros */
    .filter-temp-link { text-decoration: none; color: #666; padding: 5px 12px; border-radius: 20px; border: 1px solid #ddd; background: #fff; font-size: 0.85rem; transition: 0.2s; white-space: nowrap; }
    .filter-temp-link:hover { background: #eee; }
    .filter-temp-link.active { background: #333; color: #fff; border-color: #333; }

    .btn-limpar-filtros { 
        text-decoration: none; color: #dc3545; padding: 5px 12px; border-radius: 20px; border: 1px solid #dc3545; background: #fff; font-size: 0.85rem; transition: 0.2s; 
        display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-limpar-filtros:hover { background: #dc3545; color: #fff; }

    /* ==================== CORES POR TEMPERATURA (linha da tabela e cards) ==================== */
    tr.lead-quente,
    .lead-card.lead-quente {
        background-color: #f8d7da !important;  /* vermelho claro */
        border-left: 5px solid #dc3545 !important;
    }
    tr.lead-quente:hover,
    .lead-card.lead-quente:hover {
        background-color: #f1b0b7 !important;
    }

    tr.lead-morno,
    .lead-card.lead-morno {
        background-color: #fff3cd !important;  /* amarelo claro */
        border-left: 5px solid #ffc107 !important;
    }
    tr.lead-morno:hover,
    .lead-card.lead-morno:hover {
        background-color: #ffe69c !important;
    }

    tr.lead-frio,
    .lead-card.lead-frio {
        background-color: #d1ecf1 !important;  /* azul claro */
        border-left: 5px solid #17a2b8 !important;
    }
    tr.lead-frio:hover,
    .lead-card.lead-frio:hover {
        background-color: #a6d5e0 !important;
    }

    /* Destaque do ícone de temperatura ativo */
    .lead-quente .temp-badge[data-temp="Quente"],
    .lead-morno .temp-badge[data-temp="Morno"],
    .lead-frio .temp-badge[data-temp="Frio"] {
        transform: scale(1.25);
        filter: none;
        opacity: 1;
    }

    /* Cards mobile */
    .lead-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 16px;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.06);
        transition: background-color 0.2s;
    }
    .lead-card-header { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .lead-card-body { padding: 12px 16px; }
    .lead-card-footer { padding: 10px 16px; background: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .lead-card-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f1f1; }
    .lead-card-row:last-child { border-bottom: none; }
    .lead-card-label { font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600; }
    .lead-card-value { font-size: 0.9rem; color: #333; }

    /* Responsividade */
    @media (max-width: 991px) {
        .table-responsive-desktop { display: none !important; }
        .cards-mobile { display: block !important; }
    }
    @media (min-width: 992px) {
        .table-responsive-desktop { display: block !important; }
        .cards-mobile { display: none !important; }
    }
    @media (max-width: 767px) {
        .filtros-container { flex-direction: column !important; align-items: stretch !important; }
        .scroll-x { padding-bottom: 6px; }
        .scroll-x .btn { font-size: 0.75rem; padding: 4px 10px; }
    }
    @media (max-width: 575px) {
        .lead-card-footer .btn-group { width: 100%; }
        .lead-card-footer .btn-group .btn { flex: 1; }
    }
</style>

<div class="container-fluid px-3 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
            <p class="text-muted small mb-0"><?= count($lista) ?> leads na lista atual</p>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto">
            <form action="" method="GET" class="d-flex gap-2 flex-grow-1 flex-md-grow-0">
                <input type="text" name="busca" class="form-control" placeholder="Pesquisar..." value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-light border"><i class="bi bi-search"></i></button>
            </form>
            <a href="lead_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i>Novo</a>
        </div>
    </div>

    <!-- Filtros de Fase -->
    <div class="scroll-x mb-2">
        <?php foreach ($fases_lista as $f): ?>
            <a href="?fase=<?= urlencode($f) . ($temp_ativa ? "&temperatura=$temp_ativa" : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="btn btn-sm <?= getFaseColor($f) ?> <?= ($fase_ativa == $f) ? 'active shadow border-dark' : 'border' ?> px-3" style="white-space: nowrap;"><?= $f ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Filtros de Temperatura + Limpar -->
    <div class="d-flex flex-wrap gap-2 mb-4 align-items-center filtros-container">
        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Temperatura:</small>
        <a href="?fase=<?= urlencode($fase_ativa) . ($busca ? "&busca=$busca" : "") ?>" class="filter-temp-link <?= $temp_ativa == '' ? 'active' : '' ?>">Todos</a>
        <?php foreach ($temps_lista as $key => $label): ?>
            <a href="?temperatura=<?= $key . ($fase_ativa ? "&fase=".urlencode($fase_ativa) : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="filter-temp-link <?= $temp_ativa == $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <?php if ($fase_ativa || $temp_ativa || $busca): ?>
            <a href="leads.php" class="btn-limpar-filtros ms-auto"><i class="bi bi-x-circle"></i> Limpar Filtros</a>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- VERSÃO DESKTOP (TABELA)                      -->
    <!-- ============================================ -->
    <div class="table-responsive-desktop">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaLeads" class="table align-middle mb-0">
                        <thead class="table-light text-uppercase small fw-bold">
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Fase / Inatividade</th>
                                <th>Lead / Observações / Histórico</th>
                                <th class="text-center">Temperatura</th>
                                <th class="text-center">Compartilhar</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($lista as $l): 
        $temp = $l['temperatura'] ?: 'Morno';
        $is_shared = (int)($l['compartilhado_parceiro'] ?? 0);
        $v_max = ($l['valor_max'] > 0) ? 'R$ ' . number_format($l['valor_max'], 0, ',', '.') : 'N/I';
        // Define a classe CSS para a linha de acordo com a temperatura
        $classe_temperatura = match($temp) {
            'Quente' => 'lead-quente',
            'Morno'  => 'lead-morno',
            'Frio'   => 'lead-frio',
            default  => 'lead-morno'
        };
    ?>
    <tr id="row-lead-<?= $l['id'] ?>" class="<?= $is_shared ? 'row-shared' : '' ?> <?= $classe_temperatura ?>">
        <td class="ps-3 small text-muted btn">#<?= $l['id'] ?></td>
        <td style="width: 150px;" class="<?= $classe_temperatura ?>">
            <span class="badge <?= getFaseColor($l['fase_funil']) ?> w-100 py-2 mb-1"><?= $l['fase_funil'] ?: 'Novo' ?></span>
            <div class="text-center small text-muted fw-bold"><?= $l['dias_parado'] ?> dias parado</div>
        </td>
        <td>
<div class="name-row-container d-flex align-items-center gap-2">
    <!-- Estrela de Favorito -->
    <i class="bi <?= $l['favorito'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?> btn-favorito fs-5" 
       data-id="<?= $l['id'] ?>" 
       style="cursor: pointer;"></i>

    <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($l['nome']) ?></span>
    
    <i class="bi bi-journal-text btn-obs fs-4" 
       data-id="<?= $l['id'] ?>" 
       data-nome="<?= htmlspecialchars($l['nome']) ?>" 
       data-obs="<?= htmlspecialchars($l['observacoes']) ?>"></i>
    
    <span class="ms-auto badge bg-white text-dark border shadow-sm py-2 px-3 small">
        Teto: <strong><?= $v_max ?></strong>
    </span>
</div>
            <div class="obs-preview btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>" id="obs-preview-<?= $l['id'] ?>">
                <?= !empty($l['observacoes']) ? nl2br(htmlspecialchars($l['observacoes'])) : '<span class="text-muted italic">Clique aqui para adicionar observações...</span>' ?>
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
        <td class="text-center">
            <div class="form-check form-switch d-inline-block">
                <input class="form-check-input toggle-share" type="checkbox" role="switch" style="cursor: pointer;"
                       data-id="<?= $l['id'] ?>" <?= $is_shared ? 'checked' : '' ?>>
            </div>
        </td>
        <td class="text-end pe-3">
            <div class="btn-group shadow-sm w-100 mb-2">
                <button class="btn btn-sm btn-outline-warning btn-agendar" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" title="Agendar Visita"><i class="bi bi-calendar-plus"></i></button>
                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar"><i class="bi bi-eye-fill"></i></a>
                <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil-square"></i></a>
            </div>
            <?php if (!empty($l['ultimas_visitas_reais'])): ?>
                <div class="text-start p-2 bg-white border rounded shadow-sm" style="font-size: 0.7rem;">
                    <div class="fw-bold text-muted border-bottom mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">ÚLTIMAS VISITAS</div>
                    <?php 
                    $visitas = explode('||', $l['ultimas_visitas_reais']);
                    foreach ($visitas as $v): 
                        $corStatus = 'text-primary';
                        if (strpos($v, 'concluido') !== false) $corStatus = 'text-success';
                        if (strpos($v, 'cancelado') !== false) $corStatus = 'text-danger';
                    ?>
                        <div class="mb-1 py-1 border-bottom last-visita <?= $corStatus ?>" style="line-height: 1.2;">
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
            $v_max = ($l['valor_max'] > 0) ? 'R$ ' . number_format($l['valor_max'], 0, ',', '.') : 'N/I';
            // Mesma classe de temperatura para os cards
            $classe_temperatura = match($temp) {
                'Quente' => 'lead-quente',
                'Morno'  => 'lead-morno',
                'Frio'   => 'lead-frio',
                default  => 'lead-morno'
            };
        ?>
        <div class="lead-card <?= $is_shared ? 'row-shared' : '' ?> <?= $classe_temperatura ?>" id="card-lead-<?= $l['id'] ?>">
            <div class="lead-card-header <?= $classe_temperatura ?>">
                <div>
                    <span class="fw-bold text-dark"><?= htmlspecialchars($l['nome']) ?></span>
                    <div class="small text-muted">#<?= $l['id'] ?> · Teto: <strong><?= $v_max ?></strong></div>
                </div>
                <span class="badge <?= getFaseColor($l['fase_funil']) ?> py-2 px-3"><?= $l['fase_funil'] ?: 'Novo' ?></span>
            </div>
            <div class="lead-card-body">
                <div class="lead-card-row">
                    <span class="lead-card-label">Inatividade</span>
                    <span class="lead-card-value text-muted fw-bold"><?= $l['dias_parado'] ?> dias parado</span>
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
                    <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>" style="width: auto; min-width: 160px; font-size: 0.8rem;">
                        <option value="">Selecionar...</option>
                        <?php foreach ($opcoes_passos as $op): ?>
                            <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lead-card-row">
                    <span class="lead-card-label">Compartilhar</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-share" type="checkbox" role="switch" style="cursor: pointer;"
                               data-id="<?= $l['id'] ?>" <?= $is_shared ? 'checked' : '' ?>>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="lead-card-label mb-1">Observações</div>
                    <div class="obs-preview btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>" id="obs-preview-card-<?= $l['id'] ?>" style="border-radius: 8px;">
                        <?= !empty($l['observacoes']) ? nl2br(htmlspecialchars($l['observacoes'])) : '<span class="text-muted italic">Clique para adicionar observações...</span>' ?>
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
                <div class="btn-group shadow-sm w-100">
                    <button class="btn btn-sm btn-outline-warning btn-agendar" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" title="Agendar Visita"><i class="bi bi-calendar-plus"></i> <span class="d-none d-sm-inline">Agendar</span></button>
                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar"><i class="bi bi-eye-fill"></i> <span class="d-none d-sm-inline">Ver</span></a>
                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil-square"></i> <span class="d-none d-sm-inline">Editar</span></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modais (Observações e Agendamento) -->
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
$(document).ready(function() {
    // Alternar compartilhamento
    $(document).on('change', '.toggle-share', function() {
        const id = $(this).data('id');
        const val = $(this).is(':checked') ? 1 : 0;
        const row = $(`#row-lead-${id}`);
        const card = $(`#card-lead-${id}`);
        $.post('leads.php', { action: 'toggle_share', id: id, value: val }, function(res) {
            if(res.status === 'success') {
                if (val === 1) { row.addClass('row-shared'); card.addClass('row-shared'); }
                else { row.removeClass('row-shared'); card.removeClass('row-shared'); }
            }
        }, 'json');
    });

    // Observações
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
        $.post('leads.php', { action: 'update_obs', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') {
                $(`.btn-obs[data-id="${id}"]`).data('obs', obs);
                $(`#obs-preview-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique aqui para adicionar observações...</span>');
                $(`#obs-preview-card-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique para adicionar observações...</span>');
                $('#modalObs').modal('hide');
            }
        }, 'json');
    });

    // Temperatura
    $(document).on('click', '.temp-badge', function() {
        $.post('leads.php', { action: 'update_temp', id: $(this).data('id'), temp: $(this).data('temp') }, function() { location.reload(); });
    });

    // Próximo passo
    $(document).on('change', '.select-step', function() {
        $.post('leads.php', { action: 'update_step', id: $(this).data('id'), step: $(this).val() });
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
        $.post('leads.php', {
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
});
</script>
<script>
$(document).ready(function() {
    // Usamos $(document).on para garantir que funcione mesmo em elementos carregados dinamicamente
    $(document).on('click', '.btn-favorito', function(e) {
        e.preventDefault();
        
        const icone = $(this);
        const leadId = icone.data('id');

        console.log("Clicado no lead ID:", leadId); // Verifique se isso aparece no F12 do navegador

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
                } else {
                    console.error('Erro no servidor:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error);
                alert('Erro ao conectar com o servidor. Verifique o console (F12).');
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>