<?php
// editar_imovel_parceiro.php
require_once '../../conn_cap.php';

session_start(); // apenas para mensagens flash (opcional)

$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$id  = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$erro = '';
$sucesso = '';

// Se não veio PIN ou ID, mostra aviso
if (empty($pin) || $id <= 0) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
            <h2>Acesso inválido</h2>
            <p>Link incompleto. Utilize o link completo enviado pelo parceiro.</p>
         </div>");
}

try {
    // 1. Validar o corretor pelo PIN
    $stmtCorretor = $conn->prepare("SELECT id, nome, creci FROM corretores WHERE codigo_acesso = ? AND status = 'Ativo' AND deleted_at IS NULL");
    $stmtCorretor->execute([$pin]);
    $corretor = $stmtCorretor->fetch(PDO::FETCH_ASSOC);

    if (!$corretor) {
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2 style='color:red;'>Código Inválido</h2>
                <p>O PIN fornecido não corresponde a um corretor ativo.</p>
             </div>");
    }

    // 2. Buscar o imóvel e verificar se pertence a este corretor
    $stmtImovel = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND corretor_id = ? AND deleted_at IS NULL");
    $stmtImovel->execute([$id, $corretor['id']]);
    $imovel = $stmtImovel->fetch(PDO::FETCH_ASSOC);

    if (!$imovel) {
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2 style='color:red;'>Imóvel não encontrado</h2>
                <p>Este imóvel não existe ou não pertence ao seu cadastro.</p>
             </div>");
    }

    // 3. Processar a atualização quando o formulário for enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Função de formatação de moeda (mesma usada no form_triagem)
        $f = function($v) { 
            return (float) str_replace(['.', ','], ['', '.'], $v); 
        };

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
            'andar'             => (int)$_POST['andar'],
            'face'              => $_POST['face'],
            'tipo'              => $_POST['tipo'],
            'conservacao'       => $_POST['conservacao'],
            'regime_marinha'    => $_POST['regime_marinha'],
            'link_site'         => $_POST['link_site'],
            'observacoes_gerais'=> $_POST['observacoes_gerais'], // <--- Corrigido para corresponder à tabela
            'entrega_obra'      => !empty($_POST['entrega_obra']) ? $_POST['entrega_obra'] . "-01" : null,
            'mobiliado'         => (int)isset($_POST['mobiliado']),
            'tem_piscina'       => (int)isset($_POST['tem_piscina']),
            'tem_academia'      => (int)isset($_POST['tem_academia']),
            'tem_salao_festas'  => (int)isset($_POST['tem_salao_festas']),
            'tem_espaco_gourmet'=> (int)isset($_POST['tem_espaco_gourmet']),
            'tem_playground'    => (int)isset($_POST['tem_playground']),
            'possui_elevador'   => (int)isset($_POST['possui_elevador']),
            'categoria_registro'=> $_POST['categoria_registro'] ?? 'triagem'
        ];

        // Monta SET para UPDATE
        $set = "";
        foreach ($dados as $key => $val) {
            $set .= "$key = ?, ";
        }
        $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ? AND corretor_id = ?";
        $params = array_values($dados);
        $params[] = $id;
        $params[] = $corretor['id'];

        $stmtUp = $conn->prepare($sql);
        if ($stmtUp->execute($params)) {
            $sucesso = "Imóvel atualizado com sucesso!";
            // Recarregar os dados do imóvel para mostrar os valores atualizados no formulário
            $stmtImovel = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND corretor_id = ? AND deleted_at IS NULL");
            $stmtImovel->execute([$id, $corretor['id']]);
            $imovel = $stmtImovel->fetch(PDO::FETCH_ASSOC);
        } else {
            $erro = "Erro ao salvar as alterações.";
        }
    }

} catch (PDOException $e) {
    $erro = "Erro no sistema: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Imóvel - <?= htmlspecialchars($corretor['nome'] ?? 'Parceiro') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .form-card { max-width: 1000px; margin: 2rem auto; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .price-input { font-weight: bold; border-color: #dc3545; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="card form-card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
            <h4 class="m-0"><i class="bi bi-pencil-square me-2"></i> Editar Imóvel (Parceiro)</h4>
            <small class="text-white-50">Corretor: <?= htmlspecialchars($corretor['nome']) ?> (PIN: <?= htmlspecialchars($pin) ?>)</small>
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
                <input type="hidden" name="id" value="<?= $id ?>">

                <!-- Linha 1: Título e Tipo -->
                <div class="col-md-8">
                    <label class="form-label fw-bold">Título / Nome do Imóvel</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="tipo" class="form-select">
                        <?php 
                        $tipos = ['apartamento', 'casa', 'studio', 'flat', 'comercial', 'terreno'];
                        foreach ($tipos as $t) {
                            $selected = ($imovel['tipo'] == $t) ? 'selected' : '';
                            echo "<option value='$t' $selected>" . ucfirst($t) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Linha 2: Endereço, Bairro, CEP -->
                <div class="col-md-5">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($imovel['cep']) ?>">
                </div>

                <!-- Linha 3: Preços e valores -->
                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">Preço de Venda</label>
                    <input type="text" name="preco" class="form-control js-money price-input" value="<?= number_format($imovel['preco'], 2, ',', '.') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor Condomínio</label>
                    <input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor IPTU</label>
                    <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Previsão Entrega (mês/ano)</label>
                    <input type="month" name="entrega_obra" class="form-control" value="<?= !empty($imovel['entrega_obra']) ? substr($imovel['entrega_obra'], 0, 7) : '' ?>">
                </div>

                <!-- Linha 4: Dimensões e quartos -->
                <div class="col-md-2">
                    <label class="form-label">Área (m²)</label>
                    <input type="number" step="0.01" name="area" class="form-control" value="<?= $imovel['area'] ?>">
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
                <div class="col-md-2">
                    <label class="form-label">Vagas</label>
                    <input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Andar</label>
                    <input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?>">
                </div>

                <!-- Linha 5: Face, Marinha, Conservação, Link e Categoria -->
                <div class="col-md-3">
                    <label class="form-label">Face</label>
                    <select name="face" class="form-select">
                        <option value="nascente" <?= $imovel['face'] == 'nascente' ? 'selected' : '' ?>>Nascente</option>
                        <option value="poente" <?= $imovel['face'] == 'poente' ? 'selected' : '' ?>>Poente</option>
                        <option value="norte" <?= $imovel['face'] == 'norte' ? 'selected' : '' ?>>Norte</option>
                        <option value="sul" <?= $imovel['face'] == 'sul' ? 'selected' : '' ?>>Sul</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Regime Marinha</label>
                    <select name="regime_marinha" class="form-select">
                        <option value="nenhum" <?= $imovel['regime_marinha'] == 'nenhum' ? 'selected' : '' ?>>Nenhum</option>
                        <option value="ocupacao" <?= $imovel['regime_marinha'] == 'ocupacao' ? 'selected' : '' ?>>Ocupação</option>
                        <option value="aforamento" <?= $imovel['regime_marinha'] == 'aforamento' ? 'selected' : '' ?>>Aforamento</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado de Conservação</label>
                    <select name="conservacao" class="form-select">
                        <option value="novo" <?= $imovel['conservacao'] == 'novo' ? 'selected' : '' ?>>Novo</option>
                        <option value="usado" <?= $imovel['conservacao'] == 'usado' ? 'selected' : '' ?>>Usado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Link (WhatsApp/Ref.)</label>
                    <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($imovel['link_site']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Categoria *</label>
                    <select name="categoria_registro" class="form-select" required>
                        <option value="oficial" <?= ($imovel['categoria_registro'] ?? '') == 'oficial' ? 'selected' : '' ?>>Oficial</option>
                        <option value="triagem" <?= ($imovel['categoria_registro'] ?? 'triagem') == 'triagem' ? 'selected' : '' ?>>Triagem</option>
                    </select>
                </div>

                <!-- Comodidades -->
                <div class="col-12">
                    <label class="form-label fw-bold">Infraestrutura e Lazer</label>
                    <div class="p-3 bg-light rounded border d-flex flex-wrap gap-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_piscina" <?= $imovel['tem_piscina'] ? 'checked' : '' ?>> <label>Piscina</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_academia" <?= $imovel['tem_academia'] ? 'checked' : '' ?>> <label>Academia</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_salao_festas" <?= $imovel['tem_salao_festas'] ? 'checked' : '' ?>> <label>Salão de Festas</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_espaco_gourmet" <?= $imovel['tem_espaco_gourmet'] ? 'checked' : '' ?>> <label>Espaço Gourmet</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_playground" <?= $imovel['tem_playground'] ? 'checked' : '' ?>> <label>Playground</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="possui_elevador" <?= $imovel['possui_elevador'] ? 'checked' : '' ?>> <label>Elevador</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="mobiliado" <?= $imovel['mobiliado'] ? 'checked' : '' ?>> <label>Mobiliado</label></div>
                    </div>
                </div>

                <!-- Observações -->
                <div class="col-12">
                    <label class="form-label">Observações Gerais</label>
                    <!-- Mantido name="observacoes_gerais" pois captura o valor correto enviado via POST -->
                    <textarea name="observacoes_gerais" class="form-control" rows="3"><?= htmlspecialchars($imovel['observacoes_gerais']) ?></textarea>
                </div>

                <div class="col-12 text-end pt-3">
                    <a href="portfolio_parceiro.php?pin=<?= urlencode($pin) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success px-5">Salvar Alterações</button>
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