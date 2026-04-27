<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Define fuso horário para Recife
date_default_timezone_set('America/Recife');
$hoje_inicio = date('Y-m-d 00:00:00');

// ==========================================
// LÓGICA DE PROCESSAMENTO (POST/GET)
// ==========================================

// 0. ENVIAR AGENDA POR EMAIL
if (isset($_POST['enviar_email_agenda'])) {
    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
    $subject = "Agenda Geral - " . strtoupper($_SESSION['Usuario'] ?? 'SISTEMA') . " - " . date('d/m/Y');
    
    $sql_email = "
        SELECT data_evento, titulo, descricao FROM agenda_geral WHERE status = 'Pendente'
        UNION ALL
        SELECT data_visita as data_evento, CONCAT('VISITA: ', visitante) as titulo, descricao 
        FROM visitas WHERE status = 'pendente' AND data_visita >= ?
        ORDER BY data_evento ASC";
    
    $stmt_email = $conn->prepare($sql_email);
    $stmt_email->execute([$hoje_inicio]);
    $compromissos = $stmt_email->fetchAll(PDO::FETCH_ASSOC);
    
    $message = "AGENDA GERAL - RESUMO DE PENDÊNCIAS\n";
    $message .= "Data: " . date('d/m/Y H:i:s') . "\n------------------------------------------\n\n";
    
    if (empty($compromissos)) {
        $message .= "Nenhum compromisso pendente.";
    } else {
        foreach ($compromissos as $item) {
            $message .= "Data: " . date('d/m/Y H:i', strtotime($item['data_evento'])) . "\n";
            $message .= "Título: " . $item['titulo'] . "\n";
            $message .= "Descrição: " . ($item['descricao'] ?: "-") . "\n";
            $message .= "------------------------------------------\n";
        }
    }
    
    $headers = "From: sendmail@gabnetweb.com.br\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    mail($to, $subject, $message, $headers);
    header("Location: agenda.php?msg=email_ok");
    exit;
}

// 1. INCLUIR (Agenda Geral)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_compromisso'])) {
    $stmt = $conn->prepare("INSERT INTO agenda_geral (titulo, descricao, data_evento, categoria) VALUES (?, ?, ?, ?)");
    $stmt->execute([trim($_POST['titulo']), trim($_POST['descricao']), $_POST['data_evento'], $_POST['categoria']]);
    header("Location: agenda.php?msg=add_ok");
    exit;
}

// 2. EDITAR (Agenda Geral)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_compromisso'])) {
    $stmt = $conn->prepare("UPDATE agenda_geral SET titulo=?, descricao=?, data_evento=?, categoria=?, status=? WHERE id=?");
    $stmt->execute([trim($_POST['titulo']), trim($_POST['descricao']), $_POST['data_evento'], $_POST['categoria'], $_POST['status'], $_POST['id']]);
    header("Location: agenda.php?msg=edit_ok");
    exit;
}

// 3. EXCLUIR UNIFICADO (Agenda ou Visitas)
if (isset($_GET['excluir']) && isset($_GET['origem'])) {
    $id = (int)$_GET['excluir'];
    $origem = $_GET['origem'];

    if ($origem === 'agenda') {
        $stmt = $conn->prepare("DELETE FROM agenda_geral WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($origem === 'visitas') {
        $stmt = $conn->prepare("DELETE FROM visitas WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    header("Location: agenda.php?msg=del_ok");
    exit;
}

// ==========================================
// BUSCA DE DADOS UNIFICADA
// ==========================================
$sql_unificado = "
    SELECT id, data_evento, titulo, descricao, status, categoria, 'agenda' as origem 
    FROM agenda_geral
    UNION ALL
    SELECT id, data_visita as data_evento, visitante as titulo, descricao, 
           CASE WHEN status = 'pendente' THEN 'Pendente' WHEN status = 'concluido' THEN 'Concluído' ELSE 'Cancelado' END as status, 
           'Visita' as categoria, 'visitas' as origem 
    FROM visitas
    WHERE data_visita >= ?
    ORDER BY data_evento ASC";

$stmt = $conn->prepare($sql_unificado);
$stmt->execute([$hoje_inicio]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0"><i class="bi bi-calendar3 me-2"></i>Agenda Geral</h2>
            <p class="text-muted small mb-0">Compromissos manuais e Visitas (Hoje + Futuro)</p>
        </div>
        <div class="d-flex gap-2">
            <form method="post">
                <button type="submit" name="enviar_email_agenda" class="btn btn-outline-secondary shadow-sm">
                    <i class="bi bi-envelope"></i> Resumo E-mail
                </button>
            </form>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
                <i class="bi bi-plus-lg"></i> Novo Compromisso
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Data / Hora</th>
                            <th>Categoria</th>
                            <th>Compromisso (Lead)</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $c): 
                            $cor = ($c['status'] == 'Concluído') ? 'success' : (($c['status'] == 'Cancelado') ? 'danger' : 'warning');
                            $is_visita = ($c['origem'] == 'visitas');
                        ?>
                        <tr class="<?= $is_visita ? 'table-info' : '' ?>" style="<?= $is_visita ? '--bs-table-bg: #f0f8ff;' : '' ?>">
                            <td class="ps-3">
                                <div class="fw-bold"><?= date('d/m/y', strtotime($c['data_evento'])) ?></div>
                                <small class="text-muted"><?= date('H:i', strtotime($c['data_evento'])) ?></small>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $is_visita ? 'bg-info text-dark' : 'bg-secondary' ?> px-3">
                                    <i class="bi <?= $is_visita ? 'bi-person' : 'bi-tag' ?> me-1"></i><?= $c['categoria'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= $is_visita ? '<span class="text-primary small text-uppercase">Visita:</span> ' : '' ?>
                                    <?= htmlspecialchars($c['titulo']) ?>
                                </div>
                                <div class="small text-muted text-truncate" style="max-width: 350px;">
                                    <?= !empty($c['descricao']) ? htmlspecialchars($c['descricao']) : '<span class="opacity-50 italic small">- sem descrição -</span>' ?>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= $cor ?>"><?= $c['status'] ?></span></td>
                            <td class="text-end pe-3">
                                <div class="btn-group shadow-sm">
                                    <?php if (!$is_visita): ?>
                                        <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $c['id'] ?>" title="Editar"><i class="bi bi-pencil text-primary"></i></button>
                                    <?php else: ?>
                                        <a href="../visitas/visitas.php" class="btn btn-sm btn-light border" title="Ver Visitas"><i class="bi bi-eye text-info"></i></a>
                                    <?php endif; ?>
                                    
                                    <a href="?excluir=<?= $c['id'] ?>&origem=<?= $c['origem'] ?>" 
                                       class="btn btn-sm btn-light border text-danger" 
                                       onclick="return confirm('Tem certeza que deseja excluir este registro permanente?')" 
                                       title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </a>
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

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Novo Compromisso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small fw-bold">Data/Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select">
                            <option>Administrativo</option>
                            <option>Pessoal</option>
                            <option>Outros</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_compromisso" class="btn btn-primary w-100">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($lista as $c): if($c['origem'] == 'agenda'): ?>
<div class="modal fade" id="modalEdit<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Editar Compromisso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Título</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($c['titulo']) ?>" required>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small fw-bold">Data/Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($c['data_evento'])) ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Pendente" <?= $c['status'] == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="Concluído" <?= $c['status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                            <option value="Cancelado" <?= $c['status'] == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($c['descricao']) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_compromisso" class="btn btn-primary w-100">Atualizar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; endforeach; ?>

<?php require_once '../../includes/footer.php'; ?>