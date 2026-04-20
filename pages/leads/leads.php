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
// LÓGICA AJAX (Mantida igual)
// ==========================================
if (isset($_POST['action'])) {
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
// CONSULTA (Mantida igual)
// ==========================================
$where = "WHERE 1=1";
$params = [];
$temp_ativa = $_GET['temperatura'] ?? '';
$busca = $_GET['busca'] ?? '';

if ($temp_ativa) { $where .= " AND temperatura = ?"; $params[] = $temp_ativa; }
if ($busca) { $where .= " AND (nome LIKE ? OR telefone LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

$sql = "SELECT *, COALESCE(DATEDIFF(NOW(), ultima_interacao), 0) as dias_parado FROM leads $where 
        ORDER BY ordem DESC, CASE WHEN temperatura='Quente' THEN 1 WHEN temperatura='Morno' THEN 2 WHEN temperatura='Frio' THEN 3 ELSE 4 END ASC, ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
    /* Estilos de temperatura */
    .table tbody tr.temperatura-quente { background-color: #fff0f0 !important; border-left: 5px solid #dc3545; }
    .table tbody tr.temperatura-morno { background-color: #fff9e6 !important; border-left: 5px solid #ffc107; }
    .table tbody tr.temperatura-frio { background-color: #e6f4ff !important; border-left: 5px solid #0dcaf0; }
    
    .temp-badge { cursor: pointer; transition: all 0.2s ease; opacity: 0.3; filter: grayscale(100%); font-size: 1.2rem; padding: 2px 5px; display: inline-block; }
    .temp-badge.active { opacity: 1; filter: none; transform: scale(1.2); }
    .badge-fase { font-size: 0.7rem; padding: 5px 10px; text-transform: uppercase; }

    /* Ajuste Mobile: Transforma tabela em cards */
    @media (max-width: 768px) {
        .table thead { display: none; } /* Esconde cabeçalho */
        .table tbody tr { 
            display: block; 
            margin-bottom: 15px; 
            border: 1px solid #dee2e6; 
            border-radius: 8px;
            padding: 10px;
            position: relative;
        }
        .table tbody td { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border: none; 
            padding: 5px 0;
            text-align: right;
            font-size: 0.9rem;
        }
        .table tbody td::before { 
            content: attr(data-label); 
            font-weight: bold; 
            text-align: left; 
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #6c757d;
        }
        .table tbody td:last-child { border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px; }
        
        .btn-mobile-full { width: 100%; display: flex; justify-content: space-around; }
        .select-step { width: auto !important; min-width: 150px; }
    }
</style>

<div class="container-fluid px-3 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Leads</h2>
            <p class="text-muted small mb-0">ID #000 e filtros ativos</p>
        </div>
        <a href="lead_form.php" class="btn btn-primary shadow-sm w-100 w-md-auto"><i class="bi bi-plus-lg me-2"></i>Novo Lead</a>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <form method="GET" class="input-group input-group-sm">
                <?php if($temp_ativa): ?> <input type="hidden" name="temperatura" value="<?= $temp_ativa ?>"> <?php endif; ?>
                <input type="text" name="busca" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="col-12 col-md-8">
            <div class="d-flex gap-1 overflow-auto pb-2">
                <a href="leads.php" class="btn btn-xs btn-sm <?= !$temp_ativa ? 'btn-dark' : 'btn-outline-secondary' ?>">Todos</a>
                <a href="?temperatura=Quente&busca=<?= $busca ?>" class="btn btn-sm <?= $temp_ativa == 'Quente' ? 'btn-danger' : 'btn-outline-danger' ?>">🔥 Quentes</a>
                <a href="?temperatura=Morno&busca=<?= $busca ?>" class="btn btn-sm <?= $temp_ativa == 'Morno' ? 'btn-warning' : 'btn-outline-warning' ?>">⚖️ Mornos</a>
                <a href="?temperatura=Frio&busca=<?= $busca ?>" class="btn btn-sm <?= $temp_ativa == 'Frio' ? 'btn-info' : 'btn-outline-info' ?>">❄️ Frios</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0 p-md-2">
            <div class="table-responsive-none"> <table id="tabelaLeads" class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Fase</th>
                            <th>Lead</th>
                            <th width="140">Temp.</th>
                            <th>Ação</th>
                            <th>Valor</th>
                            <th>Interação</th>
                            <th class="text-end">Opções</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $l): 
                            $temp = $l['temperatura'] ?: 'Morno';
                            $rowClass = "temperatura-" . strtolower($temp);
                            $wa_link = "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']);
                            $status_color = ($l['dias_parado'] >= 7) ? 'text-danger' : (($l['dias_parado'] >= 3) ? 'text-warning' : 'text-success');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td data-label="ID" class="text-muted fw-bold">#<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td data-label="Fase">
                                <span class="badge <?= getFaseColor($l['fase_funil']) ?> badge-fase">
                                    <?= $l['fase_funil'] ?: 'Novo' ?>
                                </span>
                            </td>
                            <td data-label="Lead" class="text-start-mobile">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($l['nome']) ?></div>
                                <div class="small">
                                    <a href="<?= $wa_link ?>" target="_blank" class="text-success text-decoration-none fw-bold">
                                        <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($l['telefone']) ?>
                                    </a>
                                </div>
                            </td>
                            <td data-label="Temperatura">
                                <div class="d-flex justify-content-end gap-2">
                                    <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥</span>
                                    <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️</span>
                                    <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️</span>
                                </div>
                            </td>
                            <td data-label="Próximo Passo">
                                <select class="form-select form-select-sm select-step" data-id="<?= $l['id'] ?>">
                                    <option value="">-- Definir --</option>
                                    <?php foreach ($opcoes_passos as $op): ?>
                                        <option value="<?= $op ?>" <?= ($l['proximo_passo'] == $op ? 'selected' : '') ?>><?= $op ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="Valor" class="fw-bold text-success">R$ <?= number_format($l['valor_max'], 0, ',', '.') ?></td>
                            <td data-label="Última">
                                <small class="<?= $status_color ?>">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/y', strtotime($l['ultima_interacao'])) ?>
                                </small>
                            </td>
                            <td data-label="Ações" class="text-end">
                                <div class="btn-group w-100-mobile">
                                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
                                    <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> Editar</a>
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
<script>
$(document).ready(function() {
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

    $(document).on('change', '.select-step', function() {
        $.post('leads.php', { action: 'update_step', id: $(this).data('id'), step: $(this).val() });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>