<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// 1. Lógica de Exclusão de Visita
// ================================================
$alerta = '';
if (isset($_GET['id_excluir']) && is_numeric($_GET['id_excluir'])) {
    try {
        $id_del = (int)$_GET['id_excluir'];
        $stmt_del = $conn->prepare("DELETE FROM visitas WHERE id = ?");
        if ($stmt_del->execute([$id_del])) {
            $_SESSION['msg'] = "Visita removida com sucesso!";
            header("Location: list.php");
            exit;
        }
    } catch (Exception $e) {
        $alerta = "Erro ao excluir: " . $e->getMessage();
    }
}

// ================================================
// 2. Lógica de Envio de E-mail (Agenda)
// ================================================
if (isset($_POST['enviar_agenda'])) {
    try {
        $sql_agenda = "
            SELECT v.data_visita, i.titulo as imovel, l.nome as lead_nome, v.descricao
            FROM visitas v
            LEFT JOIN imoveis i ON v.imovel_id = i.id
            LEFT JOIN leads l ON v.lead_id = l.id
            WHERE DATE(v.data_visita) >= CURDATE()
            ORDER BY v.data_visita ASC
        ";
        $stmt_agenda = $conn->query($sql_agenda);
        $agendas = $stmt_agenda->fetchAll(PDO::FETCH_ASSOC);
        
        if ($agendas) {
            $to = "valterpb@hotmail.com";
            $subject = "Agenda de Visitas - " . date('d/m/Y');
           
            $message = "<html><body style='font-family:sans-serif;'>
                <h2 style='color:#0d6efd;'>Agenda de Visitas - Captacao2026</h2>
                <table style='width:100%; border-collapse:collapse;'>
                    <thead><tr style='background:#0d6efd;color:white;'>
                        <th style='padding:10px;text-align:left;'>Data/Hora</th>
                        <th style='padding:10px;text-align:left;'>Imóvel</th>
                        <th style='padding:10px;text-align:left;'>Lead</th>
                    </tr></thead><tbody>";
           
            foreach ($agendas as $ag) {
                $data_f = date('d/m/Y H:i', strtotime($ag['data_visita']));
                $message .= "<tr>
                    <td style='padding:10px;border-bottom:1px solid #eee;'><strong>{$data_f}</strong></td>
                    <td style='padding:10px;border-bottom:1px solid #eee;'>".htmlspecialchars($ag['imovel'])."</td>
                    <td style='padding:10px;border-bottom:1px solid #eee;'>".htmlspecialchars($ag['lead_nome'])."</td>
                </tr>";
            }
            $message .= "</tbody></table></body></html>";
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: agenda@seusistema.com.br\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['msg'] = "Agenda enviada com sucesso para $to";
            } else {
                $alerta = "Erro ao enviar e-mail.";
            }
        } else {
            $alerta = "Sem visitas agendadas para enviar.";
        }
    } catch (Exception $e) { 
        $alerta = "Erro: " . $e->getMessage(); 
    }
}

// ================================================
// 3. Filtros e Consulta Principal
// ================================================
$where = "WHERE 1=1";
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
if (!empty($_GET['lead_nome'])) { 
    $where .= " AND l.nome LIKE ?"; 
    $params[] = '%' . trim($_GET['lead_nome']) . '%'; 
}

