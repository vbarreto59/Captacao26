<?php
// leads_kanban.php
session_start();

// Impedir avisos de poluir o retorno JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$opcoes_passos = [
    "Lead recebido",
    "Ligar para qualificar",
    "Agendar visita",
    "Enviar simulação",
    "Cobrar feedback",
    "Enviar opções similares",
    "Aguardando retorno"
];

// ==========================================
// AJAX - PROCESSAMENTO DE DADOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_kanban') {
            $id_movido = (int)($_POST['id'] ?? 0);
            $nova_etapa = $_POST['step'] ?? '';
            $nova_ordem_ids = $_POST['order'] ?? [];

            if (!$id_movido) throw new Exception("ID inválido.");

            $conn->beginTransaction();

            // Atualiza etapa e data de interação
            $stmt = $conn->prepare("UPDATE leads SET proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
            $stmt->execute([$nova_etapa, $id_movido]);

            // Atualiza ordem dos cards na coluna de destino
            if (is_array($nova_ordem_ids)) {
                foreach ($nova_ordem_ids as $index => $id) {
                    $stmtOrder = $conn->prepare("UPDATE leads SET ordem = ? WHERE id = ?");
                    $stmtOrder->execute([(int)$index, (int)$id]);
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success']);
            exit;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// BUSCA DOS LEADS
// ==========================================
$sql = "SELECT * FROM leads ORDER BY proximo_passo ASC, ordem ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$leads_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kanban = [];
foreach ($opcoes_passos as $etapa) { $kanban[$etapa] = []; }

foreach ($leads_db as $l) {
    $etapa = trim($l['proximo_passo'] ?? '');
    if (array_key_exists($etapa, $kanban)) {
        $kanban[$etapa][] = $l;
    } else {
        $kanban["Lead recebido"][] = $l;
    }
}

require_once '../../includes/header.php';
?>

<style>
    /* RESET DE MARGENS PARA LARGURA TOTAL */
    body, html { 
        overflow-x: hidden; 
        width: 100%;
        background-color: #ebedef;
    }

    /* Força o container pai a ignorar paddings do Bootstrap */
    .main-wrapper, .container, .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        max-width: 100% !important;
    }

    /* ESTRUTURA DO TABULEIRO */
    .kanban-wrapper {
        width: 100vw;
        height: calc(100vh - 110px);
        overflow-x: auto;
        overflow-y: hidden;
        display: flex;
        flex-direction: column;
    }

    .kanban-board { 
        display: flex; 
        gap: 0.75rem; 
        padding: 10px 10px 20px 10px; /* Pequeno respiro nas pontas */
        align-items: flex-start;
        flex-grow: 1;
    }

    /* COLUNAS */
    .kanban-col { 
        background: #d1d4d8; 
        width: 300px; 
        min-width: 300px; 
        border-radius: 6px; 
        display: flex; 
        flex-direction: column; 
        max-height: 100%; 
        box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
    }

    .col-header { 
        padding: 12px 15px; 
        background: #f8f9fa; 
        border-radius: 6px 6px 0 0; 
        border-bottom: 2px solid #bcbfc2;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .col-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #495057;
        letter-spacing: 0.5px;
    }

    .sortable-area { 
        padding: 10px; 
        overflow-y: auto; 
        flex-grow: 1; 
        min-height: 200px;
    }

    /* CARDS */
    .lead-card { 
        background: white; 
        border-radius: 5px; 
        padding: 12px; 
        margin-bottom: 10px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.08); 
        cursor: grab; 
        border-left: 5px solid #ced4da; 
        transition: transform 0.1s, box-shadow 0.1s;
    }

    .lead-card:active { cursor: grabbing; transform: scale(1.02); }
    .ghost { opacity: 0.3; background: #adb5bd; border: 2px dashed #6c757d; }
    
    /* Cores de Temperatura */
    .card-Quente { border-left-color: #dc3545; }
    .card-Morno { border-left-color: #ffc107; }
    .card-Frio { border-left-color: #0dcaf0; }

    /* Estilização da Scrollbar */
    .kanban-wrapper::-webkit-scrollbar { height: 10px; }
    .kanban-wrapper::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 10px; border: 2px solid #ebedef; }
    .sortable-area::-webkit-scrollbar { width: 6px; }
    .sortable-area::-webkit-scrollbar-thumb { background: #bcbfc2; border-radius: 10px; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center bg-white border-bottom px-3 py-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Pipeline de Leads</h5>
            <small class="text-muted">Total: <?= count($leads_db) ?> leads ativos</small>
        </div>
        <div class="d-flex gap-2">
            <a href="leads.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-task"></i> Lista</a>
            <a href="lead_form.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Novo</a>
        </div>
    </div>

    <div class="kanban-wrapper">
        <div class="kanban-board">
            <?php foreach ($opcoes_passos as $etapa): ?>
            <div class="kanban-col" data-etapa="<?= htmlspecialchars($etapa) ?>">
                <div class="col-header">
                    <span class="col-title"><?= htmlspecialchars($etapa) ?></span>
                    <span class="badge bg-secondary rounded-pill"><?= count($kanban[$etapa]) ?></span>
                </div>
                
                <div class="sortable-area">
                    <?php foreach ($kanban[$etapa] as $l): ?>
                    <div class="lead-card card-<?= $l['temperatura'] ?: 'Morno' ?>" data-id="<?= $l['id'] ?>">
                        <div class="fw-bold mb-1 text-dark" style="font-size: 0.85rem;">
                            <?= htmlspecialchars($l['nome']) ?>
                        </div>
                        
                        <div class="d-flex align-items-center text-muted mb-2" style="font-size: 0.75rem;">
                            <i class="bi bi-whatsapp text-success me-1"></i>
                            <?= $l['telefone'] ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold text-success" style="font-size: 0.8rem;">
                                R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?>
                            </span>
                            <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-xs btn-outline-primary py-0 px-1">
                                <i class="bi bi-arrow-right-short" style="font-size: 1.2rem;"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
$(document).ready(function() {
    $('.sortable-area').each(function() {
        new Sortable(this, {
            group: 'kanban_leads',
            animation: 200,
            ghostClass: 'ghost',
            dragClass: 'dragging',
            forceFallback: false, // Melhora performance mobile
            onEnd: function(evt) {
                // Se não mudou de posição nem de coluna, ignora
                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

                const cardId = evt.item.getAttribute('data-id');
                const novaEtapa = evt.to.closest('.kanban-col').getAttribute('data-etapa');
                
                // Mapeia a nova ordem dos IDs na coluna destino
                const novaOrdem = Array.from(evt.to.querySelectorAll('.lead-card'))
                                     .map(el => el.getAttribute('data-id'))
                                     .filter(id => id !== null);

                $.ajax({
                    url: 'leads_kanban.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'update_kanban',
                        id: cardId,
                        step: novaEtapa,
                        order: novaOrdem
                    },
                    success: function(res) {
                        if(res.status !== 'success') {
                            console.error('Erro ao atualizar banco:', res.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erro crítico de rede:', xhr.responseText);
                    }
                });
                
                // Atualiza contadores das colunas visualmente
                updateCounters();
            }
        });
    });

    function updateCounters() {
        $('.kanban-col').each(function() {
            const count = $(this).find('.lead-card').length;
            $(this).find('.badge').text(count);
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>