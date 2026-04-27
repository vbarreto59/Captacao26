<?php
// pages/imoveis/imovel.php
require_once '../../conn_cap.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Imóvel não encontrado.");
}

// 1. Busca dados do imóvel
$stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$imovel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imovel) { die("Imóvel inexistente."); }

// 2. Busca todas as fotos
$stmt_fotos = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE imovel_id = ? ORDER BY id ASC");
$stmt_fotos->execute([$id]);
$todas_fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

$foto_capa = !empty($todas_fotos) ? $todas_fotos[0]['caminho'] : null;
$url_base_fotos = "../../uploads/fotos_imoveis/";

// Criamos um array JS com as URLs para a navegação
$fotos_js = json_encode(array_column($todas_fotos, 'caminho'));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($imovel['titulo']) ?> | Valter Barreto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; color: #333; font-family: 'Segoe UI', sans-serif; }
        .hero-img { width: 100%; height: 500px; object-fit: cover; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); cursor: pointer; }
        .preco-destaque { font-size: 2.2rem; color: #0d6efd; font-weight: 800; }
        .card-detalhes { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); background: #fff; }
        
        /* Galeria */
        .thumb-container { cursor: pointer; overflow: hidden; border-radius: 8px; height: 100px; border: 2px solid transparent; transition: 0.2s; }
        .thumb-container:hover { border-color: #0d6efd; }
        .thumb-img { width: 100%; height: 100%; object-fit: cover; }

        /* Setas da Modal */
        .modal-nav-btn {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.5); color: white; border: none;
            padding: 20px 15px; font-size: 2rem; transition: 0.3s; z-index: 1060;
        }
        .modal-nav-btn:hover { background: rgba(0,0,0,0.8); }
        .btn-prev { left: 0; border-radius: 0 5px 5px 0; }
        .btn-next { right: 0; border-radius: 5px 0 0 5px; }
        
        @media (max-width: 768px) { .hero-img { height: 300px; } .modal-nav-btn { padding: 10px 8px; font-size: 1.5rem; } }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-detalhes p-4 mb-4">
                <?php if($foto_capa): ?>
                    <img src="<?= $url_base_fotos . $foto_capa ?>" class="hero-img mb-4" alt="Capa" onclick="openGallery(0)">
                <?php endif; ?>

                <h1 class="fw-bold mb-2"><?= htmlspecialchars($imovel['titulo']) ?></h1>
                <p class="text-muted fs-5 mb-4"><i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($imovel['bairro']) ?>, <?= htmlspecialchars($imovel['cidade']) ?></p>

                <div class="row g-3 mb-4 text-center">
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <i class="bi bi-arrows-fullscreen d-block fs-3 mb-2 text-primary"></i>
                            <span class="d-block fw-bold"><?= $imovel['area'] ?> m²</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <i class="bi bi-door-open d-block fs-3 mb-2 text-primary"></i>
                            <span class="d-block fw-bold"><?= $imovel['quartos'] ?> Qts (<?= $imovel['suites'] ?> Suítes)</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <i class="bi bi-droplet d-block fs-3 mb-2 text-primary"></i>
                            <span class="d-block fw-bold"><?= $imovel['banheiros'] ?> Banheiros</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <i class="bi bi-car-front d-block fs-3 mb-2 text-primary"></i>
                            <span class="d-block fw-bold"><?= $imovel['vagas_garagem'] ?> Vagas</span>
                        </div>
                    </div>
                </div>

                <h4 class="fw-bold border-bottom pb-2 mb-3">Descrição</h4>
                <p style="white-space: pre-wrap; line-height: 1.8; color: #444;"><?= htmlspecialchars($imovel['descricao']) ?></p>

                <?php if (!empty($todas_fotos)): ?>
                <h4 class="fw-bold mb-3 mt-4">Fotos do Imóvel</h4>
                <div class="row g-2">
                    <?php foreach ($todas_fotos as $index => $f): ?>
                        <div class="col-4 col-md-2">
                            <div class="thumb-container" onclick="openGallery(<?= $index ?>)">
                                <img src="<?= $url_base_fotos . $f['caminho'] ?>" class="thumb-img">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sticky-top" style="top: 20px;">
                <div class="card card-detalhes p-4 text-center">
                    <p class="text-muted mb-1 fw-bold small">VALOR DE VENDA</p>
                    <div class="preco-destaque mb-4">R$ <?= number_format($imovel['preco'], 2, ',', '.') ?></div>
                    
                    <a href="https://wa.me/5581986755592?text=Olá Valter! Tenho interesse no imóvel <?= urlencode($imovel['titulo']) ?>" 
                       target="_blank" class="btn btn-success btn-lg w-100 fw-bold py-3 mb-3 shadow-sm">
                        <i class="bi bi-whatsapp me-2"></i> Agendar Visita
                    </a>
                    
                    <div class="p-3 bg-light rounded text-start">
                        <h6 class="fw-bold mb-1">Valter Barreto</h6>
                        <p class="small text-muted mb-0">Especialista Imobiliário | CRECI 22003</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1070;"></button>
                
                <button class="modal-nav-btn btn-prev" onclick="changeImage(-1)">
                    <i class="bi bi-chevron-left"></i>
                </button>
                
                <img src="" id="galleryImage" class="img-fluid rounded shadow-lg" style="max-height: 90vh;">
                
                <button class="modal-nav-btn btn-next" onclick="changeImage(1)">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const fotos = <?= $fotos_js ?>;
    const urlBase = "<?= $url_base_fotos ?>";
    let currentIndex = 0;
    const galleryModal = new bootstrap.Modal(document.getElementById('galleryModal'));
    const modalImg = document.getElementById('galleryImage');

    function openGallery(index) {
        currentIndex = index;
        updateModalImage();
        galleryModal.show();
    }

    function changeImage(direction) {
        currentIndex += direction;
        if (currentIndex >= fotos.length) currentIndex = 0;
        if (currentIndex < 0) currentIndex = fotos.length - 1;
        updateModalImage();
    }

    function updateModalImage() {
        modalImg.src = urlBase + fotos[currentIndex];
    }

    // Navegação por teclado
    document.addEventListener('keydown', function(e) {
        if (!document.getElementById('galleryModal').classList.contains('show')) return;
        if (e.key === "ArrowLeft") changeImage(-1);
        if (e.key === "ArrowRight") changeImage(1);
    });
</script>
</body>
</html>