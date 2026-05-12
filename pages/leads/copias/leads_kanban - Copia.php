<?php
// leads_kanban.php
session_start();

// 1. Configurações de erro para depuração
ini_set('display_errors', 0); // Mantemos 0 para não sujar o JSON, mas capturamos no log
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
// AJAX - PROCESSAMENTO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Limpa qualquer saída (espaços ou warnings) para garantir JSON puro
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_kanban') {
            $id_movido = (int)($_POST['id'] ?? 0);
            $nova_etapa = $_POST['step'] ?? '';
            $nova_ordem_ids = $_POST['order'] ?? [];

            if (!$id_movido) throw new Exception("ID do lead não recebido.");

            $conn->beginTransaction();

            // 1. Atualiza a etapa do lead movido e a data de interação
            $stmt = $conn->prepare("UPDATE leads SET proximo_passo = ?, ultima_interacao = NOW() WHERE id = ?");
            $stmt->execute([$nova_etapa, $id_movido]);

            // 2. Atualiza a ordem de todos da coluna onde o card caiu
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

        if ($_POST['action'] === 'update_temp') {
            $stmt = $conn->prepare("UPDATE leads SET temperatura = ?, ultima_interacao = NOW() WHERE id = ?");
            $res = $stmt->execute([$_POST['temp'], (int)$_POST['id']]);
            echo json_encode(['status' => $res ? 'success' : 'error']);
            exit;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// BUSCA DOS DADOS
// ==========================================
$sql = "SELECT * FROM leads ORDER BY proximo_passo ASC, ordem ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$leads_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kanban = [];
foreach ($opcoes_passos as $etapa) { $kanban[$etapa] = []; }

foreach ($leads_db as $l) {
    $etapa = trim($l['proximo_passo'] ?? '');
    // Se a etapa no banco não estiver na nossa lista, vai para a primeira coluna
    if (array_key_exists($etapa, $kanban)) {
        $kanban[$etapa][] = $l;
    } else {
        $kanban["Lead recebido"][] = $l;
    }
}

require_once '../../includes/header.php';
?>

<style>
    .kanban-board { display: flex; gap: 1rem; overflow-x: auto; padding: 20px 0; align-items: flex-start; }
    .kanban-col { background: #f4f5f7; width: 310px; min-width: 310px; border-radius: 8px; display: flex; flex-direction: column; max-height: 85vh; }
    .col-header { padding: 15px; font-weight: bold; background: #fff; border-radius: 8px 8px 0 0; border-bottom: 2px solid #ddd; }
    .sortable-area { padding: 10px; min-height: 500px; overflow-y: auto; flex-grow: 1; }
    .lead-card { background: white; border-radius: 6px; padding: 15px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: grab; border-left: 5px solid #ddd; }
    .lead-card:active { cursor: grabbing; }
    .ghost { opacity: 0.4; background: #ebedf0; border: 2px dashed #bbb; }
    
    /* Cores por temperatura */
    .card-Quente { border-left-color: #dc3545; }
    .card-Morno { border-left-color: #ffc107; }
    .card-Frio { border-left-color: #0dcaf0; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between mb-4 px-2">
        <h2 class="fw-bold">Pipeline de Leads</h2>
        <a href="lead_form.php" class="btn btn-primary shadow-sm">Novo Lead</a>
    </div>

    <div class="kanban-board">
        <?php foreach ($opcoes_passos as $etapa): ?>
        <div class="kanban-col" data-etapa="<?= htmlspecialchars($etapa) ?>">
            <div class="col-header d-flex justify-content-between">
                <span class="small"><?= htmlspecialchars($etapa) ?></span>
                <span class="badge bg-light text-dark border"><?= count($kanban[$etapa]) ?></span>
            </div>
            
            <div class="sortable-area">
                <?php foreach ($kanban[$etapa] as $l): ?>
                <div class="lead-card card-<?= $l['temperatura'] ?: 'Morno' ?>" data-id="<?= $l['id'] ?>">
                    <div class="fw-bold small mb-1"><?= htmlspecialchars($l['nome']) ?></div>
                    <div class="text-muted small mb-2"><i class="bi bi-whatsapp me-1"></i><?= $l['telefone'] ?></div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span class="fw-bold text-primary small">R$ <?= number_format($l['valor_max'] ?? 0, 0, ',', '.') ?></span>
                        <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary border-0 p-0" target="_blank"><i class="bi bi-eye"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
$(document).ready(function() {
    $('.sortable-area').each(function() {
        new Sortable(this, {
            group: 'kanban_leads',
            animation: 150,
            ghostClass: 'ghost',
            onEnd: function(evt) {
                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

                const cardId = evt.item.getAttribute('data-id');
                const novaEtapa = evt.to.closest('.kanban-col').getAttribute('data-etapa');
                
                // Pega a ordem dos IDs apenas dos cards (divs que tem data-id)
                const novaOrdem = Array.from(evt.to.querySelectorAll('.lead-card'))
                                       .map(el => el.getAttribute('data-id'))
                                       .filter(id => id !== null);

                $.ajax({
                    url: 'leads_kanban.php',
                    method: 'POST',
                    data: {
                        action: 'update_kanban',
                        id: cardId,
                        step: novaEtapa,
                        order: novaOrdem
                    },
                    success: function(res) {
                        if(res.status !== 'success') {
                            alert('Erro: ' + (res.message || 'Falha ao salvar.'));
                        }
                    },
                    error: function(xhr) {
                        // Isso vai nos mostrar o erro real se o PHP falhar
                        console.error("Erro do Servidor:", xhr.responseText);
                        alert('Erro crítico no servidor. Verifique o console (F12) para detalhes.');
                    }
                });
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>