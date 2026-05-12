<?php
require_once '../../conn_cap.php';

// 1. Validar se o código de acesso foi passado na URL
$codigo = $_GET['status'] ?? ''; 

if (empty($codigo)) {
    die("<div class='alert alert-danger m-3'>Acesso negado: Código não fornecido.</div>");
}

// 2. Buscar o corretor dono desse código
$stmtCorretor = $conn->prepare("SELECT id, nome FROM corretores WHERE codigo_acesso = ? LIMIT 1");
$stmtCorretor->execute([$codigo]);
$corretor = $stmtCorretor->fetch(PDO::FETCH_ASSOC);

if (!$corretor) {
    die("<div class='alert alert-danger m-3'>Acesso negado: Código de acesso inválido.</div>");
}

$id_corretor = $corretor['id'];
$nome_corretor = $corretor['nome'];

// 3. Buscar os leads vinculados
$sqlLeads = "SELECT primeiro_nome, genero, tipo_desejo, preferencia_localizacao, valor_max, quartos_min, mobiliado, observacoes, created_at, temperatura 
             FROM leads 
             WHERE corretor_id = ? 
             ORDER BY created_at DESC";
$stmtLeads = $conn->prepare($sqlLeads);
$stmtLeads->execute([$id_corretor]);
$leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Disponíveis - <?= htmlspecialchars($nome_corretor) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-lead { border: none; border-radius: 10px; border-top: 4px solid #dee2e6; }
        .temp-Quente { border-top-color: #dc3545; }
        .temp-Morno { border-top-color: #ffc107; }
        .temp-Frio { border-top-color: #0dcaf0; }
        .label-custom { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: bold; }
        .info-value { font-size: 0.95rem; color: #212529; font-weight: 500; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Olá, <?= htmlspecialchars($nome_corretor) ?></h4>
            <span class="text-muted small">Lista de leads atribuídos para você</span>
        </div>
        <div class="text-end text-muted small">
            <?= date('d/m/Y') ?>
        </div>
    </div>

    <div class="row">
        <?php if (count($leads) > 0): ?>
            <?php foreach ($leads as $lead): ?>
                <div class="col-12 col-md-6 mb-4">
                    <div class="card card-lead shadow-sm h-100 temp-<?= $lead['temperatura'] ?>">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="label-custom">Interessado</div>
                                    <div class="info-value text-primary"><?= htmlspecialchars($lead['primeiro_nome']) ?></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="label-custom">Gênero</div>
                                    <div class="info-value"><?= $lead['genero'] ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="label-custom">Tipo de Desejo</div>
                                    <div class="info-value"><i class="bi bi-tag"></i> <?= $lead['tipo_desejo'] ?></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="label-custom">Valor Máx.</div>
                                    <div class="info-value text-success">R$ <?= number_format($lead['valor_max'], 2, ',', '.') ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="label-custom">Localização</div>
                                    <div class="info-value"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($lead['preferencia_localizacao'] ?? 'Litoral') ?></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="label-custom">Mobiliado</div>
                                    <div class="info-value"><?= $lead['mobiliado'] ? 'Sim' : 'Não' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="label-custom">Quartos Mín.</div>
                                    <div class="info-value"><?= $lead['quartos_min'] ?> quarto(s)</div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="label-custom">Data Entrada</div>
                                    <div class="info-value" style="font-size: 0.8rem;"><?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?></div>
                                </div>
                            </div>

                            <?php if (!empty($lead['observacoes'])): ?>
                            <div class="mt-3 p-2 bg-light rounded">
                                <div class="label-custom">Observações</div>
                                <div class="small text-muted italic">"<?= nl2br(htmlspecialchars($lead['observacoes'])) ?>"</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">Nenhum lead encontrado para este código.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>