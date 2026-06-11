<?php
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php';      
require_once '../../includes/header.php';

// Detecta o ambiente para montar a URL correta
if ($_SERVER['HTTP_HOST'] == 'localhost') {
    $base_url = "http://localhost/phpCapImoveis2603/pages/imoveis/";
} else {
    $base_url = "https://dev.gabnetweb.com.br/Captacao2603/pages/imoveis/";
}

// Busca corretores e a contagem de imóveis vinculados como titular
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM imoveis WHERE corretor_id = c.id AND deleted_at IS NULL) as total_imoveis
        FROM corretores c 
        WHERE c.deleted_at IS NULL 
        ORDER BY c.nome ASC";

$corretores = $conn->query($sql)->fetchAll();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="bi bi-person-badge"></i> Gestão de Parceiros</h3>
            <small class="text-muted">Corretores vinculados ao sistema de captação</small>
        </div>
        <a href="form_corretor.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Corretor</a>
    </div>

    <div class="row">
        <?php foreach ($corretores as $c): ?>
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 border-top border-primary border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title text-truncate" style="max-width: 70%;"><?= htmlspecialchars($c['nome']) ?></h5>
                            <span class="badge <?= $c['status'] == 'Ativo' ? 'bg-success' : 'bg-danger' ?>"><?= $c['status'] ?></span>
                        </div>
                        
                        <p class="small text-muted mb-2">
                            <strong>CRECI:</strong> <?= $c['creci'] ?> | <strong>PIN:</strong> <code class="fw-bold"><?= $c['codigo_acesso'] ?></code>
                        </p>

                        <!-- Exibição da Quantidade de Imóveis -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary text-white rounded px-2 py-1 small fw-bold">
                                <i class="bi bi-house-door-fill"></i> <?= $c['total_imoveis'] ?> Imóveis
                            </div>
                        </div>

                        <?php 
                            $link_imoveis = $base_url . "ver_imoveis.php?pin=" . $c['codigo_acesso'];
                            $link_texto = $base_url . "ver_imoveis_zap.php?pin=" . $c['codigo_acesso'];
                        ?>

                        <div class="bg-light p-2 rounded mb-3" style="font-size: 0.75rem; border: 1px solid #eee;">
                            <span class="text-dark fw-bold">Link do Portfólio:</span><br>
                            <span class="text-break text-primary"><?= $link_imoveis ?></span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <div class="d-flex gap-2">
                                <a href="<?= $link_imoveis ?>" target="_blank" class="btn btn-sm btn-primary flex-grow-1">
                                    <i class="bi bi-box-arrow-up-right"></i> Abrir Portfólio
                                </a>
                                <a href="<?= $link_texto ?>" target="_blank" class="btn btn-sm btn-success">
                                    <i class="bi bi-whatsapp"></i> Versão Texto
                                </a>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-info flex-grow-1" onclick="copyLink('<?= $link_imoveis ?>')">
                                    <i class="bi bi-copy"></i> Copiar Link
                                </button>
                                <a href="form_corretor.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function copyLink(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Link copiado! Você já pode colar no WhatsApp do corretor.");
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>