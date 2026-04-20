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
            
            mail($to, $subject, $message, $headers);
            $_SESSION['msg'] = "Agenda enviada com sucesso!";
        }
    } catch (Exception $e) { $alerta = "Erro: " . $e->getMessage(); }
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
if (!empty($_GET['lead_nome'])) { 
    $where .= " AND l.nome LIKE ?"; 
    $params[] = '%' . trim($_GET['lead_nome']) . '%'; 
}

$sql = "SELECT v.id, v.imovel_id, COALESCE(i.titulo, 'Imóvel excluído') AS imovel_titulo,
               v.data_visita, l.nome as lead_nome, l.temperatura, v.descricao, v.status
        FROM visitas v
        LEFT JOIN imoveis i ON v.imovel_id = i.id
        LEFT JOIN leads l ON v.lead_id = l.id
        $where
        ORDER BY v.data_visita DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$agora = time();

function getTempBadge($temp) {
    switch ($temp) {
        case 'Quente': return '<span class="badge bg-danger">Quente</span>';
        case 'Morno':  return '<span class="badge bg-warning text-dark">Morno</span>';
        case 'Frio':   return '<span class="badge bg-info text-dark">Frio</span>';
        default:       return '<span class="badge bg-light text-dark border">N/A</span>';
    }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<?php require_once '../../includes/header.php'; ?>

<div class="container-fluid px-3 px-md-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="text-primary fw-bold mb-0">Registro de Visitas</h2>
        </div>
        <div class="col-auto d-flex gap-2">
            <form method="post"><button type="submit" name="enviar_agenda" class="btn btn-outline-success"><i class="bi bi-send me-2"></i>Agenda</button></form>
            <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nova Visita</a>
        </div>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelaVisitas" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Data/Hora</th>
                            <th>Imóvel</th>
                            <th>Lead/Cliente</th>
                            <th>Temp.</th>
                            <th>Status</th>
                            <th>Observações</th>
                            <th class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitas as $v):
                            $ts = strtotime($v['data_visita']);
                            $passou = ($ts < $agora);
                        ?>
                        <tr class="<?= $passou ? 'table-light opacity-75' : '' ?>">
                            <td data-sort="<?= $ts ?>">
                                <span class="fw-bold <?= $passou ? 'text-muted' : 'text-primary' ?>"><?= date('d/m/Y H:i', $ts) ?></span>
                            </td>
                            <td><?= htmlspecialchars($v['imovel_titulo']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['lead_nome'] ?? 'N/A') ?></span></td>
                            <td><?= getTempBadge($v['temperatura']) ?></td>
                            <td><?= $passou ? '<span class="badge bg-secondary">Realizada</span>' : '<span class="badge bg-success">Agendada</span>' ?></td>
                            <td class="small text-muted"><?= mb_strimwidth($v['descricao'] ?? '', 0, 50, "...") ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="form.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                    <button class="btn btn-sm btn-light border text-danger btn-excluir" data-id="<?= $v['id'] ?>"><i class="bi bi-trash"></i></button>
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
    $('#tabelaVisitas').DataTable({
        "pageLength": 100, // Define 100 linhas por página
        "order": [[0, "desc"]], // Ordena inicialmente pela data (primeira coluna) decrescente
        "columnDefs": [
            { "targets": 'no-sort', "orderable": false } // Desativa ordenação na coluna de ações
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" // Tradução para Português
        }
    });

    // Modal de Exclusão
    $('.btn-excluir').on('click', function() {
        const id = $(this).data('id');
        if(confirm('Deseja realmente excluir esta visita?')) {
            window.location.href = `list.php?id_excluir=${id}`;
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>