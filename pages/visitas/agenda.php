<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================
// 1. INCLUIR COMPROMISSO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_compromisso'])) {
    $titulo = trim($_POST['titulo']);
    $desc = trim($_POST['descricao']);
    $data = $_POST['data_evento'];
    $cat = $_POST['categoria'];
    $stmt = $conn->prepare("INSERT INTO agenda_geral (titulo, descricao, data_evento, categoria) VALUES (?, ?, ?, ?)");
    $stmt->execute([$titulo, $desc, $data, $cat]);
    header("Location: agenda.php?msg=add_ok");
    exit;
}

// 2. EDITAR COMPROMISSO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_compromisso'])) {
    $id_comp = $_POST['id'];
    $titulo = trim($_POST['titulo']);
    $desc = trim($_POST['descricao']);
    $data = $_POST['data_evento'];
    $cat = $_POST['categoria'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE agenda_geral SET titulo=?, descricao=?, data_evento=?, categoria=?, status=? WHERE id=?");
    $stmt->execute([$titulo, $desc, $data, $cat, $status, $id_comp]);
    header("Location: agenda.php?msg=edit_ok");
    exit;
}

// 3. EXCLUIR COMPROMISSO
if (isset($_GET['excluir'])) {
    $stmt = $conn->prepare("DELETE FROM agenda_geral WHERE id = ?");
    $stmt->execute([(int)$_GET['excluir']]);
    header("Location: agenda.php?msg=del_ok");
    exit;
}

// ==========================================
// BUSCA DE DADOS
// ==========================================
$lista = $conn->query("SELECT * FROM agenda_geral ORDER BY data_evento ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0"><i class="bi bi-journal-check me-2"></i>Agenda Geral</h2>
            <p class="text-muted small mb-0">Compromissos administrativos e pessoais</p>
        </div>
        <button class="btn btn-primary shadow-sm btn-lg w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bi bi-plus-lg me-2"></i>Novo Compromisso
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <?php 
                if($_GET['msg'] == 'add_ok') echo 'Compromisso adicionado com sucesso!';
                if($_GET['msg'] == 'edit_ok') echo 'Compromisso atualizado com sucesso!';
                if($_GET['msg'] == 'del_ok') echo 'Compromisso excluído com sucesso!';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-body p-0">

            <!-- TABELA - DESKTOP -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th width="160">Data / Hora</th>
                            <th>Título / Descrição</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th class="text-end pe-3" width="140">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum compromisso agendado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($lista as $c):
                            $is_passado = (strtotime($c['data_evento']) < time() && $c['status'] == 'Pendente');
                            $cor_status = ($c['status'] == 'Concluído') ? 'success' : (($c['status'] == 'Cancelado') ? 'danger' : 'warning');
                        ?>
                        <tr class="<?= $c['status'] == 'Concluído' ? 'opacity-50' : '' ?>">
                            <td>
                                <div class="fw-bold <?= $is_passado ? 'text-danger' : '' ?>">
                                    <?= date('d/m/Y', strtotime($c['data_evento'])) ?>
                                </div>
                                <small class="badge bg-light text-dark border"><?= date('H:i', strtotime($c['data_evento'])) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($c['titulo']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($c['descricao']) ?></small>
                            </td>
                            <td><span class="badge bg-info-subtle text-info border"><?= htmlspecialchars($c['categoria']) ?></span></td>
                            <td><span class="badge bg-<?= $cor_status ?>"><?= $c['status'] ?></span></td>
                            <td class="text-end pe-3">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-white border" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $c['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-white border text-danger" onclick="return confirm('Excluir este compromisso?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- CARDS - MOBILE -->
            <div class="d-md-none">
                <?php if (empty($lista)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                        <p class="text-muted mt-3">Nenhum compromisso agendado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lista as $c):
                        $is_passado = (strtotime($c['data_evento']) < time() && $c['status'] == 'Pendente');
                        $cor_status = ($c['status'] == 'Concluído') ? 'success' : (($c['status'] == 'Cancelado') ? 'danger' : 'warning');
                    ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold fs-5 <?= $is_passado ? 'text-danger' : '' ?>">
                                    <?= date('d/m/Y', strtotime($c['data_evento'])) ?> 
                                    <small class="text-muted"><?= date('H:i', strtotime($c['data_evento'])) ?></small>
                                </div>
                            </div>
                            <span class="badge bg-<?= $cor_status ?>"><?= $c['status'] ?></span>
                        </div>

                        <div class="mt-2 fw-bold"><?= htmlspecialchars($c['titulo']) ?></div>
                        <?php if (!empty($c['descricao'])): ?>
                            <small class="text-muted d-block"><?= htmlspecialchars($c['descricao']) ?></small>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge bg-info-subtle text-info border"><?= htmlspecialchars($c['categoria']) ?></span>
                            
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $c['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este compromisso?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ====================== MODAL ADICIONAR ====================== -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Novo Compromisso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Título da Ação</label>
                    <input type="text" name="titulo" class="form-control" placeholder="Ex: Reunião com Gerente" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-bold small">Data e Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-bold small">Categoria</label>
                        <select name="categoria" class="form-select">
                            <option>Administrativo</option>
                            <option>Pessoal</option>
                            <option>Treinamento</option>
                            <option>Outros</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label fw-bold small">Descrição / Notas</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Opcional"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="add_compromisso" class="btn btn-primary">Agendar</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================== MODAIS DE EDIÇÃO ====================== -->
<?php foreach ($lista as $c):
    $cor_status = ($c['status'] == 'Concluído') ? 'success' : (($c['status'] == 'Cancelado') ? 'danger' : 'warning');
?>
<div class="modal fade" id="modalEdit<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Editar Compromisso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Título</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($c['titulo']) ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-bold small">Data/Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" 
                               value="<?= date('Y-m-d\TH:i', strtotime($c['data_evento'])) ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-bold small">Categoria</label>
                        <select name="categoria" class="form-select">
                            <option <?= $c['categoria'] == 'Administrativo' ? 'selected' : '' ?>>Administrativo</option>
                            <option <?= $c['categoria'] == 'Pessoal' ? 'selected' : '' ?>>Pessoal</option>
                            <option <?= $c['categoria'] == 'Treinamento' ? 'selected' : '' ?>>Treinamento</option>
                            <option <?= $c['categoria'] == 'Outros' ? 'selected' : '' ?>>Outros</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="Pendente" <?= $c['status'] == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="Concluído" <?= $c['status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                        <option value="Cancelado" <?= $c['status'] == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-bold small">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($c['descricao']) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="edit_compromisso" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<style>
    .btn-white { background: #fff; }
    .table-light { background-color: #f8f9fa; }
</style>

<?php require_once '../../includes/footer.php'; ?>