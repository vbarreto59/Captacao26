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
    // NOVA AÇÃO: SALVAR NA AGENDA_GERAL
    elseif ($_POST['action'] == 'add_agenda') {
        $titulo = "Lead: " . $_POST['lead_nome'];
        $data_evento = $_POST['data_evento'];
        $descricao = $_POST['descricao'];
        $categoria = "Lead";
        
        $stmt = $conn->prepare("INSERT INTO agenda_geral (titulo, descricao, data_evento, categoria, status) VALUES (?, ?, ?, ?, 'Pendente')");
        echo json_encode(['status' => $stmt->execute([$titulo, $descricao, $data_evento, $categoria]) ? 'success' : 'error']);
    }
    exit; 
}

// ==========================================
// CONFIGURAÇÕES E OPÇÕES
// ==========================================
$opcoes_passos = ["Ligar para qualificar", "Agendar visita", "Enviar simulação", "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"];
$fases_lista = ['Novo', 'Tentativa de Contato', 'Contato Feito', 'Visita Agendada', 'Visita Realizada', 'Analisando', 'Proposta', 'Fechado', 'Perdido'];

function getFaseColor($fase) {
    $cores = [
        'Novo' => 'bg-info text-dark', 'Tentativa de Contato' => 'bg-warning text-dark',
        'Contato Feito' => 'bg-primary text-white', 'Visita Agendada' => 'bg-success text-white',
        'Visita Realizada' => 'bg-dark text-white', 'Analisando' => 'bg-secondary text-white',
        'Proposta' => 'bg-danger text-white', 'Fechado' => 'bg-success text-white', 'Perdido' => 'bg-light text-muted'
    ];
    return $cores[$fase] ?? 'bg-light text-dark';
}

function getTempBadgeClass($temp) {
    return match($temp) { 'Quente' => 'bg-danger text-white', 'Morno' => 'bg-warning text-dark', 'Frio' => 'bg-info text-white', default => 'bg-secondary text-white' };
}

function getTempBgSoft($temp) {
    return match($temp) { 'Quente' => 'bg-danger bg-opacity-10', 'Morno' => 'bg-warning bg-opacity-10', 'Frio' => 'bg-info bg-opacity-10', default => 'bg-light' };
}

// ==========================================
// CONSULTA BANCO DE DADOS COM FILTRO DE PERDIDOS
// ==========================================
$where = "WHERE 1=1";
$params = [];
$temp_ativa = $_GET['temperatura'] ?? '';
$fase_ativa = $_GET['fase'] ?? '';
$busca = $_GET['busca'] ?? '';

// REGRA: Se não clicou em "Perdido" especificamente, oculta-os da lista geral
if (!$fase_ativa) {
    $where .= " AND (fase_funil != 'Perdido' OR fase_funil IS NULL)";
} else {
    $where .= " AND fase_funil = ?";
    $params[] = $fase_ativa;
}

