<?php
session_start();
// Se você já tiver um sistema de login, descomente a linha abaixo:
// require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Captação 2026 - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; }
        .menu-card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
            color: #007bff;
        }
        .icon-box {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<?php require_once '../../includes/header.php'; ?>
<div class="container py-5">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="fw-bold text-dark">Sistema de Captação <span class="text-primary">2026</span></h1>
            <p class="text-muted">Gestão Litoral - Imóveis e Leads</p>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        
        <!-- Link: Gerenciar Leads -->
        <div class="col-md-4">
            <a href="leads.php" target="_blank" rel="noopener noreferrer" class="card h-100 menu-card shadow-sm p-4 text-center">
                <div class="icon-box bg-primary bg-opacity-10 text-primary mx-auto">
                    <i class="bi bi-person-plus-fill fs-2"></i>
                </div>
                <h4 class="fw-bold">Leads</h4>
                <p class="text-muted small">Cadastro e visualização geral de todos os contatos captados.</p>
            </a>
        </div>

        <!-- Link: Atribuição de Leads -->
        <div class="col-md-4">
            <a href="listagem_leads.php" target="_blank" rel="noopener noreferrer" class="card h-100 menu-card shadow-sm p-4 text-center border-primary border-opacity-25">
                <div class="icon-box bg-success bg-opacity-10 text-success mx-auto">
                    <i class="bi bi-person-check-fill fs-2"></i>
                </div>
                <h4 class="fw-bold">Atribuição</h4>
                <p class="text-muted small">Vincular leads aos corretores e gerenciar responsáveis.</p>
            </a>
        </div>

        <!-- Link: Gerenciar Corretores -->
        <div class="col-md-4">
            <a href="corretores.php" target="_blank" rel="noopener noreferrer" class="card h-100 menu-card shadow-sm p-4 text-center">
                <div class="icon-box bg-dark bg-opacity-10 text-dark mx-auto">
                    <i class="bi bi-briefcase-fill fs-2"></i>
                </div>
                <h4 class="fw-bold">Corretores</h4>
                <p class="text-muted small">Gestão de equipe, CRECI e códigos de acesso (AAAA).</p>
            </a>
        </div>

    </div>

    <footer class="mt-5 pt-5 text-center text-muted border-top">
        <p class="small">&copy; 2026 - Sistema de Captação Profissional - Litoral</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>