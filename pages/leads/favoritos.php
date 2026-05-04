<?php
// favoritos.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// LÓGICA AJAX (PROCESSAMENTO) - Mantida para manter funcionalidades
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
    exit; 
}

// ==========================================
// CONFIGURAÇÕES
// ==========================================
$opcoes_passos = ["Ligar para qualificar", "Agendar visita", "Enviar simulação", "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"];
$temps_lista = ['Quente' => '🔥 Quente', 'Morno' => '⚖️ Morno', 'Frio' => '❄️ Frio'];

// Filtro fixo: apenas favoritos
$where = "WHERE l.favorito = 1";
$params = [];

// Mantém busca se o usuário pesquisar dentro dos favoritos
$busca = $_GET['busca'] ?? '';
if ($busca) {
    $where .= " AND (l.nome LIKE ? OR l.telefone LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

function getFaseColor($fase) {
    $cores = ['Novo'=>'bg-info text-dark','Tentativa de Contato'=>'bg-warning text-dark','Contato Feito'=>'bg-primary text-white','Visita Agendada'=>'bg-success text-white','Visita Realizada'=>'bg-dark text-white','Analisando'=>'bg-secondary text-white','Proposta'=>'bg-danger text-white','Fechado'=>'bg-success text-white','Perdido'=>'bg-light text-muted'];
    return $cores[$fase] ?? 'bg-light text-dark';
}

// ==========================================
// CONSULTA PRINCIPAL (Filtro Favoritos Ativo)
// ==========================================
$sql = "SELECT l.*, 
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT GROUP_CONCAT(res ORDER BY data_registro DESC SEPARATOR '||') FROM (
            SELECT CONCAT(DATE_FORMAT(data_registro, '%d/%m'), ' - ', detalhes) as res, lead_id, data_registro 
            FROM lead_historico 
        ) as sub_hist WHERE sub_hist.lead_id = l.id) as resumo_historico,
        (SELECT GROUP_CONCAT(v_res ORDER BY dv DESC SEPARATOR '||') FROM (
            SELECT 
                CONCAT(DATE_FORMAT(v.data_visita, '%d/%m %H:%i'), ' - ', COALESCE(i.titulo, 'Sem imóvel')) as v_res,
                v.lead_id, v.data_visita as dv
            FROM visitas v
            LEFT JOIN imoveis i ON v.imovel_id = i.id
        ) as sub_v WHERE sub_v.lead_id = l.id) as ultimas_visitas_reais
        FROM leads l $where ORDER BY l.ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!-- Reutilizando seus estilos CSS -->
<style>
    /* ... (Copiar os mesmos estilos da leads.php para manter a identidade visual) ... */
    body { background-color: #f8f9fa; }
    .name-row-container { padding: 12px; border-radius: 8px 8px 0 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .obs-preview { background: rgba(255,255,255,0.6); padding: 8px 12px; font-size: 0.88rem; color: #444; border-radius: 0 0 8px 8px; border: 1px solid rgba(0,0,0,0.03); cursor: pointer; }
    .hist-container { font-size: 0.75rem; background: #fff; border-radius: 6px; padding: 8px; border: 1px solid #eee; margin-top: 8px; }
    .hist-item { border-bottom: 1px solid #f1f1f1; padding: 3px 0; color: #666; }
    .temp-badge { cursor: pointer; font-size: 1.2rem; filter: grayscale(1); opacity: 0.6; transition: 0.2s; }
    .temp-badge.active { filter: grayscale(0); transform: scale(1.2); opacity: 1; }
    tr.lead-quente { background-color: #f8d7da !important; border-left: 5px solid #dc3545 !important; }
    tr.lead-morno { background-color: #fff3cd !important; border-left: 5px solid #ffc107 !important; }
    tr.lead-frio { background-color: #d1ecf1 !important; border-left: 5px solid #17a2b8 !important; }
</style>

<div class="container-fluid px-3 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-warning mb-0"><i class="bi bi-star-fill"></i> Leads Favoritos</h2>
            <p class="text-muted small mb-0"><?= count($lista) ?> leads marcados com estrela</p>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto">
            <form action="" method="GET" class="d-flex gap-2 flex-grow-1 flex-md-grow-0">
                <input type="text" name="busca" class="form-control" placeholder="Buscar nos favoritos..." value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-light border"><i class="bi bi-search"></i></button>
            </form>
            <a href="leads.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <!-- Tabela Desktop -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-uppercase small fw-bold">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Fase</th>
                            <th>Lead / Histórico</th>
                            <th class="text-center">Temperatura</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $l): 
                            $temp = $l['temperatura'] ?: 'Morno';
                            $classe_temperatura = match($temp) { 'Quente' => 'lead-quente', 'Morno' => 'lead-morno', 'Frio' => 'lead-frio', default => 'lead-morno' };
                        ?>
                        <tr id="row-lead-<?= $l['id'] ?>" class="<?= $classe_temperatura ?>">
                            <td class="ps-3 small text-muted">#<?= $l['id'] ?></td>
                            <td style="width: 150px;">
                                <span class="badge <?= getFaseColor($l['fase_funil']) ?> w-100 py-2 mb-1"><?= $l['fase_funil'] ?: 'Novo' ?></span>
                            </td>
                            <td>
                                <div class="name-row-container d-flex align-items-center gap-2">
                                    <i class="bi bi-star-fill text-warning fs-5 btn-favorito" data-id="<?= $l['id'] ?>" style="cursor: pointer;"></i>
                                    <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($l['nome']) ?></span>
                                </div>
                                <div class="obs-preview btn-obs" data-id="<?= $l['id'] ?>" data-nome="<?= htmlspecialchars($l['nome']) ?>" data-obs="<?= htmlspecialchars($l['observacoes']) ?>">
                                    <?= !empty($l['observacoes']) ? nl2br(htmlspecialchars($l['observacoes'])) : '<span class="text-muted italic">Sem observações...</span>' ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                                    <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                                    <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye-fill"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($lista)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum favorito encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Obs (Reutilizado) -->
<div class="modal fade" id="modalObs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Obs: <span id="nomeLeadObs"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formObs">
                <input type="hidden" id="obs_lead_id" name="id">
                <div class="modal-body bg-light">
                    <textarea class="form-control border-0 shadow-sm" id="obs_texto" name="observacoes" rows="8"></textarea>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Salvar Notas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Ajax para atualizar favoritos (Desmarcar remove da lista ao recarregar)
    $(document).on('click', '.btn-favorito', function() {
        const leadId = $(this).data('id');
        $.post('atualizar_favorito.php', { id: leadId }, function() {
            location.reload(); // Recarrega para sumir da lista de favoritos
        });
    });

    // Observações
    $(document).on('click', '.btn-obs', function() {
        $('#obs_lead_id').val($(this).data('id'));
        $('#nomeLeadObs').text($(this).data('nome'));
        $('#obs_texto').val($(this).data('obs'));
        $('#modalObs').modal('show');
    });

    $('#formObs').on('submit', function(e) {
        e.preventDefault();
        const id = $('#obs_lead_id').val();
        const obs = $('#obs_texto').val();
        $.post('favoritos.php', { action: 'update_obs', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') location.reload();
        });
    });

    // Temperatura
    $(document).on('click', '.temp-badge', function() {
        $.post('favoritos.php', { action: 'update_temp', id: $(this).data('id'), temp: $(this).data('temp') }, function() { location.reload(); });
    });
});
</script>