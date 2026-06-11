<?php
require_once '../../conn_cap.php';

// Recebe o PIN (codigo_acesso) da URL
$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';

if (empty($pin)) {
    die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
            <h2>Acesso Restrito</h2>
            <p>Por favor, utilize o link completo enviado pelo administrador.</p>
         </div>");
}

try {
    // 1. Validar o Corretor pelo PIN
    $stmt = $conn->prepare("SELECT id, nome, creci FROM corretores WHERE codigo_acesso = ? AND status = 'Ativo' AND deleted_at IS NULL");
    $stmt->execute([$pin]);
    $corretor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$corretor) {
        die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
                <h2 style='color:red;'>Código Inválido</h2>
                <p>O acesso não foi autorizado para este código.</p>
             </div>");
    }

    // 2. Buscar todos os imóveis do corretor, ordenados por preço crescente (incluindo excluídos para listagem interna do parceiro)
    $stmtI = $conn->prepare("SELECT * FROM imoveis WHERE corretor_id = ? AND deleted_at IS NULL ORDER BY preco ASC");
    $stmtI->execute([$corretor['id']]);
    $imoveis = $stmtI->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro no sistema: " . $e->getMessage());
}

// Coleta valores únicos para os filtros (a partir dos imóveis disponíveis)
$tipos = [];
$bairros = [];
$quartos_opcoes = [];
foreach ($imoveis as $i) {
    if (!empty($i['tipo'])) $tipos[] = $i['tipo'];
    if (!empty($i['bairro'])) $bairros[] = $i['bairro'];
    if (!empty($i['quartos']) && is_numeric($i['quartos'])) $quartos_opcoes[] = (int)$i['quartos'];
}
$tipos = array_unique($tipos);
$bairros = array_unique($bairros);
$quartos_opcoes = array_unique($quartos_opcoes);
sort($quartos_opcoes);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfólio - <?= htmlspecialchars($corretor['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-property { border: 1px solid rgba(0,0,0,0.08); border-radius: 12px; transition: 0.3s; background: #fff; height: 100%; display: flex; flex-direction: column; }
        .card-property:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }
        .status-badge { position: absolute; top: 15px; right: 15px; z-index: 10; display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
        .price { font-size: 1.4rem; color: #2c3e50; font-weight: 800; }
        .detail-icon { display: inline-flex; align-items: center; gap: 4px; background: #f0f2f5; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; }
        .amenities i, .info-row i { width: 20px; color: #0d6efd; }
        .info-row { font-size: 0.8rem; margin-bottom: 4px; }
        .card-body { flex: 1; }
        .divider { border-top: 1px dashed #dee2e6; margin: 0.5rem 0; }
        .filter-bar { background: white; border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        
        /* Modificadores customizados */
        .badge-orange { background-color: #fd7e14 !important; color: #ffffff !important; }
        
        /* Estilo sutil para o card excluído */
        .card-excluido { opacity: 0.75; border: 1px solid #dc3545 !important; background-color: #fffdfd; }
        .card-excluido:hover { box-shadow: 0 12px 25px rgba(220, 53, 69, 0.15); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center mb-4">
        <div class="col-md-8 text-center">
            <h1 class="display-6 fw-bold text-dark">Imóveis com Parcerias</h1>
            <p class="text-muted">Corretor(a) Parceiro(a): <strong><?= htmlspecialchars($corretor['nome']) ?></strong></p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge bg-secondary">CRECI: <?= $corretor['creci'] ?></span>
                <span class="badge bg-outline-dark border text-dark">Total: <?= count($imoveis) ?> itens</span>
                <a href="cadastrar_imovel_parceiro.php?pin=<?= urlencode($pin) ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> Novo Imóvel
                </a>
            </div>
            <div class="alert alert-info mt-3 py-2 small" role="alert">
                <i class="bi bi-pencil-square"></i> Você pode <strong>editar</strong> qualquer imóvel clicando no botão "Editar" no card.
            </div>
        </div>
    </div>

    <!-- BARRA DE FILTROS -->
    <div class="filter-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold"><i class="bi bi-building"></i> Tipo de Imóvel</label>
                <select id="filtroTipo" class="form-select">
                    <option value="todos">Todos</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><i class="bi bi-geo-alt"></i> Bairro</label>
                <select id="filtroBairro" class="form-select">
                    <option value="todos">Todos</option>
                    <?php foreach ($bairros as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><i class="bi bi-door-open"></i> Quartos</label>
                <select id="filtroQuartos" class="form-select">
                    <option value="todos">Todos</option>
                    <?php foreach ($quartos_opcoes as $q): ?>
                        <option value="<?= $q ?>"><?= $q ?> quarto(s)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button id="btnLimparFiltros" class="btn btn-outline-secondary w-100"><i class="bi bi-eraser"></i> Limpar Filtros</button>
            </div>
        </div>
    </div>

    <?php if (empty($imoveis)): ?>
        <div class="text-center py-5">
            <i class="bi bi-house-exclamation display-1 text-muted"></i>
            <p class="fs-5 text-secondary mt-3">Nenhum imóvel encontrado nesta lista.</p>
        </div>
    <?php endif; ?>

    <div class="row g-4" id="cardsContainer">
        <?php foreach ($imoveis as $i): 
            $idFormatado = str_pad($i['id'], 3, '0', STR_PAD_LEFT);
            $estaExcluido = !empty($i['deleted_at']);
        ?>
            <div class="col-md-6 col-lg-4 card-item" 
                 data-tipo="<?= htmlspecialchars($i['tipo']) ?>" 
                 data-bairro="<?= htmlspecialchars($i['bairro']) ?>" 
                 data-quartos="<?= $i['quartos'] ?>">
                
                <!-- Injeta a classe CSS "card-excluido" se o imóvel estiver deletado logicamente -->
                <div class="card card-property shadow-sm position-relative <?= $estaExcluido ? 'card-excluido' : '' ?>">
                    
                    <div class="status-badge">
                        <!-- Badge Vermelho de Exclusão (Aparece no topo direito do Card) -->
                        <?php if ($estaExcluido): ?>
                            <span class="badge bg-danger text-uppercase"><i class="bi bi-trash-fill"></i> Excluído</span>
                        <?php endif; ?>

                        <span class="badge bg-dark text-uppercase"><?= htmlspecialchars($i['tipo']) ?></span>
                        

                    </div>
                    
                    <div class="card-body pt-4">
                        <small class="text-uppercase text-muted fw-bold">
                            #<?= $idFormatado ?> • <?= htmlspecialchars($i['bairro']) ?>
                        </small>
                        <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($i['titulo']) ?></h5>
                        
                        <div class="price mb-2">R$ <?= number_format($i['preco'], 0, ',', '.') ?></div>
                        
                        <div class="row g-1 mb-2">
                            <div class="col-6">
                                <div class="info-row"><i class="bi bi-building"></i> Condomínio: R$ <?= number_format($i['valor_condominio'], 2, ',', '.') ?></div>
                            </div>
                            <div class="col-6">
                                <div class="info-row"><i class="bi bi-receipt"></i> IPTU: R$ <?= number_format($i['valor_iptu'], 2, ',', '.') ?></div>
                            </div>
                        </div>

                        <?php if(!empty($i['regime_marinha']) && $i['regime_marinha'] != 'nenhum'): ?>
                            <div class="info-row"><i class="bi bi-water"></i> Regime Marinha: <span class="badge bg-secondary"><?= ucfirst($i['regime_marinha']) ?></span></div>
                        <?php endif; ?>

                        <div class="row g-1 mb-2">
                            <?php if(!empty($i['face']) && $i['face'] != ''): ?>
                                <div class="col-6"><div class="info-row"><i class="bi bi-sun"></i> Face: <?= ucfirst($i['face']) ?></div></div>
                            <?php endif; ?>
                            <?php if(!empty($i['andar']) && $i['andar'] > 0): ?>
                                <div class="col-6"><div class="info-row"><i class="bi bi-building"></i> Andar: <?= $i['andar'] ?>º</div></div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($i['entrega_obra']) && $i['conservacao'] == 'novo'): ?>
                            <div class="info-row"><i class="bi bi-calendar-event"></i> Entrega prevista: <?= date('m/Y', strtotime($i['entrega_obra'])) ?></div>
                        <?php endif; ?>

                        <div class="divider"></div>

                        <div class="d-flex flex-wrap gap-2 my-2">
                            <?php if($i['area'] > 0): ?>
                                <span class="detail-icon"><i class="bi bi-arrows-fullscreen"></i> <?= $i['area'] ?>m²</span>
                            <?php endif; ?>
                            <?php if($i['quartos'] > 0): ?>
                                <span class="detail-icon"><i class="bi bi-door-open"></i> <?= $i['quartos'] ?> Qts</span>
                            <?php endif; ?>
                            <?php if($i['suites'] > 0): ?>
                                <span class="detail-icon"><i class="bi bi-star-fill text-warning"></i> <?= $i['suites'] ?> Suíte(s)</span>
                            <?php endif; ?>
                            <?php if($i['banheiros'] > 0): ?>
                                <span class="detail-icon"><i class="bi bi-droplet"></i> <?= $i['banheiros'] ?> WC</span>
                            <?php endif; ?>
                            <?php if($i['vagas_garagem'] > 0): ?>
                                <span class="detail-icon"><i class="bi bi-car-front"></i> <?= $i['vagas_garagem'] ?> Vaga(s)</span>
                            <?php endif; ?>
                        </div>

                        <div class="amenities d-flex flex-wrap gap-3 mt-2 pt-2 border-top">
                            <?php if($i['tem_piscina']): ?>
                                <span><i class="bi bi-water"></i> Piscina</span>
                            <?php endif; ?>
                            <?php if($i['tem_academia']): ?>
                                <span><i class="bi bi-dumbbell"></i> Academia</span>
                            <?php endif; ?>
                            <?php if($i['tem_salao_festas']): ?>
                                <span><i class="bi bi-balloon"></i> Salão Festas</span>
                            <?php endif; ?>
                            <?php if($i['tem_espaco_gourmet']): ?>
                                <span><i class="bi bi-egg-fried"></i> Gourmet</span>
                            <?php endif; ?>
                            <?php if($i['tem_playground']): ?>
                                <span><i class="bi bi-tree"></i> Playground</span>
                            <?php endif; ?>
                            <?php if($i['possui_elevador']): ?>
                                <span><i class="bi bi-arrow-up-circle"></i> Elevador</span>
                            <?php endif; ?>
                            <?php if($i['mobiliado']): ?>
                                <span><i class="bi bi-sofa"></i> Mobiliado</span>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($i['observacoes_gerais'])): ?>
                            <div class="mt-2 small text-muted">
                                <i class="bi bi-chat-dots"></i> <?= nl2br(htmlspecialchars(substr($i['observacoes_gerais'], 0, 80))) ?><?= strlen($i['observacoes_gerais']) > 80 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>

                        <!-- Botões de ação: Editar e Ver Detalhes -->
                        <div class="mt-4 d-flex gap-2">
                            <a href="editar_imovel_parceiro.php?pin=<?= urlencode($pin) ?>&id=<?= $i['id'] ?>" class="btn <?= $estaExcluido ? 'btn-outline-danger' : 'btn-outline-primary' ?> flex-fill" target="_blank">
                                <i class="bi bi-pencil"></i> <?= $estaExcluido ? 'Ver / Restaurar' : 'Editar' ?>
                            </a>
                            <?php if(!empty($i['link_site'])): ?>
                                <a href="<?= htmlspecialchars($i['link_site']) ?>" target="_blank" class="btn btn-dark flex-fill">
                                    <i class="bi bi-whatsapp me-2"></i>Ver Detalhes
                                </a>
                            <?php else: ?>
                                <span class="btn btn-secondary flex-fill disabled">
                                    <i class="bi bi-info-circle"></i> Sem link
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<footer class="py-5 text-center text-muted">
    <hr class="w-25 mx-auto mb-4">
    <small>Imóveis disponíveis para parceiros – Código de acesso: <?= htmlspecialchars($pin) ?></small>
</footer>

<script>
    // Filtros dinâmicos (JavaScript)
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroBairro = document.getElementById('filtroBairro');
    const filtroQuartos = document.getElementById('filtroQuartos');
    const btnLimpar = document.getElementById('btnLimparFiltros');
    const cards = document.querySelectorAll('.card-item');

    function aplicarFiltros() {
        const tipoSelecionado = filtroTipo.value;
        const bairroSelecionado = filtroBairro.value;
        const quartosSelecionado = filtroQuartos.value;

        cards.forEach(card => {
            const tipo = card.getAttribute('data-tipo');
            const bairro = card.getAttribute('data-bairro');
            const quartos = card.getAttribute('data-quartos');

            let tipoOk = (tipoSelecionado === 'todos' || tipo === tipoSelecionado);
            let bairroOk = (bairroSelecionado === 'todos' || bairro === bairroSelecionado);
            let quartosOk = (quartosSelecionado === 'todos' || parseInt(quartos) === parseInt(quartosSelecionado));

            if (tipoOk && bairroOk && quartosOk) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function limparFiltros() {
        filtroTipo.value = 'todos';
        filtroBairro.value = 'todos';
        filtroQuartos.value = 'todos';
        aplicarFiltros();
    }

    filtroTipo.addEventListener('change', aplicarFiltros);
    filtroBairro.addEventListener('change', aplicarFiltros);
    filtroQuartos.addEventListener('change', aplicarFiltros);
    btnLimpar.addEventListener('click', limparFiltros);

    // Executa ao carregar (já garante estado inicial)
    aplicarFiltros();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>