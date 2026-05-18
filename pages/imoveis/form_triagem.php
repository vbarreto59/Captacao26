<?php
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php'; 

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar Triagem' : 'Nova Triagem de Parceiro';

// 1. INICIALIZAÇÃO DOS CAMPOS (Com garantia que categoria_registro existe)
$imovel = [
    'titulo' => '', 'endereco' => '', 'bairro' => '', 'cidade' => 'Recife', 'estado' => 'PE', 'cep' => '',
    'preco' => 0.00, 'area' => 0, 'quartos' => 0, 'suites' => 0, 'banheiros' => 0, 'vagas_garagem' => 0,
    'andar' => '', 'face' => 'nascente', 'tipo' => 'apartamento', 'conservacao' => 'usado',
    'corretor_id' => '', 'link_site' => '', 'observacoes_gerais' => '', 'entrega_obra' => '',
    'mobiliado' => 0, 'tem_piscina' => 0, 'tem_academia' => 0, 'tem_salao_festas' => 0,
    'tem_espaco_gourmet' => 0, 'tem_playground' => 0, 'possui_elevador' => 0,
    'valor_condominio' => 0.00, 'valor_iptu' => 0.00, 'regime_marinha' => 'nenhum',
    'categoria_registro' => 'triagem'  // Padrão inicializado
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) $imovel = array_merge($imovel, $res);
}

// 2. PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function($v) { return (float)str_replace(['.', ','], ['', '.'], $v); };
    
    $dados = [
        'titulo'            => $_POST['titulo'],
        'endereco'          => $_POST['endereco'],
        'bairro'            => $_POST['bairro'],
        'cep'               => $_POST['cep'],
        'preco'             => $f($_POST['preco']),
        'valor_condominio'  => $f($_POST['valor_condominio']),
        'valor_iptu'        => $f($_POST['valor_iptu']),
        'area'              => (float)$_POST['area'],
        'quartos'           => (int)$_POST['quartos'],
        'suites'            => (int)$_POST['suites'],
        'banheiros'         => (int)$_POST['banheiros'],
        'vagas_garagem'     => (int)$_POST['vagas_garagem'],
        'andar'             => !empty($_POST['andar']) ? (int)$_POST['andar'] : null,
        'face'              => $_POST['face'],
        'tipo'              => $_POST['tipo'],
        'conservacao'       => $_POST['conservacao'],
        'regime_marinha'    => $_POST['regime_marinha'],
        'corretor_id'       => !empty($_POST['corretor_id']) ? (int)$_POST['corretor_id'] : null,
        'link_site'         => $_POST['link_site'],
        'observacoes_gerais'=> $_POST['observacoes_gerais'],
        'entrega_obra'      => !empty($_POST['entrega_obra']) ? $_POST['entrega_obra'] . "-01" : null,
        'mobiliado'         => (int)isset($_POST['mobiliado']),
        'tem_piscina'       => (int)isset($_POST['tem_piscina']),
        'tem_academia'      => (int)isset($_POST['tem_academia']),
        'tem_salao_festas'  => (int)isset($_POST['tem_salao_festas']),
        'tem_espaco_gourmet'=> (int)isset($_POST['tem_espaco_gourmet']),
        'tem_playground'    => (int)isset($_POST['tem_playground']),
        'possui_elevador'   => (int)isset($_POST['possui_elevador']),
        'categoria_registro'=> $_POST['categoria_registro'], // Captura "triagem" ou "oficial"
        'status'            => 'parceria'
    ];

    try {
        if ($id > 0) {
            $set = "";
            foreach ($dados as $key => $val) $set .= "$key = ?, ";
            $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
            $params = array_values($dados); $params[] = $id;
            $conn->prepare($sql)->execute($params);
        } else {
            $cols = implode(", ", array_keys($dados));
            $plds = implode(", ", array_fill(0, count($dados), "?"));
            $conn->prepare("INSERT INTO imoveis ($cols) VALUES ($plds)")->execute(array_values($dados));
        }
        header("Location: form_triagem.php?msg=sucesso"); exit;
    } catch (PDOException $e) { $erro = "Erro ao salvar: " . $e->getMessage(); }
}
?>

<?php require_once '../../includes/header.php'; ?>

