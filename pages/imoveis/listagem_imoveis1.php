<?php
session_start();
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

if (!function_exists('formataCheck')) {
    function formataCheck($valor) {
        return ($valor == 1) ? 'Sim' : 'Não';
    }
}

$sql = "SELECT * FROM imoveis WHERE deleted_at IS NULL ORDER BY preco ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ficha Imobiliária Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        /* Fundo em tons azuis suaves */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            overflow-x: hidden;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* Container de rolagem por página */
        #container {
            scroll-snap-type: y mandatory;
            overflow-y: scroll;
            height: 100vh;
        }

        .page-wrapper {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            scroll-snap-align: start;
            padding: 20px;
        }

        /* Card Branco com detalhes em azul escuro */
        .card-print {
            background: #fff;
            width: 100%;
            max-width: 380px;
            border-radius: 20px;
            overflow: hidden;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .header-print {
            background: #1976d2; /* Azul corporativo */
            color: #fff;
            padding: 15px;
            text-align: center;
        }

        .price-section {
            padding: 15px 0;
            text-align: center;
            background: #f0f7ff;
            border-bottom: 1px solid #e3f2fd;
        }

        .price-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0d47a1;
            display: block;
        }

        .info-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .info-box {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f1f1;
            border-right: 1px solid #f1f1f1;
        }

        .info-box:nth-child(even) { border-right: none; }

        .label {
            display: block;
            font-size: 0.6rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #90a4ae;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .value {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #263238;
        }

        .footer-tag {
            background: #fff;
            text-align: center;
            font-size: 0.65rem;
            padding: 12px;
            color: #1976d2;
            font-weight: bold;
            border-top: 1px solid #eee;
        }

        /* Barra de busca discreta */
        .search-bar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-wrapper { height: auto; scroll-snap-align: none; display: block; padding: 0; margin-bottom: 20px; }
            .card-print { box-shadow: none; border: 1px solid #ddd; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="no-print search-bar p-2 d-flex gap-2 sticky-top">
    <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
        <input type="text" id="search" class="form-control border-start-0" placeholder="Filtrar por bairro...">
    </div>
    <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="bi bi-printer"></i></button>
</div>

<div id="container">
    <?php 
    $count = 1;
    foreach ($imoveis as $im): 
    ?>
    <section class="page-wrapper" data-bairro="<?= strtolower($im['bairro']) ?>">
        <div class="card-print">
            <div class="header-print">
                <div class="small text-uppercase fw-bold opacity-75">Oportunidade #<?= str_pad($count++, 3, '0', STR_PAD_LEFT) ?></div>
                <div class="fw-bold fs-5"><?= htmlspecialchars($im['tipo']) ?> • <?= htmlspecialchars($im['bairro']) ?></div>
            </div>

            <div class="price-section">
                <span class="label">Valor de Venda</span>
                <span class="price-value">R$ <?= number_format($im['preco'], 2, ',', '.') ?></span>
            </div>

            <div class="info-container">
                <div class="info-box"><span class="label">Área Útil</span><span class="value"><?= number_format($im['area'], 0) ?> m²</span></div>
                <div class="info-box"><span class="label">Vagas</span><span class="value"><?= (int)$im['vagas_garagem'] ?> vaga(s)</span></div>
                
                <div class="info-box"><span class="label">Quartos</span><span class="value"><?= (int)$im['quartos'] ?> (<?= (int)$im['suites'] ?> suites)</span></div>
                <div class="info-box"><span class="label">Posição</span><span class="value"><?= ucfirst($im['face'] ?? 'Nascente') ?></span></div>
                
                <div class="info-box"><span class="label">Condomínio</span><span class="value">R$ <?= number_format($im['valor_condominio'], 2, ',', '.') ?></span></div>
                <div class="info-box"><span class="label">Andar</span><span class="value"><?= $im['andar'] ? $im['andar'].'º andar' : 'Térreo' ?></span></div>
                
                <div class="info-box"><span class="label">Elevador</span><span class="value"><?= formataCheck($im['possui_elevador']) ?></span></div>
                <div class="info-box"><span class="label">Mobiliado</span><span class="value"><?= formataCheck($im['mobiliado']) ?></span></div>
            </div>

            <div class="footer-tag">
                <i class="bi bi-whatsapp"></i> Solicite mais fotos e detalhes
            </div>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<script>
    document.getElementById('search').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.page-wrapper').forEach(page => {
            let bairro = page.dataset.bairro;
            page.style.display = bairro.includes(val) ? "flex" : "none";
        });
    });
</script>

</body>
</html>