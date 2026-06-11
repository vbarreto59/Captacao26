<?php
// pages/imoveis/imovel.php
require_once '../../conn_cap.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Imóvel não encontrado.");
}

// Busca dados do imóvel
$stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$imovel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imovel) {
    die("Imóvel inexistente.");
}

// Busca todas as fotos priorizando capa e ordem
$stmt_fotos = $conn->prepare("SELECT caminho, capa FROM fotos_imoveis WHERE imovel_id = ? ORDER BY capa DESC, ordem ASC, id ASC");
$stmt_fotos->execute([$id]);
$todas_fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

$foto_capa = !empty($todas_fotos) ? $todas_fotos[0]['caminho'] : null;
$url_base_fotos = "../../uploads/fotos_imoveis/";

$fotos_js = json_encode(array_column($todas_fotos, 'caminho'));

// Mapeamento de status
$status_map = [
    'captado'  => ['label' => 'Disponível', 'class' => 'bg-success'],
    'vendido'  => ['label' => 'Vendido',    'class' => 'bg-danger'],
    'suspenso' => ['label' => 'Suspenso',   'class' => 'bg-warning text-dark']
];
$status_atual = $status_map[$imovel['status']] ?? ['label' => ucfirst($imovel['status']), 'class' => 'bg-secondary'];

// Lógica para montar o texto de inclusão do condomínio com cores de destaque
$inclusos = [];
if (!empty($imovel['agua_inclusa_condominio']) && $imovel['agua_inclusa_condominio'] == 1) { 
    $inclusos[] = '<span class="text-primary fw-bold">Água</span>'; 
}
if (!empty($imovel['gas_incluso_condominio']) && $imovel['gas_incluso_condominio'] == 1) { 
    $inclusos[] = '<span class="text-warning-dark fw-bold">Gás</span>'; 
}

