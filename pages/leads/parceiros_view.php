<?php
// parceiros_view.php
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php';

// CONSULTA: Incluído o campo primeiro_nome
$sql = "SELECT 
            id, nome, primeiro_nome, genero, tipo_desejo, preferencia_localizacao, 
            valor_min, valor_max, quartos_min, mobiliado, observacoes 
        FROM leads 
        WHERE compartilhado_parceiro = 1 
        ORDER BY id DESC";

$stmt = $conn->query($sql);
$leads_parceria = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads para Parceria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-lead { 
            border: none; 
            border-radius: 15px; 
            transition: transform 0.2s, box-shadow 0.2s; 
            background: #fff;
        }
        .card-lead:hover { 
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1) !important; 
        }
        .label-custom { 
            font-size: 0.7rem; 
            font-weight: 800; 
            color: #adb5bd; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .value-custom { font-size: 0.9rem; color: #495057; font-weight: 500; }
        .badge-desejo { 
            font-size: 0.75rem; 
            padding: 6px 15px; 
            border-radius: 50px; 
            font-weight: 600;
        }
        .lead-title { color: #0d6efd; letter-spacing: -0.5px; }
        .icon-section { color: #0d6efd; margin-right: 5px; }
        hr { opacity: 0.1; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row mb-5 text-center text-md-start">
        <div class="col">
            <h2 class="fw-bold text-dark"><i class="bi bi-person-hearts text-primary me-2"></i>Leads para Parceria</h2>
            <p class="text-muted">Explore perfis de interesse qualificados e encontre oportunidades de negócio.</p>
        </div>
    </div>

    <div class="row">
        <?php if (empty($leads_parceria)): ?>
            <div class="col-12 text-center py-5">
                <div class="display-1 text-muted mb-3"><i class="bi bi-inbox"></i></div>
                <h4 class="text-muted">Nenhum lead compartilhado no momento.</h4>
            </div>
        <?php else: ?>
            <?php foreach ($leads_parceria as $l): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 card-lead shadow-sm">
                        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary badge-desejo">
                                    <?= $l['tipo_desejo'] ?>
                                </span>
                                <span class="badge bg-light text-muted fw-normal">
                                    #<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body px-4">
                            <h5 class="fw-bold mb-1 lead-title text-truncate">
                                <?= htmlspecialchars($l['primeiro_nome'] ?: $l['nome']) ?>
                            </h5>
                            <p class="small text-muted mb-3">
                                <i class="bi bi-gender-ambiguous me-1"></i><?= $l['genero'] ?: 'Não informado' ?>
                            </p>
                            
                            <hr>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="label-custom"><i class="bi bi-geo-alt icon-section"></i>Localização</div>
                                    <div class="value-custom"><?= htmlspecialchars($l['preferencia_localizacao'] ?: 'Não informada') ?></div>
                                </div>

                                <div class="col-6 border-end">
                                    <div class="label-custom"><i class="bi bi-cash-stack icon-section"></i>Investimento</div>
                                    <div class="value-custom">
                                        <?= $l['valor_max'] > 0 ? 'Até R$ '.number_format($l['valor_max'], 0, ',', '.') : 'A combinar' ?>
                                    </div>
                                </div>

                                <div class="col-6 ps-3">
                                    <div class="label-custom"><i class="bi bi-door-open icon-section"></i>Quartos</div>
                                    <div class="value-custom"><?= $l['quartos_min'] ?> ou mais</div>
                                </div>

                                <div class="col-12">
                                    <div class="p-3 bg-light rounded-3">
                                        <div class="label-custom mb-1">Observações do Perfil</div>
                                        <div class="value-custom mb-0" style="font-style: italic; font-size: 0.85rem; line-height: 1.4;">
                                            "<?= nl2br(htmlspecialchars($l['observacoes'] ?: 'O parceiro não adicionou notas específicas para este perfil.')) ?>"
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0 pt-0 pb-4 px-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="small text-muted">
                                    <i class="bi bi-house-check me-1"></i><?= $l['mobiliado'] ? 'Exige mobília' : 'Indiferente' ?>
                                </span>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>