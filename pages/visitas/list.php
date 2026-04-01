<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Filtros
// ================================================
$where = "";
$params = [];

if (!empty($_GET['imovel_id'])) {
    $where .= " AND v.imovel_id = ?";
    $params[] = (int)$_GET['imovel_id'];
}

if (!empty($_GET['data_inicio'])) {
    $where .= " AND DATE(v.data_visita) >= ?";
    $params[] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $where .= " AND DATE(v.data_visita) <= ?";
    $params[] = $_GET['data_fim'];
}

if (!empty($_GET['visitante'])) {
    $busca = '%' . trim($_GET['visitante']) . '%';
    $where .= " AND v.visitante LIKE ?";
    $params[] = $busca;
}

if ($where !== "") {
    $where = "WHERE " . substr($where, 5); // remove primeiro " AND "
}

// Consulta principal - SEM created_at
$sql = "
    SELECT 
        v.id,
        v.imovel_id,
        COALESCE(i.titulo, 'Imóvel excluído') AS imovel_titulo,
        v.data_visita,
        v.visitante,
        v.observacoes
    FROM visitas v
    LEFT JOIN imoveis i ON v.imovel_id = i.id
    $where
    ORDER BY v.data_visita DESC, v.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="text-primary">Registro de Visitas</h2>
        <p class="text-muted">Histórico completo de visitas aos imóveis</p>
    </div>
    <div class="col-auto">
        <a href="form.php" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle-fill me-2"></i>Nova Visita
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Imóvel</label>
                <select name="imovel_id" class="form-select">
                    <option value="">Todos os imóveis</option>
                    <?php
                    $imoveis = $conn->query("SELECT id, titulo FROM imoveis WHERE deleted_at IS NULL ORDER BY titulo")->fetchAll();
                    foreach ($imoveis as $im) {
                        $selected = (isset($_GET['imovel_id']) && $_GET['imovel_id'] == $im['id']) ? 'selected' : '';
                        echo "<option value='{$im['id']}' $selected>" . htmlspecialchars($im['titulo']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Visitante</label>
                <input type="text" name="visitante" class="form-control" 
                       value="<?= htmlspecialchars($_GET['visitante'] ?? '') ?>" 
                       placeholder="Nome do visitante">
            </div>

            <div class="col-md-2">
                <label class="form-label">Data inicial</label>
                <input type="date" name="data_inicio" class="form-control" 
                       value="<?= $_GET['data_inicio'] ?? '' ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Data final</label>
                <input type="date" name="data_fim" class="form-control" 
                       value="<?= $_GET['data_fim'] ?? '' ?>">
            </div>

            <div class="col-12 text-end mt-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="list.php" class="btn btn-outline-secondary ms-2">Limpar Filtros</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tabelaVisitas" class="table table-hover table-striped mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>Data e Hora</th>
                        <th>Imóvel</th>
                        <th>Visitante</th>
                        <th>Observações</th>
                        <th width="100">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitas)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Nenhuma visita registrada ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visitas as $v): ?>
                        <tr>
                            <td class="fw-bold">
                                <?= date('d/m/Y H:i', strtotime($v['data_visita'])) ?>
                            </td>
                            <td><?= htmlspecialchars($v['imovel_titulo']) ?></td>
                            <td class="fw-medium"><?= htmlspecialchars($v['visitante'] ?? 'Não informado') ?></td>
                            <td>
                                <?= nl2br(htmlspecialchars(substr($v['observacoes'] ?? '', 0, 120))) ?>
                                <?= strlen($v['observacoes'] ?? '') > 120 ? '...' : '' ?>
                            </td>
                            <td>
                                <a href="form.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger btn-excluir" 
                                        data-id="<?= $v['id'] ?>" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Deseja realmente excluir este registro de visita?<br>
                <strong>Esta ação não pode ser desfeita.</strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a id="btnConfirmarExcluir" href="#" class="btn btn-danger">Excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaVisitas').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json' },
        order: [[0, 'desc']],
        pageLength: 25
    });

    $('.btn-excluir').on('click', function() {
        const id = $(this).data('id');
        $('#btnConfirmarExcluir').attr('href', `delete.php?id=${id}`);
        new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>