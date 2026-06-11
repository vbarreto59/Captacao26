<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// EXCLUSÃO
// ==========================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM imoveis_zap WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: zap_imoveis.php?msg=excluido");
    exit;
}

// ==========================================
// SALVAR (INSERIR / ATUALIZAR)
// ==========================================
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $nome_edificio  = trim($_POST['nome_edificio'] ?? '');
    $endereco       = trim($_POST['endereco'] ?? '');
    $tipo_imovel    = $_POST['tipo_imovel'] ?? 'apartamento';
    $quartos        = (int)($_POST['quartos'] ?? 0);
    $banheiros      = (int)($_POST['banheiros'] ?? 0);
    $vagas          = (int)($_POST['vagas'] ?? 0);
    $vaga_coberta   = isset($_POST['vaga_coberta']) ? 1 : 0;
    $suites         = (int)($_POST['suites'] ?? 0);
    $andar          = trim($_POST['andar'] ?? '');
    $area_m2        = (int)($_POST['area_m2'] ?? 0);
    
    // booleanos
    $piscina                = isset($_POST['piscina']) ? 1 : 0;
    $aceita_financiamento   = isset($_POST['aceita_financiamento']) ? 1 : 0;
    $varanda                = isset($_POST['varanda']) ? 1 : 0;
    $aceita_animais         = isset($_POST['aceita_animais']) ? 1 : 0;
    $area_lazer             = isset($_POST['area_lazer']) ? 1 : 0;
    $jardim                 = isset($_POST['jardim']) ? 1 : 0;
    $central_gas            = isset($_POST['central_gas']) ? 1 : 0;
    $portaria_24h           = isset($_POST['portaria_24h']) ? 1 : 0;
    $sistema_cameras        = isset($_POST['sistema_cameras']) ? 1 : 0;
    $gerador                = isset($_POST['gerador']) ? 1 : 0;
    $pilotis                = isset($_POST['pilotis']) ? 1 : 0;
    $playground             = isset($_POST['playground']) ? 1 : 0;
    $portao_eletronico      = isset($_POST['portao_eletronico']) ? 1 : 0;
    $salao_festas           = isset($_POST['salao_festas']) ? 1 : 0;
    $aceita_parceria        = isset($_POST['aceita_parceria']) ? 1 : 0;
    $divisao_comissao       = trim($_POST['divisao_comissao'] ?? '');
    
    // valores monetários (Removendo o "R$ " antes de formatar para float)
    $preco          = str_replace('R$', '', $_POST['preco'] ?? '0');
    $preco          = str_replace(['.', ','], ['', '.'], $preco);
    $preco          = (float)$preco;

    $condominio     = str_replace('R$', '', $_POST['condominio'] ?? '0');
    $condominio     = str_replace(['.', ','], ['', '.'], $condominio);
    $condominio     = (float)$condominio;

    $iptu           = str_replace('R$', '', $_POST['iptu'] ?? '0');
    $iptu           = str_replace(['.', ','], ['', '.'], $iptu);
    $iptu           = (float)$iptu;
    
    // outros
    $corretor       = trim($_POST['corretor'] ?? '');
    $telefone       = trim($_POST['telefone'] ?? '');
    $observacoes    = trim($_POST['observacoes'] ?? '');
    $url_pagina     = trim($_POST['url_pagina'] ?? '');
    $construtora    = trim($_POST['construtora'] ?? '');
    $ano_entrega    = !empty($_POST['ano_entrega']) ? (int)$_POST['ano_entrega'] : null;

    try {
        if ($id > 0) {
            // ATUALIZA
            $sql = "UPDATE imoveis_zap SET 
                        nome_edificio=?, endereco=?, tipo_imovel=?, quartos=?, banheiros=?, vagas=?, vaga_coberta=?, suites=?, andar=?, area_m2=?,
                        piscina=?, aceita_financiamento=?, varanda=?, aceita_animais=?, area_lazer=?, jardim=?, central_gas=?,
                        portaria_24h=?, sistema_cameras=?, gerador=?, pilotis=?, playground=?, portao_eletronico=?, salao_festas=?,
                        aceita_parceria=?, divisao_comissao=?,
                        preco=?, condominio=?, iptu=?, corretor=?, telefone=?, observacoes=?, url_pagina=?, construtora=?, ano_entrega=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome_edificio, $endereco, $tipo_imovel, $quartos, $banheiros, $vagas, $vaga_coberta, $suites, $andar, $area_m2,
                           $piscina, $aceita_financiamento, $varanda, $aceita_animais, $area_lazer, $jardim, $central_gas,
                           $portaria_24h, $sistema_cameras, $gerador, $pilotis, $playground, $portao_eletronico, $salao_festas,
                           $aceita_parceria, $divisao_comissao,
                           $preco, $condominio, $iptu, $corretor, $telefone, $observacoes, $url_pagina, $construtora, $ano_entrega, $id]);
            $mensagem = "Imóvel atualizado com sucesso!";
        } else {
            // INSERE (Corrigido: adicionado o $divisao_comissao que faltava no array)
            $sql = "INSERT INTO imoveis_zap 
                        (nome_edificio, endereco, tipo_imovel, quartos, banheiros, vagas, vaga_coberta, suites, andar, area_m2,
                         piscina, aceita_financiamento, varanda, aceita_animais, area_lazer, jardim, central_gas,
                         portaria_24h, sistema_cameras, gerador, pilotis, playground, portao_eletronico, salao_festas,
                         aceita_parceria, divisao_comissao,
                         preco, condominio, iptu, corretor, telefone, observacoes, url_pagina, construtora, ano_entrega)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome_edificio, $endereco, $tipo_imovel, $quartos, $banheiros, $vagas, $vaga_coberta, $suites, $andar, $area_m2,
                           $piscina, $aceita_financiamento, $varanda, $aceita_animais, $area_lazer, $jardim, $central_gas,
                           $portaria_24h, $sistema_cameras, $gerador, $pilotis, $playground, $portao_eletronico, $salao_festas,
                           $aceita_parceria, $divisao_comissao,
                           $preco, $condominio, $iptu, $corretor, $telefone, $observacoes, $url_pagina, $construtora, $ano_entrega]);
            $mensagem = "Imóvel cadastrado com sucesso!";
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao salvar: " . $e->getMessage();
    }
}

