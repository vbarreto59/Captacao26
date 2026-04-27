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
        // Título fixo "Visita", descrição = nome do lead + observações extras (opcional)
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

$sql = "SELECT l.*, COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT GROUP_CONCAT(res ORDER BY data_registro DESC SEPARATOR '||') FROM (
            SELECT CONCAT(DATE_FORMAT(data_registro, '%d/%m'), ' - ', detalhes) as res, lead_id, data_registro FROM lead_historico 
        ) as sub_hist WHERE sub_hist.lead_id = l.id) as resumo_historico
        FROM leads l $where ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
    body { background-color: #f8f9fa; }
    .scroll-x { overflow-x: auto; display: flex; gap: 8px; padding-bottom: 10px; }
    .name-row-container { padding: 12px; border-radius: 8px 8px 0 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .obs-preview { background: rgba(255,255,255,0.6); padding: 8px 12px; font-size: 0.88rem; color: #444; border-radius: 0 0 8px 8px; border: 1px solid rgba(0,0,0,0.03); cursor: pointer; line-height: 1.4; }
    .hist-container { font-size: 0.75rem; background: #fff; border-radius: 6px; padding: 8px; border: 1px solid #eee; margin-top: 8px; }
    .hist-item { border-bottom: 1px solid #f1f1f1; padding: 3px 0; color: #666; }
    .btn-obs { cursor: pointer; color: #0d6efd; transition: 0.2s; }
    .temp-badge { cursor: pointer; font-size: 1.2rem; filter: grayscale(1); opacity: 0.6; transition: 0.2s; }
    .temp-badge.active { filter: grayscale(0); transform: scale(1.2); opacity: 1; }
    .row-shared { background-color: #f0f7ff !important; border-left: 6px solid #0d6efd !important; }
    
    /* Estilo para o filtro de temperatura */
    .filter-temp-link { text-decoration: none; color: #666; padding: 5px 12px; border-radius: 20px; border: 1px solid #ddd; background: #fff; font-size: 0.85rem; transition: 0.2s; }
    .filter-temp-link:hover { background: #eee; }
    .filter-temp-link.active { background: #333; color: #fff; border-color: #333; }
</style>

<div class="container-fluid px-3 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
            <p class="text-muted small mb-0"><?= count($lista) ?> leads na lista atual</p>
        </div>
        <div class="d-flex gap-2">
            <form action="" method="GET" class="d-flex gap-2">
                <input type="text" name="busca" class="form-control" placeholder="Pesquisar..." value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-light border"><i class="bi bi-search"></i></button>
            </form>
            <a href="lead_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i></a>
        </div>
    </div>

    <div class="scroll-x mb-2">
        <?php foreach ($fases_lista as $f): ?>
            <a href="?fase=<?= urlencode($f) . ($temp_ativa ? "&temperatura=$temp_ativa" : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="btn btn-sm <?= getFaseColor($f) ?> <?= ($fase_ativa == $f) ? 'active shadow border-dark' : 'border' ?> px-3" style="white-space: nowrap;"><?= $f ?></a>
        <?php endforeach; ?>
    </div>

    <div class="d-flex gap-2 mb-4 align-items-center">
        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Temperatura:</small>
        <a href="?fase=<?= urlencode($fase_ativa) . ($busca ? "&busca=$busca" : "") ?>" class="filter-temp-link <?= $temp_ativa == '' ? 'active' : '' ?>">Todos</a>
        <?php foreach ($temps_lista as $key => $label): ?>
            <a href="?temperatura=<?= $key . ($fase_ativa ? "&fase=".urlencode($fase_ativa) : "") . ($busca ? "&busca=$busca" : "") ?>" 
               class="filter-temp-link <?= $temp_ativa == $key ? 'active' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

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
                            $bg_temp = match($temp){'Quente'=>'bg-danger bg-opacity-10','Morno'=>'bg-warning bg-opacity-10','Frio'=>'bg-info bg-opacity-10',default=>'bg-light'};
                        ?>
                        <tr id="row-lead-<?= $l['id'] ?>" class="<?= $is_shared ? 'row-shared' : '' ?>">
                            <td class="ps-3 small text-muted">#<?= $l['id'] ?></td>
                            <td style="width: 150px;">
                                <span class="badge <?= getFaseColor($l['fase_funil']) ?> w-100 py-2 mb-1"><?= $l['fase_funil'] ?: 'Novo' ?></span>
                                <div class="text-center small text-muted fw-bold"><?= $l['dias_parado'] ?> dias parado</div>
                            </td>
                            <td>
                                <div class="name-row-container d-flex align-items-center gap-3 <?= $bg_temp ?>">
                                    <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($l['nome']) ?></span>
                                    <i class="bi bi-journal-text btn-obs fs-4" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>"></i>
                                    <span class="ms-auto badge bg-white text-dark border shadow-sm py-2 px-3 small">Teto: <strong><?= $v_max ?></strong></span>
                                </div>
                                <div class="obs-preview <?= $bg_temp ?> btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>" id="obs-preview-<?= $l['id'] ?>">
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
                                <div class="btn-group shadow-sm">
                                    <button class="btn btn-sm btn-outline-warning btn-agendar" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" title="Agendar Visita"><i class="bi bi-calendar-plus"></i></button>
                                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar"><i class="bi bi-eye-fill"></i></a>
                                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Observações -->
<div class="modal fade" id="modalObs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
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

<!-- Modal Agendamento de Visita -->
<div class="modal fade" id="modalAgenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
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
    // Alternar compartilhamento com parceiro
    $(document).on('change', '.toggle-share', function() {
        const id = $(this).data('id');
        const val = $(this).is(':checked') ? 1 : 0;
        const row = $(`#row-lead-${id}`);
        $.post('leads.php', { action: 'toggle_share', id: id, value: val }, function(res) {
            if(res.status === 'success') {
                val === 1 ? row.addClass('row-shared') : row.removeClass('row-shared');
            }
        }, 'json');
    });

    // Abrir modal de observações
    $(document).on('click', '.btn-obs', function() {
        const id = $(this).data('id');
        $('#obs_lead_id').val(id);
        $('#nomeLeadObs').text($(this).data('nome'));
        $('#obs_texto').val($(this).data('obs'));
        $('#modalObs').modal('show');
    });

    // Salvar observações
    $('#formObs').on('submit', function(e) {
        e.preventDefault();
        const id = $('#obs_lead_id').val();
        const obs = $('#obs_texto').val();
        $.post('leads.php', { action: 'update_obs', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') {
                $(`.btn-obs[data-id="${id}"]`).data('obs', obs);
                $(`#obs-preview-${id}`).html(obs ? obs.replace(/\n/g, '<br>') : '<span class="text-muted italic">Clique aqui para adicionar observações...</span>');
                $('#modalObs').modal('hide');
            }
        }, 'json');
    });

    // Atualizar temperatura
    $(document).on('click', '.temp-badge', function() {
        $.post('leads.php', { action: 'update_temp', id: $(this).data('id'), temp: $(this).data('temp') }, function() { location.reload(); });
    });

    // Atualizar próximo passo
    $(document).on('change', '.select-step', function() {
        $.post('leads.php', { action: 'update_step', id: $(this).data('id'), step: $(this).val() });
    });

    // ================== AGENDAR VISITA ==================
    // Abrir modal de agendamento
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

    // Enviar formulário de agendamento
    $('#formAgenda').on('submit', function(e) {
        e.preventDefault();
        const leadId = $('#agenda_lead_id').val();
        const leadNome = $('#agenda_lead_nome').val();
        const dataEvento = $('#agenda_data').val();
        const descricaoExtra = $('#agenda_descricao').val();

        if (!dataEvento) {
            alert('Defina a data e hora da visita.');
            return;
        }

        $.post('leads.php', {
            action: 'add_agenda',
            lead_id: leadId,
            lead_nome: leadNome,
            data_evento: dataEvento,
            descricao: descricaoExtra   // será concatenada ao nome do lead no backend
        }, function(res) {
            if (res.status === 'success') {
                $('#modalAgenda').modal('hide');
                alert('Visita agendada com sucesso!');
            } else {
                alert('Erro ao salvar. Verifique os dados.');
            }
        }, 'json').fail(function() {
            alert('Erro de comunicação com o servidor.');
        });
    });
    // ====================================================
});
</script>

<?php require_once '../../includes/footer.php'; ?>
<!-- funcional em parte -->