<?php
// favoritos.php - Versão mobile responsiva
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// LÓGICA AJAX (PROCESSAMENTO)
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if ($_POST['action'] == 'update_temp') {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['temp'], $id]) ? 'success' : 'error']);
    } 
    elseif ($_POST['action'] == 'update_step') {
        $stmt = $conn->prepare("UPDATE leads SET proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$_POST['step'], $id]) ? 'success' : 'error']);
    }
    elseif ($_POST['action'] == 'update_obs') {
        $obs = $_POST['observacoes'];
        $stmt = $conn->prepare("UPDATE leads SET observacoes = ?, ultima_interacao = NOW() WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$obs, $id]) ? 'success' : 'error']);
    }
    exit; 
}

// ==========================================
// CONFIGURAÇÕES
// ==========================================
$opcoes_passos = ["Ligar para qualificar", "Agendar visita", "Enviar simulação", "Enviar imagens","Cobrar feedback", "Enviar opções similares", "Aguardando retorno"];
$temps_lista = ['Quente' => '🔥 Quente', 'Morno' => '⚖️ Morno', 'Frio' => '❄️ Frio'];

// Filtro fixo: apenas favoritos
$where = "WHERE l.favorito = 1";
$params = [];

$busca = $_GET['busca'] ?? '';
if ($busca) {
    $where .= " AND (l.nome LIKE ? OR l.telefone LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

function getFaseColor($fase) {
    $cores = [
        'Novo'=>'bg-info text-dark',
        'Tentativa de Contato'=>'bg-warning text-dark',
        'Contato Feito'=>'bg-primary text-white',
        'Visita Agendada'=>'bg-success text-white',
        'Visita Realizada'=>'bg-dark text-white',
        'Analisando'=>'bg-secondary text-white',
        'Proposta'=>'bg-danger text-white',
        'Fechado'=>'bg-success text-white',
        'Perdido'=>'bg-light text-muted'
    ];
    return $cores[$fase] ?? 'bg-light text-dark';
}

$sql = "SELECT l.*, 
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado,
        (SELECT GROUP_CONCAT(res ORDER BY data_registro DESC SEPARATOR '||') FROM (
            SELECT CONCAT(DATE_FORMAT(data_registro, '%d/%m'), ' - ', detalhes) as res, lead_id, data_registro 
            FROM lead_historico 
        ) as sub_hist WHERE sub_hist.lead_id = l.id) as resumo_historico,
        (SELECT GROUP_CONCAT(v_res ORDER BY dv DESC SEPARATOR '||') FROM (
            SELECT 
                CONCAT(DATE_FORMAT(v.data_visita, '%d/%m %H:%i'), ' - ', COALESCE(i.titulo, 'Sem imóvel')) as v_res,
                v.lead_id, v.data_visita as dv
            FROM visitas v
            LEFT JOIN imoveis i ON v.imovel_id = i.id
        ) as sub_v WHERE sub_v.lead_id = l.id) as ultimas_visitas_reais
        FROM leads l $where ORDER BY l.ultima_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
    /* ESTILOS MOBILE RESPONSIVO */
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Roboto, system-ui, -apple-system, sans-serif;
    }
    
    /* Card de lead */
    .lead-card {
        background: #fff;
        border-radius: 20px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: all 0.2s;
        border-left: 6px solid #6c757d;
        overflow: hidden;
    }
    .lead-card.lead-quente { border-left-color: #dc3545; background: #fff8f8; }
    .lead-card.lead-morno  { border-left-color: #ffc107; background: #fffbeb; }
    .lead-card.lead-frio   { border-left-color: #17a2b8; background: #f0fafc; }
    
    .card-header-custom {
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .lead-name {
        font-size: 1.2rem;
        font-weight: 700;
        word-break: break-word;
        flex: 1;
    }
    .lead-id {
        font-size: 0.7rem;
        color: #6c757d;
        background: #f1f3f5;
        padding: 2px 8px;
        border-radius: 30px;
    }
    .fase-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
        text-align: center;
    }
    .obs-preview-card {
        background: #f8f9fa;
        margin: 8px 16px 12px 16px;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 0.85rem;
        color: #2c3e50;
        cursor: pointer;
        border: 1px solid #e9ecef;
    }
    .temp-selector {
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: flex-start;
        background: #fff;
        padding: 8px 12px;
        border-radius: 40px;
        margin: 0 16px 12px 16px;
        border: 1px solid #e9ecef;
    }
    .temp-badge {
        font-size: 1.5rem;
        cursor: pointer;
        filter: grayscale(0.6);
        opacity: 0.5;
        transition: 0.2s;
        padding: 4px 8px;
        border-radius: 40px;
    }
    .temp-badge.active {
        filter: grayscale(0);
        opacity: 1;
        transform: scale(1.1);
        background: rgba(0,0,0,0.05);
    }
    .action-buttons {
        display: flex;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid #f0f0f0;
        background: #fff;
    }
    .btn-mobile {
        flex: 1;
        padding: 10px 0;
        font-size: 0.85rem;
        border-radius: 40px;
        font-weight: 500;
    }
    .star-fav {
        font-size: 1.6rem;
        cursor: pointer;
        color: #ffc107;
        background: transparent;
        border: none;
        line-height: 1;
    }
    .text-muted-italic {
        color: #adb5bd;
        font-style: italic;
    }
    .search-bar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #f8f9fa;
        padding: 12px 16px;
        border-bottom: 1px solid #e9ecef;
    }
    .count-badge {
        background: #e9ecef;
        border-radius: 40px;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    @media (min-width: 768px) {
        .lead-card {
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
    }
</style>

<div class="container px-2 py-3 pb-5">
    <!-- Cabeçalho + Busca -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 px-1">
        <div>
            <h3 class="fw-bold text-warning m-0"><i class="bi bi-star-fill"></i> Favoritos</h3>
            <span class="count-badge mt-1 d-inline-block"><?= count($lista) ?> leads</span>
        </div>
        <a href="leads.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="search-bar mb-3 rounded-4 shadow-sm">
        <form action="" method="GET" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control border-start-0" placeholder="Buscar nos favoritos..." value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary rounded-end-pill px-4">Buscar</button>
            </div>
        </form>
    </div>

    <?php if (empty($lista)): ?>
        <div class="text-center py-5 bg-white rounded-4 shadow-sm">
            <i class="bi bi-star display-1 text-muted"></i>
            <p class="mt-3 fs-5">Nenhum lead favorito encontrado.</p>
            <a href="leads.php" class="btn btn-primary rounded-pill px-4 mt-2">Ver todos os leads</a>
        </div>
    <?php else: ?>
        <?php foreach ($lista as $l): 
            $temp = $l['temperatura'] ?: 'Morno';
            $classe_temperatura = match($temp) { 
                'Quente' => 'lead-quente', 
                'Morno' => 'lead-morno', 
                'Frio' => 'lead-frio', 
                default => 'lead-morno' 
            };
        ?>
            <div class="lead-card <?= $classe_temperatura ?>" id="card-lead-<?= $l['id'] ?>">
                <!-- Header do card -->
                <div class="card-header-custom">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button class="star-fav btn-favorito-mobile" data-id="<?= $l['id'] ?>" title="Remover dos favoritos">
                            <i class="bi bi-star-fill"></i>
                        </button>
                        <span class="lead-name"><?= htmlspecialchars($l['nome']) ?></span>
                        <span class="lead-id">#<?= $l['id'] ?></span>
                    </div>
                </div>
                
                <!-- Fase do funil -->
                <div class="px-3 pt-2">
                    <span class="fase-badge <?= getFaseColor($l['fase_funil']) ?> w-100 text-center d-block">
                        <?= $l['fase_funil'] ?: 'Novo' ?>
                    </span>
                </div>

                <!-- Observações (clicável) -->
                <div class="obs-preview-card btn-obs-mobile" 
                     data-id="<?= $l['id'] ?>" 
                     data-nome="<?= htmlspecialchars($l['nome']) ?>" 
                     data-obs="<?= htmlspecialchars($l['observacoes']) ?>">
                    <?php if (empty($l['observacoes'])): ?>
                        <span class="text-muted-italic"><i class="bi bi-pencil-square"></i> Toque para adicionar observações...</span>
                    <?php else: ?>
                        <i class="bi bi-chat-dots"></i> <?= nl2br(htmlspecialchars(mb_substr($l['observacoes'], 0, 100))) ?>
                        <?php if (strlen($l['observacoes']) > 100): ?>...<?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Seletor de temperatura -->
                <div class="temp-selector">
                    <span class="small text-muted me-1">🌡️ Temp:</span>
                    <span class="temp-badge <?= $temp == 'Quente' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Quente">🔥 Quente</span>
                    <span class="temp-badge <?= $temp == 'Morno' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Morno">⚖️ Morno</span>
                    <span class="temp-badge <?= $temp == 'Frio' ? 'active' : '' ?>" data-id="<?= $l['id'] ?>" data-temp="Frio">❄️ Frio</span>
                </div>

                <!-- Botões de ação -->
                <div class="action-buttons">
                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-outline-primary btn-mobile">
                        <i class="bi bi-eye"></i> Ver completo
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal de Observações (mesmo do original) -->
<div class="modal fade" id="modalObsMobile" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Observações: <span id="nomeLeadObsMobile"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formObsMobile">
                <input type="hidden" id="obs_lead_id_mobile" name="id">
                <div class="modal-body bg-light">
                    <textarea class="form-control border-0 shadow-sm" id="obs_texto_mobile" name="observacoes" rows="8" placeholder="Digite aqui as observações..."></textarea>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Salvar Notas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ================= FAVORITAR / DESFAVORITAR =================
    $(document).on('click', '.btn-favorito-mobile', function(e) {
        e.stopPropagation();
        const leadId = $(this).data('id');
        $.post('atualizar_favorito.php', { id: leadId }, function() {
            location.reload(); // recarrega para remover da lista de favoritos
        });
    });

    // ================= OBSERVAÇÕES =================
    $(document).on('click', '.btn-obs-mobile', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const obs = $(this).data('obs') || '';
        $('#obs_lead_id_mobile').val(id);
        $('#nomeLeadObsMobile').text(nome);
        $('#obs_texto_mobile').val(obs);
        $('#modalObsMobile').modal('show');
    });

    $('#formObsMobile').on('submit', function(e) {
        e.preventDefault();
        const id = $('#obs_lead_id_mobile').val();
        const obs = $('#obs_texto_mobile').val();
        $.post('favoritos.php', { action: 'update_obs', id: id, observacoes: obs }, function(res) {
            if (res.status === 'success') location.reload();
        }).fail(function() { alert('Erro ao salvar observações.'); });
    });

    // ================= TEMPERATURA =================
    $(document).on('click', '.temp-badge', function() {
        const $this = $(this);
        const id = $this.data('id');
        const temp = $this.data('temp');
        $.post('favoritos.php', { action: 'update_temp', id: id, temp: temp }, function(res) {
            if (res.status === 'success') location.reload();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>