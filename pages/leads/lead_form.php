<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// ==========================================
// PRÓXIMO ID
// ==========================================
$proximo_id_formatado = "?";
try {
    $stmt_status = $conn->query("SHOW TABLE STATUS LIKE 'leads'");
    $status = $stmt_status->fetch(PDO::FETCH_ASSOC);
    $proximo_id_formatado = "LD" . str_pad($status['Auto_increment'], 3, '0', STR_PAD_LEFT);
} catch (Exception $e) {}

// ==========================================
// NAVEGAÇÃO
// ==========================================
$first_id = $last_id = $prev_id = $next_id = null;
try {
    $first_id = $conn->query("SELECT MIN(id) FROM leads")->fetchColumn();
    $last_id  = $conn->query("SELECT MAX(id) FROM leads")->fetchColumn();
    if ($id) {
        $stmt_prev = $conn->prepare("SELECT id FROM leads WHERE id < ? ORDER BY id DESC LIMIT 1");
        $stmt_prev->execute([$id]);
        $prev_id = $stmt_prev->fetchColumn();
        
        $stmt_next = $conn->prepare("SELECT id FROM leads WHERE id > ? ORDER BY id ASC LIMIT 1");
        $stmt_next->execute([$id]);
        $next_id = $stmt_next->fetchColumn();
    }
} catch (Exception $e) {}

// ==========================================
// DADOS
// ==========================================
$data = [
    'nome' => '', 'primeiro_nome' => '', 'email' => '', 'telefone' => '', 'genero' => '',
    'tipo_desejo' => 'Compra', 'perfil_uso' => '', 'fase_funil' => 'Novo', 'valor_max' => 0,
    'tipo_pagamento' => '', 'possui_sinal' => 0, 'quartos_min' => 0, 'tipologia' => '',
    'mobiliado' => 0, 'piscina' => 0, 'caracteristicas_condominio' => '',
    'andar_preferencia' => 'Indiferente', 'garagem_coberta' => 0, 'pe_na_areia' => 0,
    'vista_mar' => 'Nenhuma', 'proximidade_mar' => '', 'preferencia_localizacao' => '',
    'origem_lead' => 'Direto', 'observacoes' => '', 'temperatura' => 'Morno',
    'prazo_fechamento' => 'Até 3 meses',
    'varanda' => 0
];

$imoveis_selecionados = [];

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $data = $res;
        $stmt_im = $conn->prepare("SELECT imovel_id FROM lead_imoveis WHERE lead_id = ?");
        $stmt_im->execute([$id]);
        $imoveis_selecionados = $stmt_im->fetchAll(PDO::FETCH_COLUMN);
    }
}

$condos_salvos = !empty($data['caracteristicas_condominio']) ? explode(',', $data['caracteristicas_condominio']) : [];

