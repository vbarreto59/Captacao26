<?php
// lead_view.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ==========================================
// LÓGICA DE PESQUISA RÁPIDA POR NOME
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_lead'])) {
    $search_term = '%' . trim($_POST['search_term']) . '%';
    $stmt_search = $conn->prepare("SELECT id FROM leads WHERE nome LIKE ? LIMIT 1");
    $stmt_search->execute([$search_term]);
    $found_id = $stmt_search->fetchColumn();

    if ($found_id) {
        header("Location: lead_view.php?id=$found_id");
    } else {
        header("Location: leads.php?msg=not_found&term=" . urlencode($_POST['search_term']));
    }
    exit;
}

if ($id <= 0) {
    header("Location: leads.php");
    exit;
}

// 1. BUSCAR DADOS COMPLETOS DO LEAD
$stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    echo "<div class='alert alert-danger m-4'>Lead não encontrado.</div>";
    require_once '../../includes/footer.php';
    exit;
}

// ==========================================
// CONFIGURAÇÕES E OPÇÕES
// ==========================================
$opcoes_passos = [
    "Ligar para qualificar", "Agendar visita", "Enviar simulação", 
    "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"
];

$fases_funil = ["Novo", "Contato Feito", "Tentativa de Contato", "Visita Agendada", "Visita Realizada", "Analisando", "Proposta", "Fechado", "Perdido"];

// ==========================================
// LÓGICA DE NAVEGAÇÃO DE ID
// ==========================================
$first_id = $conn->query("SELECT MIN(id) FROM leads")->fetchColumn();
$last_id  = $conn->query("SELECT MAX(id) FROM leads")->fetchColumn();

$stmt_prev = $conn->prepare("SELECT id FROM leads WHERE id < ? ORDER BY id DESC LIMIT 1");
$stmt_prev->execute([$id]);
$prev_id = $stmt_prev->fetchColumn();

$stmt_next = $conn->prepare("SELECT id FROM leads WHERE id > ? ORDER BY id ASC LIMIT 1");
$stmt_next->execute([$id]);
$next_id = $stmt_next->fetchColumn();

