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

// Inicialização de todos os campos (Conforme DESCRIBE da tabela remota)
$imovel = [
    'proprietario_id' => '', 'titulo' => '', 'endereco' => '', 'bairro' => '', 'cidade' => '',
    'estado' => 'PE', 'cep' => '', 'latitude' => '', 'longitude' => '', 'preco' => 0,
    'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'area' => 0, 'vagas_garagem' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'apartamento', 
    'construtora' => '', 'ano_entrega' => '', 
    'descricao' => '', 'status' => 'captado',
    'mobiliado' => 0, 'gas_encanado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0,
    'tem_salao_festas' => 0, 'tem_espaco_gourmet' => 0, 'tem_playground' => 0,
    'possui_elevador' => 0, 'possui_moveis_planejados' => 0,
    'valor_condominio' => 0.00, 'valor_iptu' => 0.00,
    'contato_sindico' => '', 'contato_portaria' => '', 
    'link_site' => '', 'resposta_rapida' => '', 'observacoes_gerais' => ''
];

$erro = '';
$sucesso = '';

// Carrega dados do banco remoto se for edição
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $imovel = array_merge($imovel, $row);
    } else {
        $erro = "Imóvel não encontrado no servidor remoto.";
    }
}

// Processa o formulário (Salvar/Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
    $dados = [
        'proprietario_id' => !empty($_POST['proprietario_id']) ? (int)$_POST['proprietario_id'] : null,
        'titulo' => trim($_POST['titulo'] ?? ''),
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
        'valor_condominio' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_condominio'] ?? 0),
        'valor_iptu' => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_iptu'] ?? 0),
        'contato_sindico' => trim($_POST['contato_sindico'] ?? ''),
        'contato_portaria' => trim($_POST['contato_portaria'] ?? '')
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
            $sql = "INSERT INTO imoveis ($cols) VALUES ($plds)";
            $conn->prepare($sql)->execute(array_values($dados));
            $id = $conn->lastInsertId();
        }

        if (!empty($_FILES['fotos']['name'][0]) && $id > 0) {
            require_once 'upload_fotos.php';
        }

        header("Location: form.php?id=$id&msg=sucesso");
        exit;
    } catch (PDOException $e) {
        $erro = "Erro ao salvar no banco remoto: " . $e->getMessage();
    }
    $imovel = array_merge($imovel, $dados);
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary m-0"><?= $modo ?> Imóvel</h2>
        <span class="badge bg-info text-dark">Base: cli213_captacao2026</span>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle me-2"></i> Dados sincronizados com o servidor remoto!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger shadow-sm"><?= $erro ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3" id="formImovel">

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Título do Anúncio *</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select bg-light fw-bold text-primary">
                            <option value="captado" <?= $imovel['status']=='captado'?'selected':'' ?>>Captado</option>
                            <option value="em_negociacao" <?= $imovel['status']=='em_negociacao'?'selected':'' ?>>Em Negociação</option>
                            <option value="parceria" <?= $imovel['status']=='parceria'?'selected':'' ?>>Parceria</option>
                            <option value="vendido" <?= $imovel['status']=='vendido'?'selected':'' ?>>Vendido</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Proprietário</label>
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

        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt"></i> Localização</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">CEP</label><input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($imovel['cep']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Endereço</label><input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Cidade</label><input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($imovel['cidade']) ?>"></div>
                    
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Latitude</label>
                        <input type="text" name="latitude" id="lat_field" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['latitude']) ?>" placeholder="Cole lat, long aqui">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Longitude</label>
                        <input type="text" name="longitude" id="lng_field" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['longitude']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold">Ficha Técnica</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="apartamento" <?= $imovel['tipo']=='apartamento'?'selected':'' ?>>Apartamento</option>
                            <option value="casa" <?= $imovel['tipo']=='casa'?'selected':'' ?>>Casa</option>
                            <option value="comercial" <?= $imovel['tipo']=='comercial'?'selected':'' ?>>Comercial</option>
                            <option value="terreno" <?= $imovel['tipo']=='terreno'?'selected':'' ?>>Terreno</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Construtora</label>
                        <input type="text" name="construtora" class="form-control" value="<?= htmlspecialchars($imovel['construtora']) ?>" placeholder="Ex: Moura Dubeux">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ano Entrega</label>
                        <input type="number" name="ano_entrega" class="form-control" value="<?= $imovel['ano_entrega'] ?>">
                    </div>
                    <div class="col-md-4"><label class="form-label">Área Útil (m²)</label><input type="number" name="area" class="form-control" value="<?= $imovel['area'] ?>" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Vagas</label><input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>"></div>
                    <div class="col-md-4"><label class="form-label">Andar</label><input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>"></div>
                    <div class="col-md-4"><label class="form-label">Quartos</label><input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>"></div>
                    <div class="col-md-4"><label class="form-label text-primary fw-bold">Suítes</label><input type="number" name="suites" class="form-control border-primary" value="<?= $imovel['suites'] ?>"></div>
                    <div class="col-md-4">
                         <label class="form-label">Banheiros Totais</label>
                        <input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Face</label>
                        <select name="face" class="form-select form-select-sm">
                            <option value="nascente" <?= $imovel['face']=='nascente'?'selected':'' ?>>Nascente</option>
                            <option value="poente" <?= $imovel['face']=='poente'?'selected':'' ?>>Poente</option>
                            <option value="norte" <?= $imovel['face']=='norte'?'selected':'' ?>>Norte</option>
                            <option value="sul" <?= $imovel['face']=='sul'?'selected':'' ?>>Sul</option>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label fw-bold">Descrição Pública</label><textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($imovel['descricao']) ?></textarea></div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-4 border-info">
                <div class="card-header bg-white fw-bold text-info">Gestão Interna (Privado)</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">RESPOSTA RÁPIDA (MEMO)</label>
                        <textarea name="resposta_rapida" class="form-control bg-light" rows="5"><?= htmlspecialchars($imovel['resposta_rapida']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">OBSERVAÇÕES GERAIS (OBS)</label>
                        <textarea name="observacoes_gerais" class="form-control bg-light" rows="5"><?= htmlspecialchars($imovel['observacoes_gerais']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-light mb-3">
                <div class="card-header fw-bold">Financeiro e Contatos</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Preço de Venda</label>
                        <input type="text" name="preco" class="form-control form-control-lg fw-bold text-primary" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small">Condomínio</label><input type="text" name="valor_condominio" class="form-control" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>"></div>
                        <div class="col-6"><label class="form-label small">IPTU</label><input type="text" name="valor_iptu" class="form-control" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>"></div>
                    </div>
                    <hr>
                    <div class="mb-2"><label class="form-label small fw-bold">Síndico</label><input type="text" name="contato_sindico" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['contato_sindico']) ?>"></div>
                    <div class="mb-2"><label class="form-label small fw-bold">Portaria</label><input type="text" name="contato_portaria" class="form-control form-control-sm" value="<?= htmlspecialchars($imovel['contato_portaria']) ?>"></div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header fw-bold">Diferenciais</div>
                <div class="card-body">
                    <?php
                    $check_list = [
                        'tem_piscina' => 'Piscina', 'tem_academia' => 'Academia', 
                        'tem_salao_festas' => 'Salão de Festas', 'tem_espaco_gourmet' => 'Espaço Gourmet',
                        'tem_playground' => 'Playground', 'possui_elevador' => 'Elevador',
                        'possui_moveis_planejados' => 'Armários Planejados', 'gas_encanado' => 'Gás Encanado',
                        'mobiliado' => 'Mobiliado'
                    ];
                    foreach($check_list as $key => $label): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $imovel[$key]?'checked':'' ?>>
                            <label class="form-check-label small" for="<?= $key ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="bi bi-images"></i> Galeria Remota</div>
                <div class="card-body">
                    <input type="file" name="fotos[]" class="form-control mb-4" multiple accept="image/*">
                    <?php if ($id > 0): ?>
                        <div class="row g-3" id="galeria-imagens">
                            <?php
                            $stmt = $conn->prepare("SELECT id, caminho FROM fotos_imoveis WHERE imovel_id = ? ORDER BY id ASC");
                            $stmt->execute([$id]);
                            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $img): ?>
                                <div class="col-6 col-md-2 text-center position-relative">
                                    <img src="../../uploads/fotos_imoveis/<?= $img['caminho'] ?>" class="img-thumbnail" style="height: 120px; object-fit: cover; width: 100%;">
                                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" onclick="deletarImagem(<?= $img['id'] ?>, this)">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 text-end mt-4">
            <hr>
            <a href="list.php" class="btn btn-light border px-4 me-2">Cancelar</a>
            <?php if ($id > 0): ?>
                <button type="button" class="btn btn-outline-danger px-4 me-2" onclick="confirmarExclusao()">Excluir</button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-lg px-5 shadow">Salvar no Servidor</button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center py-4">
            <i class="bi bi-exclamation-triangle text-danger display-4"></i>
            <h4 class="mt-3">Confirmar Exclusão?</h4>
            <p>O registro será marcado como excluído no banco remoto.</p>
            <div>
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-danger px-4" onclick="excluirImovel()">Sim, Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
// LÓGICA DE AUTO-SPLIT LATITUDE/LONGITUDE
document.getElementById('lat_field').addEventListener('input', function(e) {
    const value = e.target.value;
    // Verifica se existe uma vírgula no texto colado
    if (value.includes(',')) {
        const parts = value.split(',');
        if (parts.length >= 2) {
            const lat = parts[0].trim();
            const lng = parts[1].trim();
            
            // Preenche os campos
            document.getElementById('lat_field').value = lat;
            document.getElementById('lng_field').value = lng;
            
            // Remove o foco para dar feedback visual de que funcionou
            document.getElementById('lng_field').focus();
        }
    }
});

function confirmarExclusao() { new bootstrap.Modal(document.getElementById('modalExcluir')).show(); }
function excluirImovel() {
    const f = document.getElementById('formImovel');
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'acao'; i.value = 'excluir';
    f.appendChild(i); f.submit();
}
function deletarImagem(id, btn) {
    if (!confirm('Excluir foto permanentemente?')) return;
    fetch('deletar_imagem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    }).then(() => btn.closest('.col-6').remove());
}
</script>

<?php require_once '../../includes/footer.php'; ?>