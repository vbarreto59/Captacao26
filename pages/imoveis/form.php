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

// Inicialização de todos os campos (Incluindo link_site e coordenadas)
$imovel = [
    'proprietario_id' => '', 'titulo' => '', 'endereco' => '', 'bairro' => '', 'cidade' => '', 
    'estado' => 'PE', 'cep' => '', 'latitude' => '', 'longitude' => '', 'preco' => 0, 
    'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'area' => 0, 'vagas_garagem' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'casa', 'descricao' => '', 'status' => 'captado',
    'mobiliado' => 0, 'gas_encanado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0, 
    'tem_salao_festas' => 0, 'tem_espaco_gourmet' => 0, 'tem_playground' => 0,
    'valor_condominio' => 0, 'valor_iptu' => 0, 'contato_sindico' => '', 'link_site' => ''
];

$erro    = '';
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

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Uso do operador ?? para evitar os avisos de "Undefined array key"
    $dados = [
        'proprietario_id'   => (int)($_POST['proprietario_id'] ?? 0),
        'titulo'            => trim($_POST['titulo'] ?? ''),
        'endereco'          => trim($_POST['endereco'] ?? ''),
        'bairro'            => trim($_POST['bairro'] ?? ''),
        'cidade'            => trim($_POST['cidade'] ?? ''),
        'estado'            => trim($_POST['estado'] ?? 'PE'),
        'cep'               => trim($_POST['cep'] ?? ''),
        'latitude'          => trim($_POST['latitude'] ?? ''),
        'longitude'         => trim($_POST['longitude'] ?? ''),
        'preco'             => (float)str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? 0),
        'quartos'           => (int)($_POST['quartos'] ?? 0),
        'suites'            => (int)($_POST['suites'] ?? 0),
        'banheiros'         => (int)($_POST['banheiros'] ?? 0),
        'area'              => (float)($_POST['area'] ?? 0),
        'vagas_garagem'     => (int)($_POST['vagas_garagem'] ?? 0),
        'andar'             => (int)($_POST['andar'] ?? 0),
        'face'              => $_POST['face'] ?? 'nascente',
        'tipo'              => $_POST['tipo'] ?? 'casa',
        'descricao'         => trim($_POST['descricao'] ?? ''),
        'status'            => $_POST['status'] ?? 'captado',
        'mobiliado'         => isset($_POST['mobiliado']) ? 1 : 0,
        'gas_encanado'      => isset($_POST['gas_encanado']) ? 1 : 0,
        'tem_piscina'       => isset($_POST['tem_piscina']) ? 1 : 0,
        'tem_academia'      => isset($_POST['tem_academia']) ? 1 : 0,
        'tem_salao_festas'  => isset($_POST['tem_salao_festas']) ? 1 : 0,
        'tem_espaco_gourmet'=> isset($_POST['tem_espaco_gourmet']) ? 1 : 0,
        'tem_playground'    => isset($_POST['tem_playground']) ? 1 : 0,
        'valor_condominio'  => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_condominio'] ?? 0),
        'valor_iptu'        => (float)str_replace(['.', ','], ['', '.'], $_POST['valor_iptu'] ?? 0),
        'contato_sindico'   => trim($_POST['contato_sindico'] ?? ''),
        'link_site'         => trim($_POST['link_site'] ?? '') // Novo campo incluído aqui
    ];

    try {
        if ($id > 0) {
            $set = "";
            foreach ($dados as $key => $val) { $set .= "$key = ?, "; }
            $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
            $conn->prepare($sql)->execute([...array_values($dados), $id]);
            $sucesso = "Imóvel atualizado com sucesso!";
        } else {
            $cols = implode(", ", array_keys($dados));
            $plds = implode(", ", array_fill(0, count($dados), "?"));
            $sql = "INSERT INTO imoveis ($cols, created_at) VALUES ($plds, NOW())";
            $conn->prepare($sql)->execute(array_values($dados));
            $id = $conn->lastInsertId();
            header("Location: form.php?id=$id&msg=sucesso");
            exit;
        }
        if (!empty($_FILES['fotos']['name'][0])) { upload_fotos($id); }
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
        <div class="alert alert-success alert-dismissible fade show">Dados salvos com sucesso!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Título do Imóvel *</label>
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
                        <label class="form-label text-primary fw-bold">Link do Anúncio Original (Site)</label>
                        <input type="url" name="link_site" class="form-control border-primary" value="<?= htmlspecialchars($imovel['link_site']) ?>" placeholder="https://www.olx.com.br/imovel/...">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Localização e Coordenadas</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">CEP</label><input type="text" name="cep" class="form-control" value="<?= $imovel['cep'] ?>"></div>
                    <div class="col-md-4"><label class="form-label">Endereço</label><input type="text" name="endereco" class="form-control" value="<?= $imovel['endereco'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input type="text" name="bairro" class="form-control" value="<?= $imovel['bairro'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Cidade</label><input type="text" name="cidade" class="form-control" value="<?= $imovel['cidade'] ?>"></div>
                    
                    <div class="col-md-3"><label class="form-label">Latitude</label><input type="text" name="latitude" class="form-control" value="<?= $imovel['latitude'] ?>" placeholder="-8.12345"></div>
                    <div class="col-md-3"><label class="form-label">Longitude</label><input type="text" name="longitude" class="form-control" value="<?= $imovel['longitude'] ?>" placeholder="-34.12345"></div>
                    <div class="col-md-6 text-muted small d-flex align-items-center">
                        <i class="bi bi-info-circle me-2"></i> As coordenadas ajudam na localização exata no mapa.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Características do Imóvel</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="apartamento" <?= $imovel['tipo']=='apartamento'?'selected':'' ?>>Apartamento</option>
                            <option value="casa" <?= $imovel['tipo']=='casa'?'selected':'' ?>>Casa</option>
                            <option value="terreno" <?= $imovel['tipo']=='terreno'?'selected':'' ?>>Terreno</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Área Útil (m²)</label>
                        <input type="number" name="area" class="form-control" value="<?= $imovel['area'] ?>" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vagas</label>
                        <input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>">
                    </div>

                    <div class="col-md-3"><label class="form-label">Quartos</label><input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>"></div>
                    <div class="col-md-3"><label class="form-label text-primary fw-bold">Suítes</label><input type="number" name="suites" class="form-control border-primary" value="<?= $imovel['suites'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Banheiros</label><input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Andar</label><input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>"></div>

                    <div class="col-12">
                        <label class="form-label">Descrição / Observações</label>
                        <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($imovel['descricao']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-light mb-3">
                <div class="card-header fw-bold">Financeiro</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Preço de Venda</label>
                        <input type="text" name="preco" class="form-control fw-bold text-primary" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condomínio</label>
                        <input type="text" name="valor_condominio" class="form-control" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IPTU</label>
                        <input type="text" name="valor_iptu" class="form-control" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>">
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header fw-bold">Lazer / Extras</div>
                <div class="card-body">
                    <?php 
                    $lazer = ['tem_piscina'=>'Piscina', 'tem_academia'=>'Academia', 'tem_salao_festas'=>'Salão Festas', 'tem_playground'=>'Playground'];
                    foreach($lazer as $key => $label) echo "<div class='form-check'><input class='form-check-input' type='checkbox' name='$key' id='$key' ".($imovel[$key]?'checked':'')."><label class='form-check-label' for='$key'>$label</label></div>";
                    ?>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4 text-end">
            <hr>
            <a href="list.php" class="btn btn-outline-secondary px-4 me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary px-5 shadow">Salvar Imóvel</button>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>