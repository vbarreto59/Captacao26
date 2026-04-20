<?php
require_once '../../conn_cap.php';

$imovel_id = isset($_GET['imovel_id']) ? (int)$_GET['imovel_id'] : 0;

if ($imovel_id === 0) {
    echo "<div class='alert alert-danger'>Imóvel inválido.</div>";
    exit;
}

// Filtro estrito pelo imovel_id
$sql = "SELECT v.*, l.nome as lead_nome 
        FROM visitas v 
        LEFT JOIN leads l ON v.lead_id = l.id 
        WHERE v.imovel_id = ? 
        ORDER BY v.data_visita DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$imovel_id]);
$visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$visitas) {
    echo "<div class='text-center py-3 text-muted'>Nenhuma visita para este imóvel.</div>";
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Data/Hora</th>
                <th>Cliente</th>
                <th>Status</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($visitas as $v): 
                $data_f = date('d/m/Y H:i', strtotime($v['data_visita']));
                $is_futura = strtotime($v['data_visita']) > time();
            ?>
            <tr>
                <td class="small fw-bold text-nowrap"><?= $data_f ?></td>
                <td><?= htmlspecialchars($v['lead_nome'] ?? 'N/A') ?></td>
                <td><span class="badge <?= $is_futura ? 'bg-info' : 'bg-secondary' ?>"><?= $is_futura ? 'Agendada' : 'Realizada' ?></span></td>
                <td class="small text-muted"><?= htmlspecialchars($v['descricao'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>