<?php
// lead_requirements.php - Exigências dos Leads (formato texto, sem telefone)
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Buscar todos os leads (exceto soft delete, se houver, mas vamos pegar todos)
$sql = "SELECT 
            l.id,
            l.nome,
            l.email,
            l.tipo_desejo,
            l.perfil_uso,
            l.valor_max,
            l.quartos_min,
            l.tipologia,
            l.mobiliado,
            l.piscina,
            l.garagem_coberta,
            l.pe_na_areia,
            l.varanda,
            l.vista_mar,
            l.proximidade_mar,
            l.preferencia_localizacao,
            l.andar_preferencia,
            l.caracteristicas_condominio,
            l.observacoes,
            l.fase_funil,
            l.temperatura,
            l.prazo_fechamento,
            l.origem_lead,
            l.created_at
        FROM leads l
        ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

//require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads - Relatório</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .report-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .lead-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 6px solid #0d6efd;
        }
        .lead-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .lead-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e3c72;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .badge-phase {
            font-size: 0.75rem;
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 12px;
            border-radius: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 14px;
            font-size: 0.9rem;
        }
        .info-label {
            font-weight: 700;
            color: #2c3e50;
            display: inline-block;
            width: 130px;
        }
        .info-value {
            color: #1e466e;
        }
        .comodidades-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .comodidade-badge {
            background: #e7f1ff;
            color: #0a58ca;
            border-radius: 30px;
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .text-observacao {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 12px;
            margin-top: 15px;
            font-style: italic;
        }
        .print-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 1000;
            border-radius: 60px;
            padding: 12px 24px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        @media print {
            .print-btn, .header-actions, .navbar, footer, .btn {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .lead-card {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ddd;
                box-shadow: none;
            }
            .report-header {
                background: #1e3c72;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Cabeçalho do relatório -->
    <div class="report-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="display-6 fw-bold mb-2">
                <i class="bi bi-clipboard-data me-2"></i> Leads Valter Barreto
            </h1>
            <p class="mb-0 opacity-75">
                Relatório completo de necessidades e preferências dos clientes (sem telefone)
            </p>
            <small class="text-light-emphasis">Total de leads: <?= count($leads) ?></small>
        </div>
        <div class="mt-3 mt-md-0">
            <i class="bi bi-file-text fs-1 opacity-50"></i>
        </div>
    </div>

    <!-- Listagem de Leads -->
    <?php if (empty($leads)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <h4>Nenhum lead cadastrado ainda</h4>
            <p>Comece adicionando leads para visualizar as exigências.</p>
        </div>
    <?php else: ?>
        <?php foreach ($leads as $lead): 
            // Decodificar características do condomínio
            $condo_features = !empty($lead['caracteristicas_condominio']) ? explode(',', $lead['caracteristicas_condominio']) : [];
        ?>
        <div class="lead-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="lead-title">
                    <i class="bi bi-person-circle me-2 text-primary"></i>
                    <?= htmlspecialchars($lead['nome']) ?>
                    <span class="badge-phase ms-3">
                        <!-- <i class="bi bi-funnel"></i> <?= htmlspecialchars($lead['fase_funil'] ?? 'Novo') ?> -->
                    </span>
                     <span class="badge-phase ms-1">
                        <?php 
                        $tempIcon = match($lead['temperatura']) {
                            'Quente' => '🔥',
                            'Morno' => '🌡️',
                            'Frio' => '❄️',
                            default => '⚪'
                        };
                        //echo $tempIcon . ' ' . ($lead['temperatura'] ?? 'Morno');
                        ?>
                    </span> 
                </div>
                <!-- <small class="text-muted">
                    <i class="bi bi-calendar3"></i> Cadastro: <?= date('d/m/Y', strtotime($lead['created_at'])) ?>
                </small>  -->
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-envelope"></i> E-mail:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['email'] ?: 'Não informado') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-coin"></i> Orçamento máx.:</span>
                    <span class="info-value">R$ <?= number_format($lead['valor_max'], 0, ',', '.') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-door-open"></i> Quartos mín.:</span>
                    <span class="info-value"><?= $lead['quartos_min'] ?: 'Não especificado' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-building"></i> Tipologia:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['tipologia'] ?: 'Qualquer') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-cart-check"></i> Intenção:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['tipo_desejo'] ?: 'Não definido') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-people"></i> Perfil de uso:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['perfil_uso'] ?: 'Não definido') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-geo-alt"></i> Localização:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['preferencia_localizacao'] ?: 'Não informada') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-water"></i> Proximidade mar:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['proximidade_mar'] ?: 'Indiferente') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-binoculars"></i> Vista mar:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['vista_mar'] ?: 'Nenhuma') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-layers"></i> Andar pref.:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['andar_preferencia'] ?: 'Indiferente') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-calendar-check"></i> Prazo fechamento:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['prazo_fechamento'] ?: 'Não informado') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-tag"></i> Origem lead:</span>
                    <span class="info-value"><?= htmlspecialchars($lead['origem_lead'] ?: 'Direto') ?></span>
                </div>
            </div>

            <!-- Características específicas (checkboxes) -->
            <div class="mt-3">
                <span class="fw-semibold text-secondary">Exigências específicas:</span>
                <div class="comodidades-list mt-1">
                    <?php if ($lead['pe_na_areia']): ?>
                        <span class="comodidade-badge">🏖️ Pé na areia</span>
                    <?php endif; ?>
                    <?php if ($lead['piscina']): ?>
                        <span class="comodidade-badge">🏊 Piscina</span>
                    <?php endif; ?>
                    <?php if ($lead['garagem_coberta']): ?>
                        <span class="comodidade-badge">🚗 Garagem coberta</span>
                    <?php endif; ?>
                    <?php if ($lead['mobiliado']): ?>
                        <span class="comodidade-badge">🛋️ Mobiliado</span>
                    <?php endif; ?>
                    <?php if ($lead['varanda']): ?>
                        <span class="comodidade-badge">🏠 Varanda/Sacada</span>
                    <?php endif; ?>
                    <?php if (empty($lead['pe_na_areia']) && empty($lead['piscina']) && empty($lead['garagem_coberta']) && empty($lead['mobiliado']) && empty($lead['varanda'])): ?>
                        <span class="text-muted small">Nenhuma exigência específica marcada</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Características do condomínio -->
            <?php if (!empty($condo_features)): ?>
            <div class="mt-3">
                <span class="fw-semibold text-secondary">Estrutura do condomínio desejada:</span>
                <div class="comodidades-list mt-1">
                    <?php foreach ($condo_features as $feature): ?>
                        <span class="comodidade-badge"><?= htmlspecialchars(trim($feature)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Observações gerais -->
            <?php if (!empty($lead['observacoes'])): ?>
            <div class="text-observacao">
                <i class="bi bi-chat-left-quote me-2 text-warning"></i>
                <strong>Observações:</strong><br>
                <!-- <?= nl2br(htmlspecialchars($lead['observacoes'])) ?> -->
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Botão flutuante para imprimir -->
<button class="btn btn-primary print-btn" onclick="window.print();">
    <i class="bi bi-printer-fill me-2"></i> Imprimir / Salvar PDF
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php //require_once '../../includes/footer.php'; ?>
</body>
</html>