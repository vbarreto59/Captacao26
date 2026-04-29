<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Determina se é novo ou edição
// ================================================
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar' : 'Novo';

// ====================== EXCLUSÃO DO IMÓVEL ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir' && $id > 0) {
    try {
        $stmt = $conn->prepare("UPDATE imoveis SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: list.php?msg=excluido");
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao excluir imóvel: " . $e->getMessage();
    }
}

// ====================== GERENCIAMENTO DE FOTOS ======================
// Exclusão de foto individual (GET)
if (isset($_GET['delete_foto']) && is_numeric($_GET['delete_foto']) && $id > 0) {
    $foto_id = (int)$_GET['delete_foto'];
    // Busca o caminho da foto
    $stmt = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE id = ? AND imovel_id = ?");
    $stmt->execute([$foto_id, $id]);
    $foto = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($foto) {
        $caminho_arquivo = "../../uploads/fotos_imoveis/" . $foto['caminho'];
        if (file_exists($caminho_arquivo)) unlink($caminho_arquivo);
        $conn->prepare("DELETE FROM fotos_imoveis WHERE id = ?")->execute([$foto_id]);
        
        // Se a foto excluída era a capa, define a primeira foto restante como capa
        $stmt = $conn->prepare("SELECT capa FROM fotos_imoveis WHERE id = ?");
        $stmt->execute([$foto_id]);
        $era_capa = $stmt->fetchColumn();
        if ($era_capa) {
            $stmt = $conn->prepare("SELECT id FROM fotos_imoveis WHERE imovel_id = ? ORDER BY ordem ASC LIMIT 1");
            $stmt->execute([$id]);
            $nova_capa = $stmt->fetchColumn();
            if ($nova_capa) {
                $conn->prepare("UPDATE fotos_imoveis SET capa = 1 WHERE id = ?")->execute([$nova_capa]);
            }
        }
    }
    header("Location: form.php?id=$id&msg=foto_excluida");
    exit;
}

// Definição de foto como capa (GET)
if (isset($_GET['set_capa']) && is_numeric($_GET['set_capa']) && $id > 0) {
    $foto_id = (int)$_GET['set_capa'];
    // Remove capa de todas as fotos do imóvel
    $conn->prepare("UPDATE fotos_imoveis SET capa = 0 WHERE imovel_id = ?")->execute([$id]);
    // Seta a nova capa
    $conn->prepare("UPDATE fotos_imoveis SET capa = 1 WHERE id = ? AND imovel_id = ?")->execute([$foto_id, $id]);
    header("Location: form.php?id=$id&msg=capa_alterada");
    exit;
}

// Inicialização de todos os campos (Valores Padrão para evitar erros de undefined)
$imovel = [
    'proprietario_id' => '', 'titulo' => '', 'endereco' => '', 'bairro' => '', 'cidade' => '',
    'estado' => 'PE', 'cep' => '', 'latitude' => '', 'longitude' => '', 'preco' => 0.00,
    'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'area' => 0, 'vagas_garagem' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'apartamento', 
    'construtora' => '', 'ano_entrega' => '', 
    'descricao' => '', 'status' => 'captado',
    'mobiliado' => 0, 'gas_encanado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0,
    'tem_salao_festas' => 0, 'tem_espaco_gourmet' => 0, 'tem_playground' => 0,
    'possui_elevador' => 0, 'possui_moveis_planejados' => 0, 
    'agua_inclusa_condominio' => 0, 'gas_incluso_condominio' => 0,
    'valor_condominio' => 0.00, 'valor_iptu' => 0.00,
    'contato_sindico' => '', 'contato_portaria' => '', 
    'link_site' => '', 'resposta_rapida' => '', 'observacoes_gerais' => '',
    'rip_marinha' => '', 'regime_marinha' => 'nenhum', 'valor_foro_anual' => 0.00, 'laudemio_pago' => 0,
    'aceita_financiamento' => 0, 'aceita_fgts' => 0, 'aceita_permuta' => 0, 'aceita_consorcio' => 0,
    'valor_sinal' => 0.00
];

