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

// 1. DADOS DE APOIO
$total_leads_absoluto = $conn->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$leads_list = $conn->query("SELECT id, nome FROM leads ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. CONSULTA PRINCIPAL (IMÓVEIS + FOTO + FINANCEIRO + PARCEIROS)
$where = "WHERE i.deleted_at IS NULL";
$params = [];
if (!empty($_GET['busca'])) {
    $busca = '%' . trim($_GET['busca']) . '%';
    $where .= " AND (i.titulo LIKE ? OR i.bairro LIKE ? OR i.cidade LIKE ? OR i.endereco LIKE ?)";
    $params = array_merge($params, [$busca, $busca, $busca, $busca]);
}

$sql = "SELECT i.*, p.nome as nome_proprietario,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id ORDER BY capa DESC, id ASC LIMIT 1) AS foto_capa,
        (SELECT COUNT(*) FROM visitas WHERE imovel_id = i.id) AS total_visitas_imovel,
        (SELECT SUM(valor) FROM despesas WHERE imovel_id = i.id) AS total_despesas_imovel,
        (SELECT GROUP_CONCAT(c.nome SEPARATOR '<br>') 
         FROM imovel_parceiros ip 
         JOIN corretores c ON ip.corretor_id = c.id 
         WHERE ip.imovel_id = i.id) AS nomes_parceiros
        FROM imoveis i 
        LEFT JOIN proprietarios p ON i.proprietario_id = p.id 
        $where 
        ORDER BY i.created_at DESC";

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
    .hover-up:hover { transform: translateY(-5px); transition: 0.3s; box-shadow: 0 .5rem 2rem rgba(0,0,0,.15)!important; }
    .card-img-top { height: 210px; object-fit: cover; }
    .dashboard-footer { background: #1a1d20; color: white; border-radius: 12px; }
    .badge-parceiro { font-size: 0.8rem; background-color: #f1f3f5; color: #495057; border: 1px solid #dee2e6; line-height: 1.4; }
    .check-imovel { width: 22px; height: 22px; cursor: pointer; }
    .btn-flutuante { position: fixed; bottom: 20px; right: 20px; z-index: 1050; display: none; }
</style>

<div class="container-fluid pb-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-4"><h1 class="text-primary fw-bold mb-0">Captações</h1></div>
        <div class="col-md-8 text-md-end">
            <button id="btnImprimir" class="btn btn-dark shadow btn-flutuante" onclick="gerarImpressao()">
                <i class="bi bi-printer-fill me-2"></i> Estudar Selecionados (<span id="countCheck">0</span>)
            </button>
            <form action="" method="GET" class="d-inline-block me-2">
                <div class="input-group">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <a href="form.php" class="btn btn-primary shadow">Novo Imóvel</a>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
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
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($im['titulo']) ?></h5>
                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($im['bairro']) ?></p>
                    <h4 class="text-primary fw-bold mb-3">R$ <?= number_format($im['preco'], 2, ',', '.') ?></h4>

                    <?php if (!empty($im['nomes_parceiros'])): ?>
                        <div class="badge-parceiro p-2 rounded mb-3">
                            <small class="fw-bold d-block border-bottom mb-1 pb-1 text-uppercase">Parceria:</small>
                            <div class="text-primary fw-semibold"><?= $im['nomes_parceiros'] ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <div class="p-2 bg-light rounded mb-3">
                            <small class="text-muted">Investimento: <strong class="text-danger">R$ <?= number_format($im['total_despesas_imovel'] ?? 0, 2, ',', '.') ?></strong></small>
                        </div>
                        
                        <div class="row g-2 mb-2">
                            <div class="col-6"><a href="view.php?id=<?= $im['id'] ?>" class="btn btn-primary btn-sm w-100">Detalhes</a></div>
                            <div class="col-6"><button onclick="abrirModalAgendamento(<?= $im['id'] ?>, '<?= addslashes($im['titulo']) ?>')" class="btn btn-warning btn-sm w-100 fw-bold">Agendar</button></div>
                        </div>
                        
                        <div class="btn-group btn-group-sm w-100">
                            <button onclick="abrirModalDescricao('<?= $t_desc ?>')" class="btn btn-outline-primary"><i class="bi bi-info-circle"></i></button>
                            <button onclick="abrirModalRespostaRapida('<?= $t_resp ?>')" class="btn btn-outline-info"><i class="bi bi-lightning-charge"></i></button>
                            <button onclick="abrirModalDespesas(<?= $im['id'] ?>, '<?= addslashes($im['titulo']) ?>')" class="btn btn-outline-danger"><i class="bi bi-currency-dollar"></i></button>
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

<!-- INCLUSÃO DOS MODAIS (DESPESAS, TEXTO, AGENDAMENTO) -->
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
<!-- antes caracteristicas -->