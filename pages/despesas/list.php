<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$imovel_id = $_GET['imovel_id'] ?? null;

if (!$imovel_id) {
    die("Erro: ID do imóvel não fornecido. <a href='../imoveis/list.php'>Voltar</a>");
}

// --- NOVA ROTINA DE EXCLUSÃO ---
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt_del = $conn->prepare("DELETE FROM despesas WHERE id = ? AND imovel_id = ?");
    if ($stmt_del->execute([$delete_id, $imovel_id])) {
        header("Location: list.php?imovel_id=$imovel_id&msg=deleted");
        exit;
    }
}
// -------------------------------

// Busca dados do imóvel para o cabeçalho
$stmt_imovel = $conn->prepare("SELECT titulo FROM imoveis WHERE id = ?");
$stmt_imovel->execute([$imovel_id]);
$imovel = $stmt_imovel->fetch(PDO::FETCH_ASSOC);

// Busca despesas
$sql = "SELECT id, tipo, valor, data_despesa, descricao FROM despesas WHERE imovel_id = ? ORDER BY data_despesa DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$imovel_id]);
$despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_geral = array_sum(array_column($despesas, 'valor'));

require_once '../../includes/header.php';
?>

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-primary fw-bold mb-0">Despesas</h4>
            <small class="text-muted"><?= htmlspecialchars($imovel['titulo'] ?? 'Imóvel') ?></small>
        </div>
        <a href="form_despesa.php?imovel_id=<?= $imovel_id ?>" class="btn btn-success btn-sm rounded-pill px-3">
            <i class="bi bi-plus-lg"></i> Novo
        </a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Despesa excluída com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
        <div class="card-body p-3 text-center">
            <span class="small opacity-75 text-uppercase">Total de Gastos</span>
            <h2 class="mb-0 fw-bold">R$ <?= number_format($total_geral, 2, ',', '.') ?></h2>
        </div>
    </div>

    <div class="row g-3">
        <?php if (empty($despesas)): ?>
            <div class="col-12 text-center py-5 text-muted">Nenhum registro encontrado.</div>
        <?php else: ?>
            <?php foreach ($despesas as $d): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm border-start border-4 border-info">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="max-width: 60%;">
                                    <span class="badge bg-light text-dark border text-uppercase mb-1" style="font-size: 0.65rem;">
                                        <?= htmlspecialchars($d['tipo']) ?>
                                    </span>
                                    <h6 class="mb-1 fw-bold text-truncate"><?= htmlspecialchars($d['descricao']) ?: 'Sem descrição' ?></h6>
                                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($d['data_despesa'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-dark">R$ <?= number_format($d['valor'], 2, ',', '.') ?></div>
                                    <div class="mt-2">
                                        <a href="form_despesa.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-link text-warning p-0 me-2">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </a>
                                        <a href="list.php?imovel_id=<?= $imovel_id ?>&delete_id=<?= $d['id'] ?>" 
                                           class="btn btn-sm btn-link text-danger p-0"
                                           onclick="return confirm('Tem certeza que deseja excluir esta despesa?')">
                                            <i class="bi bi-trash fs-5"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="mt-4 pb-5 text-center">
        <a href="../imoveis/list.php" class="btn btn-sm btn-outline-secondary border-0">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Imóveis
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>