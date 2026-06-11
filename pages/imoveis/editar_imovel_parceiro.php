<?php
// editar_imovel_parceiro.php
require_once '../../conn_cap.php';

session_start(); // apenas para mensagens flash (opcional)

$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$id  = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$erro = '';
$sucesso = '';

// Se o PIN estiver vazio, busca o PIN do corretor de ID 1 no banco (Fallback)
if (empty($pin) && $id > 0) {
    try {
        $stmtPadrao = $conn->prepare("SELECT codigo_acesso FROM corretores WHERE id = 1 AND status = 'Ativo' AND deleted_at IS NULL");
        $stmtPadrao->execute();
        $corretorPadrao = $stmtPadrao->fetch(PDO::FETCH_ASSOC);
        
        if ($corretorPadrao && !empty($corretorPadrao['codigo_acesso'])) {
            $pin = $corretorPadrao['codigo_acesso'];
        }
    } catch (PDOException $e) {
        // Silencioso
    }
}

// Se mesmo após a tentativa de fallback o PIN continuar vazio ou o ID for inválido, mostra aviso
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

    // Buscar listas de Proprietários e Corretores para os selects do formulário
    $listaProprietarios = $conn->query("SELECT id, nome FROM proprietarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $listaCorretores    = $conn->query("SELECT id, nome, creci FROM corretores WHERE deleted_at IS NULL ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Processar a EXCLUSÃO LÓGICA (Soft Delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_imovel']) && $_POST['acao_imovel'] === 'excluir') {
        $stmtDel = $conn->prepare("UPDATE imoveis SET deleted_at = NOW() WHERE id = ? AND corretor_id = ? AND deleted_at IS NULL");
        if ($stmtDel->execute([$id, $imovel['corretor_id'] ?? $corretor['id']])) {
            $sucesso = "Imóvel excluído com sucesso! Você pode revertê-lo abaixo se necessário.";
        } else {
            $erro = "Erro ao tentar excluir o imóvel.";
        }
    }

    // 3. Processar a RESTAURAÇÃO DO IMÓVEL (Reverter exclusão)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_imovel']) && $_POST['acao_imovel'] === 'restaurar') {
        $stmtRest = $conn->prepare("UPDATE imoveis SET deleted_at = NULL WHERE id = ? AND corretor_id = ? AND deleted_at IS NOT NULL");
        if ($stmtRest->execute([$id, $imovel['corretor_id'] ?? $corretor['id']])) {
            $sucesso = "Imóvel restaurado com sucesso! Os campos de edição foram liberados.";
        } else {
            $erro = "Erro ao tentar restaurar o imóvel.";
        }
    }

    // 4. Buscar o imóvel (Busca direta por ID para permitir trânsito de corretores)
    $stmtImovel = $conn->prepare("SELECT * FROM imoveis WHERE id = ?");
    $stmtImovel->execute([$id]);
    $imovel = $stmtImovel->fetch(PDO::FETCH_ASSOC);

    if (!$imovel) {
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2 style='color:red;'>Imóvel não encontrado</h2>
                <p>Este imóvel não existe no sistema.</p>
             </div>");
    }

    // Define se o imóvel está atualmente excluído
    $estaExcluido = !empty($imovel['deleted_at']);

    // 5. Processar a atualização quando o formulário convencional for enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao_imovel'])) {
        if ($estaExcluido) {
            $erro = "Não é possível editar um imóvel excluído. Restaure-o primeiro.";
        } else {
            // Função de formatação de moeda
            $f = function($v) { 
                return (float) str_replace(['.', ','], ['', '.'], $v); 
            };

            $dados = [
                'proprietario_id'   => (int)$_POST['proprietario_id'],
                'corretor_id'       => (int)$_POST['corretor_id'],
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
                'observacoes_gerais'=> $_POST['observacoes_gerais'],
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
            $sql = "UPDATE imoveis SET " . rtrim($set, ', ') . " WHERE id = ?";
            $params = array_values($dados);
            $params[] = $id;

            $stmtUp = $conn->prepare($sql);
            if ($stmtUp->execute($params)) {
                $sucesso = "Imóvel atualizado com sucesso!";
                
                // Recarregar os dados atualizados do imóvel
                $stmtImovel = $conn->prepare("SELECT * FROM imoveis WHERE id = ?");
                $stmtImovel->execute([$id]);
                $imovel = $stmtImovel->fetch(PDO::FETCH_ASSOC);
            } else {
                $erro = "Erro ao salvar as alterações.";
            }
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
            <small class="text-white-50">Sessão iniciada via: <?= htmlspecialchars($corretor['nome']) ?> (PIN: <?= htmlspecialchars($pin) ?>)</small>
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

            <?php if ($estaExcluido): ?>
                <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between p-3 mb-4">
                    <div>
                        <i class="bi bi-trash text-danger fs-4 me-3"></i>
                        <span>Este imóvel está <strong>Excluído (Inativo)</strong>. Os dados abaixo estão congelados.</span>
                    </div>
                    <form method="post" class="m-0">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="acao_imovel" value="restaurar">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar Imóvel
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">

                <?php $disabled = $estaExcluido ? 'disabled' : ''; ?>

                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary"><i class="bi bi-person-badge"></i> Proprietário Vinculado</label>
                    <select name="proprietario_id" class="form-select bg-white border-primary-subtle" required <?= $disabled ?>>
                        <option value="">-- Selecione o Proprietário --</option>
                        <?php foreach ($listaProprietarios as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $imovel['proprietario_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (ID: <?= $p['id'] ?>)
                            </option>
                        <?php endforeach; ?> 
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary"><i class="bi bi-person-gear"></i> Corretor Responsável</label>
                    <select name="corretor_id" class="form-select bg-white border-primary-subtle" required <?= $disabled ?>>
                        <option value="">-- Selecione o Corretor --</option>
                        <?php foreach ($listaCorretores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $imovel['corretor_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?> <?= !empty($c['creci']) ? "- CRECI " . htmlspecialchars($c['creci']) : '' ?>
                            </option>
                        <?php endforeach; ?> 
                    </select>
                </div>

                <hr class="my-4 text-muted opacity-25">

                <div class="col-md-8">
                    <label class="form-label fw-bold">Título / Nome do Imóvel</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($imovel['titulo'] ?? '') ?>" required <?= $disabled ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="tipo" class="form-select" <?= $disabled ?>>
                        <?php 
                        $tipos = ['apartamento', 'casa', 'studio', 'flat', 'comercial', 'terreno'];
                        foreach ($tipos as $t) {
                            $selected = ($imovel['tipo'] == $t) ? 'selected' : '';
                            echo "<option value='$t' $selected>" . ucfirst($t) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($imovel['endereco'] ?? '') ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($imovel['bairro'] ?? '') ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($imovel['cep'] ?? '') ?>" <?= $disabled ?>>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-danger">Preço de Venda</label>
                    <input type="text" name="preco" class="form-control js-money price-input" value="<?= number_format($imovel['preco'] ?? 0, 2, ',', '.') ?>" required <?= $disabled ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor Condomínio</label>
                    <input type="text" name="valor_condominio" class="form-control js-money" value="<?= number_format($imovel['valor_condominio'] ?? 0, 2, ',', '.') ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor IPTU</label>
                    <input type="text" name="valor_iptu" class="form-control js-money" value="<?= number_format($imovel['valor_iptu'] ?? 0, 2, ',', '.') ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Previsão Entrega (mês/ano)</label>
                    <input type="month" name="entrega_obra" class="form-control" value="<?= !empty($imovel['entrega_obra']) ? substr($imovel['entrega_obra'], 0, 7) : '' ?>" <?= $disabled ?>>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Área (m²)</label>
                    <input type="number" step="0.01" name="area" class="form-control" value="<?= $imovel['area'] ?? '' ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quartos</label>
                    <input type="number" name="quartos" class="form-control" value="<?= $imovel['quartos'] ?? '' ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Suítes</label>
                    <input type="number" name="suites" class="form-control" value="<?= $imovel['suites'] ?? '' ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Banheiros</label>
                    <input type="number" name="banheiros" class="form-control" value="<?= $imovel['banheiros'] ?? '' ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Vagas</label>
                    <input type="number" name="vagas_garagem" class="form-control" value="<?= $imovel['vagas_garagem'] ?? '' ?>" <?= $disabled ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Andar</label>
                    <input type="number" name="andar" class="form-control" value="<?= $imovel['andar'] ?? '' ?>" <?= $disabled ?>>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Face</label>
                    <select name="face" class="form-select" <?= $disabled ?>>
                        <option value="nascente" <?= ($imovel['face'] ?? '') == 'nascente' ? 'selected' : '' ?>>Nascente</option>
                        <option value="poente" <?= ($imovel['face'] ?? '') == 'poente' ? 'selected' : '' ?>>Poente</option>
                        <option value="norte" <?= ($imovel['face'] ?? '') == 'norte' ? 'selected' : '' ?>>Norte</option>
                        <option value="sul" <?= ($imovel['face'] ?? '') == 'sul' ? 'selected' : '' ?>>Sul</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Regime Marinha</label>
                    <select name="regime_marinha" class="form-select" <?= $disabled ?>>
                        <option value="nenhum" <?= ($imovel['regime_marinha'] ?? '') == 'nenhum' ? 'selected' : '' ?>>Nenhum</option>
                        <option value="ocupacao" <?= ($imovel['regime_marinha'] ?? '') == 'ocupacao' ? 'selected' : '' ?>>Ocupação</option>
                        <option value="aforamento" <?= ($imovel['regime_marinha'] ?? '') == 'aforamento' ? 'selected' : '' ?>>Aforamento</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado de Conservação</label>
                    <select name="conservacao" class="form-select" <?= $disabled ?>>
                        <option value="novo" <?= ($imovel['conservacao'] ?? '') == 'novo' ? 'selected' : '' ?>>Novo</option>
                        <option value="usado" <?= ($imovel['conservacao'] ?? '') == 'usado' ? 'selected' : '' ?>>Usado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Categoria *</label>
                    <select name="categoria_registro" class="form-select" required <?= $disabled ?>>
                        <option value="oficial" <?= ($imovel['categoria_registro'] ?? '') == 'oficial' ? 'selected' : '' ?>>Oficial</option>
                        <option value="triagem" <?= ($imovel['categoria_registro'] ?? 'triagem') == 'triagem' ? 'selected' : '' ?>>Triagem</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold text-secondary"><i class="bi bi-link-45deg"></i> Link (WhatsApp/Ref.)</label>
                    <input type="url" name="link_site" class="form-control" value="<?= htmlspecialchars($imovel['link_site'] ?? '') ?>" placeholder="https://" <?= $disabled ?>>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Infraestrutura e Lazer</label>
                    <div class="p-3 bg-light rounded border d-flex flex-wrap gap-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_piscina" <?= (!empty($imovel['tem_piscina'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Piscina</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_academia" <?= (!empty($imovel['tem_academia'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Academia</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_salao_festas" <?= (!empty($imovel['tem_salao_festas'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Salão de Festas</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_espaco_gourmet" <?= (!empty($imovel['tem_espaco_gourmet'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Espaço Gourmet</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tem_playground" <?= (!empty($imovel['tem_playground'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Playground</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="possui_elevador" <?= (!empty($imovel['possui_elevador'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Elevador</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="mobiliado" <?= (!empty($imovel['mobiliado'])) ? 'checked' : '' ?> <?= $disabled ?>> <label>Mobiliado</label></div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Observações Gerais</label>
                    <textarea name="observacoes_gerais" class="form-control" rows="3" <?= $disabled ?>><?= htmlspecialchars($imovel['observacoes_gerais'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center pt-3">
                    <div>
                        <?php if (!$estaExcluido): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluir">
                                <i class="bi bi-trash3 me-1"></i> Excluir Imóvel
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="portfolio_parceiro.php?pin=<?= urlencode($pin) ?>" class="btn btn-outline-secondary me-2">Voltar ao Portfólio</a>
                        <?php if (!$estaExcluido): ?>
                            <button type="submit" class="btn btn-success px-5">Salvar Alterações</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!$estaExcluido): ?>
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalExcluirLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirmar Exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza de que deseja excluir o imóvel <strong>"<?= htmlspecialchars($imovel['titulo'] ?? '') ?>"</strong>?</p>
                <p class="text-muted small">O imóvel deixará de aparecer nas buscas ativas, mas você poderá reverter essa exclusão nesta mesma página a qualquer momento.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="acao_imovel" value="excluir">
                    <button type="submit" class="btn btn-danger">Sim, Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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