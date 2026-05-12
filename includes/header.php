<?php
if (!defined('BASE_URL')) {
    if ($_SERVER['SERVER_NAME'] == 'localhost') {
        define('BASE_URL', '/phpCapImoveis2603'); 
    } else {
        define('BASE_URL', '/Captacao2603');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=yes">
    <title>Sistema de Captação - Imóveis</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">

    <style>
        /* ===== ESTILIZAÇÃO DE CENTRALIZAÇÃO E LARGURA ===== */
        body { background: #f4f6f9; }

        /* Limita a largura do site para não espalhar em monitores grandes */
        .limit-container {
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
        }
        
        .navbar-mobile {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            padding: 0.6rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Estilo Desktop (Centralizado) */
        @media (min-width: 992px) {
            .navbar-top-row {
                display: flex;
                align-items: center;
                justify-content: center; /* CENTRALIZA TUDO */
                gap: 30px;
            }

            .nav-scroll {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .nav-group {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 4px 12px;
                border-radius: 50px;
                background: #f8f9fa;
                border: 1px solid #ececec;
            }

            .label-group {
                font-size: 0.65rem;
                font-weight: 800;
                text-transform: uppercase;
                color: #888;
                border-right: 1px solid #ddd;
                padding-right: 8px;
                margin-right: 4px;
            }

            .nav-link-custom {
                font-size: 0.82rem;
                text-decoration: none;
                color: #333 !important;
                padding: 3px 7px;
                border-radius: 4px;
                white-space: nowrap;
            }

            .nav-link-custom:hover { background: rgba(0,0,0,0.05); }

            /* Cores dos Grupos */
            .group-leads { background-color: #e8f5e9 !important; border-color: #c8e6c9 !important; }
            .group-imoveis { background-color: #e3f2fd !important; border-color: #bbdefb !important; }
            .group-agenda { background-color: #fff3e0 !important; border-color: #ffe0b2 !important; }
            .group-mapa { background-color: #f3e5f5 !important; border-color: #e1bee7 !important; }
        }

        /* Estilo Mobile (Empilhado) */
        @media (max-width: 991.98px) {
            .navbar-top-row { display: flex; justify-content: space-between; align-items: center; padding: 0 15px; }
            .nav-scroll { display: flex; flex-direction: column; gap: 10px; padding: 15px; }
            .nav-group { 
                display: flex; flex-wrap: wrap; gap: 8px; padding: 15px; 
                background: #fff; border-radius: 12px; border-left: 4px solid #ddd;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .label-group { width: 100%; font-size: 0.75rem; font-weight: bold; color: #666; margin-bottom: 5px; }
            .navbar-toggler-custom { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 5px 12px; }
        }

        .brand-mobile { font-weight: 700; color: #0d6efd; text-decoration: none; font-size: 1.2rem; }
    </style>
</head>
<body>

<nav class="navbar-mobile">
    <div class="limit-container">
        <div class="navbar-top-row">
            <!-- LOGO -->
            <a class="brand-mobile" href="<?= BASE_URL ?>/dash.php">
                <i class="bi bi-building"></i> Captação
            </a>
            
            <!-- MENU COMPLETO -->
            <div class="navbar-collapse-mobile" id="mobileNavMenu">
                <div class="nav-scroll">
                    
                    <!-- HOME -->
                    <div class="nav-group group-home">
                        <span class="label-group">Início</span>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/dash.php"><i class="bi bi-house-door"></i> Dash</a>
                    </div>
                    
                    <!-- LEADS (TODOS OS LINKS) -->
                    <div class="nav-group group-leads">
                        <span class="label-group">Leads</span>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/leads.php">Lista</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/leads_kanban.php">Kanban</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/leads_funil.php">Funil</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/favoritos.php" title="Favoritos"><i class="bi bi-star-fill text-warning"></i></a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/leads_ppasso.php">LPP</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/contatos_hoje.php">Hoje</a>
                    </div>
                    
                    <!-- IMÓVEIS (TODOS OS LINKS) -->
                    <div class="nav-group group-imoveis">
                        <span class="label-group">Imóveis</span>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/imoveis/list.php">Lista</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/imoveis/catalogo.php">Catálogo</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/proprietarios/list.php">Proprietários</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/imoveis/imprimir_ficha.php">Ficha</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/imoveis/listagem_imoveis1.php">Invent.</a>
                    </div>
                    
                    <!-- VISITAS (TODOS OS LINKS) -->
                    <div class="nav-group group-agenda">
                        <span class="label-group">Visitas</span>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/visitas/agenda.php">Agenda</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/visitas/list.php">Lista</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/visitas/calendario.php">Calendário</a>
                    </div>

                    <!-- FERRAMENTAS -->
                    <div class="nav-group group-mapa">
                        <span class="label-group">Ferramentas</span>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/map/index.php"><i class="bi bi-geo-alt"></i> Mapa</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/menu.php">Parcerias</a>
                        <a class="nav-link-custom" href="<?= BASE_URL ?>/pages/leads/parceiros_view.php">Compartilhar</a>
                    </div>

                </div>
            </div>

            <!-- SAIR -->
            <div class="d-flex gap-2 align-items-center">
                <a class="btn btn-outline-danger btn-sm d-none d-lg-inline-flex" href="<?= BASE_URL ?>/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
                <button class="navbar-toggler-custom d-lg-none" type="button" id="mobileMenuToggle">
                    <i class="bi bi-list"></i> Menu
                </button>
            </div>

        </div>
    </div>
</nav>

<!-- INÍCIO DO CONTEÚDO CENTRALIZADO -->
<div class="limit-container mt-3 pb-5">
    <!-- Aqui entra o conteúdo das suas outras páginas -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function() {
        const btn = document.getElementById('mobileMenuToggle');
        const menu = document.getElementById('mobileNavMenu');
        
        btn?.addEventListener('click', () => {
            const isVisible = menu.style.display === 'block';
            menu.style.display = isVisible ? 'none' : 'block';
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) {
                menu.style.display = 'block';
            } else {
                menu.style.display = 'none';
            }
        });

        if (window.innerWidth >= 992) menu.style.display = 'block';
    })();
</script>
</body>
</html>