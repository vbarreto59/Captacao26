<?php

session_start();

require_once '../../includes/auth.php';

require_once '../../conn_cap.php';

require_once '../../includes/functions.php';



$hoje_agora = date('Y-m-d H:i:s');



// ================================================

// LÓGICA AJAX PARA DESPESAS

// ================================================

if (isset($_GET['action'])) {

    header('Content-Type: application/json');

    if ($_GET['action'] == 'listar_despesas' && isset($_GET['imovel_id'])) {

        $stmt = $conn->prepare("SELECT * FROM despesas WHERE imovel_id = ? ORDER BY data_despesa DESC");

        $stmt->execute([$_GET['imovel_id']]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;

    }

    if ($_GET['action'] == 'salvar_despesa' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $sql = "INSERT INTO despesas (imovel_id, tipo, valor, data_despesa, descricao) VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $res = $stmt->execute([$_POST['imovel_id'], $_POST['tipo'], $_POST['valor'], $_POST['data_despesa'], $_POST['descricao']]);

        echo json_encode(['success' => $res]); exit;

    }

    if ($_GET['action'] == 'excluir_despesa' && isset($_GET['id'])) {

        $stmt = $conn->prepare("DELETE FROM despesas WHERE id = ?");

        $res = $stmt->execute([$_GET['id']]);

        echo json_encode(['success' => $res]); exit;

    }

}



// ================================================

// LÓGICA DE AGENDAMENTO DE VISITAS

// ================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar_visita'])) {

    $data_visita = $_POST['data_visita'] . ' ' . $_POST['hora_visita'];

    $sql_agendar = "INSERT INTO visitas (imovel_id, lead_id, data_visita, descricao, status) VALUES (?, ?, ?, ?, 'pendente')";

    if ($conn->prepare($sql_agendar)->execute([$_POST['imovel_id'], $_POST['lead_id'], $data_visita, $_POST['observacoes']])) {

        $_SESSION['msg'] = "Visita agendada com sucesso!";

        header("Location: " . $_SERVER['PHP_SELF']); exit;

    }

}



// ================================================

// FILTROS

// ================================================

$localizacao = trim($_GET['localizacao'] ?? '');

$preco_referencia = floatval(str_replace(',', '.', $_GET['preco_referencia'] ?? 0));

$quartos = intval($_GET['quartos'] ?? 0);

$piscina = isset($_GET['piscina']) ? 1 : 0;

$mobiliado = isset($_GET['mobiliado']) ? 1 : 0;



// Construção da cláusula WHERE

$where = "WHERE i.deleted_at IS NULL";

$params = [];



// Localização (busca em bairro, cidade, endereço, título)

if (!empty($localizacao)) {

    $local = '%' . $localizacao . '%';

    $where .= " AND (i.titulo LIKE ? OR i.bairro LIKE ? OR i.cidade LIKE ? OR i.endereco LIKE ?)";

    $params = array_merge($params, [$local, $local, $local, $local]);

}



// Faixa de preço (±20% em relação ao valor de referência)

if ($preco_referencia > 0) {

    $preco_min = $preco_referencia * 0.8;

    $preco_max = $preco_referencia * 1.2;

    $where .= " AND i.preco BETWEEN ? AND ?";

    $params[] = $preco_min;

    $params[] = $preco_max;

}



// Quartos

if ($quartos > 0) {

    $where .= " AND i.quartos >= ?";

    $params[] = $quartos;

}



// Piscina

if ($piscina) {

    $where .= " AND i.tem_piscina = 1";

}



// Mobiliado

if ($mobiliado) {

    $where .= " AND i.mobiliado = 1";

}



// 1. DADOS DE APOIO

$total_leads_absoluto = $conn->query("SELECT COUNT(*) FROM leads")->fetchColumn();