// ==========================================
// SALVAR - CORREÇÃO DO VALOR_MAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $primeiro_nome = trim($_POST['primeiro_nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $genero = !empty($_POST['genero']) ? $_POST['genero'] : null;
    $origem = $_POST['origem_lead'] ?? 'Direto';
    $tipo_desejo = $_POST['tipo_desejo'] ?? 'Compra';
    $perfil_uso = !empty($_POST['perfil_uso']) ? $_POST['perfil_uso'] : null;
    $fase_funil = $_POST['fase_funil'] ?? 'Novo';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $temperatura = $_POST['temperatura'] ?? 'Morno';
    $prazo_fechamento = $_POST['prazo_fechamento'] ?? 'Até 3 meses';

    // ========== CORREÇÃO AQUI ==========
    // Captura o valor do campo mascarado (ex: "R$ 1.500,00") e converte para float
    $valor_max_raw = $_POST['valor_max_formatado'] ?? '0';
    // Remove tudo que não for dígito ou vírgula (elimina "R$", espaços, pontos de milhar)
    $valor_max_clean = preg_replace('/[^0-9,]/', '', $valor_max_raw);
    // Substitui vírgula decimal por ponto
    $valor_max_clean = str_replace(',', '.', $valor_max_clean);
    $valor_max = (float) $valor_max_clean;
    // ===================================

    $tipo_pagamento = !empty($_POST['tipo_pagamento']) ? $_POST['tipo_pagamento'] : null;
    $possui_sinal = isset($_POST['possui_sinal']) ? 1 : 0;
    $quartos_min = (int)($_POST['quartos_min'] ?? 0);
    $tipologia = !empty($_POST['tipologia']) ? $_POST['tipologia'] : null;

    $mobiliado = isset($_POST['mobiliado']) ? 1 : 0;
    $piscina = isset($_POST['piscina']) ? 1 : 0;
    $garagem_coberta = isset($_POST['garagem_coberta']) ? 1 : 0;
    $pe_na_areia = isset($_POST['pe_na_areia']) ? 1 : 0;
    $varanda = isset($_POST['varanda']) ? 1 : 0;

    $andar_preferencia = $_POST['andar_preferencia'] ?? 'Indiferente';
    $vista_mar = $_POST['vista_mar'] ?? 'Nenhuma';
    $proximidade_mar = !empty($_POST['proximidade_mar']) ? $_POST['proximidade_mar'] : null;
    $preferencia_localizacao = trim($_POST['preferencia_localizacao'] ?? '');

    $caracteristicas_condominio = !empty($_POST['caracteristicas_condominio']) ? implode(',', $_POST['caracteristicas_condominio']) : null;

    try {
        $conn->beginTransaction();
        if ($id) {
            $sql = "UPDATE leads SET nome=?, primeiro_nome=?, email=?, telefone=?, genero=?, origem_lead=?, tipo_desejo=?, perfil_uso=?, fase_funil=?, valor_max=?, tipo_pagamento=?, possui_sinal=?, quartos_min=?, tipologia=?, mobiliado=?, piscina=?, caracteristicas_condominio=?, andar_preferencia=?, garagem_coberta=?, pe_na_areia=?, vista_mar=?, proximidade_mar=?, preferencia_localizacao=?, observacoes=?, temperatura=?, prazo_fechamento=?, varanda=?, updated_at=NOW() WHERE id=?";
            $conn->prepare($sql)->execute([$nome, $primeiro_nome, $email, $telefone, $genero, $origem, $tipo_desejo, $perfil_uso, $fase_funil, $valor_max, $tipo_pagamento, $possui_sinal, $quartos_min, $tipologia, $mobiliado, $piscina, $caracteristicas_condominio, $andar_preferencia, $garagem_coberta, $pe_na_areia, $vista_mar, $proximidade_mar, $preferencia_localizacao, $observacoes, $temperatura, $prazo_fechamento, $varanda, $id]);
        } else {
            $sql = "INSERT INTO leads (nome, primeiro_nome, email, telefone, genero, origem_lead, tipo_desejo, perfil_uso, fase_funil, valor_max, tipo_pagamento, possui_sinal, quartos_min, tipologia, mobiliado, piscina, caracteristicas_condominio, andar_preferencia, garagem_coberta, pe_na_areia, vista_mar, proximidade_mar, preferencia_localizacao, observacoes, temperatura, prazo_fechamento, varanda, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
            $conn->prepare($sql)->execute([$nome, $primeiro_nome, $email, $telefone, $genero, $origem, $tipo_desejo, $perfil_uso, $fase_funil, $valor_max, $tipo_pagamento, $possui_sinal, $quartos_min, $tipologia, $mobiliado, $piscina, $caracteristicas_condominio, $andar_preferencia, $garagem_coberta, $pe_na_areia, $vista_mar, $proximidade_mar, $preferencia_localizacao, $observacoes, $temperatura, $prazo_fechamento, $varanda]);
            $id = $conn->lastInsertId();
            $conn->prepare("UPDATE leads SET nome = ? WHERE id = ?")->execute(["L".str_pad($id,3,'0',STR_PAD_LEFT)."-".$nome, $id]);
        }

        $conn->prepare("DELETE FROM lead_imoveis WHERE lead_id = ?")->execute([$id]);
        if (!empty($_POST['imoveis'])) {
            foreach ($_POST['imoveis'] as $im_id) {
                $conn->prepare("INSERT INTO lead_imoveis (lead_id, imovel_id) VALUES (?, ?)")->execute([$id, (int)$im_id]);
            }
        }
        $conn->commit();
        header("Location: ?id=$id&status=saved");
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $erro = "Erro: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? "Editar Lead #{$id}" : "Novo Lead" ?> - CRM Imobiliário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .card {
            border: none;
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1) !important;
        }

        .card-header {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .bg-primary-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
        }

        .bg-success-gradient {
            background: linear-gradient(135deg, var(--success-color) 0%, #146c43 100%);
        }

        .bg-info-gradient {
            background: linear-gradient(135deg, var(--info-color) 0%, #0aa2c0 100%);
        }

        .bg-warning-gradient {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e6a700 100%);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e3e9;
            transition: all 0.2s ease;
            padding: 0.6rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.1em;
            border-radius: 0.3em;
            border: 2px solid #cbd5e0;
            transition: all 0.2s;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
        }

        .amenities-group {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .imoveis-list {
            max-height: 520px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .imoveis-list::-webkit-scrollbar {
            width: 6px;
        }

        .imoveis-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .imoveis-list::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        .list-group-item {
            transition: all 0.2s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .list-group-item:hover {
            background-color: #f8f9ff;
            border-left-color: var(--primary-color);
            transform: translateX(4px);
        }

        .alert-custom {
            border-radius: 12px;
            border-left: 4px solid;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            .card-header h5 {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- ALERTA DE SUCESSO -->
    <?php if (isset($_GET['status']) && $_GET['status'] == 'saved'): ?>
        <div id="alert-success" class="alert alert-success alert-custom d-flex align-items-center shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
            <div>
                <strong>Sucesso!</strong> Lead salvo com sucesso.
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('alert-success');
                if (alert) alert.style.display = 'none';
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- CABEÇALHO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white rounded-circle p-2 shadow-sm">
                <i class="bi bi-person-badge fs-4 text-primary"></i>
            </div>
            <div>
                <h1 class="display-6 fw-bold mb-0" style="font-size: 1.8rem;">
                    <?= $id ? "Editar Lead" : "Novo Lead" ?>
                </h1>
                <?php if ($id): ?>
                    <span class="badge bg-primary mt-2 px-3 py-2 rounded-pill">
                        <i class="bi bi-hash"></i> Lead #<?= $id ?>
                    </span>
                <?php else: ?>
                    <p class="text-muted mt-2 mb-0">Próximo ID: <strong><?= $proximo_id_formatado ?></strong></p>
                <?php endif; ?>
            </div>
            
            <?php if ($id): ?>
            <div class="btn-group shadow-sm ms-3" role="group">
                <a href="?id=<?= $first_id ?>" class="btn btn-outline-secondary" title="Primeiro Lead">
                    <i class="bi bi-chevron-double-left"></i>
                </a>
                <a href="?id=<?= $prev_id ?>" class="btn btn-outline-secondary <?= !$prev_id ? 'disabled' : '' ?>" title="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="?id=<?= $next_id ?>" class="btn btn-outline-secondary <?= !$next_id ? 'disabled' : '' ?>" title="Próximo">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="?id=<?= $last_id ?>" class="btn btn-outline-secondary" title="Último Lead">
                    <i class="bi bi-chevron-double-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <?php if($id): ?>
                <a href="lead_view.php?id=<?= $id ?>" class="btn btn-info text-white shadow-sm">
                    <i class="bi bi-eye me-1"></i> Visualizar
                </a>
            <?php endif; ?>
            <a href="lead_form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Novo Lead
            </a>
            <a href="leads.php" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    <?php if(isset($erro)): ?>
        <div class="alert alert-danger alert-custom d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            <div><?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO PRINCIPAL -->
    <form method="post" id="leadForm" novalidate>
        <div class="row g-4">
            
            <!-- COLUNA ESQUERDA (Principal) -->
            <div class="col-lg-8">
                
                <!-- DADOS PESSOAIS -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary-gradient text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i> Dados Pessoais</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-person-vcard"></i> Nome Completo <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="campo_nome_completo" name="nome" class="form-control" 
                                       value="<?= htmlspecialchars($data['nome']) ?>" required
                                       placeholder="Ex: João Silva Santos">
                                <div class="invalid-feedback">Nome completo é obrigatório</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-person"></i> Primeiro Nome</label>
                                <input type="text" id="campo_primeiro_nome" name="primeiro_nome" class="form-control" 
                                       value="<?= htmlspecialchars($data['primeiro_nome']) ?>" 
                                       style="background-color: #f8f9fa;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-gender-ambiguous"></i> Gênero</label>
                                <select name="genero" class="form-select">
                                    <option value="">Não informado</option>
                                    <option value="Masculino" <?= $data['genero']=='Masculino'?'selected':'' ?>>Masculino</option>
                                    <option value="Feminino" <?= $data['genero']=='Feminino'?'selected':'' ?>>Feminino</option>
                                    <option value="Outro" <?= $data['genero']=='Outro'?'selected':'' ?>>Outro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-whatsapp"></i> WhatsApp / Telefone <span class="text-danger">*</span></label>
                                <input type="tel" name="telefone" class="form-control" 
                                       value="<?= htmlspecialchars($data['telefone']) ?>" required
                                       placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-envelope"></i> E-mail</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($data['email']) ?>"
                                       placeholder="exemplo@email.com">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PERFIL DE INTERESSE -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info-gradient text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-heart-fill me-2"></i> Perfil de Interesse</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Intenção</label>
                                <select name="tipo_desejo" class="form-select">
                                    <option value="Compra" <?= $data['tipo_desejo']=='Compra'?'selected':'' ?>>Compra</option>
                                    <option value="Aluguel" <?= $data['tipo_desejo']=='Aluguel'?'selected':'' ?>>Aluguel</option>
                                    <option value="Venda" <?= $data['tipo_desejo']=='Venda'?'selected':'' ?>>Venda</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Perfil de Uso</label>
                                <select name="perfil_uso" class="form-select">
                                    <option value="">Não definido</option>
                                    <option value="Moradia" <?= $data['perfil_uso']=='Moradia'?'selected':'' ?>>Moradia</option>
                                    <option value="Segunda Residência / Veraneio" <?= $data['perfil_uso']=='Segunda Residência / Veraneio'?'selected':'' ?>>Segunda Residência</option>
                                    <option value="Investimento (Locação por Temporada)" <?= $data['perfil_uso']=='Investimento (Locação por Temporada)'?'selected':'' ?>>Investimento (Temporada)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipologia</label>
                                <select name="tipologia" class="form-select">
                                    <option value="">Qualquer</option>
                                    <option value="Apartamento" <?= $data['tipologia']=='Apartamento'?'selected':'' ?>>Apartamento</option>
                                    <option value="Casa" <?= $data['tipologia']=='Casa'?'selected':'' ?>>Casa</option>
                                    <option value="Cobertura" <?= $data['tipologia']=='Cobertura'?'selected':'' ?>>Cobertura</option>
                                    <option value="Flat" <?= $data['tipologia']=='Flat'?'selected':'' ?>>Flat</option>
                                    <option value="Studio" <?= $data['tipologia']=='Studio'?'selected':'' ?>>Studio</option>
                                    <option value="Terreno" <?= $data['tipologia']=='Terreno'?'selected':'' ?>>Terreno</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor Máximo (R$)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">R$</span>
                                    <input type="text" id="valor_mascara" name="valor_max_formatado" class="form-control fw-bold text-success" 
                                           value="<?= number_format($data['valor_max'], 2, ',', '.') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mínimo de Quartos</label>
                                <input type="number" name="quartos_min" class="form-control" 
                                       value="<?= $data['quartos_min'] ?>" min="0" step="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Andar Preferência</label>
                                <select name="andar_preferencia" class="form-select">
                                    <option value="Indiferente" <?= $data['andar_preferencia']=='Indiferente'?'selected':'' ?>>Indiferente</option>
                                    <option value="Baixo" <?= $data['andar_preferencia']=='Baixo'?'selected':'' ?>>Baixo</option>
                                    <option value="Alto" <?= $data['andar_preferencia']=='Alto'?'selected':'' ?>>Alto</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARACTERÍSTICAS DO IMÓVEL -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success-gradient text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i> Características & Localização</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label"><i class="bi bi-geo-alt"></i> Localização / Praias Preferenciais</label>
                                <input type="text" name="preferencia_localizacao" class="form-control" 
                                       value="<?= htmlspecialchars($data['preferencia_localizacao']) ?>" 
                                       placeholder="Ex: Boa Viagem, Candeias, Porto de Galinhas">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vista do Mar</label>
                                <select name="vista_mar" class="form-select">
                                    <option value="Nenhuma" <?= $data['vista_mar']=='Nenhuma'?'selected':'' ?>>Sem Vista</option>
                                    <option value="Lateral" <?= $data['vista_mar']=='Lateral'?'selected':'' ?>>Vista Lateral</option>
                                    <option value="Definitiva / Frente" <?= $data['vista_mar']=='Definitiva / Frente'?'selected':'' ?>>Vista Total / Frente</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Proximidade do Mar</label>
                                <select name="proximidade_mar" class="form-select">
                                    <option value="">Não especificado</option>
                                    <option value="Beira-Mar" <?= $data['proximidade_mar']=='Beira-Mar'?'selected':'' ?>>Beira-Mar</option>
                                    <option value="Quadra do Mar" <?= $data['proximidade_mar']=='Quadra do Mar'?'selected':'' ?>>Quadra do Mar</option>
                                    <option value="Até 3 quadras" <?= $data['proximidade_mar']=='Até 3 quadras'?'selected':'' ?>>Até 3 quadras</option>
                                    <option value="Mais de 3 quadras" <?= $data['proximidade_mar']=='Mais de 3 quadras'?'selected':'' ?>>Mais de 3 quadras</option>
                                </select>
                            </div>

                            <!-- Exigências Específicas -->
                            <div class="col-12 mt-3">
                                <label class="form-label">Exigências Específicas:</label>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="pe_na_areia" id="pe" <?= $data['pe_na_areia']?'checked':'' ?>>
                                            <label class="form-check-label fw-semibold" for="pe">🏖️ Pé na Areia</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="piscina" id="pisc" <?= $data['piscina']?'checked':'' ?>>
                                            <label class="form-check-label fw-semibold" for="pisc">🏊 Piscina Privativa</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="garagem_coberta" id="gar" <?= $data['garagem_coberta']?'checked':'' ?>>
                                            <label class="form-check-label fw-semibold" for="gar">🚗 Garagem Coberta</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="mobiliado" id="mob" <?= $data['mobiliado']?'checked':'' ?>>
                                            <label class="form-check-label fw-semibold" for="mob">🛋️ Mobiliado</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="varanda" id="var" <?= ($data['varanda'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="var">🏠 Varanda / Sacada</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Características do Condomínio -->
                            <div class="col-12 mt-4">
                                <label class="form-label">Estrutura do Condomínio Desejada</label>
                                <div class="amenities-group">
                                    <div class="row g-3">
                                        <?php
                                        $opcoes = ['Academia', 'Salão de Festas', 'Brinquedoteca', 'Portaria 24h', 'Coworking', 'Lavanderia Compartilhada'];
                                        foreach($opcoes as $op):
                                            $checked = in_array($op, $condos_salvos) ? 'checked' : '';
                                        ?>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="caracteristicas_condominio[]" value="<?= $op ?>" <?= $checked ?> id="cond_<?= str_replace(' ', '_', $op) ?>">
                                                    <label class="form-check-label" for="cond_<?= str_replace(' ', '_', $op) ?>"><?= $op ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONDIÇÕES FINANCEIRAS -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning-gradient text-dark py-3">
                        <h5 class="mb-0"><i class="bi bi-calculator-fill me-2"></i> Condições Financeiras</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Pagamento</label>
                                <select name="tipo_pagamento" class="form-select">
                                    <option value="">Não informado</option>
                                    <option value="À Vista" <?= $data['tipo_pagamento']=='À Vista'?'selected':'' ?>>À Vista</option>
                                    <option value="Financiamento" <?= $data['tipo_pagamento']=='Financiamento'?'selected':'' ?>>Financiamento</option>
                                    <option value="FGTS 100%" <?= $data['tipo_pagamento']=='FGTS 100%'?'selected':'' ?>>100% FGTS</option>
                                    <option value="Misto" <?= $data['tipo_pagamento']=='Misto'?'selected':'' ?>>Misto</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="possui_sinal" id="sinal" <?= $data['possui_sinal']?'checked':'' ?>>
                                    <label class="form-check-label fw-bold" for="sinal">
                                        💰 Cliente possui sinal (entrada)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- OBSERVAÇÕES -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-chat-dots-fill me-2"></i> Observações Gerais</h5>
                    </div>
                    <div class="card-body p-4">
                        <textarea name="observacoes" class="form-control" rows="5" 
                                  placeholder="Anotações importantes sobre o lead..."><?= htmlspecialchars($data['observacoes']) ?></textarea>
                    </div>
                </div>

                <!-- STATUS COMERCIAL (movido para após Observações) -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="bi bi-funnel-fill me-2"></i> Status Comercial
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label">Fase do Funil</label>
                            <select name="fase_funil" class="form-select fw-semibold">
                                <?php foreach(["Novo","Contato Feito","Tentativa de Contato","Visita Agendada","Visita Realizada","Analisando","Proposta","Fechado","Perdido"] as $f): ?>
                                    <option value="<?= $f ?>" <?= $data['fase_funil']==$f?'selected':'' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Temperatura</label>
                            <select name="temperatura" class="form-select">
                                <option value="Frio" <?= $data['temperatura']=='Frio'?'selected':'' ?>>❄️ Frio</option>
                                <option value="Morno" <?= $data['temperatura']=='Morno'?'selected':'' ?>>🌡️ Morno</option>
                                <option value="Quente" <?= $data['temperatura']=='Quente'?'selected':'' ?>>🔥 Quente</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Prazo de Fechamento</label>
                            <select name="prazo_fechamento" class="form-select">
                                <option value="Imediato" <?= $data['prazo_fechamento']=='Imediato'?'selected':'' ?>>⚡ Imediato</option>
                                <option value="Até 3 meses" <?= $data['prazo_fechamento']=='Até 3 meses'?'selected':'' ?>>📅 Até 3 meses</option>
                                <option value="Até 6 meses" <?= $data['prazo_fechamento']=='Até 6 meses'?'selected':'' ?>>📅 Até 6 meses</option>
                                <option value="Apenas pesquisando" <?= $data['prazo_fechamento']=='Apenas pesquisando'?'selected':'' ?>>🔍 Pesquisando</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Origem do Lead</label>
                            <select name="origem_lead" class="form-select">
                                <?php foreach(["Direto","Instagram","Facebook","Site","WhatsApp","ZAP Imóveis","OLX","Indicação"] as $o): ?>
                                    <option value="<?= $o ?>" <?= $data['origem_lead']==$o?'selected':'' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-save w-100 text-white">
                            <i class="bi bi-save2 me-2"></i> SALVAR LEAD
                        </button>
                    </div>
                </div>

            </div>

            <!-- COLUNA DIREITA (Sidebar) -->
            <div class="col-lg-4">
                <!-- IMÓVEIS VINCULADOS -->
                <div class="card shadow">
                    <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="bi bi-house-heart me-2"></i> Imóveis Recomendados
                        </h5>
                        <span class="badge bg-primary rounded-pill px-3 py-2"><?= count($imoveis_selecionados) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="imoveis-list">
                            <?php
                            $imoveis = $conn->query("SELECT id, titulo, bairro FROM imoveis ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);
                            if(empty($imoveis)): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="bi bi-building fs-1"></i>
                                    <p class="mt-2">Nenhum imóvel cadastrado</p>
                                </div>
                            <?php else: 
                                foreach($imoveis as $im):
                                    $checked = in_array($im['id'], $imoveis_selecionados) ? 'checked' : '';
                            ?>
                                <label class="list-group-item d-flex align-items-start p-3 border-bottom" style="cursor: pointer;">
                                    <input class="form-check-input me-3 mt-1" type="checkbox" name="imoveis[]" value="<?= $im['id'] ?>" <?= $checked ?>>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($im['titulo']) ?></div>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($im['bairro']) ?>
                                        </small>
                                    </div>
                                </label>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Máscara de valor monetário
const valorInput = document.getElementById('valor_mascara');
if (valorInput) {
    valorInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value === '') value = '0';
        let number = (parseInt(value) / 100).toFixed(2);
        let formatted = number.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        e.target.value = 'R$ ' + formatted;
    });
    
    // Ajuste inicial
    let initialVal = valorInput.value.replace(/[^\d,]/g, '').replace(',', '.');
    if (!isNaN(parseFloat(initialVal))) {
        let num = parseFloat(initialVal).toFixed(2);
        valorInput.value = 'R$ ' + num.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    } else if (valorInput.value === '0,00') {
        valorInput.value = 'R$ 0,00';
    }
}

// Auto preencher primeiro nome
const nomeCompleto = document.getElementById('campo_nome_completo');
const primeiroNome = document.getElementById('campo_primeiro_nome');
if (nomeCompleto && primeiroNome) {
    nomeCompleto.addEventListener('input', function() {
        let nome = this.value.trim();
        let primeiro = nome.split(' ')[0] || '';
        primeiroNome.value = primeiro;
    });
}

// Validação do formulário
document.getElementById('leadForm')?.addEventListener('submit', function(e) {
    const nome = document.getElementById('campo_nome_completo');
    const telefone = document.querySelector('input[name="telefone"]');
    let isValid = true;
    
    if (!nome.value.trim()) {
        nome.classList.add('is-invalid');
        isValid = false;
    } else {
        nome.classList.remove('is-invalid');
    }
    
    if (!telefone.value.trim()) {
        telefone.classList.add('is-invalid');
        isValid = false;
    } else {
        telefone.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Por favor, preencha os campos obrigatórios: Nome Completo e Telefone.');
    }
});

// Tooltips automáticos para ícones
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
</body>
</html>