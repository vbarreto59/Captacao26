<?php
// leads.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// CONFIGURAÇÕES E OPÇÕES
// ==========================================
$opcoes_passos = [
    "Ligar para qualificar", "Agendar visita", "Enviar simulação", 
    "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"
];

function getFaseColor($fase) {
    $cores = [
        'Novo'                 => 'bg-info text-dark',
        'Tentativa de Contato' => 'bg-warning text-dark',
        'Contato Feito'        => 'bg-primary',
        'Visita Agendada'      => 'bg-success',
        'Visita Realizada'     => 'bg-dark',
        'Analisando'           => 'bg-secondary',
        'Proposta'             => 'bg-danger',
        'Fechado'              => 'bg-success',
        'Perdido'              => 'bg-light text-muted'
    ];
    return $cores[$fase] ?? 'bg-light text-dark';
}

// ==========================================
// LÓGICA AJAX
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    if ($_POST['action'] == 'update_temp') {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['temp'], $id]) ? 'success' : 'error']);
    } 
    if ($_POST['action'] == 'update_step') {
        $stmt = $conn->prepare("UPDATE leads SET proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['step'], $id]) ? 'success' : 'error']);
    }
    exit; 
}

// ==========================================
// CONSULTA (COM HISTÓRICO E SINALIZADOR)
// ==========================================
$where = "WHERE 1=1";
$params = [];
$temp_ativa = $_GET['temperatura'] ?? '';
$busca = $_GET['busca'] ?? '';

