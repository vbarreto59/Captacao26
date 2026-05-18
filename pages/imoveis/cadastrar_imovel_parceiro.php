<?php
// cadastrar_imovel_parceiro.php
require_once '../../conn_cap.php';

session_start();
$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$erro = '';
$sucesso = '';

if (empty($pin)) {
    die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
            <h2>Acesso Restrito</h2>
            <p>Link inválido. Utilize o link enviado pelo parceiro.</p>
         </div>");
}

// Valida o corretor
try {
    $stmt = $conn->prepare("SELECT id, nome, creci FROM corretores WHERE codigo_acesso = ? AND status = 'Ativo' AND deleted_at IS NULL");
    $stmt->execute([$pin]);
    $corretor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$corretor) {
        die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
                <h2 style='color:red;'>PIN Inválido</h2>
                <p>Não foi possível identificar o corretor.</p>
             </div>");
    }
} catch (PDOException $e) {
    die("Erro ao validar acesso: " . $e->getMessage());
}

// Processa o cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function($v) { return (float)str_replace(['.', ','], ['', '.'], $v); };

    $dados = [
        'titulo'            => $_POST['titulo'] ?? '',
        'endereco'          => $_POST['endereco'] ?? '',
        'bairro'            => $_POST['bairro'] ?? '',
        'cep'               => $_POST['cep'] ?? '',
        'preco'             => $f($_POST['preco']),
        'valor_condominio'  => $f($_POST['valor_condominio']),
        'valor_iptu'        => $f($_POST['valor_iptu']),
        'area'              => (float)($_POST['area'] ?? 0),
        'quartos'           => (int)($_POST['quartos'] ?? 0),
        'suites'            => (int)($_POST['suites'] ?? 0),
        'banheiros'         => (int)($_POST['banheiros'] ?? 0),
        'vagas_garagem'     => (int)($_POST['vagas_garagem'] ?? 0),
        'andar'             => (int)($_POST['andar'] ?? 0),
        'face'              => $_POST['face'] ?? 'nascente',
        'tipo'              => $_POST['tipo'] ?? 'apartamento',
        'conservacao'       => $_POST['conservacao'] ?? 'usado',
        'regime_marinha'    => $_POST['regime_marinha'] ?? 'nenhum',
        'corretor_id'       => $corretor['id'],
        'link_site'         => $_POST['link_site'] ?? '',
        'observacoes_gerais'=> $_POST['observacoes_gerais'] ?? '',
        'entrega_obra'      => !empty($_POST['entrega_obra']) ? $_POST['entrega_obra'] . "-01" : null,
        'mobiliado'         => isset($_POST['mobiliado']) ? 1 : 0,
        'tem_piscina'       => isset($_POST['tem_piscina']) ? 1 : 0,
        'tem_academia'      => isset($_POST['tem_academia']) ? 1 : 0,
        'tem_salao_festas'  => isset($_POST['tem_salao_festas']) ? 1 : 0,
        'tem_espaco_gourmet'=> isset($_POST['tem_espaco_gourmet']) ? 1 : 0,
        'tem_playground'    => isset($_POST['tem_playground']) ? 1 : 0,
        'possui_elevador'   => isset($_POST['possui_elevador']) ? 1 : 0,
        'categoria_registro'=> $_POST['categoria_registro'] ?? 'triagem', // Agora recebe o valor do formulário
        'status'            => 'parceria'
    ];

    try {
        $cols = implode(", ", array_keys($dados));
        $pls = implode(", ", array_fill(0, count($dados), "?"));
        $sql = "INSERT INTO imoveis ($cols) VALUES ($pls)";
        $stmtIns = $conn->prepare($sql);
        $stmtIns->execute(array_values($dados));
        $sucesso = "Imóvel cadastrado com sucesso! ID: " . $conn->lastInsertId();
        $_POST = [];
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Imóvel - <?= htmlspecialchars($corretor['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; }
        .form-card { max-width: 1000px; margin: 2rem auto; border-radius: 20px; }
    </style>
</head>
<body>
<div class="container my-4">
    <div class="card form-card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="m-0"><i class="bi bi-house-add me-2"></i> Cadastrar Novo Imóvel</h4>
            <small>Parceiro: <?= htmlspecialchars($corretor['nome']) ?> | PIN: <?= htmlspecialchars($pin) ?></small>
        </div>
        <div class="card-body p-4">
            <?php if ($sucesso): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $sucesso ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $erro ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">

                <div class="col-md-8">
                    <label class="form-label fw-bold">Título / Nome do Imóvel *</label>
                    <input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="tipo" class="form-select">
                        <?php $tipos = ['apartamento','casa','studio','flat','comercial','terreno']; ?>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t ?>" <?= (($_POST['tipo'] ?? 'apartamento') == $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($_POST['endereco'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($_POST['bairro'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($_POST['cep'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">Preço de Venda *</label>
                    <input type="text" name="preco" class="form-control js-money" required value="<?= number_format($_POST['preco'] ?? 0, 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condomínio</label>
                    <input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($_POST['valor_condominio'] ?? 0, 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">IPTU</label>
                    <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($_POST['valor_iptu'] ?? 0, 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Previsão Entrega (mês/ano)</label>
                    <input type="month" name="entrega_obra" class="form-control" value="<?= $_POST['entrega_obra'] ?? '' ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Área (m²)</label>
                    <input type="number" step="0.01" name="area" class="form-control" value="<?= $_POST['area'] ?? 0 ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Quartos</label>
                    <input type="number" name="quartos" class="form-control" value="<?= $_POST['quartos'] ?? 0 ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Suítes</label>
                    <input type="number" name="suites" class="form-control" value="<?= $_POST['suites'] ?? 0 ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Banheiros</label>
                    <input type="number" name="banheiros" class="form-control" value="<?= $_POST['banheiros'] ?? 0 ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Vagas</label>
                    <input type="number" name="vagas_garagem" class="form-control" value="<?= $_POST['vagas_garagem'] ?? 0 ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Andar</label>
                    <input type="number" name="andar" class="form-control" value="<?= $_POST['andar'] ?? 0 ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Face</label>
                    <select name="face" class="form-select">
                        <?php $faces = ['nascente','poente','norte','sul']; ?>
                        <?php foreach ($faces as $f): ?>
                            <option value="<?= $f ?>" <?= (($_POST['face'] ?? 'nascente') == $f) ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Regime Marinha</label>
                    <select name="regime_marinha" class="form-select">
                        <option value="nenhum" <?= (($_POST['regime_marinha'] ?? 'nenhum') == 'nenhum') ? 'selected' : '' ?>>Nenhum</option>
                        <option value="ocupacao" <?= (($_POST['regime_marinha'] ?? '') == 'ocupacao') ? 'selected' : '' ?>>Ocupação</option>
                        <option value="aforamento" <?= (($_POST['regime_marinha'] ?? '') == 'aforamento') ? 'selected' : '' ?>>Aforamento</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Conservação</label>
                    <select name="conservacao" class="form-select">
                        <option value="novo" <?= (($_POST['conservacao'] ?? 'usado') == 'novo') ? 'selected' : '' ?>>Novo</option>
                        <option value="usado" <?= (($_POST['conservacao'] ?? 'usado') == 'usado') ? 'selected' : '' ?>>Usado</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Link (WhatsApp/Ref.)</label>
                    <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($_POST['link_site'] ?? '') ?>">
                </div>

                <!-- CAMPO CATEGORIA REGISTRO ADICIONADO -->
                <div class="col-md-2">
                    <label class="form-label fw-bold">Categoria *</label>
                    <select name="categoria_registro" class="form-select" required>
                        <option value="oficial" <?= (($_POST['categoria_registro'] ?? '') == 'oficial') ? 'selected' : '' ?>>Oficial</option>
                        <option value="triagem" <?= (($_POST['categoria_registro'] ?? 'triagem') == 'triagem') ? 'selected' : '' ?>>Triagem</option>
                    </select>
                </div>

                <!-- Comodidades -->
                <div class="col-12">
                    <label class="form-label fw-bold">Infraestrutura e Lazer</label>
                    <div class="p-3 bg-light rounded border d-flex flex-wrap gap-4">
                        <?php $comodidades = ['tem_piscina'=>'Piscina','tem_academia'=>'Academia','tem_salao_festas'=>'Salão Festas','tem_espaco_gourmet'=>'Gourmet','tem_playground'=>'Playground','possui_elevador'=>'Elevador','mobiliado'=>'Mobiliado']; ?>
                        <?php foreach ($comodidades as $campo => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= $campo ?>" <?= isset($_POST[$campo]) ? 'checked' : '' ?>>
                                <label><?= $label ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Observações Gerais</label>
                    <textarea name="observacoes_gerais" class="form-control" rows="3"><?= htmlspecialchars($_POST['observacoes_gerais'] ?? '') ?></textarea>
                </div>

                <div class="col-12 text-end pt-3">
                    <a href="ver_imoveis.php?pin=<?= urlencode($pin) ?>" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success px-5">Cadastrar Imóvel</button>
                </div>
            </form>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>