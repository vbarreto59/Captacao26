<?php
// listar_todos_imoveis.php
session_start();
require_once '../../includes/auth.php'; // ajuste o caminho conforme sua estrutura
require_once '../../conn_cap.php';

// Mantendo o nome completo do corretor
$sql = "SELECT i.*, c.nome AS corretor_nome, c.codigo_acesso AS corretor_pin 
        FROM imoveis i 
        LEFT JOIN corretores c ON i.corretor_id = c.id 
        ORDER BY i.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista Geral de Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .table th { background-color: #e9ecef; }
        
        .badge-orange { background-color: #fd7e14 !important; color: #ffffff !important; }
        .badge-blue { background-color: #0d6efd !important; color: #ffffff !important; }

        .view-mobile { display: none; }
        .view-pc { display: block; }

        @media (max-width: 767.98px) {
            .view-mobile { display: block; }
            .view-pc { display: none; }
            
            .dataTables_wrapper .row { display: none !important; }
            #tabelaImoveis_wrapper { display: none !important; }
        }

        .mobile-property-card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-left: 4px solid #6c757d;
        }
        .mobile-property-card.border-triagem { border-left-color: #fd7e14; }
        .mobile-property-card.border-oficial { border-left-color: #0d6efd; }
        
        /* Customizações para itens Excluídos */
        .linha-excluido { opacity: 0.60; background-color: #fdf2f2 !important; }
        .linha-excluido td:first-child { text-decoration: line-through; color: #dc3545; font-weight: bold; }
        
        .mobile-property-card.border-excluido { border-left-color: #dc3545 !important; opacity: 0.75; background-color: #fffdfd; }
    </style>
</head>
<body>
<?php require_once '../../includes/header.php'; ?>
<div class="container-fluid py-3 py-md-4">
    <div class="card shadow-sm border-0">
        <a href="insert_json.php" target="_blank" class="btn btn-outline-dark fw-bold shadow-sm">
    <i class="bi bi-box-arrow-in-down me-1"></i> Importar Estrutura JSON
</a>
<br>
        <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="m-0 fs-5 fs-md-4"><i class="bi bi-database"></i> Todos os Imóveis (Geral)</h4>
                    <small class="text-white-50 d-none d-sm-inline">Lista completa de todos os imóveis cadastrados</small>
                </div>

                
                <!-- SISTEMA DE ORDENAÇÃO E BUSCA -->
                <div class="d-flex gap-2 flex-grow-1 flex-md-grow-0 justify-content-end align-items-center w-100 w-md-auto flex-wrap">
                    <div class="input-group input-group-sm style-select-ordem" style="max-width: 250px; flex-grow: 1;">
                        <span class="input-group-text bg-secondary text-white border-0"><i class="bi bi-sort-down"></i></span>
                        <select id="ordenarPor" class="form-select form-select-sm">
                            <option value="id-desc">Mais Recentes</option>
                            <option value="preco-asc">Preço: Menor para Maior</option>
                            <option value="preco-desc">Preço: Maior para Menor</option>
                            <option value="bairro-asc">Bairro: A - Z</option>
                        </select>
                    </div>
                    
                    <div class="view-mobile flex-grow-1" style="max-width: 250px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-secondary text-white border-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscaMobile" class="form-control form-control-sm" placeholder="Buscar imóvel...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body px-2 px-md-3">
            
            <!-- LAYOUT PC: TABELA DATATABLES -->
            <div class="table-responsive view-pc">
                <table id="tabelaImoveis" class="table table-striped table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Bairro</th>
                            <th>Preço (R$)</th>
                            <th>Condomínio</th>
                            <th>Corretor</th>
                            <th>Tipo</th>
                            <th>Quartos</th>
                            <th>Área (m²)</th>
                            <th>Categoria</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imoveis as $imovel): 
                            $pin_corretor = !empty($imovel['corretor_pin']) ? urlencode($imovel['corretor_pin']) : '';
                            $estaExcluido = !empty($imovel['deleted_at']);
                        ?>
                        <!-- Injeta classe se o imóvel estiver excluído logicamente -->
                        <tr class="<?= $estaExcluido ? 'linha-excluido' : '' ?>">
                            <td><?= $imovel['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars(mb_strtoupper($imovel['titulo'], 'UTF-8')) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($imovel['bairro']) ?></td>
                            <td class="text-end fw-bold">R$ <?= number_format($imovel['preco'], 2, ',', '.') ?></td>
                            <!-- Exibição corrigida para valor_condominio -->
                            <td class="text-end text-muted small">
                                <?= !empty($imovel['valor_condominio']) && $imovel['valor_condominio'] > 0 ? 'R$ ' . number_format($imovel['valor_condominio'], 2, ',', '.') : '---' ?>
                            </td>
                            <td><span class="text-dark small fw-medium"><?= htmlspecialchars($imovel['corretor_nome'] ?? '---') ?></span></td>
                            <td><?= htmlspecialchars($imovel['tipo']) ?></td>
                            <td><?= $imovel['quartos'] ?></td>
                            <td><?= $imovel['area'] ?>m²</td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($estaExcluido): ?>
                                        <span class="badge bg-danger text-uppercase"><i class="bi bi-trash-fill"></i> Excluído</span>
                                    <?php endif; ?>

                                    <?php if ($imovel['categoria_registro'] == 'triagem'): ?>
                                        <span class="badge badge-orange text-uppercase">Triagem</span>
                                    <?php elseif ($imovel['categoria_registro'] == 'oficial'): ?>
                                        <span class="badge badge-blue text-uppercase">Oficial</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($imovel['categoria_registro']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="editar_imovel_parceiro.php?pin=<?= $pin_corretor ?>&id=<?= $imovel['id'] ?>" class="btn btn-sm <?= $estaExcluido ? 'btn-danger text-white' : 'btn-outline-primary' ?>" title="Editar Imóvel" target="_blank">
                                    <i class="bi <?= $estaExcluido ? 'bi-eye-fill' : 'bi-pencil' ?>"></i> <?= $estaExcluido ? 'Ver / Restaurar' : 'Editar' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- LAYOUT MOBILE: CARDS -->
            <div class="view-mobile" id="conteinerMobile">
                <?php foreach ($imoveis as $imovel): 
                    $estaExcluido = !empty($imovel['deleted_at']);
                    
                    if ($estaExcluido) {
                        $classeBorda = 'border-excluido';
                    } else {
                        $classeBorda = 'border-secondary';
                        if($imovel['categoria_registro'] == 'triagem') $classeBorda = 'border-triagem';
                        if($imovel['categoria_registro'] == 'oficial') $classeBorda = 'border-oficial';
                    }
                    
                    $pin_corretor = !empty($imovel['corretor_pin']) ? urlencode($imovel['corretor_pin']) : '';
                ?>
                    <div class="mobile-property-card <?= $classeBorda ?> card-item-mobile" 
                         data-id="<?= $imovel['id'] ?>"
                         data-preco="<?= $imovel['preco'] ?>"
                         data-bairro="<?= htmlspecialchars(strtolower($imovel['bairro'])) ?>"
                         data-searchable="<?= strtolower(htmlspecialchars($imovel['id'] . ' ' . $imovel['titulo'] . ' ' . $imovel['bairro'] . ' ' . $imovel['tipo'] . ' ' . ($imovel['corretor_nome'] ?? '') . ($estaExcluido ? ' excluido deletado' : ''))) ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small fw-bold">
                                #<?= $imovel['id'] ?> 
                                <?php if(!empty($imovel['corretor_nome'])): ?>
                                    • CRT: <?= htmlspecialchars($imovel['corretor_nome']) ?>
                                <?php endif; ?>
                            </span>
                            <div class="d-flex gap-1">
                                <?php if ($estaExcluido): ?>
                                    <span class="badge bg-danger text-uppercase"><i class="bi bi-trash-fill"></i> Excluído</span>
                                <?php endif; ?>

                                <?php if ($imovel['categoria_registro'] == 'triagem'): ?>
                                    <span class="badge badge-orange text-uppercase">Triagem</span>
                                <?php elseif ($imovel['categoria_registro'] == 'oficial'): ?>
                                    <span class="badge badge-blue text-uppercase">Oficial</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($imovel['categoria_registro']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h6 class="mb-1 text-dark fw-bold"><?= htmlspecialchars(mb_strtoupper($imovel['titulo'], 'UTF-8')) ?></h6>
                        <div class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($imovel['bairro']) ?></div>
                        
                        <div class="d-flex justify-content-between align-items-center my-2 pt-2 border-top">
                            <div>
                                <div class="text-primary fw-bold fs-5">
                                    R$ <?= number_format($imovel['preco'], 2, ',', '.') ?>
                                </div>
                                <!-- Exibição corrigida para valor_condominio no Mobile -->
                                <?php if(!empty($imovel['valor_condominio']) && $imovel['valor_condominio'] > 0): ?>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        Cond.: R$ <?= number_format($imovel['valor_condominio'], 2, ',', '.') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                <span class="me-2"><i class="bi bi-door-open"></i> <?= $imovel['quartos'] ?>Q</span>
                                <span><i class="bi bi-arrows-fullscreen"></i> <?= $imovel['area'] ?>m²</span>
                            </div>
                        </div>

                        <!-- Botão de ação com id e pin adaptativo -->
                        <div class="d-grid mt-2">
                            <a href="editar_imovel_parceiro.php?pin=<?= $pin_corretor ?>&id=<?= $imovel['id'] ?>" class="btn btn-sm <?= $estaExcluido ? 'btn-danger text-white' : 'btn-outline-primary' ?>" target="_blank">
                                <i class="bi <?= $estaExcluido ? 'bi-eye-fill' : 'bi-pencil' ?> me-1"></i> <?= $estaExcluido ? 'Ver / Restaurar Imóvel' : 'Editar Imóvel' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializa o DataTable para o PC
    const tabela = $('#tabelaImoveis').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "pageLength": 100,
        "order": [[0, "desc"]], 
        "columnDefs": [
            { "className": "dt-body-right", "targets": [3, 4] }, // Alinha Preço e Condomínio à direita
            { "orderable": false, "targets": [10] }
        ]
    });

    // Evento do Filtro de Ordenação (PC e Mobile)
    $('#ordenarPor').on('change', function() {
        const valorSelecao = $(this).val();

        // Regra para PC
        if (valorSelecao === 'id-desc') {
            tabela.order([0, 'desc']).draw();
        } else if (valorSelecao === 'preco-asc') {
            tabela.order([3, 'asc']).draw();
        } else if (valorSelecao === 'preco-desc') {
            tabela.order([3, 'desc']).draw();
        } else if (valorSelecao === 'bairro-asc') {
            tabela.order([2, 'asc']).draw();
        }

        // Regra para Mobile
        reordenarCardsMobile(valorSelecao);
    });

    function reordenarCardsMobile(criterio) {
        const conteiner = $('#conteinerMobile');
        const cards = conteiner.children('.card-item-mobile').get();

        cards.sort(function(a, b) {
            if (criterio === 'id-desc') {
                return parseInt($(b).data('id')) - parseInt($(a).data('id'));
            }
            if (criterio === 'preco-asc') {
                return parseFloat($(a).data('preco')) - parseFloat($(b).data('preco'));
            }
            if (criterio === 'preco-desc') {
                return parseFloat($(b).data('preco')) - parseFloat($(a).data('preco'));
            }
            if (criterio === 'bairro-asc') {
                return $(a).data('bairro').localeCompare($(b).data('bairro'));
            }
            return 0;
        });

        $.each(cards, function(indice, card) {
            conteiner.append(card);
        });
    }

    // Busca dinâmica Mobile
    $('#buscaMobile').on('keyup', function() {
        const termo = $(this).val().toLowerCase().trim();
        tabela.search(termo).draw();

        $('.card-item-mobile').each(function() {
            const dadosCard = $(this).data('searchable');
            if (dadosCard.includes(termo)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>

</body>
</html>