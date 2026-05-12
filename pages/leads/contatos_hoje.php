<?php
// contatos_hoje.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Processamento da Limpeza se for chamado nesta página
if (isset($_POST['action']) && $_POST['action'] == 'limpar_hoje') {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = 0 WHERE contatar_hoje = 1");
    echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
    exit;
}

// Consulta dos leads selecionados
$sql = "SELECT l.*, 
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado
        FROM leads l 
        WHERE l.contatar_hoje = 1 
        ORDER BY l.nome DESC, l.id DESC";

$stmt = $conn->query($sql);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
    .card-lead-hoje { transition: transform 0.2s; border: none; border-left: 5px solid #ccc; }
    .card-lead-hoje:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .temp-Quente { border-left-color: #dc3545; }
    .temp-Morno { border-left-color: #ffc107; }
    .temp-Frio { border-left-color: #17a2b8; }
    .btn-whatsapp { background-color: #25d366; color: white; border: none; }
    .btn-whatsapp:hover { background-color: #128c7e; color: white; }
</small></style>

<div class="container mt-4">
    <!-- Cabeçalho Estratégico -->
    <div class="card border-0 shadow-sm bg-primary text-white mb-4">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-3 mb-md-0">
                <h2 class="fw-bold mb-0"><i class="bi bi- megaphone me-2"></i>Contatos de Hoje</h2>
                <p class="mb-0 opacity-75">Foco total: você tem <?= count($leads) ?> leads na fila de execução.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (count($leads) > 0): ?>
                    <button id="btnLimparTudo" class="btn btn-outline-light shadow-sm">
                        <i class="bi bi-trash"></i> Limpar Lista
                    </button>
                <?php endif; ?>
                <a href="leads.php" class="btn btn-light fw-bold text-primary shadow-sm">
                    <i class="bi bi-arrow-left"></i> Voltar Geral
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($leads)): ?>
        <div class="text-center py-5">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            <h3 class="mt-3 fw-bold">Tudo limpo por aqui!</h3>
            <p class="text-muted">Selecione novos leads na página principal para começar a trabalhar.</p>
            <a href="leads.php" class="btn btn-primary px-4 py-2">Ir para Gestão de Leads</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($leads as $l): 
                $tel = preg_replace('/\D/', '', $l['telefone']);
                $classeTemp = 'temp-' . ($l['temperatura'] ?: 'Morno');
            ?>
            <div class="col-12 col-md-6 col-lg-4 mb-4" id="card-lead-<?= $l['id'] ?>">
                <div class="card h-100 shadow-sm card-lead-hoje <?= $classeTemp ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($l['nome']) ?></h5>
                            <span class="badge bg-light text-dark border"><?= $l['temperatura'] ?></span>
                        </div>
                        
                        <p class="small text-muted mb-3">
                            <i class="bi bi-clock-history"></i> Parado há <?= $l['dias_parado'] ?> dias
                        </p>

                        <div class="p-2 bg-light rounded small mb-3 text-secondary" style="min-height: 60px;">
                            <strong>Obs:</strong> <?= nl2br(htmlspecialchars(mb_strimwidth($l['observacoes'], 0, 120, "..."))) ?>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="https://wa.me/55<?= $tel ?>" target="_blank" class="btn btn-whatsapp fw-bold">
                                <i class="bi bi-whatsapp"></i> Chamar no WhatsApp
                            </a>
                            <div class="btn-group">
                                <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i> Detalhes
                                </a>
                                <button class="btn btn-outline-danger btn-sm btn-concluido" data-id="<?= $l['id'] ?>">
                                    <i class="bi bi-check2-square"></i> Finalizado
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    
    // Botão de Finalizado (Individual)
    $('.btn-concluido').on('click', function() {
        const id = $(this).data('id');
        const card = $(`#card-lead-${id}`);
        
        $.post('leads.php', { action: 'toggle_hoje', id: id, value: 0 }, function(res) {
            card.fadeOut('fast', function() {
                if ($('.card-lead-hoje:visible').length === 0) {
                    location.reload(); // Recarrega para mostrar a tela vazia
                }
            });
        }, 'json');
    });

    // Botão Limpar Tudo (Massa)
    $('#btnLimparTudo').on('click', function() {
        if (confirm('Deseja retirar TODOS os leads da lista de hoje?')) {
            $.post('contatos_hoje.php', { action: 'limpar_hoje' }, function(res) {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert('Erro ao limpar a lista.');
                }
            }, 'json');
        }
    });

});
</script>

<?php // require_once '../../includes/footer.php'; ?>