if ($temp_ativa) { $where .= " AND temperatura = ?"; $params[] = $temp_ativa; }
if ($busca) { $where .= " AND (nome LIKE ? OR telefone LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

// SQL robusta: Incluída contagem de histórico para sinalização
$sql = "SELECT l.*, COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT COUNT(*) FROM lead_historico WHERE lead_id = l.id) as total_historico,
        (
            SELECT GROUP_CONCAT(res SEPARATOR '||')
            FROM (
                SELECT CONCAT(acao, ': ', detalhes) as res, lead_id 
                FROM lead_historico 
                ORDER BY data_registro DESC 
            ) as sub_hist 
            WHERE sub_hist.lead_id = l.id
            LIMIT 2
        ) as resumo_historico
        FROM leads l $where 
        ORDER BY l.ordem DESC, 
        CASE WHEN l.temperatura='Quente' THEN 1 WHEN l.temperatura='Morno' THEN 2 WHEN l.temperatura='Frio' THEN 3 ELSE 4 END ASC, 
        l.ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
    .table tbody tr.temperatura-quente { border-left: 5px solid #dc3545; }
    .table tbody tr.temperatura-morno { border-left: 5px solid #ffc107; }
    .table tbody tr.temperatura-frio { border-left: 5px solid #0dcaf0; }
    
    .temp-badge { cursor: pointer; transition: all 0.2s ease; opacity: 0.3; filter: grayscale(100%); font-size: 1.2rem; padding: 2px 5px; display: inline-block; }
    .temp-badge.active { opacity: 1; filter: none; transform: scale(1.2); }
    .badge-fase { font-size: 0.7rem; padding: 5px 10px; text-transform: uppercase; }

    .hist-item { 
        font-size: 0.72rem; 
        line-height: 1.2; 
        color: #6c757d; 
        border-left: 2px solid #dee2e6; 
        padding-left: 6px; 
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 300px;
    }
    
    @media (max-width: 768px) {
        .d-mobile-none { display: none !important; }
    }
</style>

<div class="container-fluid px-3 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
            <p class="text-muted small mb-0">Total de <?= count($lista) ?> leads encontrados</p>
        </div>
        <a href="lead_form.php" class="btn btn-primary shadow-sm w-100 w-md-auto"><i class="bi bi-plus-lg me-2"></i>Novo Lead</a>
    </div>

    <div class="mb-4 overflow-auto d-flex gap-1 pb-2">
        <a href="leads.php" class="btn btn-sm <?= !$temp_ativa ? 'btn-dark' : 'btn-outline-secondary' ?>">Todos</a>
        <a href="?temperatura=Quente" class="btn btn-sm <?= $temp_ativa == 'Quente' ? 'btn-danger' : 'btn-outline-danger' ?>">🔥 Quentes</a>
        <a href="?temperatura=Morno" class="btn btn-sm <?= $temp_ativa == 'Morno' ? 'btn-warning' : 'btn-outline-warning' ?>">⚖️ Mornos</a>
        <a href="?temperatura=Frio" class="btn btn-sm <?= $temp_ativa == 'Frio' ? 'btn-info' : 'btn-outline-info' ?>">❄️ Frios</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabelaLeads" class="table align-middle mb-0" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th width="30" class="no-sort text-center"><i class="bi bi-info-circle"></i></th>
                            <th width="50">ID</th>
                            <th>Fase</th>
                            <th>Lead / Últimos Contatos</th>
                            <th width="120" class="no-sort">Temp.</th>
                            <th>Ação Sugerida</th>
                            <th>Valor Máx.</th>
                            <th>Última Interação</th>
                            <th width="100" class="no-sort text-end">Opções</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $l): 
                            $temp = $l['temperatura'] ?: 'Morno';
                            $rowClass = "temperatura-" . strtolower($temp);
                            $wa_link = "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']);
                            $ts_interacao = strtotime($l['ultima_interacao']);
                            $status_color = ($l['dias_parado'] >= 7) ? 'text-danger' : (($l['dias_parado'] >= 3) ? 'text-warning' : 'text-success');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="text-center">
                                <?php if ($l['total_historico'] == 0): ?>
                                    <span class="text-danger" title="Sem histórico registrado"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity:0.3"><i class="bi bi-chat-left-check"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted fw-bold">#<?= $l['id'] ?></td>
                            <td>
                                <span class="badge <?= getFaseColor($l['fase_funil']) ?> badge-fase">
                                    <?= $l['fase_funil'] ?: 'Novo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($l['nome']) ?></div>
                                <div class="small mb-1">
                                    <a href="<?= $wa_link ?>" target="_blank" class="text-success text-decoration-none fw-bold">
                                        <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($l['telefone']) ?>
                                    </a>
                                </div>
                                
                                <?php if (!empty($l['resumo_historico'])): 
                                    $historicos = explode('||', $l['resumo_historico']);
                                    foreach ($historicos as $hist): ?>
                                        <div class="hist-item" title="<?= htmlspecialchars($hist) ?>">
                                            <i class="bi bi-dot"></i> <?= htmlspecialchars($hist) ?>
                                        </div>
                                    <?php endforeach; 
                                endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                                    <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                                    <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
                                </div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>" style="min-width: 140px;">
                                    <option value="">-- Definir --</option>
                                    <?php foreach ($opcoes_passos as $op): ?>
                                        <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-sort="<?= $l['valor_max'] ?>">
                                <span class="fw-bold text-success">R$ <?= number_format($l['valor_max'], 0, ',', '.') ?></span>
                            </td>
                            <td data-sort="<?= $ts_interacao ?>">
                                <small class="<?= $status_color ?> fw-bold">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/y', $ts_interacao) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" title="Ver Detalhes"><i class="bi bi-eye-fill text-primary"></i></a>
                                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" title="Editar"><i class="bi bi-pencil-square"></i></a>
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

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializa DataTable
    const tabela = $('#tabelaLeads').DataTable({
        "pageLength": 50,
        "order": [[7, "desc"]], // Ajustado para coluna de data que agora é índice 7
        "columnDefs": [
            { "targets": 'no-sort', "orderable": false }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        }
    });

    // Lógica Temperatura AJAX
    $(document).on('click', '.temp-badge', function() {
        const el = $(this);
        const id = el.data('id');
        const temp = el.data('temp');
        const row = el.closest('tr');

        el.closest('td').find('.temp-badge').removeClass('active');
        el.addClass('active');

        $.post('leads.php', { action: 'update_temp', id: id, temp: temp }, function(response) {
            if (response.status === 'success') {
                row.removeClass('temperatura-quente temperatura-morno temperatura-frio')
                   .addClass('temperatura-' + temp.toLowerCase());
            }
        }, 'json');
    });

    // Lógica Próximo Passo AJAX
    $(document).on('change', '.select-step', function() {
        const id = $(this).data('id');
        const step = $(this).val();
        $.post('leads.php', { action: 'update_step', id: id, step: step });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>