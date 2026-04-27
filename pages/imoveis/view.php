<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/header.php';

// Correção da variável global $_GET
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

// Coordenadas para o Mapa
$lat = $im['latitude'] ?? 0;
$lng = $im['longitude'] ?? 0;
// URL Universal do Google Maps para abrir em nova aba
$googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    /* Estilo Geral e Galeria */
    #map { height: 420px; width: 100%; border: 1px solid #ddd; border-radius: 10px; cursor: pointer; }
    
    .foto-principal-container {
        position: relative;
        overflow: hidden;
        border-radius: 10px;
        cursor: zoom-in;
        background: #f8f9fa;
        height: 500px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eee;
    }
    
    .foto-principal { max-width: 100%; max-height: 100%; object-fit: contain; transition: 0.3s; }
    .foto-principal-container:hover .foto-principal { transform: scale(1.01); }

    .foto-galeria {
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s;
    }
    .foto-galeria:hover { opacity: 0.8; transform: translateY(-3px); }
    .foto-galeria.active { border-color: #0d6efd; }

    .secao-titulo { border-bottom: 3px solid #0d6efd; padding-bottom: 8px; margin-bottom: 20px; }

    /* Ajustes para Impressão */
    @media print {
        .btn, .navbar, .d-print-none, footer, .fancybox__container { display: none !important; }
        .container { margin-bottom: 50px !important; }
        .foto-galeria { display: none !important; }
        .footer-corretor { position: fixed; bottom: 0; width: 100%; display: block !important; border-top: 1px solid #ccc; padding: 10px; }
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
        <div class="mb-4">
            <h1 class="fw-bold"><?= htmlspecialchars($im['titulo'] ?? '') ?></h1>
            <h2 class="text-success fw-bold mb-0">
                R$ <?= number_format($im['preco'] ?? 0, 2, ',', '.') ?>
            </h2>
        </div>

        <div class="row mb-5">
            <div class="col-12">
                <?php if (!empty($fotos)): ?>
                    <a id="main-photo-link" data-fancybox="gallery" href="../../uploads/fotos_imoveis/<?= htmlspecialchars($fotos[0]['caminho']) ?>">
                        <div class="foto-principal-container shadow-sm">
                            <img id="main-photo" src="../../uploads/fotos_imoveis/<?= htmlspecialchars($fotos[0]['caminho']) ?>" 
                                 class="foto-principal" alt="Ver em tamanho maior">
                        </div>
                    </a>
                    
                    <div class="row g-2 mt-3">
                        <?php foreach ($fotos as $idx => $f): ?>
                            <div class="col-3 col-md-2 col-lg-1">
                                <img src="../../uploads/fotos_imoveis/<?= htmlspecialchars($f['caminho']) ?>" 
                                     class="foto-galeria w-100 <?= $idx === 0 ? 'active' : '' ?>" 
                                     onclick="updateGallery(this, '../../uploads/fotos_imoveis/<?= htmlspecialchars($f['caminho']) ?>')"
                                     alt="Miniatura">
                                <?php if($idx > 0): ?>
                                    <a data-fancybox="gallery" href="../../uploads/fotos_imoveis/<?= htmlspecialchars($f['caminho']) ?>" style="display:none"></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center border p-5">Nenhuma foto disponível para este imóvel.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <h5 class="fw-bold text-primary secao-titulo">Endereço Completo</h5>
                <p class="fs-5 mb-4">
                    <?= htmlspecialchars($im['endereco'] ?? '') ?>, <?= htmlspecialchars($im['bairro'] ?? '') ?> - 
                    <?= htmlspecialchars($im['cidade'] ?? '') ?> / <?= htmlspecialchars($im['estado'] ?? '') ?>
                </p>

                <?php if (!empty($im['descricao'])): ?>
                    <h5 class="fw-bold text-primary secao-titulo">Descrição</h5>
                    <p class="lead mb-5 text-muted" style="text-align: justify;"><?= nl2br(htmlspecialchars($im['descricao'])) ?></p>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold text-primary mb-0">Localização no Mapa</h5>
                    <a href="<?= $googleMapsUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary d-print-none">
                        <i class="bi bi-geo-alt"></i> Abrir no Google Maps
                    </a>
                </div>
                
                <a href="<?= $googleMapsUrl ?>" target="_blank" style="display:block; text-decoration:none;">
                    <div id="map" class="shadow-sm"></div>
                    <p class="mt-2 text-center text-muted d-print-none">
                        <small><i class="bi bi-info-circle"></i> Clique no mapa para ver rotas no Google Maps.</small>
                    </p>
                </a>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-primary text-white fw-bold">Ficha Técnica</div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3 border-bottom pb-2">
                                <strong class="fs-4"><?= $im['quartos'] ?? 0 ?></strong><br><small>Quartos</small>
                            </div>
                            <div class="col-6 mb-3 border-bottom pb-2">
                                <strong class="fs-4"><?= $im['suites'] ?? 0 ?></strong><br><small>Suítes</small>
                            </div>
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['banheiros'] ?? 0 ?></strong><br><small>Banheiros</small>
                            </div>
                            <div class="col-6 mb-3">
                                <strong class="fs-4"><?= $im['vagas_garagem'] ?? 0 ?></strong><br><small>Vagas</small>
                            </div>
                            <div class="col-12 mt-3 bg-light p-2 rounded">
                                <strong class="fs-3 text-primary"><?= number_format($im['area'] ?? 0, 2, ',', '.') ?> m²</strong><br>Área Útil
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white fw-bold">Diferenciais</div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <?php 
                            $diferenciais = [
                                'tem_piscina' => ['bi-water text-info', 'Piscina'],
                                'tem_academia' => ['bi-heart-pulse text-danger', 'Academia'],
                                'tem_salao_festas' => ['bi-people', 'Salão de Festas'],
                                'tem_espaco_gourmet' => ['bi-egg-fried text-warning', 'Espaço Gourmet'],
                                'tem_playground' => ['bi-bicycle text-success', 'Playground'],
                                'possui_elevador' => ['bi-arrow-up-circle', 'Elevador'],
                                'mobiliado' => ['bi-house-door', 'Mobiliado'],
                                'gas_encanado' => ['bi-fire text-warning', 'Gás Encanado']
                            ];
                            foreach($diferenciais as $key => $info):
                                if(!empty($im[$key])): ?>
                                    <li class="mb-2"><i class="bi <?= $info[0] ?> me-2"></i> <?= $info[1] ?></li>
                                <?php endif; 
                            endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-corretor mt-5 d-none d-print-block">
        <p class="mb-0 fw-bold fs-5">Corretor Valter Barreto | CRECI-PE: 22003</p>
        <p class="mb-0 text-muted"><i class="bi bi-whatsapp"></i> (81) 98842-1455</p>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

<script>
// Galeria interativa
function updateGallery(el, src) {
    document.getElementById('main-photo').src = src;
    document.getElementById('main-photo-link').href = src;
    document.querySelectorAll('.foto-galeria').forEach(img => img.classList.remove('active'));
    el.classList.add('active');
}

// Inicializa Lightbox
Fancybox.bind("[data-fancybox]", {
    infinite: true,
    transitionEffect: "fade"
});

// Inicializa Mapa
window.onload = function() {
    var lat = <?= floatval($lat) ?>;
    var lng = <?= floatval($lng) ?>;

    if (lat !== 0 && lng !== 0) {
        var map = L.map('map', {
            scrollWheelZoom: false,
            dragging: false, // Facilita o clique para abrir o link
            touchZoom: false
        }).setView([lat, lng], 16);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([lat, lng]).addTo(map);

        // Garante que o clique no mapa abra o Google Maps
        map.on('click', function() {
            window.open('<?= $googleMapsUrl ?>', '_blank');
        });
    } else {
        document.getElementById('map').innerHTML = '<div class="alert alert-warning text-center p-5">Coordenadas não cadastradas.</div>';
    }
};
</script>

<?php require_once '../../includes/header.php'; // Nota: use footer.php se tiver um ?>