$erro = '';
$sucesso = isset($_GET['msg']) && ($_GET['msg'] == 'sucesso' || $_GET['msg'] == 'foto_excluida' || $_GET['msg'] == 'capa_alterada');
$msg_texto = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'sucesso') $msg_texto = 'Dados salvos com sucesso!';
    elseif ($_GET['msg'] == 'foto_excluida') $msg_texto = 'Foto excluída com sucesso!';
    elseif ($_GET['msg'] == 'capa_alterada') $msg_texto = 'Foto de capa alterada!';
}

// Carrega dados do banco se for edição (antes de qualquer POST)
if ($id > 0 && empty($_POST)) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) { $imovel = array_merge($imovel, $row); }
}

// Processa o formulário ao salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
    
    // Validação básica
    $titulo = trim($_POST['titulo'] ?? '');
    if (empty($titulo)) {
        $erro = "O campo Título é obrigatório.";
    } else {
        // Monta array de dados
        $dados = [
            'proprietario_id' => !empty($_POST['proprietario_id']) ? (int)$_POST['proprietario_id'] : null,
            'titulo' => $titulo,
            'endereco' => trim($_POST['endereco'] ?? ''),
            'bairro' => trim($_POST['bairro'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => trim($_POST['estado'] ?? 'PE'),
            'cep' => trim($_POST['cep'] ?? ''),
            'latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
            'longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
            'preco' => (float)str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? 0),
            'quartos' => (int)($_POST['quartos'] ?? 0),
            'suites' => (int)($_POST['suites'] ?? 0),
            'banheiros' => (int)($_POST['banheiros'] ?? 0),
            'area' => (float)($_POST['area'] ?? 0),
            'vagas_garagem' => (int)($_POST['vagas_garagem'] ?? 0),
            'andar' => !empty($_POST['andar']) ? (int)$_POST['andar'] : null,
            'face' => $_POST['face'] ?? 'nascente',
            'tipo' => $_POST['tipo'] ?? 'apartamento',
            'construtora' => trim($_POST['construtora'] ?? ''),
            'ano_entrega' => !empty($_POST['ano_entrega']) ? (int)$_POST['ano_entrega'] : null,
            'descricao' => trim($_POST['descricao'] ?? ''),
            'status' => $_POST['status'] ?? 'captado',
            'link_site' => trim($_POST['link_site'] ?? ''),
            'resposta_rapida' => trim($_POST['resposta_rapida'] ?? ''),
            'observacoes_gerais' => trim($_POST['observacoes_gerais'] ?? ''),
            'mobiliado' => isset($_POST['mobiliado']) ? 1 : 0,
            'gas_encanado' => isset($_POST['gas_encanado']) ? 1 : 0,
            'tem_piscina' => isset($_POST['tem_piscina']) ? 1 : 0,
            'tem_academia' => isset($_POST['tem_academia']) ? 1 : 0,
            'tem_salao_festas' => isset($_POST['tem_salao_festas']) ? 1 : 0,
            'tem_espaco_gourmet' => isset($_POST['tem_espaco_gourmet']) ? 1 : 0,
            'tem_playground' => isset($_POST['tem_playground']) ? 1 : 0,
            'possui_elevador' => isset($_POST['possui_elevador']) ? 1 : 0,
            'possui_moveis_planejados' => isset($_POST['possui_moveis_planejados']) ? 1 : 0,
            'agua_inclusa_condominio' => isset($_POST['agua_inclusa_condominio']) ? 1 : 0,
            'gas_incluso_condominio' => isset($_POST['gas_incluso_condominio']) ? 1 : 0,
            'valor_condominio' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_condominio'] ?? 0),
            'valor_iptu' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_iptu'] ?? 0),
            'contato_sindico' => trim($_POST['contato_sindico'] ?? ''),
            'contato_portaria' => trim($_POST['contato_portaria'] ?? ''),
            'rip_marinha' => trim($_POST['rip_marinha'] ?? ''),
            'regime_marinha' => $_POST['regime_marinha'] ?? 'nenhum',
            'valor_foro_anual' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_foro_anual'] ?? 0),
            'laudemio_pago' => isset($_POST['laudemio_pago']) ? 1 : 0,
            'aceita_financiamento' => isset($_POST['aceita_financiamento']) ? 1 : 0,
            'aceita_fgts' => isset($_POST['aceita_fgts']) ? 1 : 0,
            'aceita_permuta' => isset($_POST['aceita_permuta']) ? 1 : 0,
            'aceita_consorcio' => isset($_POST['aceita_consorcio']) ? 1 : 0,
            'valor_sinal' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_sinal'] ?? 0)
        ];

        if (empty($erro)) {
            try {
                if ($id > 0) {
                    // UPDATE
                    $set = "";
                    foreach ($dados as $key => $val) { $set .= "$key = ?, "; }
                    $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
                    $conn->prepare($sql)->execute([...array_values($dados), $id]);
                } else {
                    // INSERT
                    $cols = implode(", ", array_keys($dados));
                    $plds = implode(", ", array_fill(0, count($dados), "?"));
                    $sql = "INSERT INTO imoveis ($cols) VALUES ($plds)";
                    $conn->prepare($sql)->execute(array_values($dados));
                    $id = $conn->lastInsertId();
                }
                
                // ========== UPLOAD DE FOTOS (inline) ==========
                if (!empty($_FILES['fotos']['name'][0])) {
                    $target_dir = "../../uploads/fotos_imoveis/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    // Pega a maior ordem atual
                    $ordem = $conn->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM fotos_imoveis WHERE imovel_id = ?");
                    $ordem->execute([$id]);
                    $proxima_ordem = $ordem->fetchColumn();
                    
                    $arquivos = $_FILES['fotos'];
                    $total = count($arquivos['name']);
                    for ($i = 0; $i < $total; $i++) {
                        if ($arquivos['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $ext = strtolower(pathinfo($arquivos['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
                        
                        $nome_unico = uniqid() . "_" . time() . "." . $ext;
                        $destino = $target_dir . $nome_unico;
                        
                        if (move_uploaded_file($arquivos['tmp_name'][$i], $destino)) {
                            // Verifica se é a primeira foto do imóvel
                            $contar = $conn->prepare("SELECT COUNT(*) FROM fotos_imoveis WHERE imovel_id = ?");
                            $contar->execute([$id]);
                            $primeira = ($contar->fetchColumn() == 0);
                            $capa = $primeira ? 1 : 0;
                            
                            $stmt_foto = $conn->prepare("INSERT INTO fotos_imoveis (imovel_id, caminho, capa, ordem) VALUES (?, ?, ?, ?)");
                            $stmt_foto->execute([$id, $nome_unico, $capa, $proxima_ordem++]);
                        }
                    }
                }
                
                header("Location: form.php?id=$id&msg=sucesso");
                exit;
            } catch (PDOException $e) { 
                $erro = "Erro ao salvar: " . $e->getMessage(); 
            }
        }
    }
    
    // Em caso de erro, repopula $imovel com os dados enviados
    if (!empty($erro)) {
        foreach ($dados as $key => $value) {
            if (array_key_exists($key, $imovel)) {
                $imovel[$key] = $value;
            }
        }
    }
}

// Busca as fotos do imóvel (para exibição na galeria)
$fotos = [];
if ($id > 0) {
    $stmt_fotos = $conn->prepare("SELECT id, caminho, capa, ordem FROM fotos_imoveis WHERE imovel_id = ? ORDER BY ordem ASC");
    $stmt_fotos->execute([$id]);
    $fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-primary m-0"><?= $modo ?> Imóvel</h2>
            <small class="text-muted">Cadastro técnico detalhado</small>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-info text-dark p-2">Versão 2026.1</span>
            <?php if($id > 0): ?>
                <form method="POST" onsubmit="return confirm('Excluir este imóvel permanentemente?')">
                    <input type="hidden" name="acao" value="excluir">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if($sucesso && $msg_texto): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= $msg_texto ?></div>
    <?php endif; ?>
    
    <?php if(!empty($erro)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3" id="formImovel">
        
        <!-- Bloco de Identificação -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Título do Anúncio *</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required placeholder="Ex: Edf. Maria Julia - 3 qts - Beira Mar">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status de Captação</label>
                        <select name="status" class="form-select fw-bold text-primary">
                            <option value="captado" <?= $imovel['status']=='captado'?'selected':'' ?>>Captado / Ativo</option>
                            <option value="vendido" <?= $imovel['status']=='vendido'?'selected':'' ?>>Vendido / Inativo</option>
                            <option value="suspenso" <?= $imovel['status']=='suspenso'?'selected':'' ?>>Suspenso</option>
                        </select>
                    </div>
<div class="col-md-6">
    <label class="form-label fw-bold">Proprietário</label>
    <select name="proprietario_id" class="form-select" required>
        <option value="">Selecione um proprietário...</option>
        <?php
        // A busca poderia estar no topo do arquivo, mas mantendo aqui:
        $queryProps = "SELECT id, nome FROM proprietarios WHERE deleted_at IS NULL ORDER BY nome";
        $props = $conn->query($queryProps)->fetchAll();

        foreach($props as $p): 
            $selected = ($imovel['proprietario_id'] == $p['id']) ? 'selected' : '';
            // Usando htmlspecialchars para segurança total
            $nome = htmlspecialchars($p['nome']);
            echo "<option value='{$p['id']}' {$selected}>{$nome}</option>";
        endforeach;
        ?>
    </select>
</div>
                </div>
            </div>
        </div>

        <!-- Localização -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt"></i> Localização</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">CEP</label><input type="text" name="cep" class="form-control js-cep" value="<?= htmlspecialchars($imovel['cep']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Endereço</label><input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Cidade</label><input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($imovel['cidade']) ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label small">Coordenadas (Cole aqui: Lat, Long)</label>
                        <input type="text" id="lat_field" class="form-control form-control-sm" 
                               value="<?= (!empty($imovel['latitude']) && !empty($imovel['longitude'])) ? $imovel['latitude'].', '.$imovel['longitude'] : '' ?>" 
                               placeholder="-8.123, -34.888">
                        <input type="hidden" name="latitude" id="hidden_lat" value="<?= $imovel['latitude'] ?>">
                        <input type="hidden" name="longitude" id="hidden_lng" value="<?= $imovel['longitude'] ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- NOVA SEÇÃO: FOTOS DO IMÓVEL (gerenciamento completo) -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-images"></i> Fotos do Imóvel</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Adicionar novas fotos</label>
                        <input type="file" name="fotos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
                        <small class="text-muted">Você pode selecionar várias fotos. Formatos: JPG, PNG, WEBP (máx. 5MB cada). A primeira foto enviada será automaticamente a capa, a menos que você altere depois.</small>
                    </div>

                    <?php if ($id > 0 && count($fotos) > 0): ?>
                        <hr>
                        <label class="form-label fw-bold">Galeria atual (<?= count($fotos) ?> fotos)</label>
                        <div class="row g-3 mt-1" id="galeria-fotos">
                            <?php foreach ($fotos as $foto): 
                                $caminho_imagem = "../../uploads/fotos_imoveis/" . $foto['caminho'];
                                if (!file_exists($caminho_imagem)) continue;
                            ?>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <img src="<?= $caminho_imagem ?>" class="card-img-top" style="height: 180px; object-fit: cover; border-radius: 8px 8px 0 0;">
                                        <div class="card-body p-2 text-center">
                                            <?php if ($foto['capa'] == 1): ?>
                                                <span class="badge bg-primary mb-2"><i class="bi bi-star-fill"></i> Foto de Capa</span>
                                            <?php else: ?>
                                                <a href="?id=<?= $id ?>&set_capa=<?= $foto['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 mb-1" onclick="return confirm('Definir esta foto como capa?')">
                                                    <i class="bi bi-star"></i> Definir como Capa
                                                </a>
                                            <?php endif; ?>
                                            <a href="?id=<?= $id ?>&delete_foto=<?= $foto['id'] ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Excluir esta foto permanentemente?')">
                                                <i class="bi bi-trash"></i> Excluir
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($id > 0 && count($fotos) == 0): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-camera fs-1"></i>
                            <p>Nenhuma foto cadastrada ainda. Envie as imagens acima.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detalhes e Descrição (mantido igual) -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold">Ficha Técnica do Imóvel</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Área Útil (m²)</label>
                        <input type="number" name="area" class="form-control" value="<?= $imovel['area'] ?>" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quartos</label>
                        <input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Suítes</label>
                        <input type="number" name="suites" class="form-control" value="<?= $imovel['suites'] ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Banheiros</label>
                        <input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Vagas</label>
                        <input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Face</label>
                        <select name="face" class="form-select">
                            <option value="nascente" <?= $imovel['face']=='nascente'?'selected':'' ?>>Nascente</option>
                            <option value="norte" <?= $imovel['face']=='norte'?'selected':'' ?>>Norte</option>
                            <option value="sul" <?= $imovel['face']=='sul'?'selected':'' ?>>Sul</option>
                            <option value="poente" <?= $imovel['face']=='poente'?'selected':'' ?>>Poente</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Descrição Completa</label>
                        <textarea name="descricao" class="form-control" rows="6"><?= htmlspecialchars($imovel['descricao']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Financeira (mantido igual) -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-light mb-3">
                <div class="card-header fw-bold text-uppercase">Valores</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Preço de Venda</label>
                        <input type="text" name="preco" class="form-control form-control-lg fw-bold text-primary js-money" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Condomínio (R$)</label>
                        <input type="text" name="valor_condominio" class="form-control js-money mb-2" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                        <div class="d-flex gap-2 p-2 bg-white border rounded">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="agua_inclusa_condominio" id="agua_inc" <?= $imovel['agua_inclusa_condominio']?'checked':'' ?>>
                                <label class="form-check-label small fw-bold text-primary" for="agua_inc">Água Inc.</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="gas_incluso_condominio" id="gas_inc" <?= $imovel['gas_incluso_condominio']?'checked':'' ?>>
                                <label class="form-check-label small fw-bold text-danger" for="gas_inc">Gás Inc.</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">IPTU (Anual)</label>
                        <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>">
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header fw-bold">Diferenciais</div>
                <div class="card-body">
                    <?php
                    $diferenciais = [
                        'tem_piscina' => 'Piscina Adulto/Inf.',
                        'tem_academia' => 'Academia Equipada',
                        'tem_salao_festas' => 'Salão de Festas',
                        'tem_espaco_gourmet' => 'Espaço Gourmet',
                        'tem_playground' => 'Playground',
                        'possui_elevador' => 'Elevador Social/Serv.',
                        'possui_moveis_planejados' => 'Móveis Planejados',
                        'gas_encanado' => 'Gás Encanado (Infra)',
                        'mobiliado' => 'Mobiliado (Porteira Fechada)'
                    ];
                    foreach($diferenciais as $key => $label): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $imovel[$key]?'checked':'' ?>>
                            <label class="form-check-label small" for="<?= $key ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Marinha e Condições de Fechamento (mantido igual) -->
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-4 border-warning mb-3">
                <div class="card-header bg-white fw-bold">Documentação / Terreno de Marinha</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Regime Patrimonial</label>
                        <select name="regime_marinha" class="form-select">
                            <option value="nenhum" <?= $imovel['regime_marinha']=='nenhum'?'selected':'' ?>>Nenhum / Próprio</option>
                            <option value="ocupacao" <?= $imovel['regime_marinha']=='ocupacao'?'selected':'' ?>>Ocupação (Marinha)</option>
                            <option value="aforamento" <?= $imovel['regime_marinha']=='aforamento'?'selected':'' ?>>Aforamento (Marinha)</option>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Número RIP</label><input type="text" name="rip_marinha" class="form-control" value="<?= $imovel['rip_marinha'] ?>" placeholder="0000.000..."></div>
                    <div class="col-md-3"><label class="form-label">Foro Anual (R$)</label><input type="text" name="valor_foro_anual" class="form-control js-money" value="<?= number_format($imovel['valor_foro_anual'], 2, ',', '.') ?>"></div>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="laudemio_pago" id="lau" <?= $imovel['laudemio_pago']?'checked':'' ?>><label class="form-check-label fw-bold" for="lau">Laudêmio está Pago?</label></div></div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-4 border-success mb-5">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Condições de Pagamento Aceitas</span>
                    <span class="badge bg-danger">Sinal de reserva é obrigatório</span>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-4 mt-2">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_financiamento" id="f" <?= $imovel['aceita_financiamento']?'checked':'' ?>><label class="form-check-label" for="f">Financiamento Bancário</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_fgts" id="fg" <?= $imovel['aceita_fgts']?'checked':'' ?>><label class="form-check-label" for="fg">Uso do FGTS</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_permuta" id="p" <?= $imovel['aceita_permuta']?'checked':'' ?>><label class="form-check-label" for="p">Aceita Permuta</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_consorcio" id="c" <?= $imovel['aceita_consorcio']?'checked':'' ?>><label class="form-check-label" for="c">Consórcio</label></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border border-success rounded bg-light shadow-sm">
                            <label class="form-label fw-bold text-success">Valor do Sinal Sugerido (R$)</label>
                            <input type="text" name="valor_sinal" class="form-control form-control-lg fw-bold text-success js-money" value="<?= number_format($imovel['valor_sinal'], 2, ',', '.') ?>">
                            <small class="text-muted d-block mt-1">Geralmente 10% a 20% do valor.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-center bg-white border-top p-4 fixed-bottom shadow-lg">
            <a href="list.php" class="btn btn-light border px-5 me-2">Voltar à Lista</a>
            <button type="submit" class="btn btn-primary btn-lg px-5 shadow">GRAVAR DADOS DO IMÓVEL</button>
        </div>
    </form>
</div>

<!-- SCRIPTS DE MÁSCARA E LÓGICA -->
<script>
// Máscara Financeira Brasileira (BRL)
function formatarMoeda(e) {
    let valor = e.target.value.replace(/\D/g, "");
    valor = (valor / 100).toFixed(2) + "";
    valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    e.target.value = valor;
}
document.querySelectorAll('.js-money').forEach(el => el.addEventListener('input', formatarMoeda));

// Máscara CEP
document.querySelectorAll('.js-cep').forEach(el => {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, "");
        if (v.length > 5) v = v.replace(/^(\d{5})(\d)/, "$1-$2");
        e.target.value = v.substring(0, 9);
    });
});

// Processamento rápido de Coordenadas do Google Maps
const latField = document.getElementById('lat_field');
if (latField) {
    latField.addEventListener('input', function(e) {
        const val = e.target.value;
        if (val.includes(',')) {
            const parts = val.split(',');
            const lat = parts[0].trim();
            const lng = parts[1].trim();
            if(!isNaN(lat) && !isNaN(lng)) {
                document.getElementById('hidden_lat').value = lat;
                document.getElementById('hidden_lng').value = lng;
                e.target.classList.add('is-valid');
                e.target.classList.remove('is-invalid');
            } else {
                e.target.classList.add('is-invalid');
            }
        }
    });
}
</script>

<style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 12px; }
    .form-control:focus, .form-select:focus { 
        border-color: #0d6efd; 
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); 
    }
    .badge { font-weight: 500; }
    #formImovel { margin-bottom: 100px; }
</style>

<?php require_once '../../includes/footer.php'; ?>