// ==========================================
// LISTAGEM
// ==========================================
$stmt = $conn->query("SELECT * FROM imoveis_zap ORDER BY id DESC");
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// DADOS PARA EDIÇÃO
// ==========================================
$editando = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM imoveis_zap WHERE id = ?");
    $stmt->execute([$idEdit]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imóveis Pesquisados no Zap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui; }
        .card-custom { border-radius: 20px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white; padding: 15px 20px; font-weight: 600; }
        .table-custom th { background-color: #e9ecef; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .btn-sm-custom { border-radius: 30px; padding: 5px 15px; }
        .valor-destaque { font-weight: 700; color: #198754; }
        .link-zap { color: #25d366; font-size: 1.2rem; }
        .link-zap:hover { color: #128C7E; }
        .badge-comodidade { background-color: #e9ecef; color: #2c3e50; font-size: 0.7rem; margin-right: 4px; margin-bottom: 4px; }
    </style>
</head>
<body>

<div class="container py-4">
    <?php if ($mensagem): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Imóvel removido com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-custom">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-building me-2"></i> <?= $editando ? 'Editar Imóvel' : 'Novo Imóvel Pesquisado' ?></h5>
            <?php if ($editando): ?>
                <a href="zap_imoveis.php" class="btn btn-sm btn-light">+ Novo</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
            <form method="post">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome do Edifício</label>
                        <input type="text" name="nome_edificio" class="form-control" required 
                               value="<?= htmlspecialchars($editando['nome_edificio'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endereço completo</label>
                        <input type="text" name="endereco" class="form-control" required 
                               value="<?= htmlspecialchars($editando['endereco'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="tipo_imovel" class="form-select">
                            <option value="apartamento" <?= ($editando['tipo_imovel'] ?? 'apartamento') == 'apartamento' ? 'selected' : '' ?>>Apartamento</option>
                            <option value="casa" <?= ($editando['tipo_imovel'] ?? '') == 'casa' ? 'selected' : '' ?>>Casa</option>
                            <option value="flat" <?= ($editando['tipo_imovel'] ?? '') == 'flat' ? 'selected' : '' ?>>Flat</option>
                            <option value="studio" <?= ($editando['tipo_imovel'] ?? '') == 'studio' ? 'selected' : '' ?>>Studio</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Quartos</label>
                        <input type="number" name="quartos" class="form-control" min="0" value="<?= $editando['quartos'] ?? 0 ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Banheiros</label>
                        <input type="number" name="banheiros" class="form-control" min="0" value="<?= $editando['banheiros'] ?? 0 ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Vagas</label>
                        <input type="number" name="vagas" class="form-control" min="0" value="<?= $editando['vagas'] ?? 0 ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="vaga_coberta" id="vaga_coberta" <?= ($editando['vaga_coberta'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="vaga_coberta">🚗 Vaga coberta</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Suítes</label>
                        <input type="number" name="suites" class="form-control" min="0" value="<?= $editando['suites'] ?? 0 ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Andar</label>
                        <input type="text" name="andar" class="form-control" placeholder="Ex: 5º, Térreo, Cobertura" value="<?= htmlspecialchars($editando['andar'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Área (m²)</label>
                        <input type="number" name="area_m2" class="form-control" min="0" value="<?= $editando['area_m2'] ?? 0 ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Preço (R$)</label>
                        <input type="text" name="preco" class="form-control money" value="<?= isset($editando['preco']) ? number_format($editando['preco'], 2, ',', '.') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Condomínio (R$)</label>
                        <input type="text" name="condominio" class="form-control money" value="<?= isset($editando['condominio']) ? number_format($editando['condominio'], 2, ',', '.') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">IPTU (R$)</label>
                        <input type="text" name="iptu" class="form-control money" value="<?= isset($editando['iptu']) ? number_format($editando['iptu'], 2, ',', '.') : '' ?>">
                    </div>

                    <div class="col-12 mt-2">
                        <label class="form-label fw-semibold">Características e Comodidades</label>
                        <div class="row g-2">
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="piscina" id="piscina" <?= ($editando['piscina'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="piscina">🏊 Piscina</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="varanda" id="varanda" <?= ($editando['varanda'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="varanda">🏠 Varanda</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_animais" id="aceita_animais" <?= ($editando['aceita_animais'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="aceita_animais">🐕 Aceita animais</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="area_lazer" id="area_lazer" <?= ($editando['area_lazer'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="area_lazer">🎾 Área de lazer</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_financiamento" id="aceita_financiamento" <?= ($editando['aceita_financiamento'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="aceita_financiamento">💰 Financiamento</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_parceria" id="aceita_parceria" <?= ($editando['aceita_parceria'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="aceita_parceria">🤝 Aceita parceria</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="jardim" id="jardim" <?= ($editando['jardim'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="jardim">🌳 Jardim</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="central_gas" id="central_gas" <?= ($editando['central_gas'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="central_gas">⛽ Central de gás</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="portaria_24h" id="portaria_24h" <?= ($editando['portaria_24h'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="portaria_24h">🛡️ Portaria 24h</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistema_cameras" id="sistema_cameras" <?= ($editando['sistema_cameras'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="sistema_cameras">📹 Câmeras</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="gerador" id="gerador" <?= ($editando['gerador'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="gerador">🔌 Gerador</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="pilotis" id="pilotis" <?= ($editando['pilotis'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="pilotis">🏢 Pilotis</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="playground" id="playground" <?= ($editando['playground'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="playground">🧸 Playground</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="portao_eletronico" id="portao_eletronico" <?= ($editando['portao_eletronico'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="portao_eletronico">🚪 Portão eletrônico</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="salao_festas" id="salao_festas" <?= ($editando['salao_festas'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="salao_festas">🎉 Salão de festas</label></div></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Divisão da Comissão</label>
                        <input type="text" name="divisao_comissao" class="form-control" placeholder="Ex: 50/50, 60/40, a combinar" value="<?= htmlspecialchars($editando['divisao_comissao'] ?? '') ?>">
                        <small class="text-muted">Como será dividida a comissão entre os corretores</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Construtora</label>
                        <input type="text" name="construtora" class="form-control" value="<?= htmlspecialchars($editando['construtora'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Ano de Entrega</label>
                        <input type="number" name="ano_entrega" class="form-control" min="1900" max="2030" value="<?= $editando['ano_entrega'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Corretor / Contato</label>
                        <input type="text" name="corretor" class="form-control" value="<?= htmlspecialchars($editando['corretor'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telefone do corretor</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($editando['telefone'] ?? '') ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-semibold">URL do anúncio (Zap Imóveis)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" name="url_pagina" class="form-control" value="<?= htmlspecialchars($editando['url_pagina'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-semibold">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($editando['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i> Salvar Imóvel</button>
                    <?php if ($editando): ?>
                        <a href="zap_imoveis.php" class="btn btn-secondary ms-2">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="bi bi-database me-2"></i> Imóveis Pesquisados no Zap</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Edifício / Endereço</th>
                            <th>Dimensões</th>
                            <th>Preços</th>
                            <th>Comodidades</th>
                            <th>Corretor</th>
                            <th>Link</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imoveis as $im): 
                            $comodidades = [];
                            if ($im['piscina']) $comodidades[] = '🏊 Piscina';
                            if ($im['varanda']) $comodidades[] = '🏠 Varanda';
                            if ($im['aceita_animais']) $comodidades[] = '🐕 Aceita pets';
                            if ($im['area_lazer']) $comodidades[] = '🎾 Lazer';
                            if ($im['jardim']) $comodidades[] = '🌳 Jardim';
                            if ($im['central_gas']) $comodidades[] = '⛽ Gás';
                            if ($im['portaria_24h']) $comodidades[] = '🛡️ Portaria 24h';
                            if ($im['sistema_cameras']) $comodidades[] = '📹 Câmeras';
                            if ($im['gerador']) $comodidades[] = '🔌 Gerador';
                            if ($im['pilotis']) $comodidades[] = '🏢 Pilotis';
                            if ($im['playground']) $comodidades[] = '🧸 Playground';
                            if ($im['portao_eletronico']) $comodidades[] = '🚪 Portão eletrônico';
                            if ($im['salao_festas']) $comodidades[] = '🎉 Salão de festas';
                            if ($im['aceita_financiamento']) $comodidades[] = '💰 Financiamento';
                            if ($im['aceita_parceria']) $comodidades[] = '🤝 Parceria';
                        ?>
                        <tr>
                            <td><?= $im['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($im['nome_edificio']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($im['endereco']) ?></small><br>
                                <small class="text-muted"><?= ucfirst($im['tipo_imovel']) ?> • <?= $im['quartos'] ?> q • <?= $im['banheiros'] ?> b • <?= $im['vagas'] ?> v</small>
                                <?php if ($im['vaga_coberta']): ?>
                                    <span class="badge bg-success" style="font-size: 0.65rem;">Coberta</span>
                                <?php endif; ?>
                                <small class="text-muted"> • <?= $im['suites'] ?> s</small>
                                <?php if ($im['andar']): ?><small class="text-muted"> • Andar: <?= htmlspecialchars($im['andar']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?= number_format($im['area_m2'], 0, ',', '.') ?> m²<br>
                                <?php if ($im['construtora']): ?><small>Construtora: <?= htmlspecialchars($im['construtora']) ?></small><br><?php endif; ?>
                                <?php if ($im['ano_entrega']): ?><small>Entrega: <?= $im['ano_entrega'] ?></small><?php endif; ?>
                            </td>
                            <td>
                                <div><strong class="valor-destaque">R$ <?= number_format($im['preco'], 2, ',', '.') ?></strong></div>
                                <small>Cond: R$ <?= number_format($im['condominio'], 2, ',', '.') ?></small><br>
                                <small>IPTU: R$ <?= number_format($im['iptu'], 2, ',', '.') ?></small>
                                <?php if (!empty($im['divisao_comissao'])): ?>
                                    <div><small>Divisão: <?= htmlspecialchars($im['divisao_comissao']) ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap">
                                    <?php if (!empty($comodidades)): ?>
                                        <?php foreach ($comodidades as $c): ?>
                                            <span class="badge badge-comodidade me-1 mb-1"><?= $c ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($im['corretor']) ?><br>
                                <small><?= htmlspecialchars($im['telefone']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($im['url_pagina'])): ?>
                                    <a href="<?= htmlspecialchars($im['url_pagina']) ?>" target="_blank" class="link-zap"><i class="bi bi-whatsapp"></i> Zap</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?= $im['id'] ?>" class="btn btn-sm btn-outline-primary btn-sm-custom" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                <a href="?delete=<?= $im['id'] ?>" class="btn btn-sm btn-outline-danger btn-sm-custom" title="Excluir" onclick="return confirm('Excluir este imóvel?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($imoveis)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum imóvel cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.money').forEach(input => {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value === '') value = '0';
        let number = (parseInt(value) / 100).toFixed(2);
        let formatted = number.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        e.target.value = 'R$ ' + formatted;
    });
    let val = input.value.replace(/[^\d,]/g, '').replace(',', '.');
    if (!isNaN(parseFloat(val))) {
        let num = parseFloat(val).toFixed(2);
        input.value = 'R$ ' + num.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once '../../includes/footer.php'; ?>
</body>
</html>