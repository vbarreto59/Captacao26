<?php
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php'; 

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar Triagem' : 'Nova Triagem de Parceiro';

// 1. INICIALIZAÇÃO COM AS NOVAS OPÇÕES
$imovel = [
    'titulo' => '', 'bairro' => '', 'cidade' => 'Recife', 'preco' => 0.00,
    'area' => 0, 'quartos' => 0, 'corretor_id' => '', 'link_site' => '', 
    'observacoes_gerais' => '', 'entrega_obra' => '', 'andar' => '',
    'face' => 'nascente', 'mobiliado' => 0, 'regime_marinha' => 'nenhum',
    'valor_condominio' => 0.00, 'valor_iptu' => 0.00, 'tipo' => 'apartamento'
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) $imovel = array_merge($imovel, $res);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function($v) { return (float)str_replace(['.', ','], ['', '.'], $v); };
    
    $dados = [
        'titulo'            => $_POST['titulo'],
        'tipo'              => $_POST['tipo'], 
        'bairro'            => $_POST['bairro'],
        'cidade'            => $_POST['cidade'],
        'preco'             => $f($_POST['preco']),
        'valor_condominio'  => $f($_POST['valor_condominio']),
        'valor_iptu'        => $f($_POST['valor_iptu']),
        'area'              => (float)$_POST['area'],
        'quartos'           => (int)$_POST['quartos'],
        'andar'             => (int)$_POST['andar'],
        'face'              => $_POST['face'],
        'mobiliado'         => (int)$_POST['mobiliado'],
        'regime_marinha'    => $_POST['regime_marinha'],
        'corretor_id'       => !empty($_POST['corretor_id']) ? (int)$_POST['corretor_id'] : null,
        'link_site'         => $_POST['link_site'],
        'observacoes_gerais'=> $_POST['observacoes_gerais'],
        'entrega_obra'      => !empty($_POST['entrega_obra']) ? $_POST['entrega_obra'] . "-01" : null,
        'categoria_registro'=> 'triagem',
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
    } catch (PDOException $e) { $erro = $e->getMessage(); }
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container mt-4">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4">
            <i class="bi bi-check-circle-fill me-2"></i> Salvo com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header e Mensagens -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-primary m-0"><?= $modo ?> Imóvel</h2>
        <small class="text-muted">Cadastro técnico detalhado - CRECI-PE 22003</small>
    </div>
    <div class="d-flex gap-2">
        <!-- Botão para Corretores Parceiros -->
        <a href="corretores_parceiros.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people-fill"></i> Gerenciar Corretores
        </a>
        <a href="list.php" class="btn btn-light btn-sm border">
            <i class="bi bi-arrow-left"></i> Voltar à Lista
        </a>
        <span class="badge bg-info text-dark p-2 d-flex align-items-center">Versão 2026.1</span>
    </div>
