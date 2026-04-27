<?php
// leads_funil.php - VISUALIZAÇÃO COMPLETA POR FUNIL COM HISTÓRICO RECENTE
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// CONFIGURAÇÃO DAS FASES E SUAS CORES
// ==========================================
$config_fases = [
    "Novo"                => ["cor" => "#6c757d"],
    "Tentativa de Contato"=> ["cor" => "#0dcaf0"],
    "Contato Feito"       => ["cor" => "#0d6efd"],
    "Visita Agendada"     => ["cor" => "#fd7e14"],
    "Visita Realizada"    => ["cor" => "#f5a623"],
    "Analisando"          => ["cor" => "#6610f2"],
    "Proposta"            => ["cor" => "#20c997"],
    "Fechado"             => ["cor" => "#198754"],
    "Perdido"             => ["cor" => "#dc3545"]
];

$fases_funil = array_keys($config_fases);

// ==========================================
// AJAX PARA ATUALIZAÇÃO RÁPIDA
// ==========================================
if (isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    if ($_POST['action'] == 'update_temp') {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['temp'], $id]) ? 'success' : 'error']);
    }
    if ($_POST['action'] == 'update_fase') {
        $stmt = $conn->prepare("UPDATE leads SET fase_funil = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['fase'], $id]) ? 'success' : 'error']);
    }
    exit;
}

// ==========================================
// BUSCA E FILTROS
// ==========================================
$where = "WHERE 1=1";
$params = [];
$temp_ativa = $_GET['temperatura'] ?? '';
$fase_ativa = $_GET['fase'] ?? '';

if ($temp_ativa) {
    $where .= " AND l.temperatura = ?";
    $params[] = $temp_ativa;
}

if ($fase_ativa) {
    $where .= " AND l.fase_funil = ?";
    $params[] = $fase_ativa;
}

// SQL OTIMIZADO: Busca leads e concatena os 2 últimos registros da tabela lead_historico
$sql = "SELECT l.*, DATEDIFF(NOW(), l.ultima_interacao) as dias_parado,
        (
            SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(h.data_registro, '%d/%m'), '|', h.acao, '|', IFNULL(h.detalhes, '')) SEPARATOR '||')
            FROM (
                SELECT h1.lead_id, h1.data_registro, h1.acao, h1.detalhes
                FROM lead_historico h1
                ORDER BY h1.id DESC
            ) h
            WHERE h.lead_id = l.id
            LIMIT 2
        ) as mini_historico
        FROM leads l
        $where 
        ORDER BY FIELD(l.fase_funil, '" . implode("','", $fases_funil) . "'), l.ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar resultados para o loop de fases
$leads_por_fase = [];
foreach ($fases_funil as $fase) { $leads_por_fase[$fase] = []; }
foreach ($leads as $l) {
    $f = trim($l['fase_funil'] ?? '');
    if (array_key_exists($f, $leads_por_fase)) {
        $leads_por_fase[$f][] = $l;
    }
}

require_once '../../includes/header.php';
?>