$leads_list = $conn->query("SELECT id, nome FROM leads ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);



// 2. CONSULTA PRINCIPAL (IMÓVEIS + FOTO + FINANCEIRO + PARCEIROS + CORRETOR TITULAR)

$sql = "SELECT i.*, i.link_site, p.nome as nome_proprietario,

        c_titular.nome as nome_corretor_titular,

        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id ORDER BY capa DESC, id ASC LIMIT 1) AS foto_capa,

        (SELECT COUNT(*) FROM visitas WHERE imovel_id = i.id) AS total_visitas_imovel,

        (SELECT SUM(valor) FROM despesas WHERE imovel_id = i.id) AS total_despesas_imovel,

        (SELECT GROUP_CONCAT(c.nome SEPARATOR '<br>') 

         FROM imovel_parceiros ip 

         JOIN corretores c ON ip.corretor_id = c.id 

         WHERE ip.imovel_id = i.id) AS nomes_parceiros

        FROM imoveis i 

        LEFT JOIN proprietarios p ON i.proprietario_id = p.id 

        LEFT JOIN corretores c_titular ON i.corretor_id = c_titular.id

        WHERE i.categoria_registro = 'oficial' 

        AND i.deleted_at IS NULL

        ORDER BY i.preco";



$stmt = $conn->prepare($sql);

$stmt->execute($params);

$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);



// 3. CÁLCULO DE MÉTRICAS GERAIS (DASHBOARD)

$geral_despesas = 0; $geral_visitas = 0;

foreach ($imoveis as $im) { 

    $geral_despesas += $im['total_despesas_imovel'] ?? 0; 

    $geral_visitas += $im['total_visitas_imovel'] ?? 0;

}

$cpl = ($total_leads_absoluto > 0) ? ($geral_despesas / $total_leads_absoluto) : 0;

$cpv = ($geral_visitas > 0) ? ($geral_despesas / $geral_visitas) : 0;

?>



<?php require_once '../../includes/header.php'; ?>



