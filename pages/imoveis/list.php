<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

$hoje_agora = date('Y-m-d H:i:s');

// ================================================
// Lógica para Salvar Agendamento
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar_visita'])) {
    $imovel_id = $_POST['imovel_id'];
    $lead_id = $_POST['lead_id'];
    $data_visita = $_POST['data_visita'] . ' ' . $_POST['hora_visita'];
    $obs = $_POST['observacoes'];

    $sql_agendar = "INSERT INTO visitas (imovel_id, lead_id, data_visita, descricao, status) VALUES (?, ?, ?, ?, 'pendente')";
    $stmt_agendar = $conn->prepare($sql_agendar);
    if ($stmt_agendar->execute([$imovel_id, $lead_id, $data_visita, $obs])) {
        $_SESSION['msg'] = "Visita agendada com sucesso!";
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit;
    }
}

// Busca de Leads para o Select
$leads = $conn->query("SELECT id, nome FROM leads ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// ================================================
// Lógica de Filtros
// ================================================
$where = "WHERE i.deleted_at IS NULL";
$params = [];
if (!empty($_GET['busca'])) {
    $busca = '%' . trim($_GET['busca']) . '%';
    $where .= " AND (i.titulo LIKE ? OR i.bairro LIKE ? OR i.cidade LIKE ? OR i.endereco LIKE ?)";
    $params = array_merge($params, [$busca, $busca, $busca, $busca]);
}

// ================================================
// Consulta SQL Principal (Mantendo todos os campos)
// ================================================
$sql = "
    SELECT
        i.*,
        p.nome as nome_proprietario,
        (SELECT caminho FROM fotos_imoveis 
         WHERE imovel_id = i.id ORDER BY id ASC LIMIT 1) AS foto_capa,
        (SELECT COUNT(*) FROM fotos_imoveis WHERE imovel_id = i.id) AS total_fotos,
        (SELECT COUNT(*) FROM visitas v WHERE v.imovel_id = i.id AND v.data_visita < '$hoje_agora') AS visitas_realizadas,
        (SELECT COUNT(*) FROM visitas v WHERE v.imovel_id = i.id AND v.data_visita >= '$hoje_agora') AS visitas_agendadas
    FROM imoveis i
    LEFT JOIN proprietarios p ON i.proprietario_id = p.id
    $where
    ORDER BY i.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>

<style>
    .hover-up:hover { transform: translateY(-8px); transition: all 0.3s ease; box-shadow: 0 1rem 3rem rgba(0,0,0,0.15)!important; }
    .card-img-top { height: 220px; object-fit: cover; transition: transform 0.4s ease; }
    .card:hover .card-img-top { transform: scale(1.05); }
    .foto-count { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.75); color: white; font-size: 0.8rem; padding: 3px 8px; border-radius: 12px; }
    
    .visitas-badges { position: absolute; top: 10px; left: 10px; display: flex; flex-direction: column; gap: 5px; z-index: 10; }
    .badge-visita { font-size: 0.65rem; padding: 4px 8px; border-radius: 4px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer; transition: 0.2s; }
    .badge-visita:hover { filter: brightness(1.2); transform: scale(1.1); }

    .bg-financeiro { background-color: #f0f7ff; }
</style>

<div class="container-fluid pb-5">
    <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="text-primary fw-bold mb-0">Gestão de Captações</h1>
            <p class="text-muted small">Portfólio em <?= date('Y') ?> | Boa Viagem e Região</p>
        </div>
        <div class="col-md-6 text-md-end">
            <form action="" method="GET" class="d-inline-block me-2">
                <div class="input-group">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar bairro, título..." value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <a href="form.php" class="btn btn-primary shadow">
                <i class="bi bi-plus-circle me-2"></i>Novo Imóvel
            </a>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        <?php foreach ($imoveis as $im):
            $foto_capa = $im['foto_capa'] ? '../../uploads/fotos_imoveis/' . htmlspecialchars($im['foto_capa']) : 'https://via.placeholder.com/400x250?text=Sem+Foto';
            $status_color = match($im['status']) { 'captado' => 'success', 'em_negociacao' => 'warning', 'vendido' => 'danger', default => 'secondary' };
            
            // Link do Maps
            $address_encoded = urlencode($im['endereco'] . ', ' . $im['bairro'] . ', ' . $im['cidade']);
            $maps_link = "https://www.google.com/maps/search/?api=1&query={$address_encoded}";
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0 hover-up transition">
                <div class="position-relative overflow-hidden">
                    <img src="<?= $foto_capa ?>" class="card-img-top" alt="Foto">
                    
                    <span class="position-absolute top-0 end-0 m-2 badge bg-<?= $status_color ?> shadow">
                        <?= ucfirst(str_replace('_', ' ', $im['status'])) ?>
                    </span>

                    <div class="visitas-badges">
                        <?php if ((int)$im['visitas_realizadas'] > 0): ?>
                            <span class="badge-visita bg-secondary text-white" onclick="verHistoricoVisitas(<?= $im['id'] ?>, '<?= addslashes(htmlspecialchars($im['titulo'])) ?>')">
                                <i class="bi bi-check2-all"></i> Realizadas: <?= $im['visitas_realizadas'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ((int)$im['visitas_agendadas'] > 0): ?>
                            <span class="badge-visita bg-info text-white" onclick="verHistoricoVisitas(<?= $im['id'] ?>, '<?= addslashes(htmlspecialchars($im['titulo'])) ?>')">
                                <i class="bi bi-calendar-event"></i> Agendadas: <?= $im['visitas_agendadas'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ((int)$im['total_fotos'] > 1): ?>
                        <span class="foto-count"><i class="bi bi-images"></i> <?= $im['total_fotos'] ?></span>
                    <?php endif; ?>

                    <div class="position-absolute bottom-0 start-0 m-2 d-flex gap-1">
                        <?php if($im['andar']): ?> <span class="badge bg-dark"><?= $im['andar'] ?>º Andar</span> <?php endif; ?>
                    </div>
                </div>

                <div class="card-body d-flex flex-column">
                    <h5 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($im['titulo']) ?></h5>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($im['bairro']) ?>
                    </p>

                    <div class="bg-financeiro rounded p-3 mb-3">
                        <h4 class="fw-bold text-primary mb-0">R$ <?= number_format($im['preco'], 2, ',', '.') ?></h4>
                    </div>

                    <div class="row text-center g-0 mb-3 border rounded py-2 bg-white small">
                        <div class="col-4 border-end"><b><?= $im['quartos'] ?></b><br>Quartos</div>
                        <div class="col-4 border-end"><b><?= $im['vagas_garagem'] ?></b><br>Vagas</div>
                        <div class="col-4"><b><?= (float)$im['area'] ?>m²</b><br>Área</div>
                    </div>

                    <div class="mt-auto">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="view.php?id=<?= $im['id'] ?>" class="btn btn-primary w-100 btn-sm">Ver Detalhes</a>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-warning w-100 btn-sm fw-bold shadow-sm" 
                                        onclick="abrirModalAgendamento(<?= $im['id'] ?>, '<?= addslashes(htmlspecialchars($im['titulo'])) ?>')">
                                    <i class="bi bi-calendar-plus"></i> Agendar
                                </button>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="btn-group w-100">
                                    <a href="<?= $maps_link ?>" target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-geo-alt"></i> Mapa</a>
                                    <a href="form.php?id=<?= $im['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="modalAgendarVisita" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-check me-2"></i>Agendar Visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="imovel_id" id="modal_imovel_id">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Imóvel</label>
                    <input type="text" id="modal_imovel_titulo" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Lead (Cliente)</label>
                    <select name="lead_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label fw-bold">Data</label><input type="date" name="data_visita" class="form-control" required min="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6 mb-3"><label class="form-label fw-bold">Hora</label><input type="time" name="hora_visita" class="form-control" required></div>
                </div>
                <div class="mb-0"><label class="form-label fw-bold">Observações</label><textarea name="observacoes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" name="agendar_visita" class="btn btn-warning fw-bold px-4">Confirmar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalVerVisitas" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="titulo_modal_historico">Histórico de Visitas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lista_visitas_conteudo"></div>
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModalAgendamento(id, titulo) {
        document.getElementById('modal_imovel_id').value = id;
        document.getElementById('modal_imovel_titulo').value = titulo;
        new bootstrap.Modal(document.getElementById('modalAgendarVisita')).show();
    }

    function verHistoricoVisitas(imovelId, imovelTitulo) {
        document.getElementById('titulo_modal_historico').innerText = "Visitas: " + imovelTitulo;
        const container = document.getElementById('lista_visitas_conteudo');
        container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        new bootstrap.Modal(document.getElementById('modalVerVisitas')).show();

        fetch('get_visitas_imovel.php?imovel_id=' + imovelId)
            .then(r => r.text())
            .then(html => { container.innerHTML = html; });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>