<style>
    .funnel-section { margin-bottom: 3rem; }
    .fase-header {
        padding: 12px 20px; border-radius: 8px 8px 0 0; color: white;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .table-container { background: white; border-radius: 0 0 8px 8px; border: 1px solid #dee2e6; border-top: none; }
    .table-funnel thead th { background-color: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #eee; }
    
    /* Histórico */
    .hist-container { margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e9ecef; }
    .hist-item { font-size: 0.72rem; line-height: 1.2; margin-bottom: 3px; color: #0d6efd; }
    .hist-date { color: #6c757d; font-weight: 500; font-size: 0.65rem; }
    .hist-acao { font-weight: 700; color: #0a58ca; }

    /* Temperatura */
    .temp-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .bg-Quente { background-color: #dc3545; box-shadow: 0 0 5px rgba(220,53,69,0.5); }
    .bg-Morno { background-color: #ffc107; box-shadow: 0 0 5px rgba(255,193,7,0.5); }
    .bg-Frio { background-color: #0dcaf0; box-shadow: 0 0 5px rgba(13,202,240,0.5); }

    .btn-action { padding: 5px 10px; font-size: 1.1rem; color: #6c757d; transition: all 0.2s; }
    .btn-action:hover { background-color: #f8f9fa; color: #0d6efd; border-radius: 4px; }

    /* Estilos dos Filtros */
    .filter-group { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px; }
    .filter-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #999; margin-bottom: 8px; display: block; }
</style>

<div class="container-fluid px-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Funil de Vendas</h2>
            <p class="text-muted">Acompanhe o progresso e as interações dos seus leads</p>
        </div>
        <a href="lead_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Novo Lead</a>
    </div>

    <div class="filter-group shadow-sm">
        <div class="row g-3">
            <div class="col-md-4">
                <span class="filter-label">Filtrar por Temperatura</span>
                <div class="btn-group w-100">
                    <a href="?temperatura=<?= $fase_ativa ? "&fase=$fase_ativa" : "" ?>" class="btn btn-sm <?= !$temp_ativa ? 'btn-dark' : 'btn-outline-dark' ?>">Todos</a>
                    <a href="?temperatura=Quente<?= $fase_ativa ? "&fase=$fase_ativa" : "" ?>" class="btn btn-sm <?= $temp_ativa == 'Quente' ? 'btn-danger' : 'btn-outline-danger' ?>">🔥 Quentes</a>
                    <a href="?temperatura=Morno<?= $fase_ativa ? "&fase=$fase_ativa" : "" ?>" class="btn btn-sm <?= $temp_ativa == 'Morno' ? 'btn-warning' : 'btn-outline-warning' ?>">⚖️ Mornos</a>
                    <a href="?temperatura=Frio<?= $fase_ativa ? "&fase=$fase_ativa" : "" ?>" class="btn btn-sm <?= $temp_ativa == 'Frio' ? 'btn-info' : 'btn-outline-info' ?>">❄️ Frios</a>
                </div>
            </div>

            <div class="col-md-8">
                <span class="filter-label">Filtrar por Fase</span>
                <div class="d-flex flex-wrap gap-1">
                    <a href="?fase=<?= $temp_ativa ? "&temperatura=$temp_ativa" : "" ?>" class="btn btn-xs <?= !$fase_ativa ? 'btn-dark' : 'btn-outline-dark' ?> py-1 px-2" style="font-size: 0.75rem;">Todas</a>
                    <?php foreach ($fases_funil as $f): ?>
                        <a href="?fase=<?= urlencode($f) ?><?= $temp_ativa ? "&temperatura=$temp_ativa" : "" ?>" 
                           class="btn btn-xs py-1 px-2 <?= $fase_ativa == $f ? 'btn-primary' : 'btn-outline-secondary' ?>" 
                           style="font-size: 0.75rem; border-color: <?= $config_fases[$f]['cor'] ?>; <?= $fase_ativa == $f ? "background-color: ".$config_fases[$f]['cor']."; border-color: ".$config_fases[$f]['cor'] : "color: ".$config_fases[$f]['cor'] ?>">
                            <?= $f ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php 
    $total_exibido = 0;
    foreach ($fases_funil as $fase): 
        $lista = $leads_por_fase[$fase];
        if (count($lista) == 0) continue;
        $total_exibido++;
        $cor_hex = $config_fases[$fase]['cor'];
    ?>
    <div class="funnel-section">
        <div class="fase-header" style="background-color: <?= $cor_hex ?>;">
            <h5 class="fw-bold mb-0 text-uppercase" style="font-size: 0.95rem; letter-spacing: 1px;">
                <?= $fase ?> 
                <span class="badge bg-white text-dark rounded-pill ms-2"><?= count($lista) ?></span>
            </h5>
            <div class="small fw-bold text-white">
                R$ <?= number_format(array_sum(array_column($lista, 'valor_max')), 0, ',', '.') ?>
            </div>
        </div>

        <div class="table-container shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="70" class="ps-3">Cód</th>
                            <th>Lead / Últimas Interações</th>
                            <th width="150">Temperatura</th>
                            <th width="180">Mudar Fase</th>
                            <th>Valor/Interesse</th>
                            <th class="text-center" width="120">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $l): 
                            $temp = $l['temperatura'] ?: 'Morno';
                            $hist_rows = $l['mini_historico'] ? explode('||', $l['mini_historico']) : [];
                        ?>
                        <tr>
                            <td class="ps-3 text-muted fw-bold">#<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($l['nome']) ?></div>
                                <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($l['telefone']) ?></div>
                                
                                <?php if (!empty($hist_rows)): ?>
                                <div class="hist-container">
                                    <?php foreach ($hist_rows as $row): 
                                        $p = explode('|', $row); 
                                    ?>
                                        <?php if(count($p) >= 2): ?>
                                        <div class="hist-item">
                                            <span class="hist-date"><?= $p[0] ?></span> 
                                            <span class="hist-acao"><?= htmlspecialchars($p[1]) ?>:</span> 
                                            <span><?= mb_strimwidth(htmlspecialchars($p[2] ?? ''), 0, 60, "...") ?></span>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="temp-dot bg-<?= $temp ?>"></span>
                                    <select class="form-select form-select-sm border-0 bg-light upd-temp" data-id="<?= $l['id'] ?>">
                                        <option value="Quente" <?= $temp == 'Quente' ? 'selected' : '' ?>>Quente</option>
                                        <option value="Morno"  <?= $temp == 'Morno'  ? 'selected' : '' ?>>Morno</option>
                                        <option value="Frio"   <?= $temp == 'Frio'   ? 'selected' : '' ?>>Frio</option>
                                    </select>
                                </div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm upd-fase" data-id="<?= $l['id'] ?>">
                                    <?php foreach ($fases_funil as $ff): ?>
                                        <option value="<?= $ff ?>" <?= $l['fase_funil'] == $ff ? 'selected' : '' ?>><?= $ff ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="fw-bold text-primary">R$ <?= number_format($l['valor_max'], 0, ',', '.') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($l['tipo_desejo'] ?? '') ?></div>
                            </td>
                            <td class="text-center">
                                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn-action" target="_blank" title="Visualizar Detalhes"><i class="bi bi-eye"></i></a>
                                <a href="lead_form.php?id=<?= $l['id'] ?>" class="btn-action" target="_blank" title="Editar"><i class="bi bi-pencil-square"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($total_exibido == 0): ?>
        <div class="alert alert-info text-center py-5 shadow-sm">
            <i class="bi bi-search mb-3 d-block" style="font-size: 2rem;"></i>
            Nenhum lead encontrado com os filtros aplicados.
            <br><a href="leads_funil.php" class="btn btn-sm btn-link">Limpar filtros</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.upd-temp').change(function() {
        const id = $(this).data('id');
        const temp = $(this).val();
        const dot = $(this).siblings('.temp-dot');
        
        $.post('leads_funil.php', { action: 'update_temp', id: id, temp: temp }, function(res) {
            dot.removeClass('bg-Quente bg-Morno bg-Frio').addClass('bg-' + temp);
        });
    });

    $('.upd-fase').change(function() {
        const id = $(this).data('id');
        const fase = $(this).val();
        $.post('leads_funil.php', { action: 'update_fase', id: id, fase: fase }, function(res) {
            window.location.reload();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>