<style>

    /* Estilos já existentes + novos para os filtros */

    .hover-up:hover { transform: translateY(-5px); transition: 0.3s; box-shadow: 0 .5rem 2rem rgba(0,0,0,.15)!important; }

    .card-img-top { height: 210px; object-fit: cover; }

    .dashboard-footer { background: #1a1d20; color: white; border-radius: 12px; }

    .badge-parceiro { font-size: 0.8rem; background-color: #f1f3f5; color: #495057; border: 1px solid #dee2e6; line-height: 1.4; }

    .check-imovel { width: 22px; height: 22px; cursor: pointer; }

    .btn-flutuante { position: fixed; bottom: 20px; right: 20px; z-index: 1050; display: none; }

    

    /* Estilos para as características */

    .caracteristicas-grid {

        display: grid;

        grid-template-columns: repeat(2, 1fr);

        gap: 8px;

        background: #f8f9fa;

        padding: 10px;

        border-radius: 12px;

        margin: 10px 0;

        font-size: 0.8rem;

    }

    .carac-item {

        display: flex;

        align-items: center;

        gap: 6px;

        color: #2c3e50;

    }

    .carac-item i {

        width: 20px;

        color: #0d6efd;

    }

    .comodidades-badge {

        display: flex;

        flex-wrap: wrap;

        gap: 6px;

        margin: 10px 0;

    }

    .badge-comod {

        background: #e9ecef;

        padding: 4px 10px;

        border-radius: 20px;

        font-size: 0.7rem;

        font-weight: 500;

    }

    .valores-detalhe {

        background: #eef2ff;

        padding: 8px;

        border-radius: 10px;

        margin: 8px 0;

        display: flex;

        flex-wrap: wrap;

        justify-content: space-between;

        font-size: 0.75rem;

        font-weight: 600;

    }

    .aceita-text {

        font-size: 0.7rem;

        color: #28a745;

        font-weight: bold;

    }

    .filtros-card {

        background: white;

        border-radius: 16px;

        padding: 20px;

        margin-bottom: 30px;

        box-shadow: 0 4px 12px rgba(0,0,0,0.05);

    }

    .btn-limpar {

        background-color: #6c757d;

        color: white;

    }

    .btn-limpar:hover {

        background-color: #5a6268;

        color: white;

    }

    .faixa-preco-ajuda {

        font-size: 0.7rem;

        color: #6c757d;

        margin-top: 4px;

    }

    .corretor-titular {

        font-size: 0.8rem;

        color: #0d6efd;

        background: #e7f1ff;

        display: inline-block;

        padding: 2px 8px;

        border-radius: 20px;

        margin-top: 5px;

        margin-bottom: 8px;

    }

    .btn-whatsapp-sm {

        background-color: #25d366;

        color: white;

        border-color: #25d366;

    }

    .btn-whatsapp-sm:hover {

        background-color: #128c7e;

        color: white;

        border-color: #128c7e;

    }

</style>



<div class="container-fluid pb-5">

    <div class="row align-items-center mb-4">

        <div class="col-md-4"><h1 class="text-primary fw-bold mb-0">Captações</h1></div>

        <div class="col-md-8 text-md-end">

            <button id="btnImprimir" class="btn btn-dark shadow btn-flutuante" onclick="gerarImpressao()">

                <i class="bi bi-printer-fill me-2"></i> Estudar Selecionados (<span id="countCheck">0</span>)

            </button>

            <a href="form.php" class="btn btn-primary shadow">Novo Imóvel</a>

        </div>

    </div>



    <!-- ========================================== -->

    <!-- FORMULÁRIO DE FILTROS                      -->

    <!-- ========================================== -->

    <div class="filtros-card">

        <form method="GET" action="" class="row g-3 align-items-end">

            <div class="col-md-3">

                <label class="form-label fw-bold"><i class="bi bi-geo-alt"></i> Localização</label>

                <input type="text" name="localizacao" class="form-control" placeholder="Bairro, cidade, endereço..." value="<?= htmlspecialchars($localizacao) ?>">

            </div>

            <div class="col-md-2">

                <label class="form-label fw-bold"><i class="bi bi-cash-stack"></i> Preço referência</label>

                <input type="text" name="preco_referencia" class="form-control money" placeholder="Valor central" value="<?= $preco_referencia ? number_format($preco_referencia, 0, ',', '.') : '' ?>">

                

            </div>

            <div class="col-md-2">

                <label class="form-label fw-bold"><i class="bi bi-door-open"></i> Quartos</label>

                <select name="quartos" class="form-select">

                    <option value="0">Qualquer</option>

                    <option value="1" <?= $quartos == 1 ? 'selected' : '' ?>>1+ quarto</option>

                    <option value="2" <?= $quartos == 2 ? 'selected' : '' ?>>2+ quartos</option>

                    <option value="3" <?= $quartos == 3 ? 'selected' : '' ?>>3+ quartos</option>

                    <option value="4" <?= $quartos == 4 ? 'selected' : '' ?>>4+ quartos</option>

                </select>

            </div>

            <div class="col-md-2">

                <label class="form-label fw-bold"><i class="bi bi-water"></i> Piscina</label>

                <div class="form-check">

                    <input class="form-check-input" type="checkbox" name="piscina" id="piscina" value="1" <?= $piscina ? 'checked' : '' ?>>

                    <label class="form-check-label" for="piscina">Sim</label>

                </div>

            </div>

            <div class="col-md-2">

                <label class="form-label fw-bold"><i class="bi bi-sofa"></i> Mobiliado</label>

                <div class="form-check">

                    <input class="form-check-input" type="checkbox" name="mobiliado" id="mobiliado" value="1" <?= $mobiliado ? 'checked' : '' ?>>

                    <label class="form-check-label" for="mobiliado">Sim</label>

                </div>

            </div>

            <div class="col-md-1">

                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>

            </div>

            <div class="col-md-1">

                <a href="?" class="btn btn-limpar w-100"><i class="bi bi-eraser"></i> Limpar</a>

            </div>

        </form>

    </div>



    <!-- RESULTADOS -->

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">

        <?php if (count($imoveis) == 0): ?>

            <div class="col-12 text-center py-5">

                <i class="bi bi-search display-1 text-muted"></i>

                <h3 class="mt-3">Nenhum imóvel encontrado</h3>

                <p>Tente ajustar os filtros ou <a href="?">limpar a busca</a>.</p>

            </div>

        <?php endif; ?>



        <?php foreach ($imoveis as $im): 

            $foto = $im['foto_capa'] ? '../../uploads/fotos_imoveis/' . $im['foto_capa'] : 'https://via.placeholder.com/400x250';

            $t_resp = str_replace(["\r", "\n"], ['\r', '\n'], addslashes($im['resposta_rapida'] ?? ''));

            $t_desc = str_replace(["\r", "\n"], ['\r', '\n'], addslashes($im['descricao'] ?? ''));

        ?>

        <div class="col">

            <div class="card h-100 shadow-sm border-0 hover-up">

                <div class="position-relative">

                    <div class="position-absolute top-0 end-0 m-2" style="z-index: 5;">

                        <input type="checkbox" class="form-check-input check-imovel shadow" value="<?= $im['id'] ?>" onchange="atualizarContagem()">

                    </div>

                    <img src="<?= $foto ?>" class="card-img-top rounded-top">

                </div>

                

                <div class="card-body d-flex flex-column">

                    <div class="mb-1">

                        <?php if (isset($im['reservado']) && $im['reservado'] == true): ?>

                            <span class="badge bg-danger text-white fw-bold px-2 py-1.5 mb-1 text-uppercase small shadow-sm">

                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Reservado / Vendido / Indisponível

                            </span>

                        <?php endif; ?>

                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($im['titulo']) ?></h5>

                    </div>

                    

                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($im['bairro']) ?></p>

                    

                    <!-- EXIBIÇÃO DO CORRETOR TITULAR -->

                    <div class="corretor-titular">

                        <i class="bi bi-person-badge"></i> Corretor: 

                        <?= htmlspecialchars($im['nome_corretor_titular'] ?? 'Não definido') ?>

                    </div>



                    <h4 class="text-primary fw-bold mb-3">R$ <?= number_format($im['preco'], 2, ',', '.') ?></h4>



                    <!-- Características do imóvel (grid) -->

                    <div class="caracteristicas-grid">

                        <?php if(!empty($im['quartos'])): ?>

                            <div class="carac-item"><i class="bi bi-door-open"></i> <?= $im['quartos'] ?> quartos</div>

                        <?php endif; ?>

                        <?php if(!empty($im['suites']) && $im['suites'] > 0): ?>

                            <div class="carac-item"><i class="bi bi-suit-heart"></i> <?= $im['suites'] ?> suíte(s)</div>

                        <?php endif; ?>

                        <?php if(!empty($im['banheiros']) && $im['banheiros'] > 0): ?>

                            <div class="carac-item"><i class="bi bi-droplet"></i> <?= $im['banheiros'] ?> banheiros</div>

                        <?php endif; ?>

                        <?php if(!empty($im['vagas_garagem'])): ?>

                            <div class="carac-item"><i class="bi bi-car-front"></i> <?= $im['vagas_garagem'] ?> vaga(s)</div>

                        <?php endif; ?>

                        <?php if(!empty($im['area'])): ?>

                            <div class="carac-item"><i class="bi bi-arrows-fullscreen"></i> <?= number_format($im['area'], 0) ?> m²</div>

                        <?php endif; ?>

                        <?php if(!empty($im['andar']) && $im['andar'] > 0): ?>

                            <div class="carac-item"><i class="bi bi-layers"></i> <?= $im['andar'] ?>º andar</div>

                        <?php endif; ?>

                        <?php if(!empty($im['face'])): ?>

                            <div class="carac-item"><i class="bi bi-brightness-alt-high"></i> Face <?= ucfirst($im['face']) ?></div>

                        <?php endif; ?>

                        <?php if(!empty($im['tipo'])): ?>

                            <div class="carac-item"><i class="bi bi-building"></i> <?= ucfirst($im['tipo']) ?></div>

                        <?php endif; ?>

                        <!-- CAMPOS ADICIONADOS: Construtora e Ano de Entrega -->

                        <?php if(!empty($im['construtora'])): ?>

                            <div class="carac-item"><i class="bi bi-tools"></i> <?= htmlspecialchars($im['construtora']) ?></div>

                        <?php endif; ?>

                        <?php if(!empty($im['ano_entrega']) && $im['ano_entrega'] > 0): ?>

                            <div class="carac-item"><i class="bi bi-calendar-check"></i> Entrega <?= $im['ano_entrega'] ?></div>

                        <?php endif; ?>

                        <?php if($im['mobiliado'] == 1): ?>

                            <div class="carac-item"><i class="bi bi-sofa"></i> Mobiliado</div>

                        <?php endif; ?>

                        <?php if($im['possui_elevador'] == 1): ?>

                            <div class="carac-item"><i class="bi bi-arrow-up-short"></i> Elevador</div>

                        <?php endif; ?>

                        <?php if($im['possui_moveis_planejados'] == 1): ?>

                            <div class="carac-item"><i class="bi bi-grid-3x3-gap-fill"></i> Móveis planejados</div>

                        <?php endif; ?>

                    </div>



                    <!-- Valores adicionais -->

                    <div class="valores-detalhe">

                        <?php if($im['valor_condominio'] > 0): ?>

                            <span><i class="bi bi-building"></i> Cond. R$ <?= number_format($im['valor_condominio'], 2, ',', '.') ?></span>

                        <?php endif; ?>

                        <?php if($im['valor_iptu'] > 0): ?>

                            <span><i class="bi bi-receipt"></i> IPTU R$ <?= number_format($im['valor_iptu'], 2, ',', '.') ?></span>

                        <?php endif; ?>

                        <?php if($im['valor_sinal'] > 0): ?>

                            <span><i class="bi bi-currency-exchange"></i> Sinal R$ <?= number_format($im['valor_sinal'], 2, ',', '.') ?></span>

                        <?php endif; ?>

                    </div>



                    <!-- Comodidades -->

                    <div class="comodidades-badge">

                        <?php if($im['gas_encanado'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-fuel-pump"></i> Gás encanado</span>

                        <?php endif; ?>

                        <?php if($im['tem_piscina'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-water"></i> Piscina</span>

                        <?php endif; ?>

                        <?php if($im['tem_academia'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-heart-pulse"></i> Academia</span>

                        <?php endif; ?>

                        <?php if($im['tem_salao_festas'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-balloon"></i> Salão festas</span>

                        <?php endif; ?>

                        <?php if($im['tem_espaco_gourmet'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-egg-fried"></i> Espaço gourmet</span>

                        <?php endif; ?>

                        <?php if($im['tem_playground'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-tree"></i> Playground</span>

                        <?php endif; ?>

                        <?php if($im['agua_inclusa_condominio'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-droplet"></i> Água inclusa</span>

                        <?php endif; ?>

                        <?php if($im['gas_incluso_condominio'] == 1): ?>

                            <span class="badge-comod"><i class="bi bi-fire"></i> Gás incluso</span>

                        <?php endif; ?>

                    </div>



                    <!-- Aceita -->

                    <?php if($im['aceita_financiamento'] == 1 || $im['aceita_fgts'] == 1 || $im['aceita_permuta'] == 1 || $im['aceita_consorcio'] == 1): ?>

                        <div class="aceita-text mb-2">

                            <i class="bi bi-hand-thumbs-up"></i> Aceita:

                            <?= $im['aceita_financiamento'] ? ' Financiamento' : '' ?>

                            <?= $im['aceita_fgts'] ? ' FGTS' : '' ?>

                            <?= $im['aceita_permuta'] ? ' Permuta' : '' ?>

                            <?= $im['aceita_consorcio'] ? ' Consórcio' : '' ?>

                        </div>

                    <?php endif; ?>



                    <?php if (!empty($im['nomes_parceiros'])): ?>

                        <div class="badge-parceiro p-2 rounded mb-3">

                            <small class="fw-bold d-block border-bottom mb-1 pb-1 text-uppercase">Parceria:</small>

                            <div class="text-primary fw-semibold"><?= $im['nomes_parceiros'] ?></div>

                        </div>

                    <?php endif; ?>



                    <div class="mt-auto">



                        

                        <div class="row g-2 mb-2">

                            <div class="<?= !empty($im['link_site']) ? 'col-4' : 'col-6' ?>">

                                <a href="view.php?id=<?= $im['id'] ?>" class="btn btn-primary btn-sm w-100">Detalhes</a>

                            </div>

                            

                            <?php if (!empty($im['link_site'])): ?>

                                <div class="col-4">

                                    <a href="<?= htmlspecialchars($im['link_site']) ?>" target="_blank" class="btn btn-outline-success btn-sm w-100" title="Ver no Site Externo">

                                        <i class="bi bi-box-arrow-up-right"></i> Ver Site

                                    </a>

                                </div>

                            <?php endif; ?>



                            <div class="<?= !empty($im['link_site']) ? 'col-4' : 'col-6' ?>">

                                <button onclick="abrirModalAgendamento(<?= $im['id'] ?>, '<?= addslashes($im['titulo']) ?>')" class="btn btn-warning btn-sm w-100 fw-bold">Agendar</button>

                            </div>

                        </div>

                        

<div class="btn-group btn-group-sm w-100">

    <button onclick="abrirModalDescricao('<?= $t_desc ?>')" class="btn btn-outline-primary"><i class="bi bi-info-circle"></i></button>

    <button onclick="abrirModalRespostaRapida('<?= $t_resp ?>')" class="btn btn-outline-info"><i class="bi bi-lightning-charge"></i></button>

    <button onclick="abrirModalDespesas(<?= $im['id'] ?>, '<?= addslashes($im['titulo']) ?>')" class="btn btn-outline-danger"><i class="bi bi-currency-dollar"></i></button>

    <!-- Link para entorno.php -->

    <a href="entorno.php?id=<?= $im['id'] ?>" class="btn btn-outline-info btn-sm" title="Ver entorno"><i class="bi bi-map"></i></a>

    <a href="copiar_whatsapp.php?id=<?= $im['id'] ?>" target="_blank" class="btn btn-whatsapp-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>

    <a href="form.php?id=<?= $im['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>

</div>

                    </div>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>



    <!-- DASHBOARD FOOTER -->

    <div class="card dashboard-footer mt-5 border-0 shadow-lg">

        <div class="card-body p-4">

            <div class="row text-center align-items-center">

                <div class="col-md-2 border-end border-secondary">

                    <small class="opacity-75 d-block mb-1">TOTAL INVESTIDO</small>

                    <h4 class="text-danger fw-bold mb-0">R$ <?= number_format($geral_despesas, 2, ',', '.') ?></h4>

                </div>

                <div class="col-md-2 border-end border-secondary">

                    <small class="opacity-75 d-block mb-1">LEADS TOTAIS</small>

                    <h4 class="text-warning fw-bold mb-0"><?= $total_leads_absoluto ?></h4>

                </div>

                <div class="col-md-3 border-end border-secondary">

                    <small class="opacity-75 d-block mb-1">CUSTO POR LEAD</small>

                    <h4 class="text-success fw-bold mb-0">R$ <?= number_format($cpl, 2, ',', '.') ?></h4>

                </div>

                <div class="col-md-2 border-end border-secondary">

                    <small class="opacity-75 d-block mb-1">VISITAS</small>

                    <h4 class="text-info fw-bold mb-0"><?= $geral_visitas ?></h4>

                </div>

                <div class="col-md-3">

                    <small class="opacity-75 d-block mb-1">CUSTO POR VISITA</small>

                    <h4 class="text-white fw-bold mb-0">R$ <?= number_format($cpv, 2, ',', '.') ?></h4>

                </div>

            </div>

        </div>

    </div>

</div>



<!-- MODAIS -->

<div class="modal fade" id="modalDespesas" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-danger text-white"><h5>Despesas: <span id="span_imovel_titulo"></span></h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>

            <div class="modal-body">

                <form id="formNovaDespesa" class="row g-2 mb-3">

                    <input type="hidden" name="imovel_id" id="despesa_imovel_id">

                    <div class="col-4"><select name="tipo" class="form-select form-select-sm"><option>Marketing</option><option>Manutenção</option></select></div>

                    <div class="col-4"><input type="number" step="0.01" name="valor" class="form-control form-control-sm" placeholder="Valor" required></div>

                    <div class="col-4"><input type="date" name="data_despesa" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>

                    <div class="col-10"><input type="text" name="descricao" class="form-control form-control-sm" placeholder="Descrição..."></div>

                    <div class="col-2"><button type="submit" class="btn btn-danger btn-sm w-100">Add</button></div>

                </form>

                <table class="table table-sm"><thead><tr><th>Data</th><th>Tipo</th><th>Valor</th><th></th></tr></thead><tbody id="listaDespesasCorpo"></tbody></table>

            </div>

        </div>

    </div>

</div>



<div class="modal fade" id="modalAgendarVisita" tabindex="-1">

    <div class="modal-dialog">

        <form action="" method="POST" class="modal-content">

            <div class="modal-header bg-warning"><h5>Agendar Visita</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

            <div class="modal-body">

                <input type="hidden" name="imovel_id" id="modal_imovel_id">

                <div class="mb-2"><label class="small fw-bold">Lead</label>

                <select name="lead_id" class="form-select" required>

                    <option value="">Selecione...</option>

                    <?php foreach ($leads_list as $l): ?>

                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>

                    <?php endforeach; ?>

                </select></div>

                <div class="row g-2 mb-2">

                    <div class="col-6"><input type="date" name="data_visita" class="form-control" required></div>

                    <div class="col-6"><input type="time" name="hora_visita" class="form-control" required></div>

                </div>

                <textarea name="observacoes" class="form-control" rows="2" placeholder="Observações..."></textarea>

            </div>

            <div class="modal-footer"><button type="submit" name="agendar_visita" class="btn btn-warning fw-bold">Confirmar</button></div>

        </form>

    </div>

</div>



<div class="modal fade" id="modalTexto" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white"><h5 id="tituloModal"></h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>

            <div class="modal-body">

                <div id="corpoTexto" class="p-3 bg-light border mb-3" style="white-space:pre-wrap"></div>

                <button class="btn btn-primary w-100" onclick="copiar()">Copiar Texto</button>

            </div>

        </div>

    </div>

</div>



<script>

// Formatação de moeda para o campo preço_referência

document.querySelectorAll('.money').forEach(el => {

    el.addEventListener('input', function(e) {

        let value = this.value.replace(/\D/g, '');

        if (value) {

            value = (parseInt(value) / 100).toFixed(2);

            value = value.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            this.value = 'R$ ' + value;

        } else {

            this.value = '';

        }

    });

    el.closest('form')?.addEventListener('submit', function() {

        let raw = el.value.replace(/[^\d,]/g, '').replace(',', '.');

        el.value = parseFloat(raw) || 0;

    });

});



let textoAtual = "";

function atualizarContagem() {

    let selecionados = document.querySelectorAll('.check-imovel:checked').length;

    document.getElementById('countCheck').innerText = selecionados;

    document.getElementById('btnImprimir').style.display = selecionados > 0 ? 'block' : 'none';

}

function gerarImpressao() {

    let ids = Array.from(document.querySelectorAll('.check-imovel:checked')).map(cb => cb.value);

    window.open('print_estudo.php?ids=' + ids.join(','), '_blank');

}

function abrirModalDescricao(t) { textoAtual = t.replace(/\\n/g, '\n'); document.getElementById('tituloModal').innerText = "Descrição"; document.getElementById('corpoTexto').innerText = textoAtual; new bootstrap.Modal(document.getElementById('modalTexto')).show(); }

function abrirModalRespostaRapida(t) { textoAtual = t.replace(/\\n/g, '\n'); document.getElementById('tituloModal').innerText = "Resposta Rápida"; document.getElementById('corpoTexto').innerText = textoAtual; new bootstrap.Modal(document.getElementById('modalTexto')).show(); }

function copiar() { navigator.clipboard.writeText(textoAtual); alert("Copiado!"); }

function abrirModalDespesas(id, tit) { document.getElementById('despesa_imovel_id').value = id; document.getElementById('span_imovel_titulo').innerText = tit; carregarDespesas(id); new bootstrap.Modal(document.getElementById('modalDespesas')).show(); }

function carregarDespesas(id) { fetch(`?action=listar_despesas&imovel_id=${id}`).then(r=>r.json()).then(data=>{ let h = ''; data.forEach(d => { h += `<tr><td>${d.data_despesa.split('-').reverse().join('/')}</td><td>${d.tipo}</td><td class="text-danger fw-bold">R$ ${parseFloat(d.valor).toLocaleString('pt-BR')}</td><td><button class="btn text-danger btn-sm" onclick="excluirD(${d.id},${id})">X</button></td></tr>`; }); document.getElementById('listaDespesasCorpo').innerHTML = h; }); }

document.getElementById('formNovaDespesa').onsubmit = function(e){ e.preventDefault(); fetch('?action=salvar_despesa',{method:'POST', body:new FormData(this)}).then(()=>carregarDespesas(document.getElementById('despesa_imovel_id').value)); this.reset(); };

function excluirD(id, iid){ if(confirm('Excluir?')) fetch(`?action=excluir_despesa&id=${id}`).then(()=>carregarDespesas(iid)); }

function abrirModalAgendamento(id, tit) { document.getElementById('modal_imovel_id').value = id; new bootstrap.Modal(document.getElementById('modalAgendarVisita')).show(); }

</script>



<?php require_once '../../includes/footer.php'; ?>

<!-- antes do entorno -->