if ($temp_ativa) { $where .= " AND temperatura = ?"; $params[] = $temp_ativa; }
if ($busca) { $where .= " AND (nome LIKE ? OR telefone LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

$sql = "SELECT l.*, COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT COUNT(*) FROM lead_historico WHERE lead_id = l.id) as total_historico,
        (SELECT GROUP_CONCAT(res ORDER BY data_registro DESC SEPARATOR '||') FROM (
            SELECT CONCAT(DATE_FORMAT(data_registro, '%d/%m'), ' - ', acao, ': ', detalhes) as res, lead_id, data_registro FROM lead_historico 
        ) as sub_hist WHERE sub_hist.lead_id = l.id) as resumo_historico
        FROM leads l $where ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
    body { font-size: 16px; }
    .hist-container { font-size: 0.75rem; line-height: 1.2; }
    .hist-item { border-bottom: 1px solid #f0f0f0; padding: 2px 0; }
    .badge-temp-click { cursor: pointer; transition: opacity 0.2s; }
    .name-row-container { transition: background 0.3s ease; padding: 5px 10px; border-radius: 8px; }
    .temp-badge { cursor: pointer; font-size: 1.2rem; filter: grayscale(1); transition: 0.3s; }
    .temp-badge.active { filter: grayscale(0); transform: scale(1.2); }
    .scroll-x { overflow-x: auto; display: flex; gap: 5px; padding-bottom: 10px; }
    @media (max-width: 768px) {
        #tabelaLeads thead { display: none; }
        #tabelaLeads tr { display: flex; flex-direction: column; background: #fff; border: 1px solid #eee !important; border-radius: 12px; margin-bottom: 15px; padding: 15px; }
        #tabelaLeads td { display: block; width: 100% !important; padding: 5px 0 !important; border: none !important; }
    }
</style>

<div class="container-fluid px-3 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
            <p class="text-muted small mb-0"><?= count($lista) ?> registros encontrados <?= !$fase_ativa ? '(ocultando perdidos)' : '' ?></p>
        </div>
        <a href="lead_form.php" class="btn btn-primary btn-lg shadow-sm w-100 w-md-auto"><i class="bi bi-plus-lg me-2"></i>Novo Lead</a>
    </div>

    <div class="mb-4">
        <div class="scroll-x mb-2">
            <a href="leads.php" class="btn btn-sm <?= (!$temp_ativa && !$fase_ativa) ? 'btn-dark' : 'btn-outline-secondary' ?> px-3">Todos os Ativos</a>
            <a href="?temperatura=Quente<?= $fase_ativa ? '&fase='.urlencode($fase_ativa) : '' ?>" class="btn btn-sm <?= $temp_ativa == 'Quente' ? 'btn-danger' : 'btn-outline-danger' ?> px-3">🔥 Quentes</a>
            <a href="?temperatura=Morno<?= $fase_ativa ? '&fase='.urlencode($fase_ativa) : '' ?>" class="btn btn-sm <?= $temp_ativa == 'Morno' ? 'btn-warning' : 'btn-outline-warning' ?> px-3">⚖️ Mornos</a>
            <a href="?temperatura=Frio<?= $fase_ativa ? '&fase='.urlencode($fase_ativa) : '' ?>" class="btn btn-sm <?= $temp_ativa == 'Frio' ? 'btn-info' : 'btn-outline-info' ?> px-3">❄️ Frios</a>
        </div>
        <div class="scroll-x">
            <?php foreach ($fases_lista as $f): ?>
                <a href="?fase=<?= urlencode($f) . ($temp_ativa ? "&temperatura=$temp_ativa" : "") ?>" 
                   class="btn btn-sm <?= getFaseColor($f) ?> <?= ($fase_ativa == $f) ? 'active shadow-sm border-dark' : '' ?> px-3 border mb-1" style="white-space: nowrap;"><?= $f ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabelaLeads" class="table align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-fase">Fase</th>
                            <th class="col-lead">Lead / Histórico</th>
                            <th class="col-temp text-center">Temp.</th>
                            <th class="col-step">Próximo Passo</th>
                            <th class="col-valor text-center">Valor Máx.</th>
                            <th class="col-interacao">Última Interação</th>
                            <th class="text-end col-opcoes">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $l): 
                            $temp = $l['temperatura'] ?: 'Morno';
                            $wa_link = "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']);
                            $ts_interacao = strtotime($l['ultima_interacao']);
                            $status_color = ($l['dias_parado'] >= 7) ? 'text-danger' : (($l['dias_parado'] >= 3) ? 'text-warning' : 'text-success');
                        ?>
                        <tr id="row-lead-<?= $l['id'] ?>">
                            <td class="col-id text-muted small">#<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td><span class="badge <?= getFaseColor($l['fase_funil']) ?> px-2 py-2 w-100"><?= $l['fase_funil'] ?: 'Novo' ?></span></td>
                            <td class="col-lead">
                                <div class="name-row-container d-flex align-items-center gap-2 flex-wrap <?= getTempBgSoft($temp) ?>" id="name-container-<?= $l['id'] ?>">
                                    <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($l['nome']) ?></div>
                                    <div class="dropdown">
                                        <span class="badge badge-temp-click <?= getTempBadgeClass($temp) ?> dropdown-toggle" id="main-badge-<?= $l['id'] ?>" data-bs-toggle="dropdown"><?= $temp ?></span>
                                        <ul class="dropdown-menu shadow">
                                            <li><a class="dropdown-item quick-temp" href="#" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥 Quente</a></li>
                                            <li><a class="dropdown-item quick-temp" href="#" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️ Morno</a></li>
                                            <li><a class="dropdown-item quick-temp" href="#" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️ Frio</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="mt-2 ps-2">
                                    <a href="<?= $wa_link ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                                </div>
                                <div class="d-none d-md-block hist-container ps-2 mt-2">
                                    <?php if (!empty($l['resumo_historico'])): 
                                        foreach (array_slice(explode('||', $l['resumo_historico']), 0, 2) as $h): ?>
                                            <div class="hist-item text-muted small"><i class="bi bi-chat-text me-1 text-primary"></i><?= htmlspecialchars($h) ?></div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                                    <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                                    <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
                                </div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>">
                                    <option value="">-- Próximo Passo --</option>
                                    <?php foreach ($opcoes_passos as $op): ?>
                                        <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center"><span class="fw-bold text-success">R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?></span></td>
                            <td><div class="<?= $status_color ?> fw-bold small"><i class="bi bi-calendar3"></i> <?= date('d/m/y', $ts_interacao) ?> (<?= $l['dias_parado'] ?>d)</div></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-agendar" data-nome="<?= htmlspecialchars($l['nome']) ?>" title="Agendar"><i class="bi bi-calendar-plus"></i></button>
                                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye-fill"></i></a>
                                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
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

<div class="modal fade" id="modalAgenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Novo Agendamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAgenda">
                <input type="hidden" id="agenda_lead_nome" name="lead_nome">
                <div class="modal-body">
                    <p>Agendando para: <strong id="nomeLeadModal" class="text-primary"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Data e Hora</label>
                        <input type="datetime-local" class="form-control" name="data_evento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" rows="3" placeholder="Ex: Apresentar imóvel de X quartos"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-warning">Confirmar Agendamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    const tabela = $('#tabelaLeads').DataTable({
        "pageLength": 100,
        "order": [[0, "desc"]],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" }
    });

    // Funções de Temperatura
    function updateTempVisual(id, temp) {
        const row = $(`#row-lead-${id}`);
        const mainBadge = $(`#main-badge-${id}`);
        const nameContainer = $(`#name-container-${id}`);
        
        mainBadge.text(temp).removeClass('bg-danger bg-warning bg-info text-white text-dark');
        nameContainer.removeClass('bg-danger bg-warning bg-info bg-opacity-10 bg-light');

        if(temp === 'Quente') { mainBadge.addClass('bg-danger text-white'); nameContainer.addClass('bg-danger bg-opacity-10'); }
        else if(temp === 'Morno') { mainBadge.addClass('bg-warning text-dark'); nameContainer.addClass('bg-warning bg-opacity-10'); }
        else { mainBadge.addClass('bg-info text-white'); nameContainer.addClass('bg-info bg-opacity-10'); }

        row.find('.temp-badge').removeClass('active');
        row.find(`.temp-badge[data-temp="${temp}"]`).addClass('active');
    }

    $(document).on('click', '.temp-badge, .quick-temp', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const temp = $(this).data('temp');
        $.post('leads.php', { action: 'update_temp', id: id, temp: temp }, function(res) {
            if (res.status === 'success') updateTempVisual(id, temp);
        }, 'json');
    });

    // Próximo Passo
    $(document).on('change', '.select-step', function() {
        $.post('leads.php', { action: 'update_step', id: $(this).data('id'), step: $(this).val() });
    });

    // --- LOGICA MODAL AGENDA ---
    $(document).on('click', '.btn-agendar', function() {
        const nome = $(this).data('nome');
        $('#nomeLeadModal').text(nome);
        $('#agenda_lead_nome').val(nome);
        $('#modalAgenda').modal('show');
    });

    $('#formAgenda').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Salvando...');

        $.post('leads.php', {
            action: 'add_agenda',
            lead_nome: $('#agenda_lead_nome').val(),
            data_evento: $('input[name="data_evento"]').val(),
            descricao: $('textarea[name="descricao"]').val()
        }, function(res) {
            if (res.status === 'success') {
                alert('Agendamento salvo na Agenda Geral!');
                $('#modalAgenda').modal('hide');
                $('#formAgenda')[0].reset();
            } else {
                alert('Erro ao salvar agendamento.');
            }
            btn.prop('disabled', false).text('Confirmar Agendamento');
        }, 'json');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>