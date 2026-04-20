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

// Inicialização de todos os campos
$imovel = [
    'proprietario_id' => '', 'titulo' => '', 'endereco' => '', 'bairro' => '', 'cidade' => '',
    'estado' => 'PE', 'cep' => '', 'latitude' => '', 'longitude' => '', 'preco' => 0,
    'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'area' => 0, 'vagas_garagem' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'casa', 'descricao' => '', 'status' => 'captado',
    'mobiliado' => 0, 'gas_encanado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0,
    'tem_salao_festas' => 0, 'tem_espaco_gourmet' => 0, 'tem_playground' => 0,
    'possui_elevador' => 0, 'possui_moveis_planejados' => 0,
    'valor_condominio' => 0, 'valor_iptu' => 0,
    'contato_sindico' => '', 'contato_portaria' => '', 'link_site' => ''
];

$erro = '';
$sucesso = '';

// Carrega dados se for edição
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $imovel = array_merge($imovel, $row);
    } else {
        $erro = "Imóvel não encontrado.";
    }
}

// Processa o formulário (Salvar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
    $dados = [
        'proprietario_id' => (int)($_POST['proprietario_id'] ?? 0),
        'titulo' => trim($_POST['titulo'] ?? ''),
        'endereco' => trim($_POST['endereco'] ?? ''),
        'bairro' => trim($_POST['bairro'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'estado' => trim($_POST['estado'] ?? 'PE'),
        'cep' => trim($_POST['cep'] ?? ''),
        'latitude' => trim($_POST['latitude'] ?? ''),
        'longitude' => trim($_POST['longitude'] ?? ''),
        'preco' => (float)str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? 0),
        'quartos' => (int)($_POST['quartos'] ?? 0),
        'suites' => (int)($_POST['suites'] ?? 0),
        'banheiros' => (int)($_POST['banheiros'] ?? 0),
        'area' => (float)($_POST['area'] ?? 0),
        'vagas_garagem' => (int)($_POST['vagas_garagem'] ?? 0),
        'andar' => (int)($_POST['andar'] ?? 0),
        'face' => $_POST['face'] ?? 'nascente',
        'tipo' => $_POST['tipo'] ?? 'casa',
        'descricao' => trim($_POST['descricao'] ?? ''),
        'status' => $_POST['status'] ?? 'captado',
        'mobiliado' => isset($_POST['mobiliado']) ? 1 : 0,
        'gas_encanado' => isset($_POST['gas_encanado']) ? 1 : 0,
        'tem_piscina' => isset($_POST['tem_piscina']) ? 1 : 0,
        'tem_academia' => isset($_POST['tem_academia']) ? 1 : 0,
        'tem_salao_festas' => isset($_POST['tem_salao_festas']) ? 1 : 0,
        'tem_espaco_gourmet' => isset($_POST['tem_espaco_gourmet']) ? 1 : 0,
        'tem_playground' => isset($_POST['tem_playground']) ? 1 : 0,
        'possui_elevador' => isset($_POST['possui_elevador']) ? 1 : 0,
        'possui_moveis_planejados' => isset($_POST['possui_moveis_planejados']) ? 1 : 0,
        'valor_condominio' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_condominio'] ?? 0),
        'valor_iptu' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_iptu'] ?? 0),
        'contato_sindico' => trim($_POST['contato_sindico'] ?? ''),
        'contato_portaria' => trim($_POST['contato_portaria'] ?? ''),
        'link_site' => trim($_POST['link_site'] ?? '')
    ];

    try {
        if ($id > 0) {
            $set = "";
            foreach ($dados as $key => $val) { $set .= "$key = ?, "; }
            $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
            $conn->prepare($sql)->execute([...array_values($dados), $id]);
        } else {
            $cols = implode(", ", array_keys($dados));
            $plds = implode(", ", array_fill(0, count($dados), "?"));
            $sql = "INSERT INTO imoveis ($cols, created_at) VALUES ($plds, NOW())";
            $conn->prepare($sql)->execute(array_values($dados));
            $id = $conn->lastInsertId();
        }

        // Upload de fotos
        if (!empty($_FILES['fotos']['name'][0]) && $id > 0) {
            require_once 'upload_fotos.php';
        }

        if ($id > 0) {
            header("Location: form.php?id=$id&msg=sucesso");
            exit;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }

    $imovel = array_merge($imovel, $dados);
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container pb-5">
    <h2 class="mb-4 text-primary"><?= $modo ?> Imóvel</h2>

    <?php if ($sucesso || isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Dados salvos com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3" id="formImovel">

        <!-- Cabeçalho -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Título do Anúncio *</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="captado" <?= $imovel['status']=='captado'?'selected':'' ?>>Captado</option>
                            <option value="em_negociacao" <?= $imovel['status']=='em_negociacao'?'selected':'' ?>>Em Negociação</option>
                            <option value="vendido" <?= $imovel['status']=='vendido'?'selected':'' ?>>Vendido</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Proprietário</label>
                        <select name="proprietario_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php
                            $props = $conn->query("SELECT id, nome FROM proprietarios WHERE deleted_at IS NULL ORDER BY nome")->fetchAll();
                            foreach($props as $p) echo "<option value='{$p['id']}' ".($imovel['proprietario_id']==$p['id']?'selected':'').">{$p['nome']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-primary fw-bold">Link Externo (OLX, Zap, Site)</label>
                        <input type="url" name="link_site" class="form-control border-primary" value="<?= htmlspecialchars($imovel['link_site']) ?>" placeholder="https://...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Localização -->
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt"></i> Localização</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">CEP</label><input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($imovel['cep']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Endereço</label><input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Cidade</label><input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($imovel['cidade']) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Latitude</label><input type="text" name="latitude" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['latitude']) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Longitude</label><input type="text" name="longitude" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['longitude']) ?>"></div>
                </div>
            </div>
        </div>

        <!-- Ficha Técnica -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Ficha Técnica</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="apartamento" <?= $imovel['tipo']=='apartamento'?'selected':'' ?>>Apartamento</option>
                            <option value="casa" <?= $imovel['tipo']=='casa'?'selected':'' ?>>Casa</option>
                            <option value="comercial" <?= $imovel['tipo']=='comercial'?'selected':'' ?>>Comercial</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Área Útil (m²)</label><input type="number" name="area" class="form-control" value="<?= $imovel['area'] ?>" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Vagas</label><input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Quartos</label><input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>"></div>
                    <div class="col-md-3"><label class="form-label text-primary fw-bold">Suítes</label><input type="number" name="suites" class="form-control border-primary" value="<?= $imovel['suites'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Banheiros</label><input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Andar</label><input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>"></div>
                    <div class="col-12"><label class="form-label">Descrição Interna</label><textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($imovel['descricao']) ?></textarea></div>
                </div>
            </div>
        </div>

        <!-- Financeiro e Diferenciais -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-financeiro mb-3" style="background-color: #f8f9fa;">
                <div class="card-header fw-bold">Financeiro e Contatos</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Preço Venda</label>
                        <input type="text" name="preco" class="form-control fw-bold text-primary" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condomínio</label>
                        <input type="text" name="valor_condominio" class="form-control" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Contato Síndico</label>
                        <input type="text" name="contato_sindico" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['contato_sindico']) ?>" placeholder="Nome / Telefone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Contato Portaria</label>
                        <input type="text" name="contato_portaria" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['contato_portaria']) ?>" placeholder="Telefone Portaria">
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header fw-bold">Diferenciais</div>
                <div class="card-body">
                    <?php
                    $check_list = [
                        'tem_piscina' => 'Piscina',
                        'tem_academia' => 'Academia',
                        'tem_salao_festas' => 'Salão de Festas',
                        'tem_playground' => 'Playground / Kids',
                        'possui_elevador' => 'Elevador',
                        'possui_moveis_planejados' => 'Armários Planejados',
                        'gas_encanado' => 'Gás Encanado',
                        'mobiliado' => 'Mobiliado'
                    ];
                    foreach($check_list as $key => $label): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $imovel[$key]?'checked':'' ?>>
                            <label class="form-check-label" for="<?= $key ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ====================== IMAGENS DO IMÓVEL ====================== -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-images"></i> Imagens do Imóvel
                    <?php if ($id > 0): 
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM fotos_imoveis WHERE imovel_id = ?");
                        $stmt->execute([$id]);
                        $qtd_fotos = $stmt->fetchColumn();
                    ?>
                        <span class="badge bg-primary ms-2"><?= $qtd_fotos ?> foto<?= $qtd_fotos != 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Enviar novas fotos</label>
                        <input type="file" name="fotos[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">Selecione várias imagens de uma vez (máx. 10MB cada)</small>
                    </div>

                    <?php if ($id > 0): ?>
                    <div class="mt-4">
                        <label class="form-label fw-bold">Imagens cadastradas (<?= $qtd_fotos ?? 0 ?>)</label>
                        <div class="row g-3" id="galeria-imagens">
                            <?php
                            $stmt = $conn->prepare("SELECT id, caminho FROM fotos_imoveis WHERE imovel_id = ? ORDER BY id ASC");
                            $stmt->execute([$id]);
                            $imagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (count($imagens) > 0) {
                                foreach ($imagens as $img): ?>
                                    <div class="col-6 col-md-3 col-lg-2 text-center">
                                        <div class="position-relative">
                                            <img src="../../uploads/fotos_imoveis/<?= htmlspecialchars($img['caminho']) ?>" 
                                                 class="img-thumbnail" 
                                                 style="height: 140px; object-fit: cover; width: 100%;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                                                    onclick="deletarImagem(<?= $img['id'] ?>, this)">×</button>
                                        </div>
                                    </div>
                                <?php endforeach;
                            } else {
                                echo '<p class="text-muted">Nenhuma imagem cadastrada ainda.</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="col-12 text-end mt-4">
            <hr>
            <a href="list.php" class="btn btn-light border px-4 me-2">Voltar</a>
            
            <?php if ($id > 0): ?>
                <button type="button" class="btn btn-danger px-4 me-2" onclick="confirmarExclusao()">
                    <i class="bi bi-trash"></i> Excluir Imóvel
                </button>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary px-5 shadow">Salvar Dados</button>
        </div>
    </form>
</div>

<!-- Modal Excluir -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-triangle-fill text-danger display-4"></i>
                <h4 class="mt-3">Confirmar exclusão</h4>
                <p class="text-muted">Esta ação moverá o imóvel para a lixeira.<br>Tem certeza que deseja continuar?</p>
                <div class="mt-4">
                    <button type="button" class="btn btn-secondary px-4 me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger px-5" onclick="excluirImovel()">Sim, Excluir Imóvel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao() {
    new bootstrap.Modal(document.getElementById('modalExcluir')).show();
}

function excluirImovel() {
    const form = document.getElementById('formImovel');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'acao';
    input.value = 'excluir';
    form.appendChild(input);
    form.submit();
}

function deletarImagem(id, btn) {
    if (!confirm('Deseja realmente excluir esta imagem?')) return;
    
    fetch('deletar_imagem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('.col-6, .col-md-3, .col-lg-2').remove();
            location.reload();
        } else {
            alert(data.message || 'Erro ao excluir imagem');
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>