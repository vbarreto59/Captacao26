<?php
// lead_view.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================

// Atualizar Temperatura e Fase do Funil (Ações Rápidas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_rapido'])) {
    $temp = $_POST['temperatura'];
    $fase = $_POST['fase_funil'];
    $stmt_up = $conn->prepare("UPDATE leads SET temperatura = ?, fase_funil = ? WHERE id = ?");
    $stmt_up->execute([$temp, $fase, $id]);
    header("Location: lead_view.php?id=$id&msg=status_ok");
    exit;
}

// Adicionar Registro no Histórico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_historico'])) {
    $acao = trim($_POST['acao']);
    $detalhes = trim($_POST['detalhes']);
    if (!empty($acao)) {
        $stmt_hist = $conn->prepare("INSERT INTO lead_historico (lead_id, acao, detalhes) VALUES (?, ?, ?)");
        $stmt_hist->execute([$id, $acao, $detalhes]);
        header("Location: lead_view.php?id=$id&msg=hist_ok");
        exit;
    }
}

// Agendar Nova Visita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visita'])) {
    $imovel_id = $_POST['imovel_id'];
    $data_visita = $_POST['data_visita'];
    $observacoes = trim($_POST['observacoes']);

    if (!empty($data_visita)) {
        $stmt_visita = $conn->prepare("INSERT INTO visitas (lead_id, imovel_id, data_visita, observacoes) VALUES (?, ?, ?, ?)");
        $stmt_visita->execute([$id, $imovel_id, $data_visita, $observacoes]);
        
        // Atualiza a fase do funil automaticamente ao agendar visita
        $conn->prepare("UPDATE leads SET fase_funil = 'Visita Agendada' WHERE id = ?")->execute([$id]);
        
        header("Location: lead_view.php?id=$id&msg=visita_ok");
        exit;
    }
}

// ==========================================
// BUSCA DE DADOS PARA A INTERFACE
// ==========================================
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

// Opções do Funil (Centralizado)
$fases_funil = ["Novo", "Contato Feito", "Tentativa de Contato", "Visita Agendada", "Visita Realizada", "Analisando", "Proposta", "Fechado", "Perdido"];

// Definir cores baseadas na temperatura
$temp_class = 'bg-secondary';
$temp_label = $lead['temperatura'] ?: 'Morno';
if ($temp_label == 'Quente') $temp_class = 'bg-danger';
if ($temp_label == 'Morno') $temp_class = 'bg-warning text-dark';
if ($temp_label == 'Frio') $temp_class = 'bg-info text-white';

require_once '../../includes/header.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold text-primary mb-0"><?= htmlspecialchars($lead['nome']) ?></h2>
            <div class="mt-2">
                <span class="badge bg-dark">Lead #<?= $lead['id'] ?></span>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($lead['fase_funil']) ?></span>
                <span class="badge <?= $temp_class ?>"><i class="bi bi-thermometer-half"></i> <?= $temp_label ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="lead_form.php?id=<?= $id ?>" target="_blank" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-pencil-square"></i> Editar Registro
            </a>
            <a href="leads.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4">
            
            <div class="card shadow-sm border-0 mb-4 bg-light border-start border-primary border-4">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Fase do Funil</label>
                            <select name="fase_funil" class="form-select form-select-sm shadow-none mb-2">
                                <?php foreach ($fases_funil as $fase): ?>
                                    <option value="<?= $fase ?>" <?= $lead['fase_funil'] == $fase ? 'selected' : '' ?>><?= $fase ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Temperatura</label>
                            <select name="temperatura" class="form-select form-select-sm shadow-none">
                                <option value="Frio" <?= $temp_label == 'Frio' ? 'selected' : '' ?>>❄️ Frio</option>
                                <option value="Morno" <?= $temp_label == 'Morno' ? 'selected' : '' ?>>🌤️ Morno</option>
                                <option value="Quente" <?= $temp_label == 'Quente' ? 'selected' : '' ?>>🔥 Quente</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status_rapido" class="btn btn-sm btn-primary w-100 fw-bold">Atualizar Status</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white fw-bold">Informações do Cliente</div>
                <div class="card-body">
                    <p class="mb-2"><strong><i class="bi bi-whatsapp text-success"></i> WhatsApp:</strong><br>
                    <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone']) ?>" target="_blank" class="text-decoration-none fw-bold">
                        <?= htmlspecialchars($lead['telefone']) ?>
                    </a></p>
                    
                    <p class="mb-2"><strong><i class="bi bi-envelope"></i> E-mail:</strong><br>
                    <small><?= htmlspecialchars($lead['email'] ?: 'Não informado') ?></small></p>
                    
                    <hr>
                    
                    <p class="mb-1"><strong>Preço:</strong> <span class="text-success fw-bold">R$ <?= number_format($lead['valor_max'], 2, ',', '.') ?></span></p>
                    <p class="mb-1"><strong>Localização:</strong> <small><?= htmlspecialchars($lead['preferencia_localizacao'] ?: 'N/A') ?></small></p>
                    
                    <div class="bg-light p-2 rounded small mt-3 text-muted">
                        <strong>Observações iniciais:</strong><br>
                        <?= nl2br(htmlspecialchars($lead['observacoes'])) ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-top border-info border-4">
                <div class="card-header bg-white fw-bold">Agendar Nova Visita</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-2">
                            <label class="small fw-bold">Imóvel</label>
                            <select name="imovel_id" class="form-select form-select-sm" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($imoveis_list as $im): ?>
                                    <option value="<?= $im['id'] ?>"><?= htmlspecialchars($im['titulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Data/Hora</label>
                            <input type="datetime-local" name="data_visita" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Notas</label>
                            <textarea name="observacoes" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" name="add_visita" class="btn btn-info btn-sm w-100 text-white fw-bold">Agendar Visita</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-geo-alt text-danger"></i> Visitas ao Imóvel</span>
                    <span class="badge rounded-pill bg-danger"><?= count($visitas) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Data</th>
                                <th>Imóvel</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visitas)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3 small">Nenhuma visita registrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($visitas as $v): ?>
                                <tr>
                                    <td class="small fw-bold"><?= date('d/m/Y H:i', strtotime($v['data_visita'])) ?></td>
                                    <td class="small text-primary fw-bold"><?= htmlspecialchars($v['imovel_titulo']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($v['observacoes']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Linha do Tempo de Contatos</div>
                <div class="card-body">
                    <form method="post" class="row g-2 mb-4">
                        <div class="col-md-3">
                            <input type="text" name="acao" class="form-control form-control-sm" placeholder="Ação (Ex: Ligação)" required>
                        </div>
                        <div class="col-md-7">
                            <input type="text" name="detalhes" class="form-control form-control-sm" placeholder="Detalhes do contato...">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add_historico" class="btn btn-success btn-sm w-100">Registrar</button>
                        </div>
                    </form>

                    <div class="list-group list-group-flush">
                        <?php foreach ($historico as $h): ?>
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($h['acao']) ?></h6>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('d/m/Y H:i', strtotime($h['data_registro'])) ?></small>
                            </div>
                            <p class="mb-0 small text-dark"><?= nl2br(htmlspecialchars($h['detalhes'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<!-- 268 -->