</div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="m-0"><i class="bi bi-funnel"></i> <?= $modo ?></h5>
            <a href="form_triagem.php" class="btn btn-sm btn-outline-light <?= $id == 0 ? 'd-none' : '' ?>">Novo</a>
        </div>
        <div class="card-body bg-white shadow-sm">
            <form method="post" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-primary">Tipo</label>
                    <select name="tipo" class="form-select border-primary shadow-sm">
                        <option value="apartamento" <?= $imovel['tipo']=='apartamento'?'selected':'' ?>>Apartamento</option>
                        <option value="studio" <?= $imovel['tipo']=='studio'?'selected':'' ?>>Studio</option>
                        <option value="flat" <?= $imovel['tipo']=='flat'?'selected':'' ?>>Flat</option>
                        <option value="casa" <?= $imovel['tipo']=='casa'?'selected':'' ?>>Casa</option>
                        <option value="comercial" <?= $imovel['tipo']=='comercial'?'selected':'' ?>>Comercial</option>
                        <option value="terreno" <?= $imovel['tipo']=='terreno'?'selected':'' ?>>Terreno</option>
                        <option value="outro" <?= $imovel['tipo']=='outro'?'selected':'' ?>>Outro</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Corretor Parceiro</label>
                    <select name="corretor_id" class="form-select border-primary" required>
                        <option value="">Selecione...</option>
                        <?php
                        $corretores = $conn->query("SELECT id, nome FROM corretores ORDER BY nome")->fetchAll();
                        foreach($corretores as $c) {
                            $sel = ($imovel['corretor_id'] == $c['id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>{$c['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Edifício</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">Valor Venda</label>
                    <input type="text" name="preco" class="form-control js-money fw-bold text-danger border-danger" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>">
                </div>

                <!-- DEMAIS CAMPOS -->
                <div class="col-md-2">
                    <label class="form-label">Condomínio</label>
                    <input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">IPTU</label>
                    <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mês/Ano Entrega</label>
                    <input type="month" name="entrega_obra" class="form-control" value="<?= !empty($imovel['entrega_obra']) ? substr($imovel['entrega_obra'], 0, 7) : '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Andar</label>
                    <input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Área m²</label>
                    <input type="number" step="0.01" name="area" class="form-control" value="<?= $imovel['area'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quartos</label>
                    <input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Posição</label>
                    <select name="face" class="form-select">
                        <option value="nascente" <?= $imovel['face']=='nascente'?'selected':'' ?>>Nascente</option>
                        <option value="poente" <?= $imovel['face']=='poente'?'selected':'' ?>>Poente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mobiliado?</label>
                    <select name="mobiliado" class="form-select">
                        <option value="0" <?= $imovel['mobiliado']==0?'selected':'' ?>>Não</option>
                        <option value="1" <?= $imovel['mobiliado']==1?'selected':'' ?>>Sim</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marinha</label>
                    <select name="regime_marinha" class="form-select border-info">
                        <option value="nenhum" <?= $imovel['regime_marinha']=='nenhum'?'selected':'' ?>>Não (Alodial)</option>
                        <option value="ocupacao" <?= $imovel['regime_marinha']=='ocupacao'?'selected':'' ?>>Ocupação</option>
                        <option value="aforamento" <?= $imovel['regime_marinha']=='aforamento'?'selected':'' ?>>Aforamento</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Link Anúncio / WhatsApp</label>
                    <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($imovel['link_site']) ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes_gerais" class="form-control" rows="2"><?= htmlspecialchars($imovel['observacoes_gerais']) ?></textarea>
                </div>

                <input type="hidden" name="cidade" value="<?= $imovel['cidade'] ?>">

                <div class="col-12 text-end border-top pt-3">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow">
                        <i class="bi bi-save"></i> SALVAR TRIAGEM
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTA (ÚLTIMAS 15) -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Tipo / Imóvel</th>
                        <th>Bairro</th>
                        <th>Valores</th>
                        <th>Resumo</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res_lista = $conn->query("SELECT i.*, c.nome as parceiro FROM imoveis i LEFT JOIN corretores c ON i.corretor_id = c.id WHERE i.deleted_at IS NULL ORDER BY i.id DESC LIMIT 15")->fetchAll();
                    foreach ($res_lista as $it):
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;"><?= $it['tipo'] ?></span>
                            <div class="fw-bold"><?= htmlspecialchars($it['titulo']) ?></div>
                            <small class="text-muted"><?= $it['parceiro'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($it['bairro']) ?></td>
                        <td>
                            <div class="text-danger fw-bold">R$ <?= number_format($it['preco'], 0, ',', '.') ?></div>
                            <small class="text-muted">Cond: <?= number_format($it['valor_condominio'], 0, ',', '.') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= $it['area'] ?>m²</span>
                            <span class="badge bg-light text-dark border"><?= $it['andar'] ?>º andar</span>
                            <span class="badge bg-light text-dark border"><?= ucfirst($it['face']) ?></span>
                        </td>
                        <td class="text-center">
                            <a href="form_triagem.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if($it['link_site']): ?>
                                <a href="<?= $it['link_site'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-whatsapp"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.js-money').forEach(function(input) {
    input.addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, "");
        v = (v / 100).toFixed(2).replace(".", ",");
        v = v.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        e.target.value = v;
    });
});
</script>
<?php require_once '../../includes/footer.php'; ?>