$textoInclusos = '';
if (!empty($inclusos)) {
    $textoInclusos = ' <small class="text-muted" style="font-size: 0.8rem;">(' . implode(' e ', $inclusos) . ' inclusos)</small>';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($imovel['titulo']) ?> | Valter Barreto Imóveis</title>
    
    <!-- Bootstrap 5 + Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f4f7fc; }
        
        /* Classe customizada para o Gás ter um tom de laranja/amarelo mais visível no fundo branco */
        .text-warning-dark { color: #e67e22 !important; }
        
        /* Cards modernos */
        .card-moderno {
            background: #ffffff;
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-moderno:hover {
            box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.1);
        }
        
        /* Imagem capa */
        .hero-img {
            width: 100%;
            height: 480px;
            object-fit: cover;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .hero-img:hover { filter: brightness(0.96); transform: scale(1.01); }
        
        /* Seções de título */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #e2e8f0;
            display: inline-block;
        }
        .section-title i { color: #0d6efd; margin-right: 10px; }
        
        /* Badges de características rápidas */
        .feature-badge {
            background: #f1f5f9;
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Tags de diferenciais */
        .tag-presente {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tag-ausente {
            background: #f1f5f9;
            color: #64748b;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 400;
            text-decoration: line-through;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Preço destaque */
        .preco-principal {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0f3b2c 0%, #1e6f5c 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }
        
        /* Thumbnails galeria */
        .thumb-galeria {
            cursor: pointer;
            border-radius: 16px;
            overflow: hidden;
            aspect-ratio: 1 / 1;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .thumb-galeria:hover {
            transform: scale(1.02);
            border-color: #0d6efd;
        }
        .thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Mapa */
        #mapaImovel {
            height: 380px;
            width: 100%;
            border-radius: 20px;
            z-index: 1;
        }
        
        /* Botões modais galeria */
        .modal-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: white;
            border: none;
            padding: 18px 12px;
            font-size: 2rem;
            border-radius: 60px;
            transition: 0.2s;
            z-index: 1060;
        }
        .modal-nav-btn:hover {
            background: rgba(0,0,0,0.9);
        }
        .btn-prev { left: 20px; }
        .btn-next { right: 20px; }
        
        @media (max-width: 768px) {
            .hero-img { height: 280px; }
            .preco-principal { font-size: 1.8rem; }
            .feature-badge { padding: 6px 12px; font-size: 0.75rem; }
            .section-title { font-size: 1.3rem; }
            .modal-nav-btn { padding: 10px 8px; font-size: 1.4rem; }
        }
        
        /* Sidebar fixa no desktop */
        .sticky-sidebar {
            position: sticky;
            top: 24px;
        }
        
        /* Botão WhatsApp personalizado */
        .btn-whatsapp-custom {
            background-color: #25D366;
            border: none;
            color: white;
            font-weight: 700;
            padding: 12px;
            border-radius: 50px;
            transition: 0.2s;
        }
        .btn-whatsapp-custom:hover {
            background-color: #1da15a;
            transform: scale(1.02);
        }
        
        /* Card financeiro (mobile e desktop) */
        .finance-card {
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .finance-divider {
            height: 1px;
            background: #eef2f6;
            margin: 1rem 0;
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">
    <div class="row g-4">
        <!-- COLUNA PRINCIPAL (ESQUERDA) -->
        <div class="col-lg-8">
            <div class="card-moderno p-3 p-md-4">
                <!-- Imagem capa -->
                <?php if($foto_capa): ?>
                    <img src="<?= $url_base_fotos . $foto_capa ?>" class="hero-img w-100 mb-4" alt="Capa do imóvel" onclick="openGallery(0)">
                <?php else: ?>
                    <div class="hero-img bg-light d-flex align-items-center justify-content-center mb-4">
                        <i class="bi bi-image fs-1 text-muted"></i>
                    </div>
                <?php endif; ?>
                
                <!-- Título e endereço -->
                <h1 class="fw-bold mb-2">AP<?= $imovel['id'] ?>-<?= htmlspecialchars($imovel['titulo']) ?></h1>
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
                    <p class="text-muted">
                        <i class="bi bi-geo-alt-fill text-danger"></i> 
                        <?= htmlspecialchars($imovel['endereco']) ?>, <?= htmlspecialchars($imovel['bairro']) ?> - <?= htmlspecialchars($imovel['cidade']) ?>/<?= $imovel['estado'] ?>
                        <?php if($imovel['cep']): ?> • CEP: <?= $imovel['cep'] ?><?php endif; ?>
                    </p>
                    <span class="badge <?= $status_atual['class'] ?> px-3 py-2 fs-6 rounded-pill">
                        <i class="bi bi-info-circle-fill me-1"></i> <?= $status_atual['label'] ?>
                    </span>
                </div>
                
                <!-- Resumo rápido (ícones) -->
                <div class="d-flex flex-wrap gap-2 mb-5">
                    <div class="feature-badge"><i class="bi bi-arrows-fullscreen fs-5"></i> <?= number_format($imovel['area'], 0, ',', '.') ?> m²</div>
                    <div class="feature-badge"><i class="bi bi-door-open fs-5"></i> <?= $imovel['quartos'] ?> quartos</div>
                    <div class="feature-badge"><i class="bi bi-droplet fs-5"></i> <?= $imovel['banheiros'] ?> banheiros</div>
                    <div class="feature-badge"><i class="bi bi-car-front fs-5"></i> <?= $imovel['vagas_garagem'] ?> vagas</div>
                    <?php if($imovel['suites'] > 0): ?>
                        <div class="feature-badge"><i class="bi bi-cup-straw fs-5"></i> <?= $imovel['suites'] ?> suíte(s)</div>
                    <?php endif; ?>
                    <?php if($imovel['andar']): ?>
                        <div class="feature-badge"><i class="bi bi-layers fs-5"></i> <?= $imovel['andar'] ?>º andar</div>
                    <?php endif; ?>
                </div>

                <!-- DIFERENCIAIS & COMODIDADES -->
                <div class="mb-4">
                    <h3 class="section-title"><i class="bi bi-stars"></i> Diferenciais & Comodidades</h3>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php
                        // Lista de diferenciais
                        $itens = [
                            'tem_piscina' => ['Piscina', 'bi-water'],
                            'tem_academia' => ['Academia', 'bi-dumbbell'],
                            'tem_salao_festas' => ['Salão de Festas', 'bi-people'],
                            'tem_espaco_gourmet' => ['Espaço Gourmet', 'bi-egg-fried'],
                            'tem_playground' => ['Playground', 'bi-tree'],
                            'possui_elevador' => ['Elevador', 'bi-arrow-up-short'],
                            'possui_moveis_planejados' => ['Móveis Planejados', 'bi-grid-3x3-gap'],
                            'gas_encanado' => ['Gás Encanado', 'bi-fire'],
                            'mobiliado' => ['Mobiliado', 'bi-sofa'],
                            'agua_inclusa_condominio' => ['Água inclusa no condomínio', 'bi-droplet-half'],
                            'gas_incluso_condominio' => ['Gás incluso no condomínio', 'bi-fuel-pump']
                        ];
                        foreach($itens as $campo => $info):
                            if(isset($imovel[$campo]) && $imovel[$campo] == 1):
                                $icone = $info[1];
                                $texto = $info[0];
                        ?>
                            <div class="tag-presente">
                                <i class="bi <?= $icone ?>"></i> <?= $texto ?>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>

                <!-- CARD FINANCEIRO (VISÍVEL APENAS NO MOBILE) -->
                <div class="d-block d-lg-none mb-5">
                    <div class="finance-card p-4">
                        <div class="text-center mb-3">
                            <span class="text-uppercase small text-muted">Valor do imóvel</span>
                            <div class="preco-principal mb-2">R$ <?= number_format($imovel['preco'], 2, ',', '.') ?></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-building"></i> Condomínio</span>
                            <div class="text-end">
                                <strong>R$ <?= number_format($imovel['valor_condominio'], 2, ',', '.') ?></strong>
                                <?= $textoInclusos ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span><i class="bi bi-receipt"></i> IPTU anual</span>
                            <strong>R$ <?= number_format($imovel['valor_iptu'], 2, ',', '.') ?></strong>
                        </div>
                        
                        <div class="finance-divider"></div>
                        
                        <div class="mb-3">
                            <strong class="small text-uppercase text-secondary">Condições de pagamento</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small"><i class="bi bi-cash-stack"></i> À vista</span>
                                <?php if($imovel['aceita_financiamento']): ?>
                                    <span class="bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small"><i class="bi bi-bank"></i> Financiamento</span>
                                <?php endif; ?>
                                <?php if($imovel['aceita_fgts']): ?>
                                    <span class="bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small"><i class="bi bi-wallet2"></i> FGTS</span>
                                <?php endif; ?>
                                <?php if($imovel['aceita_permuta']): ?>
                                    <span class="bg-info bg-opacity-10 text-info rounded-pill px-2 py-1 small"><i class="bi bi-arrow-left-right"></i> Permuta</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if($imovel['valor_sinal'] > 0): ?>
                            <div class="alert alert-success py-2 px-3 rounded-4 small">
                                <i class="bi bi-check-circle-fill"></i> Sinal sugerido: <strong>R$ <?= number_format($imovel['valor_sinal'], 2, ',', '.') ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DESCRIÇÃO -->
                <div>
                    <h3 class="section-title"><i class="bi bi-file-text"></i> Descrição</h3>
                    <div class="ps-2 pe-2" style="white-space: pre-line; line-height: 1.7; color: #2d3e50;">
                        <?= htmlspecialchars($imovel['descricao']) ?>
                    </div>
                </div>

                <!-- CONTATOS (síndico/portaria) -->
                <?php if(!empty($imovel['contato_sindico']) || !empty($imovel['contato_portaria'])): ?>
                <div class="mb-4">
                    <h3 class="section-title"><i class="bi bi-people"></i> Referências no condomínio</h3>
                    <div class="bg-light p-3 rounded-4">
                        <?php if($imovel['contato_sindico']): ?>
                            <div><i class="bi bi-person-badge me-2"></i> <strong>Síndico(a):</strong> <?= htmlspecialchars($imovel['contato_sindico']) ?></div>
                        <?php endif; ?>
                        <?php if($imovel['contato_portaria']): ?>
                            <div class="mt-2"><i class="bi bi-building me-2"></i> <strong>Portaria:</strong> <?= htmlspecialchars($imovel['contato_portaria']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- GALERIA DE FOTOS -->
                <?php if (!empty($todas_fotos)): ?>
                <div>
                    <h3 class="section-title"><i class="bi bi-images"></i> Galeria completa</h3>
                    <div class="row g-2 mt-2">
                        <?php foreach ($todas_fotos as $index => $f): ?>
                            <div class="col-4 col-md-3 col-lg-2">
                                <div class="thumb-galeria" onclick="openGallery(<?= $index ?>)">
                                    <img src="<?= $url_base_fotos . $f['caminho'] ?>" class="thumb-img" alt="Foto <?= $index+1 ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- MAPA -->
                <?php if(!empty($imovel['latitude']) && !empty($imovel['longitude'])): ?>
                <div class="mb-4">
                    <h3 class="section-title"><i class="bi bi-map"></i> Localização no mapa</h3>
                    <div id="mapaImovel" class="shadow-sm"></div>
                    <div class="text-center mt-2">
                        <a href="https://www.google.com/maps?q=<?= $imovel['latitude'] ?>,<?= $imovel['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-4">
                            <i class="bi bi-google"></i> Ver rotas no Google Maps
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        
        <!-- COLUNA LATERAL DIREITA (APENAS DESKTOP) -->
        <div class="col-lg-4 d-none d-lg-block">
            <div class="sticky-sidebar">
                <div class="finance-card p-4">
                    <div class="text-center mb-3">
                        <span class="text-uppercase small text-muted">Valor do imóvel</span>
                        <div class="preco-principal mb-2">R$ <?= number_format($imovel['preco'], 2, ',', '.') ?></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-building"></i> Condomínio</span>
                        <div class="text-end">
                            <strong>R$ <?= number_format($imovel['valor_condominio'], 2, ',', '.') ?></strong>
                            <?= $textoInclusos ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span><i class="bi bi-receipt"></i> IPTU anual</span>
                        <strong>R$ <?= number_format($imovel['valor_iptu'], 2, ',', '.') ?></strong>
                    </div>
                    
                    <div class="finance-divider"></div>
                    
                    <div class="mb-3">
                        <strong class="small text-uppercase text-secondary">Condições de pagamento</strong>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small"><i class="bi bi-cash-stack"></i> À vista</span>
                            <?php if($imovel['aceita_financiamento']): ?>
                                <span class="bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small"><i class="bi bi-bank"></i> Financiamento</span>
                            <?php endif; ?>
                            <?php if($imovel['aceita_fgts']): ?>
                                <span class="bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small"><i class="bi bi-wallet2"></i> FGTS</span>
                            <?php endif; ?>
                            <?php if($imovel['aceita_permuta']): ?>
                                <span class="bg-info bg-opacity-10 text-info rounded-pill px-2 py-1 small"><i class="bi bi-arrow-left-right"></i> Permuta</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if($imovel['valor_sinal'] > 0): ?>
                        <div class="alert alert-success py-2 px-3 rounded-4 small">
                            <i class="bi bi-check-circle-fill"></i> Sinal sugerido: <strong>R$ <?= number_format($imovel['valor_sinal'], 2, ',', '.') ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <a href="https://wa.me/5581986755592?text=Olá Valter! Tenho interesse no imóvel <?= urlencode($imovel['titulo']) ?>" 
                       target="_blank" class="btn btn-whatsapp-custom w-100 mb-3">
                        <i class="bi bi-whatsapp me-2"></i> Falar com Valter
                    </a>
                    
                    <div class="text-center mt-3 pt-2 border-top">
                        <img src="valter-perfil.JPG" alt="Valter Barreto" class="rounded-circle shadow-sm mb-2" width="80" height="80" style="object-fit: cover;">
                        <h5 class="fw-bold mb-0">Valter Barreto</h5>
                        <p class="text-muted small">CRECI-PE 22003 | Corretor</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="tel:5581986755592" class="text-decoration-none text-dark"><i class="bi bi-telephone-fill fs-5"></i></a>
                            <a href="mailto:valter@imoveis.com" class="text-decoration-none text-dark"><i class="bi bi-envelope-fill fs-5"></i></a>
                        </div>
                    </div>
                </div>
                
                <?php if(!empty($imovel['link_site'])): ?>
                <div class="card-moderno p-3 text-center mt-3">
                    <i class="bi bi-link-45deg"></i> Anúncio externo:
                    <a href="<?= htmlspecialchars($imovel['link_site']) ?>" target="_blank" class="d-block text-truncate">
                        <?= htmlspecialchars($imovel['link_site']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE GALERIA -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1070;"></button>
                <button class="modal-nav-btn btn-prev" onclick="changeImage(-1)"><i class="bi bi-chevron-left"></i></button>
                <img src="" id="galleryImage" class="img-fluid rounded shadow-lg" style="max-height: 85vh; object-fit: contain;">
                <button class="modal-nav-btn btn-next" onclick="changeImage(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Galeria
    const fotos = <?= $fotos_js ?>;
    const urlBase = "<?= $url_base_fotos ?>";
    let currentIndex = 0;
    const galleryModal = new bootstrap.Modal(document.getElementById('galleryModal'));
    const modalImg = document.getElementById('galleryImage');
    
    function openGallery(index) {
        if(!fotos.length) return;
        currentIndex = index;
        updateModalImage();
        galleryModal.show();
    }
    function changeImage(direction) {
        currentIndex += direction;
        if(currentIndex >= fotos.length) currentIndex = 0;
        if(currentIndex < 0) currentIndex = fotos.length - 1;
        updateModalImage();
    }
    function updateModalImage() {
        modalImg.src = urlBase + fotos[currentIndex];
    }
    
    // Mapa
    <?php if(!empty($imovel['latitude']) && !empty($imovel['longitude'])): ?>
        const lat = <?= $imovel['latitude'] ?>;
        const lng = <?= $imovel['longitude'] ?>;
        
        const map = L.map('mapaImovel').setView([lat, lng], 16);
        
        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        });
        
        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
        
        osmLayer.addTo(map);
        
        const marker = L.marker([lat, lng]).addTo(map)
            .bindPopup("<strong><?= addslashes(htmlspecialchars($imovel['titulo'])) ?></strong><br>Localização aproximada")
            .openPopup();
        
        const controlContainer = L.control({ position: 'topright' });
        controlContainer.onAdd = function() {
            const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            div.style.backgroundColor = 'white';
            div.style.padding = '5px 10px';
            div.style.borderRadius = '4px';
            div.style.boxShadow = '0 1px 5px rgba(0,0,0,0.3)';
            div.style.cursor = 'pointer';
            div.style.fontWeight = 'bold';
            div.style.fontSize = '14px';
            div.innerHTML = '🗺️ Mapa | 🛰️ Satélite';
            div.title = 'Clique para alternar entre mapa e satélite';
            
            div.onclick = function() {
                if (map.hasLayer(osmLayer) && !map.hasLayer(satelliteLayer)) {
                    map.removeLayer(osmLayer);
                    satelliteLayer.addTo(map);
                    div.innerHTML = '🗺️ Mapa | 🛰️ Ativo';
                    div.style.background = '#e9ecef';
                } else {
                    map.removeLayer(satelliteLayer);
                    osmLayer.addTo(map);
                    div.innerHTML = '🗺️ Ativo | 🛰️ Satélite';
                    div.style.background = 'white';
                }
            };
            return div;
        };
        controlContainer.addTo(map);
        
        setTimeout(() => { map.invalidateSize(); }, 200);
    <?php endif; ?>
</script>

</body>
</html>