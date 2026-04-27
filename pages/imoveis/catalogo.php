<?php
// pages/imoveis/catalogo.php
require_once '../../conn_cap.php';

// 1. Consulta SQL atualizada
$sql = "
    SELECT 
        i.id,
        i.titulo,
        i.bairro,
        i.cidade,
        i.preco,
        i.quartos,
        i.area,
        i.vagas_garagem,
        i.mobiliado,
        i.possui_elevador,
        i.status,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id ORDER BY id ASC LIMIT 1) AS foto_capa
    FROM imoveis i 
    WHERE i.deleted_at IS NULL 
    AND i.status != 'vendido'
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

$url_fotos = "../../uploads/fotos_imoveis/";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Imóveis | Valter Barreto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root { --primary-color: #0d6efd; --bg-light: #f4f7f6; }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Navbar Profissional */
        .navbar-brand-custom { text-align: center; width: 100%; padding: 20px 0; background: #fff; border-bottom: 4px solid var(--primary-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .nome-corretor { font-size: 1.8rem; font-weight: 900; color: #1a1a1a; margin: 0; letter-spacing: -1px; }
        .creci-label { font-size: 0.8rem; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; }

        /* Card de Imóvel Estilizado */
        .card-imovel { 
            border: none; 
            border-radius: 16px; 
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }
        .card-imovel:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); }
        
        /* Container da Imagem */
        .img-wrapper { position: relative; height: 240px; overflow: hidden; }
        .img-vitrine { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .card-imovel:hover .img-vitrine { transform: scale(1.1); }
        
        /* Badge de Status/Preço sobre a imagem */
        .price-overlay {
            position: absolute; bottom: 12px; left: 12px;
            background: rgba(13, 110, 253, 0.95); color: #fff;
            padding: 5px 15px; border-radius: 8px; font-weight: 800; font-size: 1.2rem;
            backdrop-filter: blur(4px);
        }

        /* Grade Técnica Unificada */
        .tech-grid { 
            display: grid; grid-template-columns: repeat(2, 1fr); 
            gap: 10px; background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 15px;
        }
        .tech-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444; font-weight: 500; }
        .tech-item i { color: var(--primary-color); font-size: 1rem; }

        .btn-detalhes { 
            width: 100%; border-radius: 10px; font-weight: 700; padding: 10px;
            transition: all 0.3s ease; text-transform: uppercase; font-size: 0.85rem;
        }

        .whatsapp-float {
            position: fixed; bottom: 30px; right: 30px; background-color: #25d366;
            color: white; width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); z-index: 1000; transition: 0.3s;
        }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }
    </style>
</head>
<body>

<header class="navbar-brand-custom mb-5">
    <div class="container">
        <h1 class="nome-corretor">VALTER BARRETO</h1>
        <span class="creci-label">Especialista Imobiliário • CRECI-PE 22003</span>
    </div>
</header>

<div class="container pb-5">
    <div class="row g-4">
        <?php foreach ($imoveis as $im): 
            $foto = $im['foto_capa'] ? $url_fotos . $im['foto_capa'] : 'https://via.placeholder.com/400x300?text=Foto+em+Breve';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 card-imovel">
                <div class="img-wrapper">
                    <img src="<?= $foto ?>" class="img-vitrine" alt="<?= htmlspecialchars($im['titulo']) ?>">
                    <div class="price-overlay">
                        R$ <?= number_format($im['preco'], 0, ',', '.') ?>
                    </div>
                </div>

                <div class="card-body p-4 d-flex flex-column">
                    <div class="mb-3">
                        <h5 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($im['titulo']) ?></h5>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($im['bairro']) ?>, <?= htmlspecialchars($im['cidade']) ?>
                        </p>
                    </div>

                    <div class="tech-grid">
                        <div class="tech-item">
                            <i class="bi bi-arrows-fullscreen"></i> <?= number_format($im['area'], 0) ?> m² Útil
                        </div>
                        <div class="tech-item">
                            <i class="bi bi-door-open"></i> <?= $im['quartos'] ?> Quartos
                        </div>
                        <div class="tech-item">
                            <i class="bi bi-car-front"></i> <?= $im['vagas_garagem'] ?> Vagas
                        </div>
                        <div class="tech-item">
                            <?php if($im['mobiliado'] == 1): ?>
                                <i class="bi bi-check-circle-fill text-success"></i> Mobiliado
                            <?php else: ?>
                                <i class="bi bi-building"></i> 
                                <?= ($im['possui_elevador'] == 1) ? 'Com Elevador' : 'Andar Baixo' ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="imovel.php?id=<?= $im['id'] ?>" class="btn btn-outline-primary btn-detalhes">
                            Ver Detalhes Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<footer class="py-5 bg-white text-center border-top">
    <p class="text-muted mb-0">© <?= date('Y') ?> <strong>Valter Barreto</strong> - CRECI-PE: 22003</p>
    <small class="text-muted">Recife - Pernambuco</small>
</footer>

<a href="https://wa.me/5581986755592?text=Olá Valter! Gostaria de informações sobre os imóveis do seu catálogo." class="whatsapp-float" target="_blank">
    <i class="bi bi-whatsapp"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>