<!-- CDN do DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container mt-4">
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $erro ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold m-0"><?= $modo ?></h2>
        <a href="form_triagem.php" class="btn btn-outline-primary <?= $id==0?'d-none':'' ?>">+ Nova Triagem</a>
    </div>

    <!-- FORMULÁRIO DE CADASTRO -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-dark text-white">
            <h5 class="m-0"><i class="bi bi-pencil-square"></i> Dados do Imóvel</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">

                <div class="col-md-3">
                    <label class="form-label fw-bold">Corretor Parceiro</label>
                    <select name="corretor_id" class="form-select border-primary" required>
                        <option value="">Selecione o Parceiro...</option>
                        <?php
                        $corretores = $conn->query("SELECT id, nome FROM corretores ORDER BY nome")->fetchAll();
                        foreach($corretores as $c) echo "<option value='{$c['id']}' ".($imovel['corretor_id']==$c['id']?'selected':'').">{$c['nome']}</option>";
                        ?>
                    </select>
                </div>
                
                <!-- Categoria Registro (Triagem / Oficial) -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">Categoria do Registro</label>
                    <select name="categoria_registro" class="form-select border-primary" required>
                        <option value="triagem" <?= $imovel['categoria_registro'] == 'triagem' ? 'selected' : '' ?>>📋 Triagem (Parceiro)</option>
                        <option value="oficial" <?= $imovel['categoria_registro'] == 'oficial' ? 'selected' : '' ?>>⭐ Oficial (Imobiliária)</option>
                    </select>
                    <small class="text-muted">Define se o imóvel é uma triagem de parceiro ou um lançamento oficial.</small>
                </div>

                <!-- LINHA 1 -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Edifício / Nome do Imóvel</label>
                    <input type="text" name="titulo" class="form-control border-primary" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="tipo" class="form-select border-primary">
                        <?php 
                        $tipos = ['apartamento', 'casa', 'studio', 'flat', 'comercial', 'terreno'];
                        foreach($tipos as $t) echo "<option value='$t' ".($imovel['tipo']==$t?'selected':'').">".ucfirst($t)."</option>";
                        ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold text-primary">Estado do Imóvel</label>
                    <select name="conservacao" class="form-select border-primary">
                        <option value="novo" <?= $imovel['conservacao']=='novo'?'selected':'' ?>>✨ Novo (Em obra / Nunca habitado)</option>
                        <option value="usado" <?= $imovel['conservacao']=='usado'?'selected':'' ?>>🏠 Usado (Já habitado)</option>
                    </select>
                </div>

                <!-- LINHA 2 (Localização) -->
                <div class="col-md-5">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($imovel['cep']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Regime Marinha</label>
                    <select name="regime_marinha" class="form-select">
                        <option value="nenhum" <?= $imovel['regime_marinha']=='nenhum'?'selected':'' ?>>Nenhum</option>
                        <option value="ocupacao" <?= $imovel['regime_marinha']=='ocupacao'?'selected':'' ?>>Ocupação</option>
                        <option value="aforamento" <?= $imovel['regime_marinha']=='aforamento'?'selected':'' ?>>Aforamento</option>
                    </select>
                </div>

                <!-- LINHA 3 (Valores) -->
                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">Preço de Venda</label>
                    <input type="text" name="preco" class="form-control js-money fw-bold text-danger border-danger" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted">Valor Condomínio</label>
                    <input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted">Valor IPTU</label>
                    <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Previsão Entrega</label>
                    <input type="month" name="entrega_obra" class="form-control" value="<?= !empty($imovel['entrega_obra']) ? substr($imovel['entrega_obra'], 0, 7) : '' ?>">
                </div>

                <!-- LINHA 4 (Ficha Técnica) -->
                <div class="col-md-2">
                    <label class="form-label">Área (m²)</label>
                    <input type="number" step="0.01" name="area" class="form-control" value="<?= $imovel['area'] ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Quartos</label>
                    <input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Suítes</label>
                    <input type="number" name="suites" class="form-control" value="<?= $imovel['suites'] ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Banheiros</label>
                    <input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Vagas</label>
                    <input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Andar</label>
                    <input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Face</label>
                    <select name="face" class="form-select">
                        <option value="nascente" <?= $imovel['face']=='nascente'?'selected':'' ?>>Nascente</option>
                        <option value="poente" <?= $imovel['face']=='poente'?'selected':'' ?>>Poente</option>
                        <option value="norte" <?= $imovel['face']=='norte'?'selected':'' ?>>Norte</option>
                        <option value="sul" <?= $imovel['face']=='sul'?'selected':'' ?>>Sul</option>
                    </select>
                </div>

                <!-- LINHA 5 (Lazer / Infra) -->
                <div class="col-12">
                    <label class="form-label fw-bold d-block">Infraestrutura e Comodidades</label>
                    <div class="p-3 bg-light rounded border d-flex flex-wrap gap-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_piscina" <?= $imovel['tem_piscina']?'checked':'' ?>> <label>Piscina</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_academia" <?= $imovel['tem_academia']?'checked':'' ?>> <label>Academia</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_salao_festas" <?= $imovel['tem_salao_festas']?'checked':'' ?>> <label>Salão de Festas</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_espaco_gourmet" <?= $imovel['tem_espaco_gourmet']?'checked':'' ?>> <label>E. Gourmet</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_playground" <?= $imovel['tem_playground']?'checked':'' ?>> <label>Playground</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="possui_elevador" <?= $imovel['possui_elevador']?'checked':'' ?>> <label>Elevador</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="mobiliado" <?= $imovel['mobiliado']?'checked':'' ?>> <label>Mobiliado</label></div>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Link do WhatsApp / Referência</label>
                    <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($imovel['link_site']) ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Observações Gerais</label>
                    <textarea name="observacoes_gerais" class="form-control" rows="2"><?= htmlspecialchars($imovel['observacoes_gerais']) ?></textarea>
                </div>

                <div class="col-12 text-end pt-3">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow">SALVAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTAGEM DINÂMICA (DATATABLES) -->
    <div class="card border-0 shadow-sm p-4">
        <h5 class="mb-4 text-primary fw-bold"><i class="bi bi-list-check"></i> Todos os Imóveis</h5>
        <div class="table-responsive">
            <table id="tabelaTriagem" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Imóvel / Estado</th>
                        <th>Bairro / Parceiro</th>
                        <th>Valores</th>
                        <th>Ficha Técnica</th>
                        <th>Categoria</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_list = "SELECT i.*, c.nome as parceiro FROM imoveis i LEFT JOIN corretores c ON i.corretor_id = c.id WHERE i.deleted_at IS NULL ORDER BY i.id DESC";
                    $res_lista = $conn->query($sql_list)->fetchAll();
                    foreach ($res_lista as $it):
                        $isNovo = ($it['conservacao'] == 'novo');
                        $categoria = $it['categoria_registro'];
                        
                        if ($categoria == 'oficial') {
                            $badge_class = 'bg-success';
                            $badge_text = '⭐ Oficial';
                        } else {
                            $badge_class = 'bg-info text-dark';
                            $badge_text = '📋 Triagem';
                        }
                    ?>

                    <tr>
                        <td><?= str_pad($it['id'], 3, '0', STR_PAD_LEFT)?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($it['titulo']) ?></div>
                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.6rem;"><?= $it['tipo'] ?></span>
                            <span class="badge <?= $isNovo ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 0.6rem;">
                                <?= $isNovo ? 'NOVO' : 'USADO' ?>
                            </span>
                        </td>
                        <td>
                            <div class="small fw-bold"><?= htmlspecialchars($it['bairro']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($it['parceiro'] ?? 'Sem parceiro') ?></div>
                        </td>
                        <td>
                            <div class="text-danger fw-bold">R$ <?= number_format($it['preco'], 0, ',', '.') ?></div>
                            <div class="small text-muted">Cond: R$ <?= number_format($it['valor_condominio'], 0, ',', '.') ?></div>
                        </td>
                        <td>
                            <div class="small">
                                <span class="badge bg-light text-dark border"><?= $it['area'] ?>m²</span>
                                <span class="badge bg-light text-dark border"><?= $it['quartos'] ?>Q (<?= $it['suites'] ?>S)</span>
                                <span class="badge bg-light text-dark border"><?= $it['vagas_garagem'] ?>V</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $badge_class ?> p-2"><?= $badge_text ?></span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="form_triagem.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-pencil"></i></a>
                                <?php if($it['link_site']): ?>
                                    <a href="<?= $it['link_site'] ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelaTriagem').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "order": [[0, "desc"]],
        "pageLength": 100
    });

    document.querySelectorAll('.js-money').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, "");
            v = (v / 100).toFixed(2).replace(".", ",");
            v = v.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            e.target.value = v;
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>