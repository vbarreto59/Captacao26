<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Busca os dados do imóvel
$stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$im = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$im) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Imóvel não encontrado.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Busca todas as fotos
$stmt_fotos = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE imovel_id = ? ORDER BY id ASC");
$stmt_fotos->execute([$id]);
$fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    #map { height: 420px; width: 100%; border: 1px solid #ddd; border-radius: 10px; margin-bottom: 25px; }
    .foto-principal {
        width: 100%;
        max-height: 450px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .foto-galeria {
        height: 140px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid #fff;
        transition: all 0.3s;
    }
    .foto-galeria:hover {
        border-color: #0d6efd;
        transform: scale(1.05);
    }
    .secao-titulo {
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 8px;
        margin-bottom: 20px;
    }
    @media print {
        .btn, .navbar, .d-print-none, footer { display: none !important; }
        .container { margin-bottom: 100px !important; }
        .foto-galeria { display: none !important; }
        .footer-corretor { position: fixed; bottom: 0; width: 100%; background: white; border-top: 1px solid #333; padding: 10px 0; }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <h2 class="text-primary fw-bold">Ficha Completa do Imóvel</h2>
        <div>
            <button onclick="window.print()" class="btn btn-danger shadow">
                <i class="bi bi-file-earmark-pdf me-2"></i> Gerar PDF
            </button>
            <a href="list.php" class="btn btn-outline-secondary ms-2">Voltar</a>
        </div>
    </div>

    <div class="ficha-imovel">
        <!-- Título e Preço -->
        <div class="mb-4">
            <h1 class="fw-bold"><?= htmlspecialchars($im['titulo']) ?></h1>
            <h2 class="text-success fw-bold mb-0">
                R$ <?= number_format($im['preco'] ?? 0, 2, ',', '.') ?>
            </h2>
        </div>

        <!-- Fotos -->
        <div class="row mb-5">
            <div class="col-12">
                <?php if (!empty($fotos)): ?>
                    <!-- Foto Principal (primeira foto) -->
                    <img src="../../uploads/fotos_imoveis/<?= htmlspecialchars($fotos[0]['caminho']) ?>" 
                         class="foto-principal" alt="Foto principal">
                    
                    <!-- Galeria de miniaturas -->
                    <div class="row g-2 mt-3">
                        <?php foreach ($fotos as $f): ?>
                            <div class="col-2 col-md-2 col-lg-1">
                                <img src="../../uploads/fotos_imoveis/<?= htmlspecialchars($f['caminho']) ?>" 
                                     class="foto-galeria w-100" 
                                     onclick="this.src=this.src" alt="Foto">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        Nenhuma foto cadastrada para este imóvel.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-lg-8">
                <h5 class="fw-bold text-primary secao-titulo">Endereço Completo</h5>
                <p class="fs-5 mb-4">
                    <?= htmlspecialchars($im['endereco']) ?>, <?= htmlspecialchars($im['bairro']) ?> - 
                    <?= htmlspecialchars($im['cidade']) ?> / <?= htmlspecialchars($im['estado']) ?>
                </p>

                <?php if (!empty($im['descricao'])): ?>
                <h5 class="fw-bold text-primary secao-titulo">Descrição</h5>
                <p class="lead"><?= nl2br(htmlspecialchars($im['descricao'])) ?></p>
                <?php endif; ?>

                <!-- Mapa -->
                <h5 class="fw-bold text-primary secao-titulo mt-5">Localização no Mapa</h5>
                <div id="map"></div>
            </div>

            <!-- Coluna Direita - Ficha Técnica -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white fw-bold">
                        Ficha Técnica
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['quartos'] ?></strong><br>Quartos
                            </div>
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['suites'] ?></strong><br>Suítes
                            </div>
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['banheiros'] ?></strong><br>Banheiros
                            </div>
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['vagas_garagem'] ?></strong><br>Vagas
                            </div>
                            <div class="col-12 mt-3">
                                <strong class="fs-4"><?= number_format($im['area'], 2, ',', '.') ?> m²</strong><br>Área Útil
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diferenciais -->
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white fw-bold">
                        Diferenciais
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <?php if($im['tem_piscina']): ?><li><i class="bi bi-water text-info"></i> Piscina</li><?php endif; ?>
                            <?php if($im['tem_academia']): ?><li><i class="bi bi-heart-pulse text-danger"></i> Academia</li><?php endif; ?>
                            <?php if($im['tem_salao_festas']): ?><li><i class="bi bi-people"></i> Salão de Festas</li><?php endif; ?>
                            <?php if($im['tem_espaco_gourmet']): ?><li><i class="bi bi-egg-fried"></i> Espaço Gourmet</li><?php endif; ?>
                            <?php if($im['tem_playground']): ?><li><i class="bi bi-bicycle"></i> Playground</li><?php endif; ?>
                            <?php if($im['possui_elevador']): ?><li><i class="bi bi-arrow-up-circle"></i> Elevador</li><?php endif; ?>
                            <?php if($im['mobiliado']): ?><li><i class="bi bi-house-door"></i> Mobiliado</li><?php endif; ?>
                            <?php if($im['gas_encanado']): ?><li><i class="bi bi-fire text-warning"></i> Gás Encanado</li><?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé para Impressão -->
    <div class="footer-corretor mt-5">
        <div class="container">
            <p class="mb-0 fw-bold fs-5">Corretor Valter Barreto | CRECI-PE: 22003</p>
            <p class="mb-0 text-muted">
                <i class="bi bi-whatsapp"></i> (81) 98842-1455
            </p>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
window.onload = function() {
    var lat = <?= floatval($im['latitude'] ?? 0) ?>;
    var lng = <?= floatval($im['longitude'] ?? 0) ?>;

    if (lat !== 0 && lng !== 0) {
        var map = L.map('map').setView([lat, lng], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        L.marker([lat, lng]).addTo(map)
         .bindPopup("<?= htmlspecialchars($im['titulo']) ?>").openPopup();
        
        setTimeout(() => map.invalidateSize(), 500);
    } else {
        document.getElementById('map').innerHTML = '<div class="alert alert-warning text-center p-5">Coordenadas não cadastradas.</div>';
    }
};
</script>

<?php require_once '../../includes/footer.php'; ?>