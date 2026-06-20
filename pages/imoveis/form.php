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

// ====================== DUPLICAR IMÓVEL ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'duplicar' && $id > 0) {
    try {
        // Buscar imóvel original
        $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$original) {
            throw new Exception("Imóvel não encontrado.");
        }
        
        // Remover campos que não devem ser copiados
        unset($original['id']);
        unset($original['deleted_at']);
        if (isset($original['created_at'])) unset($original['created_at']);
        if (isset($original['updated_at'])) unset($original['updated_at']);
        
        // Adicionar "DUPE " ao título
        $original['titulo'] = "DUPE " . $original['titulo'];
        
        // Inserir novo imóvel
        $cols = implode(", ", array_keys($original));
        $placeholders = implode(", ", array_fill(0, count($original), "?"));
        $stmt = $conn->prepare("INSERT INTO imoveis ($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($original));
        $novo_id = $conn->lastInsertId();
        
        // Duplicar corretores parceiros
        $stmt = $conn->prepare("SELECT corretor_id FROM imovel_parceiros WHERE imovel_id = ?");
        $stmt->execute([$id]);
        $parceiros = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($parceiros) {
            $stmt_par = $conn->prepare("INSERT INTO imovel_parceiros (imovel_id, corretor_id) VALUES (?, ?)");
            foreach ($parceiros as $c_id) {
                $stmt_par->execute([$novo_id, $c_id]);
            }
        }
        
        // Duplicar fotos (copiar arquivos e registrar)
        $stmt = $conn->prepare("SELECT id, caminho, capa, ordem FROM fotos_imoveis WHERE imovel_id = ?");
        $stmt->execute([$id]);
        $fotos_orig = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $target_dir = "../../uploads/fotos_imoveis/";
        foreach ($fotos_orig as $foto) {
            $caminho_orig = $target_dir . $foto['caminho'];
            if (file_exists($caminho_orig)) {
                $ext = pathinfo($foto['caminho'], PATHINFO_EXTENSION);
                $novo_nome = uniqid() . "_" . time() . "_dupe." . $ext;
                $caminho_novo = $target_dir . $novo_nome;
                if (copy($caminho_orig, $caminho_novo)) {
                    $stmt_foto = $conn->prepare("INSERT INTO fotos_imoveis (imovel_id, caminho, capa, ordem) VALUES (?, ?, ?, ?)");
                    $stmt_foto->execute([$novo_id, $novo_nome, $foto['capa'], $foto['ordem']]);
                }
            }
        }
        
        // Redirecionar para o formulário do novo imóvel
        header("Location: form.php?id=$novo_id&msg=duplicado");
        exit;
    } catch (Exception $e) {
        $erro = "Erro ao duplicar: " . $e->getMessage();
    }
}

