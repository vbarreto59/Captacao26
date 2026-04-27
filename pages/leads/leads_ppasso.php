<?php
// leads_ppasso.php - PIPELINE EM FORMATO TABELA
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// CONFIGURAÇÃO
// ==========================================
$opcoes_passos = [
    "Lead recebido",
    "Ligar para qualificar",
    "Agendar visita",
    "Enviar simulação",
    "Cobrar feedback",
    "Enviar opções similares",
    "Aguardando retorno"
];

$opcoes_fases = [
    "Novo",
    "Tentativa de Contato",
    "Contato Feito",
    "Visita Agendada",
    "Visita Realizada",
    "Analisando",
    "Proposta",
    "Fechado",
    "Perdido"
];

// ==========================================
// AJAX
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

// Exclusão
if (isset($_GET['excluir'])) {
    $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
    $stmt->execute([(int)$_GET['excluir']]);
    header("Location: leads_ppasso.php?msg=Lead removido com sucesso");
    exit;
}

// ==========================================
// FILTROS E SQL
// ==========================================
$where = "WHERE 1=1";
$params = [];

if (!empty($_GET['temperatura'])) {
    $where .= " AND temperatura = ?";
    $params[] = $_GET['temperatura'];
}

if (!empty($_GET['proximo_passo'])) {
    $where .= " AND proximo_passo = ?";
    $params[] = $_GET['proximo_passo'];
}

if (!empty($_GET['fase_funil'])) {
    $where .= " AND fase_funil = ?";
    $params[] = $_GET['fase_funil'];
}

$sql = "SELECT *, DATEDIFF(NOW(), ultima_interacao) as dias_parado 
        FROM leads $where 
        ORDER BY ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por Próximo Passo
$leads_por_passo = [];
foreach ($opcoes_passos as $passo) { $leads_por_passo[$passo] = []; }
$leads_sem_passo = [];

foreach ($leads as $l) {
    $passo = trim($l['proximo_passo'] ?? '');
    if (in_array($passo, $opcoes_passos)) {
        $leads_por_passo[$passo][] = $l;
    } else {
        $leads_sem_passo[] = $l;
    }
}

require_once '../../includes/header.php';
?>

