<?php
// leads.php - PIPELINE POR PRÓXIMO PASSO (Somente etapas com leads)
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
// FILTROS
// ==========================================
$where = "WHERE 1=1";
$params = [];

if (!empty($_GET['temperatura'])) {
    $where .= " AND temperatura = ?";
    $params[] = $_GET['temperatura'];
}

$sql = "SELECT *, DATEDIFF(NOW(), ultima_interacao) as dias_parado 
        FROM leads $where 
        ORDER BY ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por Próximo Passo
$leads_por_passo = [];
foreach ($opcoes_passos as $passo) {
    $leads_por_passo[$passo] = [];
}
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
    .pipeline-section {
        margin-bottom: 2.8rem;
    }
    .section-header {
        background: linear-gradient(90deg, #f8f9fa, #ffffff);
        border-left: 6px solid #0d6efd;
        padding: 14px 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .lead-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .lead-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
    }
    .temp-select {
        border-radius: 50px;
        font-weight: 600;
        padding: 10px 16px;
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-1">Pipeline de Leads</h2>
            <p class="text-muted mb-0">Total de leads: <strong><?= count($leads) ?></strong></p>
        </div>
        <a href="lead_form.php" class="btn btn-primary btn-lg shadow-sm px-4">
            <i class="bi bi-plus-lg me-2"></i>Novo Lead
        </a>
    </div>

    <!-- Filtros -->
    <div class="mb-5">
        <div class="d-flex gap-2 flex-wrap">
            <a href="leads_ppasso.php" class="btn btn-outline-secondary <?= empty($_GET['temperatura']) ? 'active' : '' ?>">Todos</a>
            <a href="?temperatura=Quente" class="btn btn-danger <?= ($_GET['temperatura'] ?? '') === 'Quente' ? 'active' : '' ?>">🔥 Quentes</a>
            <a href="?temperatura=Morno"  class="btn btn-warning <?= ($_GET['temperatura'] ?? '') === 'Morno'  ? 'active' : '' ?>">⚖️ Mornos</a>
            <a href="?temperatura=Frio"   class="btn btn-info <?= ($_GET['temperatura'] ?? '') === 'Frio'   ? 'active' : '' ?>">❄️ Frios</a>
        </div>
    </div>

    <!-- Pipeline - Apenas etapas com leads -->
    <?php foreach ($opcoes_passos as $passo): 
        $lista = $leads_por_passo[$passo];
        $quantidade = count($lista);
        
        if ($quantidade == 0) continue; // ← Não exibe seção vazia
    ?>
    <div class="pipeline-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <?= htmlspecialchars($passo) ?>
                <span class="badge bg-primary rounded-pill ms-3"><?= $quantidade ?></span>
            </h5>
            <small class="text-muted"><?= $quantidade ?> lead(s)</small>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($lista as $l):
                $temp = $l['temperatura'] ?: 'Morno';
                $wa_link = $l['telefone'] ? "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']) : '#';
            ?>
            <div class="col">
                <div class="card lead-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-bold fs-5"><?= htmlspecialchars($l['nome']) ?></div>
                            <span class="badge bg-light text-dark">#<?= $l['id'] ?></span>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-2 mb-3">
                            <small class="text-muted"><?= htmlspecialchars($l['telefone'] ?? 'Sem telefone') ?></small>
                            <?php if ($l['telefone']): ?>
                                <a href="<?= $wa_link ?>" target="_blank" class="text-success fs-5">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <select class="form-select temp-select" data-id="<?= $l['id'] ?>">
                            <option value="Quente" <?= $temp === 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                            <option value="Morno"  <?= $temp === 'Morno'  ? 'selected' : '' ?>>⚖️ Morno</option>
                            <option value="Frio"   <?= $temp === 'Frio'   ? 'selected' : '' ?>>❄️ Frio</option>
                        </select>

                        <div class="mt-3">
                            <small class="text-muted">Próximo Passo</small>
                            <select class="form-select form-select-sm select-next-step mt-1" data-id="<?= $l['id'] ?>">
                                <option value="">— Alterar etapa —</option>
                                <?php foreach ($opcoes_passos as $op): ?>
                                    <option value="<?= $op ?>" <?= $l['proximo_passo'] === $op ? 'selected' : '' ?>><?= $op ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mt-4 small">
                            <div class="col-6">
                                <small class="text-muted d-block">Valor</small>
                                <span class="fw-bold">R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?></span>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block">Interação</small>
                                <span><?= date('d/m/Y', strtotime($l['ultima_interacao'])) ?></span>
                                <?php if (($l['dias_parado'] ?? 0) >= 3): ?>
                                    <small class="text-danger d-block">(<?= $l['dias_parado'] ?> dias)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light d-flex justify-content-between align-items-center pt-3">
                        <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                        <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-dark">Editar</a>
                        <a href="?excluir=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este lead?')">Excluir</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Leads sem próximo passo definido -->
    <?php if (count($leads_sem_passo) > 0): ?>
    <div class="pipeline-section">
        <div class="section-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10 border-warning">
            <h5 class="mb-0 fw-semibold text-warning">
                ⚠️ Sem próximo passo definido
                <span class="badge bg-warning rounded-pill ms-3"><?= count($leads_sem_passo) ?></span>
            </h5>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($leads_sem_passo as $l):
                $temp = $l['temperatura'] ?: 'Morno';
                $wa_link = $l['telefone'] ? "https://wa.me/55" . preg_replace('/\D/', '', $l['telefone']) : '#';
            ?>
            <div class="col">
                <div class="card lead-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-bold fs-5"><?= htmlspecialchars($l['nome']) ?></div>
                            <span class="badge bg-light text-dark">#<?= $l['id'] ?></span>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-2 mb-3">
                            <small class="text-muted"><?= htmlspecialchars($l['telefone'] ?? 'Sem telefone') ?></small>
                            <?php if ($l['telefone']): ?>
                                <a href="<?= $wa_link ?>" target="_blank" class="text-success fs-5"><i class="bi bi-whatsapp"></i></a>
                            <?php endif; ?>
                        </div>

                        <select class="form-select temp-select" data-id="<?= $l['id'] ?>">
                            <option value="Quente" <?= $temp === 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                            <option value="Morno"  <?= $temp === 'Morno'  ? 'selected' : '' ?>>⚖️ Morno</option>
                            <option value="Frio"   <?= $temp === 'Frio'   ? 'selected' : '' ?>>❄️ Frio</option>
                        </select>

                        <div class="mt-3">
                            <small class="text-muted">Próximo Passo</small>
                            <select class="form-select form-select-sm select-next-step mt-1" data-id="<?= $l['id'] ?>">
                                <option value="">— Escolher etapa —</option>
                                <?php foreach ($opcoes_passos as $op): ?>
                                    <option value="<?= $op ?>"><?= $op ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mt-4 small">
                            <div class="col-6">
                                <small class="text-muted d-block">Valor</small>
                                <span class="fw-bold">R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?></span>
                            </div>
                            <div class="col-6 text-end">
                                <span><?= date('d/m/Y', strtotime($l['ultima_interacao'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light d-flex justify-content-between align-items-center pt-3">
                        <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                        <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-dark">Editar</a>
                        <a href="?excluir=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este lead?')">Excluir</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    $(document).on('change', '.temp-select', function() {
        const id = $(this).data('id');
        const temp = $(this).val();
        $.post('leads_ppasso.php', { action: 'update_temp', id: id, temp: temp });
    });

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