// ====================== GERENCIAMENTO DE FOTOS ======================
if (isset($_GET['delete_foto']) && is_numeric($_GET['delete_foto']) && $id > 0) {
    $foto_id = (int)$_GET['delete_foto'];
    $stmt = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE id = ? AND imovel_id = ?");
    $stmt->execute([$foto_id, $id]);
    $foto = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($foto) {
        $caminho_arquivo = "../../uploads/fotos_imoveis/" . $foto['caminho'];
        if (file_exists($caminho_arquivo)) unlink($caminho_arquivo);
        $conn->prepare("DELETE FROM fotos_imoveis WHERE id = ?")->execute([$foto_id]);
        
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

if (isset($_GET['set_capa']) && is_numeric($_GET['set_capa']) && $id > 0) {
    $foto_id = (int)$_GET['set_capa'];
    $conn->prepare("UPDATE fotos_imoveis SET capa = 0 WHERE imovel_id = ?")->execute([$id]);
    $conn->prepare("UPDATE fotos_imoveis SET capa = 1 WHERE id = ? AND imovel_id = ?")->execute([$foto_id, $id]);
    header("Location: form.php?id=$id&msg=capa_alterada");
    exit;
}

// Inicialização dos campos (incluindo os novos)
$imovel = [
    'proprietario_id' => '', 'corretor_id' => '', 'titulo' => '', 'nome_edificio' => '', 'endereco' => '', 'bairro' => '', 'cidade' => '',
    'estado' => 'PE', 'cep' => '', 'latitude' => '', 'longitude' => '', 'preco' => 0.00,
    'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'area' => 0, 'vagas_garagem' => 0,
    'vaga_coberta' => 0, 'varanda' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'apartamento', 
    'construtora' => '', 'ano_entrega' => '', 
    'descricao' => '', 'status' => 'captado',
    'aceita_parceria' => 1, 'divisao_comissao' => '',
    'mobiliado' => 0, 'aceita_animais' => 1, 'gas_encanado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0,
    'tem_salao_festas' => 0, 'tem_espaco_gourmet' => 0, 'area_lazer' => 0, 'jardim' => 0, 'tem_playground' => 0,
    'possui_elevador' => 0, 'possui_moveis_planejados' => 0, 
    'agua_inclusa_condominio' => 0, 'gas_incluso_condominio' => 0,
    'valor_condominio' => 0.00, 'valor_iptu' => 0.00,
    'contato_sindico' => '', 'telefone' => '', 'contato_portaria' => '', 
    'portaria_24h' => 0, 'sistema_cameras' => 0, 'gerador' => 0, 'pilotis' => 0, 'portao_eletronico' => 0,
    'link_site' => '', 'resposta_rapida' => '', 'observacoes_gerais' => '',
    'rip_marinha' => '', 'regime_marinha' => 'nenhum', 'valor_foro_anual' => 0.00, 'laudemio_pago' => 0,
    'aceita_financiamento' => 0, 'aceita_fgts' => 0, 'aceita_permuta' => 0, 'aceita_consorcio' => 0,
    'valor_sinal' => 0.00,
    'reservado' => 0,
    'data_reserva' => '',
    'data_venda' => '',
    // NOVOS CAMPOS
    'distancia_mar_metros' => 0,
    'valor_taxa_extra' => 0.00,
    'taxa_extra_limite' => ''
];

$erro = '';
$sucesso = isset($_GET['msg']) && ($_GET['msg'] == 'sucesso' || $_GET['msg'] == 'foto_excluida' || $_GET['msg'] == 'capa_alterada' || $_GET['msg'] == 'duplicado');
$msg_texto = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'sucesso') $msg_texto = 'Dados salvos com sucesso!';
    elseif ($_GET['msg'] == 'foto_excluida') $msg_texto = 'Foto excluída com sucesso!';
    elseif ($_GET['msg'] == 'capa_alterada') $msg_texto = 'Foto de capa alterada!';
    elseif ($_GET['msg'] == 'duplicado') $msg_texto = 'Imóvel duplicado com sucesso!';
}

// Carrega dados do imóvel se for edição
if ($id > 0 && empty($_POST)) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $imovel = array_merge($imovel, $row);
}