<style>
    .pipeline-section { margin-bottom: 3rem; }
    .section-header {
        background: #f8f9fa;
        border-left: 5px solid #0d6efd;
        padding: 10px 15px;
        margin-bottom: 10px;
        border-radius: 4px;
    }
    .table-pipeline thead th {
        background-color: #f1f3f5;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }
    .table-pipeline tbody td { vertical-align: middle; }
    .temp-select-sm {
        font-size: 0.85rem;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 600;
        width: auto;
        display: inline-block;
    }
    .dias-badge { font-size: 0.75rem; padding: 3px 7px; }
    .table-pipeline tr:hover { background-color: #fcfcfc; }
    .filter-box { background: #fff; border: 1px solid #dee2e6; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
</style>

<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">Pipeline de Leads</h2>
            <small class="text-muted">Visualização por Tabela de Etapas</small>
        </div>
        <a href="lead_form.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-2"></i>Novo Lead
        </a>
    </div>

    <div class="filter-box shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Temperatura</label>
                <select name="temperatura" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="Quente" <?= ($_GET['temperatura'] ?? '') == 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                    <option value="Morno" <?= ($_GET['temperatura'] ?? '') == 'Morno' ? 'selected' : '' ?>>⚖️ Morno</option>
                    <option value="Frio" <?= ($_GET['temperatura'] ?? '') == 'Frio' ? 'selected' : '' ?>>❄️ Frio</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Próximo Passo</label>
                <select name="proximo_passo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach($opcoes_passos as $op): ?>
                        <option value="<?= $op ?>" <?= ($_GET['proximo_passo'] ?? '') == $op ? 'selected' : '' ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Fase do Funil</label>
                <select name="fase_funil" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach($opcoes_fases as $f): ?>
                        <option value="<?= $f ?>" <?= ($_GET['fase_funil'] ?? '') == $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <a href="leads_ppasso.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <?php 
    // Função auxiliar para renderizar a tabela
    function renderTabelaLeads($lista, $opcoes_passos) {
        if (empty($lista)) return '';
        ?>
        <div class="table-responsive shadow-sm border rounded">
            <table class="table table-pipeline table-hover mb-0 bg-white">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Lead / Contato</th>
                        <th width="140">Fase Funil</th>
                        <th width="150">Temperatura</th>
                        <th width="200">Mudar Etapa</th>
                        <th width="130">Valor Int.</th>
                        <th width="140">Últ. Interação</th>
                        <th width="120" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $l): 
                        $temp = $l['temperatura'] ?: 'Morno';
                        $wa_link = $l['telefone'] ? "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']) : '#';
                        $classe_temp = ($temp == 'Quente') ? 'border-danger text-danger' : (($temp == 'Morno') ? 'border-warning text-dark' : 'border-info text-info');
                    ?>
                    <tr>
                        <td class="text-muted">#<?= $l['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($l['nome']) ?></div>
                            <div class="small d-flex align-items-center gap-2 text-muted">
                                <?= htmlspecialchars($l['telefone'] ?: 'S/ Tel') ?>
                                <?php if ($l['telefone']): ?>
                                    <a href="<?= $wa_link ?>" target="_blank" class="text-success"><i class="bi bi-whatsapp"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border fw-normal" style="font-size: 0.75rem;">
                                <?= htmlspecialchars($l['fase_funil'] ?: 'Novo') ?>
                            </span>
                        </td>
                        <td>
                            <select class="form-select form-select-sm temp-select-sm <?= $classe_temp ?>" data-id="<?= $l['id'] ?>">
                                <option value="Quente" <?= $temp === 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                                <option value="Morno"  <?= $temp === 'Morno'  ? 'selected' : '' ?>>⚖️ Morno</option>
                                <option value="Frio"   <?= $temp === 'Frio'   ? 'selected' : '' ?>>❄️ Frio</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm select-next-step" data-id="<?= $l['id'] ?>">
                                <option value="">— Mover para —</option>
                                <?php foreach ($opcoes_passos as $op): ?>
                                    <option value="<?= $op ?>" <?= $l['proximo_passo'] === $op ? 'selected' : '' ?>><?= $op ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="fw-semibold">R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?></td>
                        <td>
                            <div class="small"><?= date('d/m', strtotime($l['ultima_interacao'])) ?></div>
                            <?php if (($l['dias_parado'] ?? 0) >= 3): ?>
                                <span class="badge bg-danger-subtle text-danger dias-badge" style="font-size: 0.65rem;"><?= $l['dias_parado'] ?>d parado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" title="Ver"><i class="bi bi-eye"></i></a>
                                <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="?excluir=<?= $l['id'] ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Excluir este lead?')" title="Excluir"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // Renderizar Etapas Definidas
    foreach ($opcoes_passos as $passo): 
        $lista = $leads_por_passo[$passo];
        if (count($lista) == 0) continue;
    ?>
    <div class="pipeline-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-arrow-right-circle me-2 text-primary"></i>
                <?= htmlspecialchars($passo) ?>
                <span class="badge bg-primary rounded-pill ms-2"><?= count($lista) ?></span>
            </h5>
        </div>
        <?php renderTabelaLeads($lista, $opcoes_passos); ?>
    </div>
    <?php endforeach; ?>

    <?php if (count($leads_sem_passo) > 0): ?>
    <div class="pipeline-section">
        <div class="section-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10 border-warning">
            <h5 class="mb-0 fw-bold text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Sem etapa definida
                <span class="badge bg-warning text-dark rounded-pill ms-2"><?= count($leads_sem_passo) ?></span>
            </h5>
        </div>
        <?php renderTabelaLeads($leads_sem_passo, $opcoes_passos); ?>
    </div>
    <?php endif; ?>

    <?php if(empty($leads)): ?>
        <div class="text-center py-5 shadow-sm bg-white rounded border">
            <i class="bi bi-search text-muted mb-2" style="font-size: 2rem;"></i>
            <p class="text-muted">Nenhum lead encontrado com estes filtros.</p>
            <a href="leads_ppasso.php" class="btn btn-sm btn-link">Limpar filtros</a>
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    // Atualizar Temperatura
    $(document).on('change', '.temp-select-sm', function() {
        const id = $(this).data('id');
        const temp = $(this).val();
        const $select = $(this);
        
        $.post('leads_ppasso.php', { action: 'update_temp', id: id, temp: temp }, function() {
            $select.removeClass('border-danger border-warning border-info text-danger text-dark text-info');
            if(temp === 'Quente') $select.addClass('border-danger text-danger');
            else if(temp === 'Morno') $select.addClass('border-warning text-dark');
            else $select.addClass('border-info text-info');
        });
    });

    // Atualizar Próximo Passo
    $(document).on('change', '.select-next-step', function() {
        const id = $(this).data('id');
        const step = $(this).val();
        if (step) {
            $.post('leads_ppasso.php', { action: 'update_step', id: id, step: step }, function() {
                window.location.reload();
            });
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>