// ==========================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_historico'])) {
    $hist_id = (int)$_POST['historico_id'];
    $stmt_del = $conn->prepare("DELETE FROM lead_historico WHERE id = ? AND lead_id = ?");
    $stmt_del->execute([$hist_id, $id]);
    header("Location: lead_view.php?id=$id&msg=del_ok");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_rapido'])) {
    $temp = $_POST['temperatura'];
    $fase = $_POST['fase_funil'];
    $passo = $_POST['proximo_passo'];
    
    $stmt_up = $conn->prepare("UPDATE leads SET temperatura = ?, fase_funil = ?, proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
    $stmt_up->execute([$temp, $fase, $passo, $id]);
    header("Location: lead_view.php?id=$id&msg=status_ok");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_historico'])) {
    $acao = trim($_POST['acao']);
    $detalhes = trim($_POST['detalhes']);
    if (!empty($acao)) {
        $stmt_hist = $conn->prepare("INSERT INTO lead_historico (lead_id, acao, detalhes) VALUES (?, ?, ?)");
        $stmt_hist->execute([$id, $acao, $detalhes]);
        $conn->prepare("UPDATE leads SET ultima_interacao = NOW() WHERE id = ?")->execute([$id]);
        header("Location: lead_view.php?id=$id&msg=hist_ok");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visita'])) {
    $imovel_id = $_POST['imovel_id'];
    $data_visita = $_POST['data_visita'];
    $observacoes = trim($_POST['observacoes']);

    if (!empty($data_visita)) {
        $stmt_visita = $conn->prepare("INSERT INTO visitas (lead_id, imovel_id, data_visita, observacoes) VALUES (?, ?, ?, ?)");
        $stmt_visita->execute([$id, $imovel_id, $data_visita, $observacoes]);
        $conn->prepare("UPDATE leads SET fase_funil = 'Visita Agendada', ultima_interacao = NOW() WHERE id = ?")->execute([$id]);
        header("Location: lead_view.php?id=$id&msg=visita_ok");
        exit;
    }
}

// BUSCA DE DADOS ADICIONAIS
$imoveis_list = $conn->query("SELECT id, titulo, bairro FROM imoveis ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt_h = $conn->prepare("SELECT * FROM lead_historico WHERE lead_id = ? ORDER BY data_registro DESC");
$stmt_h->execute([$id]);
$historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

$stmt_v = $conn->prepare("
    SELECT v.*, i.titulo as imovel_titulo 
    FROM visitas v
    LEFT JOIN imoveis i ON v.imovel_id = i.id
    WHERE v.lead_id = ? 
    ORDER BY v.data_visita DESC
");
$stmt_v->execute([$id]);
$visitas = $stmt_v->fetchAll(PDO::FETCH_ASSOC);

$temp_class = 'bg-secondary';
$temp_label = $lead['temperatura'] ?: 'Morno';
if ($temp_label == 'Quente') $temp_class = 'bg-danger';
if ($temp_label == 'Morno') $temp_class = 'bg-warning text-dark';
if ($temp_label == 'Frio') $temp_class = 'bg-info text-white';

require_once '../../includes/header.php';
?>

<div class="container-fluid py-4 px-4">
    
    <div class="card border-0 shadow mb-4 sticky-top" style="background: linear-gradient(to right, #ffffff, #f8f9fa); border-radius: 15px; border-left: 6px solid #0d6efd !important; top: 10px; z-index: 1020;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-xl-7 col-lg-6">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <h2 class="fw-bold text-primary mb-0" style="letter-spacing: -0.5px;">
                            <?= htmlspecialchars($lead['nome']) ?>
                        </h2>
                        
                        <div class="btn-group shadow-sm bg-white">
                            <a href="lead_view.php?id=<?= $first_id ?>" class="btn btn-outline-secondary btn-sm <?= ($id <= $first_id) ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-left"></i></a>
                            <a href="lead_view.php?id=<?= $prev_id ?>" class="btn btn-outline-secondary btn-sm <?= !$prev_id ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
                            <a href="lead_view.php?id=<?= $next_id ?>" class="btn btn-outline-secondary btn-sm <?= !$next_id ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
                            <a href="lead_view.php?id=<?= $last_id ?>" class="btn btn-outline-secondary btn-sm <?= ($id >= $last_id) ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-4 mt-3 mb-2 align-items-center">
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone']) ?>" target="_blank" class="text-decoration-none fw-bold text-success fs-5">
                            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($lead['telefone']) ?>
                        </a>
                        <?php if($lead['email']): ?>
                            <span class="text-muted"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($lead['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
                        <span class="small"><strong class="text-muted">Preço:</strong> <span class="text-success fw-bold">R$ <?= number_format($lead['valor_max'], 2, ',', '.') ?></span></span>
                        <span class="small"><strong class="text-muted">Localização:</strong> <span class="text-dark"><?= htmlspecialchars($lead['preferencia_localizacao'] ?: 'N/A') ?></span></span>
                        <?php if($lead['proximo_passo']): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2">
                                <i class="bi bi-arrow-right-circle me-1"></i><?= htmlspecialchars($lead['proximo_passo']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <span class="badge bg-dark rounded-pill px-3">ID #<?= $lead['id'] ?></span>
                        <span class="badge bg-light text-dark border rounded-pill px-3"><?= htmlspecialchars($lead['fase_funil']) ?></span>
                        <span class="badge <?= $temp_class ?> rounded-pill px-3"><i class="bi bi-thermometer-half"></i> <?= $temp_label ?></span>
                    </div>
                </div>

                <div class="col-xl-5 col-lg-6 mt-3 mt-lg-0">
                    <div class="d-flex flex-column gap-3 align-items-lg-end">
                        <form method="post" class="d-flex gap-1 w-100 justify-content-lg-end">
                            <div class="input-group input-group-sm shadow-sm" style="max-width: 320px;">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search_term" class="form-control border-start-0" placeholder="Pesquisar lead por nome..." required>
                                <button type="submit" name="search_lead" class="btn btn-primary px-3 fw-bold">Pesquisar</button>
                            </div>
                        </form>

                        <div class="d-flex gap-2">
                            <a href="lead_form.php?id=<?= $id ?>" target="_blank" class="btn btn-warning fw-bold shadow-sm px-4">
                                <i class="bi bi-pencil-square me-2"></i>Editar
                            </a>
                            <a href="leads.php" class="btn btn-white border shadow-sm px-4">
                                <i class="bi bi-arrow-left me-2"></i>Voltar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 bg-light border-start border-primary border-4">
                <div class="card-body p-3">
                    <form method="post">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="fw-bold small text-muted text-uppercase" style="font-size: 0.65rem;">Fase</label>
                                <select name="fase_funil" class="form-select form-select-sm">
                                    <?php foreach ($fases_funil as $fase): ?>
                                        <option value="<?= $fase ?>" <?= $lead['fase_funil'] == $fase ? 'selected' : '' ?>><?= $fase ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="fw-bold small text-muted text-uppercase" style="font-size: 0.65rem;">Temperatura</label>
                                <select name="temperatura" class="form-select form-select-sm">
                                    <option value="Frio" <?= $temp_label == 'Frio' ? 'selected' : '' ?>>❄️ Frio</option>
                                    <option value="Morno" <?= $temp_label == 'Morno' ? 'selected' : '' ?>>🌤️ Morno</option>
                                    <option value="Quente" <?= $temp_label == 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                                </select>
                            </div>
                            <div class="col-12 mt-2">
                                <label class="fw-bold small text-muted text-uppercase" style="font-size: 0.65rem;">Próximo Passo</label>
                                <select name="proximo_passo" class="form-select form-select-sm">
                                    <option value="">-- Definir Ação --</option>
                                    <?php foreach ($opcoes_passos as $op): ?>
                                        <option value="<?= $op ?>" <?= $lead['proximo_passo'] == $op ? 'selected' : '' ?>><?= $op ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="submit" name="update_status_rapido" class="btn btn-sm btn-primary w-100 fw-bold">Salvar Status</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3 border-top border-info border-4">
                <div class="card-header bg-white fw-bold py-2 small text-uppercase text-info">Agendar Visita</div>
                <div class="card-body p-3">
                    <form method="post">
                        <select name="imovel_id" class="form-select form-select-sm mb-2" required>
                            <option value="">Imóvel...</option>
                            <?php foreach ($imoveis_list as $im): ?>
                                <option value="<?= $im['id'] ?>"><?= htmlspecialchars($im['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="datetime-local" name="data_visita" class="form-control form-control-sm mb-2" required>
                        <textarea name="observacoes" class="form-control form-control-sm mb-2" rows="2" placeholder="Notas..."></textarea>
                        <button type="submit" name="add_visita" class="btn btn-info btn-sm w-100 text-white fw-bold">Agendar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4 border-top border-danger border-4">
                <div class="card-header bg-white fw-bold py-2 small text-uppercase d-flex justify-content-between align-items-center text-danger">
                    <span>Visitas</span>
                    <span class="badge rounded-pill bg-danger"><?= count($visitas) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light small">
                                <tr><th>Data</th><th>Imóvel</th><th>Observações</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($visitas)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma visita registrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($visitas as $v): ?>
                                    <tr>
                                        <td class="small fw-bold py-3 px-3"><?= date('d/m/Y H:i', strtotime($v['data_visita'])) ?></td>
                                        <td class="small text-primary fw-bold"><?= htmlspecialchars($v['imovel_titulo']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($v['observacoes']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<div class="card shadow-sm border-0 border-top border-success border-4">
    <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Linha do Tempo</span>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2 mb-4 bg-light p-3 rounded border shadow-sm">
            <div class="col-md-3">
                <input type="text" name="acao" class="form-control form-control-sm" placeholder="Ação..." required>
            </div>
            <div class="col-md-7">
                <input type="text" name="detalhes" class="form-control form-control-sm" placeholder="Detalhes...">
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_historico" class="btn btn-success btn-sm w-100 fw-bold">Registrar</button>
            </div>
        </form>

        <div class="list-group list-group-flush">
            <?php foreach ($historico as $h): ?>
            <div class="list-group-item px-0 py-2 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                    
                    <div class="text-dark">
                        <span class="text-muted me-2" style="font-size: 0.8rem; font-family: monospace;">
                            [<?= date('d/m/y H:i', strtotime($h['data_registro'])) ?>]
                        </span>
                        
                        <span class="fw-bold text-primary me-1"><?= htmlspecialchars($h['acao']) ?>:</span>
                        <span class="small"><?= nl2br(htmlspecialchars($h['detalhes'])) ?></span>
                    </div>

                    <form method="post" onsubmit="return confirm('Excluir registro?')" class="ms-2">
                        <input type="hidden" name="historico_id" value="<?= $h['id'] ?>">
                        <button type="submit" name="del_historico" class="btn btn-link text-danger btn-sm p-0 m-0" style="line-height: 1;">
                            <i class="bi bi-trash3" style="font-size: 0.85rem;"></i>
                        </button>
                    </form>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

            <div class="card shadow-sm border-0 mt-4 border-start border-warning border-4">
                <div class="card-header bg-white fw-bold text-warning text-uppercase small">Observações Iniciais</div>
                <div class="card-body bg-light">
                    <div class="p-2 text-muted">
                        <?= $lead['observacoes'] ? nl2br(htmlspecialchars($lead['observacoes'])) : '<em>Nenhuma observação.</em>' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>