$sql = "SELECT v.id, v.imovel_id, COALESCE(i.titulo, 'Imóvel excluído') AS imovel_titulo,
               v.data_visita, l.nome as lead_nome, v.descricao, v.status
        FROM visitas v
        LEFT JOIN imoveis i ON v.imovel_id = i.id
        LEFT JOIN leads l ON v.lead_id = l.id
        $where
        ORDER BY v.data_visita DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$agora = time();
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container-fluid px-3 px-md-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="text-primary fw-bold mb-0">Registro de Visitas</h2>
            <p class="text-muted small">Total de <strong><?= count($visitas) ?></strong> registros</p>
        </div>
        <div class="col-auto d-flex gap-2 flex-wrap">
            <form method="post">
                <button type="submit" name="enviar_agenda" class="btn btn-outline-success shadow-sm">
                    <i class="bi bi-send-check me-2"></i>Enviar Agenda
                </button>
            </form>
            <a href="form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-2"></i>Nova Visita
            </a>
        </div>
    </div>

    <?php if ($alerta): ?>
        <div class="alert alert-danger shadow-sm"><?= $alerta ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success shadow-sm alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Imóvel</label>
                    <select name="imovel_id" class="form-select">
                        <option value="">Todos os imóveis</option>
                        <?php
                        $imoveis_list = $conn->query("SELECT id, titulo FROM imoveis WHERE deleted_at IS NULL ORDER BY titulo")->fetchAll();
                        foreach ($imoveis_list as $im) {
                            $sel = (isset($_GET['imovel_id']) && $_GET['imovel_id'] == $im['id']) ? 'selected' : '';
                            echo "<option value='{$im['id']}' $sel>{$im['titulo']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Lead / Cliente</label>
                    <input type="text" name="lead_nome" class="form-control" 
                           value="<?= htmlspecialchars($_GET['lead_nome'] ?? '') ?>" 
                           placeholder="Nome do lead...">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold">De</label>
                    <input type="date" name="data_inicio" class="form-control" 
                           value="<?= $_GET['data_inicio'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold">Até</label>
                    <input type="date" name="data_fim" class="form-control" 
                           value="<?= $_GET['data_fim'] ?? '' ?>">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-dark flex-grow-1">Filtrar</button>
                    <a href="list.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">

            <!-- TABELA - DESKTOP -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Data/Hora</th>
                            <th>Imóvel</th>
                            <th>Lead/Cliente</th>
                            <th>Status</th>
                            <th>Observações</th>
                            <th width="110">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitas as $v):
                            $ts = strtotime($v['data_visita']);
                            $passou = ($ts < $agora);
                        ?>
                        <tr class="<?= $passou ? 'table-light opacity-75' : '' ?>">
                            <td class="fw-bold">
                                <span class="<?= $passou ? 'text-muted' : 'text-primary' ?>">
                                    <?= date('d/m H:i', $ts) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($v['imovel_titulo']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['lead_nome'] ?? 'N/A') ?></span></td>
                            <td>
                                <?php if($passou): ?>
                                    <span class="badge bg-secondary">Realizada</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Agendada</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= mb_strimwidth($v['descricao'] ?? '', 0, 60, "...") ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="form.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                    <button class="btn btn-sm btn-light border text-danger btn-excluir" data-id="<?= $v['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- CARDS - MOBILE -->
            <div class="d-md-none">
                <?php if (empty($visitas)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                        <p class="text-muted mt-3">Nenhuma visita encontrada.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($visitas as $v):
                        $ts = strtotime($v['data_visita']);
                        $passou = ($ts < $agora);
                    ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-bold fs-5 <?= $passou ? 'text-muted' : 'text-primary' ?>">
                                    <?= date('d/m H:i', $ts) ?>
                                </span>
                            </div>
                            <?php if($passou): ?>
                                <span class="badge bg-secondary">Realizada</span>
                            <?php else: ?>
                                <span class="badge bg-success">Agendada</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-2 fw-bold"><?= htmlspecialchars($v['imovel_titulo']) ?></div>
                        <div class="small text-muted">
                            Lead: <?= htmlspecialchars($v['lead_nome'] ?? 'Não informado') ?>
                        </div>

                        <?php if (!empty($v['descricao'])): ?>
                            <div class="mt-2 small text-muted border-start border-2 ps-2">
                                <?= htmlspecialchars($v['descricao']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="form.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <button class="btn btn-sm btn-outline-danger btn-excluir" data-id="<?= $v['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal de Exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                Tem certeza que deseja remover este registro de visita?
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <a id="btnConfirmarExcluir" href="#" class="btn btn-danger px-4">Sim, Excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-excluir').forEach(btn => {
    btn.onclick = function() {
        const id = this.dataset.id;
        document.getElementById('btnConfirmarExcluir').href = `list.php?id_excluir=${id}`;
        new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>