// Busca parceiros vinculados (para edição)
$parceiros_selecionados = [];
if ($id > 0) {
    $stmt_p = $conn->prepare("SELECT corretor_id FROM imovel_parceiros WHERE imovel_id = ?");
    $stmt_p->execute([$id]);
    $parceiros_selecionados = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    if (empty($titulo)) {
        $erro = "O campo Título é obrigatório.";
    } else {
        $dados = [
            'proprietario_id' => !empty($_POST['proprietario_id']) ? (int)$_POST['proprietario_id'] : null,
            'corretor_id' => !empty($_POST['corretor_id']) ? (int)$_POST['corretor_id'] : null,
            'titulo' => $titulo,
            'nome_edificio' => trim($_POST['nome_edificio'] ?? ''),
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
            'vaga_coberta' => isset($_POST['vaga_coberta']) ? 1 : 0,
            'varanda' => isset($_POST['varanda']) ? 1 : 0,
            'andar' => !empty($_POST['andar']) ? (int)$_POST['andar'] : null,
            'face' => $_POST['face'] ?? 'nascente',
            'tipo' => $_POST['tipo'] ?? 'apartamento',
            'construtora' => trim($_POST['construtora'] ?? ''),
            'ano_entrega' => !empty($_POST['ano_entrega']) ? (int)$_POST['ano_entrega'] : null,
            'descricao' => trim($_POST['descricao'] ?? ''),
            'status' => $_POST['status'] ?? 'captado',
            'aceita_parceria' => isset($_POST['aceita_parceria']) ? 1 : 0,
            'divisao_comissao' => trim($_POST['divisao_comissao'] ?? ''),
            'link_site' => trim($_POST['link_site'] ?? ''),
            'resposta_rapida' => trim($_POST['resposta_rapida'] ?? ''),
            'observacoes_gerais' => trim($_POST['observacoes_gerais'] ?? ''),
            'mobiliado' => isset($_POST['mobiliado']) ? 1 : 0,
            'aceita_animais' => isset($_POST['aceita_animais']) ? 1 : 0,
            'gas_encanado' => isset($_POST['gas_encanado']) ? 1 : 0,
            'tem_piscina' => isset($_POST['tem_piscina']) ? 1 : 0,
            'tem_academia' => isset($_POST['tem_academia']) ? 1 : 0,
            'tem_salao_festas' => isset($_POST['tem_salao_festas']) ? 1 : 0,
            'tem_espaco_gourmet' => isset($_POST['tem_espaco_gourmet']) ? 1 : 0,
            'area_lazer' => isset($_POST['area_lazer']) ? 1 : 0,
            'jardim' => isset($_POST['jardim']) ? 1 : 0,
            'tem_playground' => isset($_POST['tem_playground']) ? 1 : 0,
            'possui_elevador' => isset($_POST['possui_elevador']) ? 1 : 0,
            'possui_moveis_planejados' => isset($_POST['possui_moveis_planejados']) ? 1 : 0,
            'agua_inclusa_condominio' => isset($_POST['agua_inclusa_condominio']) ? 1 : 0,
            'gas_incluso_condominio' => isset($_POST['gas_incluso_condominio']) ? 1 : 0,
            'valor_condominio' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_condominio'] ?? 0),
            'valor_iptu' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_iptu'] ?? 0),
            'contato_sindico' => trim($_POST['contato_sindico'] ?? ''),
            'telefone' => trim($_POST['telefone'] ?? ''),
            'contato_portaria' => trim($_POST['contato_portaria'] ?? ''),
            'portaria_24h' => isset($_POST['portaria_24h']) ? 1 : 0,
            'sistema_cameras' => isset($_POST['sistema_cameras']) ? 1 : 0,
            'gerador' => isset($_POST['gerador']) ? 1 : 0,
            'pilotis' => isset($_POST['pilotis']) ? 1 : 0,
            'portao_eletronico' => isset($_POST['portao_eletronico']) ? 1 : 0,
            'rip_marinha' => trim($_POST['rip_marinha'] ?? ''),
            'regime_marinha' => $_POST['regime_marinha'] ?? 'nenhum',
            'valor_foro_anual' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_foro_anual'] ?? 0),
            'laudemio_pago' => isset($_POST['laudemio_pago']) ? 1 : 0,
            'aceita_financiamento' => isset($_POST['aceita_financiamento']) ? 1 : 0,
            'aceita_fgts' => isset($_POST['aceita_fgts']) ? 1 : 0,
            'aceita_permuta' => isset($_POST['aceita_permuta']) ? 1 : 0,
            'aceita_consorcio' => isset($_POST['aceita_consorcio']) ? 1 : 0,
            'valor_sinal' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_sinal'] ?? 0),
            'reservado' => isset($_POST['reservado']) ? 1 : 0,
            'data_reserva' => !empty($_POST['data_reserva']) ? $_POST['data_reserva'] : null,
            'data_venda' => !empty($_POST['data_venda']) ? $_POST['data_venda'] : null,
            // NOVOS CAMPOS
            'distancia_mar_metros' => (float)str_replace(',', '.', $_POST['distancia_mar_metros'] ?? 0),
            'valor_taxa_extra' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_taxa_extra'] ?? 0),
            'taxa_extra_limite' => !empty($_POST['taxa_extra_limite']) ? $_POST['taxa_extra_limite'] : null
        ];

        if ($dados['reservado'] == 0) {
            $dados['data_reserva'] = null;
            $dados['data_venda'] = null;
        }

        try {
            if ($id > 0) {
                $set = "";
                foreach ($dados as $key => $val) $set .= "$key = ?, ";
                $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
                $conn->prepare($sql)->execute([...array_values($dados), $id]);
            } else {
                $cols = implode(", ", array_keys($dados));
                $plds = implode(", ", array_fill(0, count($dados), "?"));
                $sql = "INSERT INTO imoveis ($cols) VALUES ($plds)";
                $conn->prepare($sql)->execute(array_values($dados));
                $id = $conn->lastInsertId();
            }

            // --- GESTÃO DE PARCEIROS ---
            $conn->prepare("DELETE FROM imovel_parceiros WHERE imovel_id = ?")->execute([$id]);
            if (isset($_POST['parceiros']) && is_array($_POST['parceiros'])) {
                $stmt_parceiro = $conn->prepare("INSERT INTO imovel_parceiros (imovel_id, corretor_id) VALUES (?, ?)");
                foreach ($_POST['parceiros'] as $corretor_id) {
                    if (!empty($corretor_id)) {
                        $stmt_parceiro->execute([$id, (int)$corretor_id]);
                    }
                }
            }
            
            // Upload de fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                $target_dir = "../../uploads/fotos_imoveis/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $ordem = $conn->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM fotos_imoveis WHERE imovel_id = ?");
                $ordem->execute([$id]);
                $proxima_ordem = $ordem->fetchColumn();
                $total = count($_FILES['fotos']['name']);
                for ($i = 0; $i < $total; $i++) {
                    if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                    $nome_unico = uniqid() . "_" . time() . "." . $ext;
                    $destino = $target_dir . $nome_unico;
                    if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $destino)) {
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

// Busca fotos
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
            <small class="text-muted">Cadastro técnico detalhado - CRECI-PE 22003</small>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-info text-dark p-2">Versão 2026.1</span>
            <?php if($id > 0): ?>
                <form method="POST" onsubmit="return confirm('Excluir este imóvel permanentemente?')">
                    <input type="hidden" name="acao" value="excluir">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir</button>
                </form>
                <form method="POST" onsubmit="return confirm('Duplicar este imóvel? O novo imóvel terá \"DUPE \" no início do título e as fotos serão copiadas.')">
                    <input type="hidden" name="acao" value="duplicar">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-files"></i> Duplicar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if($sucesso && $msg_texto): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= $msg_texto ?></div>
    <?php endif; ?>
    <?php if(!empty($erro)): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3" id="formImovel">
        
        <!-- Bloco de Identificação -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Título do Anúncio *</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
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
                        <label class="form-label fw-bold">Nome do Edifício / Condomínio</label>
                        <input type="text" name="nome_edificio" class="form-control" value="<?= htmlspecialchars($imovel['nome_edificio']) ?>" placeholder="Ex: Mirante do Mar, Parque das Flores">
                    </div>

                    <!-- Corretor Titular -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-primary">Corretor Titular</label>
                        <select name="corretor_id" class="form-select">
                            <option value="">Selecione o corretor responsável...</option>
                            <?php
                            $corretores = $conn->query("SELECT id, nome FROM corretores WHERE deleted_at IS NULL ORDER BY nome")->fetchAll();
                            foreach($corretores as $c):
                                $selected = ($imovel['corretor_id'] == $c['id']) ? 'selected' : '';
                                echo "<option value='{$c['id']}' {$selected}>" . htmlspecialchars($c['nome']) . "</option>";
                            endforeach;
                            ?>
                        </select>
                        <small class="text-muted">Corretor principal responsável pelo imóvel</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Proprietário</label>
                        <select name="proprietario_id" class="form-select" required>
                            <option value="">Selecione um proprietário...</option>
                            <?php
                            $props = $conn->query("SELECT id, nome FROM proprietarios WHERE deleted_at IS NULL ORDER BY nome")->fetchAll();
                            foreach($props as $p): 
                                $selected = ($imovel['proprietario_id'] == $p['id']) ? 'selected' : '';
                                echo "<option value='{$p['id']}' {$selected}>" . htmlspecialchars($p['nome']) . "</option>";
                            endforeach;
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reservado</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="reservado" id="reservado" <?= $imovel['reservado'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reservado">Sim, imóvel reservado</label>
                        </div>
                    </div>
                    <div class="col-md-4" id="div_data_reserva" style="<?= $imovel['reservado'] ? '' : 'display:none' ?>">
                        <label class="form-label">Data da Reserva</label>
                        <input type="date" name="data_reserva" id="data_reserva" class="form-control" value="<?= $imovel['data_reserva'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data da Venda (se vendido)</label>
                        <input type="date" name="data_venda" id="data_venda" class="form-control" value="<?= $imovel['data_venda'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-primary fw-bold">Link do Site / Externo</label>
                        <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($imovel['link_site']) ?>">
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
                        <label class="form-label small">Coordenadas (Lat, Long)</label>
                        <input type="text" id="lat_field" class="form-control" value="<?= (!empty($imovel['latitude']) && !empty($imovel['longitude'])) ? $imovel['latitude'].', '.$imovel['longitude'] : '' ?>" placeholder="-8.123, -34.888">
                        <input type="hidden" name="latitude" id="hidden_lat" value="<?= $imovel['latitude'] ?>">
                        <input type="hidden" name="longitude" id="hidden_lng" value="<?= $imovel['longitude'] ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Fotos -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-images"></i> Fotos do Imóvel</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Adicionar novas fotos</label>
                        <input type="file" name="fotos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <?php if ($id > 0 && count($fotos) > 0): ?>
                        <div class="row g-3 mt-1">
                            <?php foreach ($fotos as $foto): 
                                $caminho_imagem = "../../uploads/fotos_imoveis/" . $foto['caminho'];
                                if (!file_exists($caminho_imagem)) continue;
                            ?>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <img src="<?= $caminho_imagem ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                                        <div class="card-body p-2 text-center">
                                            <?php if ($foto['capa'] == 1): ?>
                                                <span class="badge bg-primary mb-2"><i class="bi bi-star-fill"></i> Capa</span>
                                            <?php else: ?>
                                                <a href="?id=<?= $id ?>&set_capa=<?= $foto['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 mb-1" onclick="return confirm('Definir como capa?')"><i class="bi bi-star"></i> Definir Capa</a>
                                            <?php endif; ?>
                                            <a href="?id=<?= $id ?>&delete_foto=<?= $foto['id'] ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Excluir esta foto?')"><i class="bi bi-trash"></i> Excluir</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ficha Técnica -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold">Ficha Técnica</div>
                <div class="card-body row g-3">
                    <div class="col-md-3"><label class="form-label">Área Útil (m²)</label><input type="number" name="area" class="form-control" value="<?= $imovel['area'] ?>" step="0.01"></div>
                    <div class="col-md-2"><label class="form-label">Quartos</label><input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>"></div>
                    <div class="col-md-2"><label class="form-label">Suítes</label><input type="number" name="suites" class="form-control" value="<?= $imovel['suites'] ?>"></div>
                    <div class="col-md-2"><label class="form-label">Banheiros</label><input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>"></div>
                    <div class="col-md-2"><label class="form-label">Vagas</label><input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>"></div>
                    <div class="col-md-2"><label class="form-label">Vaga Coberta?</label>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="vaga_coberta" <?= $imovel['vaga_coberta']?'checked':'' ?>> <label class="form-check-label">Sim</label></div>
                    </div>
                    <div class="col-md-2"><label class="form-label">Varanda?</label>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="varanda" <?= $imovel['varanda']?'checked':'' ?>> <label class="form-check-label">Sim</label></div>
                    </div>
                    <div class="col-md-1"><label class="form-label">Andar</label><input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] !== '' ? $imovel['andar'] : '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Construtora</label><input type="text" name="construtora" class="form-control" value="<?= htmlspecialchars($imovel['construtora']) ?>" placeholder="Ex: MRV, Direcional..."></div>
                    <div class="col-md-2"><label class="form-label">Ano de Entrega</label><input type="number" name="ano_entrega" class="form-control" value="<?= $imovel['ano_entrega'] ?>" placeholder="AAAA" min="1900" max="2100"></div>
                    <div class="col-md-2"><label class="form-label">Face</label>
                        <select name="face" class="form-select">
                            <option value="nascente" <?= $imovel['face']=='nascente'?'selected':'' ?>>Nascente</option>
                            <option value="norte" <?= $imovel['face']=='norte'?'selected':'' ?>>Norte</option>
                            <option value="sul" <?= $imovel['face']=='sul'?'selected':'' ?>>Sul</option>
                            <option value="poente" <?= $imovel['face']=='poente'?'selected':'' ?>>Poente</option>
                        </select>
                    </div>
                    <!-- Campo Distância do Mar mantido aqui -->
                    <div class="col-md-3">
                        <label class="form-label">Distância do Mar (metros)</label>
                        <input type="number" name="distancia_mar_metros" class="form-control" value="<?= $imovel['distancia_mar_metros'] ?>" step="1">
                    </div>
                    <div class="col-12"><label class="form-label fw-bold">Descrição</label><textarea name="descricao" class="form-control" rows="6"><?= htmlspecialchars($imovel['descricao']) ?></textarea></div>
                </div>
            </div>
        </div>

        <!-- Sidebar Financeira (Valores com fundo cinza claro) -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-light mb-3">
                <div class="card-header fw-bold">Valores</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label fw-bold">Preço</label><input type="text" name="preco" class="form-control js-money" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>"></div>
                    <div class="mb-3"><label class="form-label small">Condomínio</label><input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>"></div>
                    <div class="mb-3"><label class="form-label small">IPTU</label><input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>"></div>
                    <!-- Campos movidos para cá -->
                    <div class="mb-3"><label class="form-label small">Valor Taxa Extra</label><input type="text" name="valor_taxa_extra" class="form-control js-money" value="<?= number_format($imovel['valor_taxa_extra'], 2, ',', '.') ?>"></div>
                    <div class="mb-3"><label class="form-label small">Limite da Taxa Extra (data)</label><input type="date" name="taxa_extra_limite" class="form-control" value="<?= htmlspecialchars($imovel['taxa_extra_limite']) ?>"></div>
                </div>
            </div>
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header fw-bold">Diferenciais</div>
                <div class="card-body">
                    <?php
                    $diferenciais = [
                        'tem_piscina' => 'Piscina', 'tem_academia' => 'Academia', 'tem_salao_festas' => 'Salão de Festas',
                        'tem_espaco_gourmet' => 'Espaço Gourmet', 'area_lazer' => 'Área de Lazer', 'jardim' => 'Jardim',
                        'tem_playground' => 'Playground', 'possui_elevador' => 'Elevador',
                        'possui_moveis_planejados' => 'Móveis Planejados', 'gas_encanado' => 'Gás Encanado', 
                        'mobiliado' => 'Mobiliado', 'aceita_animais' => 'Aceita Animais',
                        'agua_inclusa_condominio' => 'Água inclusa no condomínio', 'gas_incluso_condominio' => 'Gás incluso no condomínio'
                    ];
                    foreach($diferenciais as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="<?= $key ?>" <?= $imovel[$key]?'checked':'' ?>>
                            <label class="form-check-label small"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Segurança e Infraestrutura -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header fw-bold">Segurança / Infraestrutura</div>
                <div class="card-body">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="portaria_24h" <?= $imovel['portaria_24h']?'checked':'' ?>><label class="form-check-label small">Portaria 24h</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="sistema_cameras" <?= $imovel['sistema_cameras']?'checked':'' ?>><label class="form-check-label small">Sistema de Câmeras</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="gerador" <?= $imovel['gerador']?'checked':'' ?>><label class="form-check-label small">Gerador Próprio</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="pilotis" <?= $imovel['pilotis']?'checked':'' ?>><label class="form-check-label small">Pilotis</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="portao_eletronico" <?= $imovel['portao_eletronico']?'checked':'' ?>><label class="form-check-label small">Portão Eletrônico</label></div>
                </div>
            </div>
        </div>

        <!-- Gestão Interna e Parcerias -->
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-4 border-info mb-3">
                <div class="card-header bg-white fw-bold text-info">Gestão Interna e Condições Comerciais</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">Resposta Rápida</label><textarea name="resposta_rapida" class="form-control bg-light" rows="4"><?= htmlspecialchars($imovel['resposta_rapida']) ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">Observações Gerais</label><textarea name="observacoes_gerais" class="form-control bg-light" rows="4"><?= htmlspecialchars($imovel['observacoes_gerais']) ?></textarea></div>
                    <div class="col-md-4">
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="aceita_parceria" <?= $imovel['aceita_parceria']?'checked':'' ?> id="aceita_parceria"> <label class="form-check-label fw-bold" for="aceita_parceria">Aceita parceria com outros corretores?</label></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Divisão da Comissão (Ex: 50/50, 70/30)</label>
                        <input type="text" name="divisao_comissao" class="form-control" value="<?= htmlspecialchars($imovel['divisao_comissao']) ?>" placeholder="Ex: 50/50">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone de Contato (exibir no site)</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($imovel['telefone']) ?>" placeholder="(81) 99999-9999">
                    </div>
                </div>
            </div>
        </div>

        <!-- Corretores Parceiros -->
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-4 border-primary mb-3">
                <div class="card-header bg-white fw-bold text-primary"><i class="bi bi-people"></i> Corretores Parceiros</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php 
                        $corretores = $conn->query("SELECT id, nome FROM corretores WHERE deleted_at IS NULL ORDER BY nome")->fetchAll();
                        for ($i = 0; $i < 3; $i++): 
                            $valor_atual = $parceiros_selecionados[$i] ?? '';
                        ?>
                        <div class="col-md-4">
                            <label class="form-label small">Parceiro <?= $i + 1 ?></label>
                            <select name="parceiros[]" class="form-select">
                                <option value="">Nenhum</option>
                                <?php foreach($corretores as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $valor_atual == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentação e Marinha -->
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-4 border-warning mb-3">
                <div class="card-header bg-white fw-bold">Documentação / Marinha</div>
                <div class="card-body row g-3">
                    <div class="col-md-3"><label class="form-label">Regime</label><select name="regime_marinha" class="form-select"><option value="nenhum" <?= $imovel['regime_marinha']=='nenhum'?'selected':'' ?>>Nenhum</option><option value="ocupacao" <?= $imovel['regime_marinha']=='ocupacao'?'selected':'' ?>>Ocupação</option><option value="aforamento" <?= $imovel['regime_marinha']=='aforamento'?'selected':'' ?>>Aforamento</option></select></div>
                    <div class="col-md-3"><label class="form-label">RIP</label><input type="text" name="rip_marinha" class="form-control" value="<?= $imovel['rip_marinha'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Foro Anual</label><input type="text" name="valor_foro_anual" class="form-control js-money" value="<?= number_format($imovel['valor_foro_anual'], 2, ',', '.') ?>"></div>
                    <div class="col-md-3"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="laudemio_pago" <?= $imovel['laudemio_pago']?'checked':'' ?>><label class="form-check-label">Laudêmio pago?</label></div></div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-4 border-success mb-5">
                <div class="card-header bg-white fw-bold">Condições de Pagamento</div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_financiamento" <?= $imovel['aceita_financiamento']?'checked':'' ?>><label>Financiamento</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_fgts" <?= $imovel['aceita_fgts']?'checked':'' ?>><label>FGTS</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_permuta" <?= $imovel['aceita_permuta']?'checked':'' ?>><label>Permuta</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="aceita_consorcio" <?= $imovel['aceita_consorcio']?'checked':'' ?>><label>Consórcio</label></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor do Sinal (R$)</label>
                        <input type="text" name="valor_sinal" class="form-control js-money" value="<?= number_format($imovel['valor_sinal'], 2, ',', '.') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-center bg-white border-top p-4 fixed-bottom shadow-lg">
            <a href="list.php" class="btn btn-light border px-4 me-2">Voltar</a>
            <button type="submit" class="btn btn-primary btn-lg px-5">SALVAR</button>
        </div>
    </form>
</div>

<script>
// Máscara financeira
function formatarMoeda(e) { let v = e.target.value.replace(/\D/g,""); v = (v/100).toFixed(2).replace(".",",").replace(/\B(?=(\d{3})+(?!\d))/g,"."); e.target.value = v; }
document.querySelectorAll('.js-money').forEach(el => el.addEventListener('input', formatarMoeda));
// Máscara CEP
document.querySelectorAll('.js-cep').forEach(el => el.addEventListener('input', e => { let v = e.target.value.replace(/\D/g,""); if(v.length>5) v=v.replace(/^(\d{5})(\d)/,"$1-$2"); e.target.value=v.substring(0,9); }));
// Coordenadas
const latField = document.getElementById('lat_field'); if(latField) latField.addEventListener('input', function(e) { const val = e.target.value; if(val.includes(',')) { const parts = val.split(','); const lat = parts[0].trim(); const lng = parts[1].trim(); if(!isNaN(lat)&&!isNaN(lng)) { document.getElementById('hidden_lat').value = lat; document.getElementById('hidden_lng').value = lng; } } });

const reservadoCheck = document.getElementById('reservado');
const divDataReserva = document.getElementById('div_data_reserva');
if(reservadoCheck) {
    reservadoCheck.addEventListener('change', function() {
        divDataReserva.style.display = this.checked ? 'block' : 'none';
        if(!this.checked) { document.getElementById('data_reserva').value = ''; }
    });
}
</script>

<style>
body{background:#f8f9fa}.card{border-radius:12px}#formImovel{margin-bottom:100px}.fixed-bottom{z-index:1030}
</style>

<?php require_once '../../includes/footer.php'; ?>