<?php
// pages/imoveis/catalogo.php
require_once '../../conn_cap.php';

// 1. Consulta SQL com TODOS os campos relevantes (incluindo vendidos)
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
        i.suites,
        i.banheiros,
        i.andar,
        i.face,
        i.tipo,
        i.construtora,
        i.ano_entrega,
        i.valor_condominio,
        i.valor_iptu,
        i.valor_sinal,
        i.gas_encanado,
        i.tem_piscina,
        i.tem_academia,
        i.tem_salao_festas,
        i.tem_espaco_gourmet,
        i.tem_playground,
        i.possui_moveis_planejados,
        i.agua_inclusa_condominio,
        i.gas_incluso_condominio,
        i.aceita_financiamento,
        i.aceita_fgts,
        i.aceita_permuta,
        i.aceita_consorcio,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id ORDER BY capa DESC, ordem ASC, id ASC LIMIT 1) AS foto_capa
    FROM imoveis i 
    WHERE i.deleted_at IS NULL AND i.data_venda IS NULL AND categoria_registro = 'oficial'
    ORDER BY i.preco 
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
        
        .navbar-brand-custom { text-align: center; width: 100%; padding: 20px 0; background: #fff; border-bottom: 4px solid var(--primary-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .nome-corretor { font-size: 1.8rem; font-weight: 900; color: #1a1a1a; margin: 0; letter-spacing: -1px; }
        .creci-label { font-size: 0.8rem; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; }

        .card-imovel { 
            border: none; 
            border-radius: 16px; 
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }
        .card-imovel:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); }
        
        .img-wrapper { position: relative; height: 240px; overflow: hidden; }
        .img-vitrine { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .card-imovel:hover .img-vitrine { transform: scale(1.1); }
        
        .price-overlay {
            position: absolute; bottom: 12px; left: 12px;
            background: rgba(13, 110, 253, 0.95); color: #fff;
            padding: 5px 15px; border-radius: 8px; font-weight: 800; font-size: 1.2rem;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        /* TARJA DE VENDIDO */
        .sold-tag {
            position: absolute;
            top: 15px;
            right: -30px;
            background-color: #dc3545;
            color: white;
            font-weight: 800;
            font-size: 0.9rem;
            padding: 5px 40px;
            transform: rotate(45deg);
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 3;
            white-space: nowrap;
        }

        /* Efeito de escurecimento na imagem quando vendido */
        .img-wrapper.vendido .img-vitrine {
            filter: brightness(0.7);
        }

        .tech-grid { 
            display: grid; grid-template-columns: repeat(2, 1fr); 
            gap: 10px; background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 15px;
        }
        .tech-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444; font-weight: 500; }
        .tech-item i { color: var(--primary-color); font-size: 1rem; }

        .valores-box {
            background: #eef2ff;
            border-radius: 12px;
            padding: 8px 12px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .valores-item { font-weight: 600; color: #1e3a8a; }
        .valores-item i { margin-right: 5px; color: #0d6efd; }

        .comodidades { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .badge-comodidade {
            background: #e9ecef;
            color: #2c3e50;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

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
            $tipoIcone = match($im['tipo']) {
                'casa' => 'bi-house-door-fill',
                'apartamento' => 'bi-building',
                'terreno' => 'bi-pin-map-fill',
                'comercial' => 'bi-briefcase-fill',
                default => 'bi-building'
            };
            $isVendido = ($im['status'] == 'vendido');
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 card-imovel">
                <div class="img-wrapper <?= $isVendido ? 'vendido' : '' ?>">
                    <img src="<?= $foto ?>" class="img-vitrine" alt="<?= htmlspecialchars($im['titulo']) ?>">
                    <div class="price-overlay">
                        R$ <?= number_format($im['preco'], 0, ',', '.') ?>
                    </div>
                    <!-- Tarja de VENDIDO -->
                    <?php if ($isVendido): ?>
                        <div class="sold-tag">VENDIDO</div>
                    <?php endif; ?>
                    <span class="position-absolute top-0 start-0 m-2 bg-dark bg-opacity-75 text-white px-2 py-1 rounded-pill small">
                        <i class="<?= $tipoIcone ?>"></i> <?= ucfirst($im['tipo']) ?>
                    </span>
                </div>

                <div class="card-body p-4 d-flex flex-column">
                    <div class="mb-3">
                        <h5 class="fw-bold text-dark mb-1 text-truncate">AP<?= $im['id'] ?>-<?= htmlspecialchars($im['titulo']) ?></h5>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($im['bairro']) ?>, <?= htmlspecialchars($im['cidade']) ?>
                        </p>
                        <?php if(!empty($im['construtora'])): ?>
                            <small class="text-muted"><i class="bi bi-tools"></i> Construtora: <?= htmlspecialchars($im['construtora']) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="tech-grid">
                        <div class="tech-item"><i class="bi bi-arrows-fullscreen"></i> <?= number_format($im['area'], 0) ?> m²</div>
                        <div class="tech-item"><i class="bi bi-door-open"></i> <?= $im['quartos'] ?> quartos</div>
                        <?php if($im['suites'] > 0): ?>
                            <div class="tech-item"><i class="bi bi-suit-heart"></i> <?= $im['suites'] ?> suíte(s)</div>
                        <?php endif; ?>
                        <?php if($im['banheiros'] > 0): ?>
                            <div class="tech-item"><i class="bi bi-droplet"></i> <?= $im['banheiros'] ?> banheiros</div>
                        <?php endif; ?>
                        <div class="tech-item"><i class="bi bi-car-front"></i> <?= $im['vagas_garagem'] ?> vagas</div>
                        <?php if($im['andar'] !== null && $im['andar'] > 0): ?>
                            <div class="tech-item"><i class="bi bi-layers"></i> <?= $im['andar'] ?>º andar</div>
                        <?php endif; ?>
                        <?php if($im['face'] !== null && $im['face'] != ''): ?>
                            <div class="tech-item"><i class="bi bi-brightness-alt-high"></i> Face: <?= ucfirst($im['face']) ?></div>
                        <?php endif; ?>
                        <?php if($im['ano_entrega'] !== null && $im['ano_entrega'] > 0): ?>
                            <div class="tech-item"><i class="bi bi-calendar-check"></i> Entrega: <?= $im['ano_entrega'] ?></div>
                        <?php endif; ?>
                        <?php if($im['mobiliado'] == 1): ?>
                            <div class="tech-item"><i class="bi bi-sofa"></i> Mobiliado</div>
                        <?php endif; ?>
                        <?php if($im['possui_elevador'] == 1): ?>
                            <div class="tech-item"><i class="bi bi-arrow-up-short"></i> Elevador</div>
                        <?php endif; ?>
                        <?php if($im['possui_moveis_planejados'] == 1): ?>
                            <div class="tech-item"><i class="bi bi-grid-3x3-gap-fill"></i> Móveis planejados</div>
                        <?php endif; ?>
                    </div>

                    <div class="valores-box">
                        <?php if($im['valor_condominio'] > 0): ?>
                            <div class="valores-item"><i class="bi bi-building"></i> Cond. R$ <?= number_format($im['valor_condominio'], 2, ',', '.') ?></div>
                        <?php endif; ?>
                        <?php if($im['valor_iptu'] > 0): ?>
                            <div class="valores-item"><i class="bi bi-receipt"></i> IPTU R$ <?= number_format($im['valor_iptu'], 2, ',', '.') ?></div>
                        <?php endif; ?>
                        <?php if($im['valor_sinal'] > 0): ?>
                            <div class="valores-item"><i class="bi bi-currency-exchange"></i> Sinal: R$ <?= number_format($im['valor_sinal'], 2, ',', '.') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="comodidades">
                        <?php if($im['gas_encanado'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-fuel-pump"></i> Gás encanado</span>
                        <?php endif; ?>
                        <?php if($im['tem_piscina'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-water"></i> Piscina</span>
                        <?php endif; ?>
                        <?php if($im['tem_academia'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-heart-pulse"></i> Academia</span>
                        <?php endif; ?>
                        <?php if($im['tem_salao_festas'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-balloon"></i> Salão de festas</span>
                        <?php endif; ?>
                        <?php if($im['tem_espaco_gourmet'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-egg-fried"></i> Espaço gourmet</span>
                        <?php endif; ?>
                        <?php if($im['tem_playground'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-tree"></i> Playground</span>
                        <?php endif; ?>
                        <?php if($im['agua_inclusa_condominio'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-droplet"></i> Água inclusa</span>
                        <?php endif; ?>
                        <?php if($im['gas_incluso_condominio'] == 1): ?>
                            <span class="badge-comodidade"><i class="bi bi-fire"></i> Gás incluso</span>
                        <?php endif; ?>
                    </div>

                    <?php if(!$isVendido && ($im['aceita_financiamento'] == 1 || $im['aceita_fgts'] == 1 || $im['aceita_permuta'] == 1 || $im['aceita_consorcio'] == 1)): ?>
                    <div class="mb-3 small text-success">
                        <i class="bi bi-hand-thumbs-up"></i> Aceita:
                        <?= $im['aceita_financiamento'] ? ' Financiamento' : '' ?>
                        <?= $im['aceita_fgts'] ? ' FGTS' : '' ?>
                        <?= $im['aceita_permuta'] ? ' Permuta' : '' ?>
                        <?= $im['aceita_consorcio'] ? ' Consórcio' : '' ?>
                    </div>
                    <?php endif; ?>

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