<?php
session_start();
require_once '../../conn_cap.php'; 

// --- LÓGICA DE ATRIBUIÇÃO VIA AJAX ---
if (isset($_POST['action']) && $_POST['action'] == 'atribuir_corretor') {
    header('Content-Type: application/json');
    $lead_id = (int)$_POST['lead_id'];
    $corretor_id = empty($_POST['corretor_id']) ? null : (int)$_POST['corretor_id'];

    $stmt = $conn->prepare("UPDATE leads SET corretor_id = ? WHERE id = ?");
    $ok = $stmt->execute([$corretor_id, $lead_id]);
    
    echo json_encode(['status' => $ok ? 'success' : 'error']);
    exit;
}

// --- CONSULTAS ---
$corretores = $conn->query("SELECT id, nome FROM corretores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT l.*, c.nome as nome_corretor 
        FROM leads l 
        LEFT JOIN corretores c ON l.corretor_id = c.id 
        ORDER BY l.created_at DESC";
$leads = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Função auxiliar para cores de temperatura
function getTempClass($temp) {
    switch ($temp) {
        case 'Quente': return ['bg' => 'bg-danger-subtle', 'text' => 'text-danger', 'badge' => 'bg-danger'];
        case 'Morno': return ['bg' => 'bg-warning-subtle', 'text' => 'text-warning-emphasis', 'badge' => 'bg-warning text-dark'];
        case 'Frio': return ['bg' => 'bg-info-subtle', 'text' => 'text-info-emphasis', 'badge' => 'bg-info text-white'];
        default: return ['bg' => '', 'text' => '', 'badge' => 'bg-secondary'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Atribuição - Leads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-hover tbody tr { transition: background 0.2s; }
        .currency-font { font-family: 'Courier New', Courier, monospace; font-weight: bold; }
        .unassigned { background-color: #fff4f4 !important; border-left: 5px solid #dc3545; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-person-check-fill text-primary"></i> Painel de Atribuição Estratégica</h5>
            <span class="badge bg-dark">Total: <?= count($leads) ?> leads</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr class="small text-uppercase">
                            <th class="ps-3">Lead / Contato</th>
                            <th>Temperatura / Fase</th>
                            <th>Interesse / Local</th>
                            <th>Investimento Máx.</th>
                            <th>Data Cadastro</th>
                            <th class="pe-3">Responsável Atual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): 
                            $estilo = getTempClass($lead['temperatura']);
                            $is_vago = empty($lead['corretor_id']);
                        ?>
                        <tr class="<?= $estilo['bg'] ?> <?= $is_vago ? 'unassigned' : '' ?>">
                            <td class="ps-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($lead['nome']) ?></div>
                                        <div class="small text-muted"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($lead['telefone']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $estilo['badge'] ?> mb-1 d-block" style="width: fit-content;">
                                    <?= $lead['temperatura'] ?>
                                </span>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">
                                    <i class="bi bi-funnel"></i> <?= $lead['fase_funil'] ?>
                                </small>
                            </td>
                            <td>
                                <div class="small fw-medium text-dark">
                                    <i class="bi bi-house-door"></i> <?= $lead['tipo_desejo'] ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($lead['preferencia_localizacao'] ?? 'Litoral') ?>
                                </div>
                            </td>
                            <td class="currency-font text-success">
                                R$ <?= number_format($lead['valor_maximo'] ?? 0, 2, ',', '.') ?>
                            </td>
                            <td>
                                <div class="small text-dark"><?= date('d/m/Y', strtotime($lead['created_at'])) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= date('H:i', strtotime($lead['created_at'])) ?>h</div>
                            </td>
                            <td class="pe-3">
                                <div class="d-flex align-items-center">
                                    <select class="form-select form-select-sm <?= $is_vago ? 'border-danger shadow-sm' : 'border-secondary-subtle' ?>" 
                                            onchange="atribuirCorretor(<?= $lead['id'] ?>, this.value)"
                                            style="min-width: 180px;">
                                        <option value="">-- SEM CORRETOR --</option>
                                        <?php foreach ($corretores as $cor): ?>
                                            <option value="<?= $cor['id'] ?>" <?= ($lead['corretor_id'] == $cor['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cor['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="loader-<?= $lead['id'] ?>" class="ms-2 spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                                </div>
                                <?php if($is_vago): ?>
                                    <small class="text-danger fw-bold" style="font-size: 0.7rem;">REQUER ATENÇÃO!</small>
                                <?php endif; ?>
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
<script>
function atribuirCorretor(leadId, corretorId) {
    const loader = $(`#loader-${leadId}`);
    loader.removeClass('d-none');

    $.ajax({
        url: 'listagem_leads.php',
        type: 'POST',
        data: {
            action: 'atribuir_corretor',
            lead_id: leadId,
            corretor_id: corretorId
        },
        success: function(response) {
            setTimeout(() => {
                loader.addClass('d-none');
                if (response.status === 'success') {
                    // Recarregar suavemente ou mudar cores via JS
                    location.reload(); 
                } else {
                    alert('Erro ao salvar alteração.');
                }
            }, 300);
        },
        error: function() {
            loader.addClass('d-none');
            alert('Erro na comunicação com o servidor.');
        }
    });
}
</script>

</body>
</html>