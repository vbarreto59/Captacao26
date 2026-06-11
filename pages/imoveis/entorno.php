<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header('Location: list.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, titulo, latitude, longitude FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$imovel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$imovel) {
    header('Location: list.php');
    exit;
}

$coordenadas_imovel = '';
if (!is_null($imovel['latitude']) && !is_null($imovel['longitude'])) {
    $coordenadas_imovel = $imovel['latitude'] . ', ' . $imovel['longitude'];
}

$erro = '';
$sucesso = '';

// Atualizar coordenadas do imóvel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_coordenadas') {
    $coordenadas = trim($_POST['coordenadas_imovel'] ?? '');
    if (preg_match('/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/', $coordenadas)) {
        list($lat, $lng) = array_map('trim', explode(',', $coordenadas));
        $lat = (float)$lat;
        $lng = (float)$lng;
        $stmt = $conn->prepare("UPDATE imoveis SET latitude = ?, longitude = ? WHERE id = ?");
        if ($stmt->execute([$lat, $lng, $id])) {
            $sucesso = "Coordenadas do imóvel atualizadas!";
            $imovel['latitude'] = $lat;
            $imovel['longitude'] = $lng;
            $coordenadas_imovel = $coordenadas;
        } else {
            $erro = "Erro ao atualizar coordenadas.";
        }
    } else {
        $erro = "Formato inválido. Use: latitude, longitude (ex: -8.144419, -34.909889)";
    }
}

// Adicionar novo estabelecimento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar_entorno') {
    $nome = trim($_POST['nome'] ?? '');
    $coordenadas = trim($_POST['coordenadas'] ?? '');
    if ($nome && preg_match('/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/', $coordenadas)) {
        $stmt = $conn->prepare("INSERT INTO entorno_estabelecimentos (imovel_id, nome, coordenadas) VALUES (?, ?, ?)");
        if ($stmt->execute([$id, $nome, $coordenadas])) {
            $sucesso = "Estabelecimento adicionado!";
        } else {
            $erro = "Erro ao adicionar.";
        }
    } else {
        $erro = "Preencha nome e coordenadas no formato lat, lng (ex: -8.123456, -34.123456).";
    }
}

// Excluir estabelecimento
if (isset($_GET['delete_entorno']) && is_numeric($_GET['delete_entorno'])) {
    $ent_id = (int)$_GET['delete_entorno'];
    $stmt = $conn->prepare("DELETE FROM entorno_estabelecimentos WHERE id = ? AND imovel_id = ?");
    if ($stmt->execute([$ent_id, $id])) {
        $sucesso = "Estabelecimento removido!";
    } else {
        $erro = "Erro ao remover.";
    }
    header("Location: entorno.php?id=$id&msg=deleted");
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, coordenadas FROM entorno_estabelecimentos WHERE imovel_id = ? ORDER BY nome");
$stmt->execute([$id]);
$estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $sucesso = "Estabelecimento removido!";

require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Entorno do Imóvel - <?= htmlspecialchars($imovel['titulo']) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #map { height: 500px; margin-bottom: 20px; border-radius: 12px; background: #e9ecef; }
        .list-group-item { font-size: 0.9rem; }
        .coordenada-exemplo { font-size: 0.75rem; color: #6c757d; }
        /* Estilo personalizado para os ícones coloridos usando filter */
        .leaflet-marker-icon.red-marker {
            filter: hue-rotate(0deg) saturate(100%) brightness(1);
        }
        .leaflet-marker-icon.blue-marker {
            filter: hue-rotate(200deg) saturate(200%);
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-primary">🌍 Entorno do Imóvel</h2>
            <p class="text-muted"><?= htmlspecialchars($imovel['titulo']) ?></p>
        </div>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= $erro ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">Localização do Imóvel e Arredores</div>
                <div class="card-body">
                    <div id="map"></div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <form method="POST" class="row g-2 align-items-end">
                                <input type="hidden" name="acao" value="atualizar_coordenadas">
                                <div class="col-8">
                                    <label class="form-label small fw-bold">Coordenadas do imóvel (latitude, longitude)</label>
                                    <input type="text" name="coordenadas_imovel" id="coordenadas_imovel" class="form-control form-control-sm" 
                                           placeholder="-8.144419618853743, -34.90988905588781"
                                           value="<?= htmlspecialchars($coordenadas_imovel) ?>">
                                    <div class="coordenada-exemplo">Formato: lat, lng (com vírgula e espaço)</div>
                                </div>
                                <div class="col-4">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Atualizar</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">Clique no mapa para adicionar um ponto de interesse</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">➕ Adicionar ponto de interesse</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="acao" value="adicionar_entorno">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nome do local *</label>
                            <input type="text" name="nome" id="nome_local" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Coordenadas (latitude, longitude) *</label>
                            <input type="text" name="coordenadas" id="coordenadas_novo" class="form-control" placeholder="-8.144419618853743, -34.90988905588781">
                            <div class="coordenada-exemplo">Clique no mapa para preencher automaticamente</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Salvar ponto</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white fw-bold">📍 Pontos de interesse próximos</div>
                <div class="card-body p-0">
                    <?php if (count($estabelecimentos) == 0): ?>
                        <div class="text-center p-4 text-muted">Nenhum ponto cadastrado ainda. Clique no mapa ou preencha o formulário.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($estabelecimentos as $e): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($e['nome']) ?></strong><br>
                                        <small class="text-muted">📍 <?= htmlspecialchars($e['coordenadas']) ?></small>
                                    </div>
                                    <a href="?id=<?= $id ?>&delete_entorno=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover este ponto?')"><i class="bi bi-trash"></i></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let latImovel = <?= $imovel['latitude'] ?? 'null' ?>;
        let lngImovel = <?= $imovel['longitude'] ?? 'null' ?>;
        if (latImovel === null || lngImovel === null || isNaN(latImovel) || isNaN(lngImovel)) {
            latImovel = -8.054277;
            lngImovel = -34.881256;
        }

        var map = L.map('map').setView([latImovel, lngImovel], 15);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> & CartoDB'
        }).addTo(map);

        // Criar ícone vermelho personalizado (sobrepondo o padrão com filtro)
        var redIcon = L.divIcon({
            html: '<div style="background-color: #dc3545; width: 22px; height: 22px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
            iconSize: [22, 22],
            className: 'custom-marker-red'
        });

        var blueIcon = L.divIcon({
            html: '<div style="background-color: #0d6efd; width: 22px; height: 22px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
            iconSize: [22, 22],
            className: 'custom-marker-blue'
        });

        // Marcador do imóvel (arrastável)
        var markerImovel = L.marker([latImovel, lngImovel], { draggable: true, icon: redIcon }).addTo(map);
        markerImovel.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            document.getElementById('coordenadas_imovel').value = pos.lat.toFixed(8) + ', ' + pos.lng.toFixed(8);
            document.querySelector("form input[name='acao'][value='atualizar_coordenadas']").closest('form').submit();
        });
        markerImovel.bindTooltip("<b>Imóvel: <?= addslashes($imovel['titulo']) ?></b>", { permanent: true, direction: 'top' });
        markerImovel.openTooltip();

        // Marcadores dos estabelecimentos (azuis)
        <?php foreach ($estabelecimentos as $e): 
            list($lat, $lng) = array_map('trim', explode(',', $e['coordenadas']));
            $lat = (float)$lat; $lng = (float)$lng;
            if (is_numeric($lat) && is_numeric($lng)): ?>
            var marker = L.marker([<?= $lat ?>, <?= $lng ?>], { icon: blueIcon }).addTo(map);
            marker.bindTooltip("<b><?= addslashes($e['nome']) ?></b>", { permanent: true, direction: 'top' });
        <?php endif; endforeach; ?>

        // Preencher campo de coordenadas ao clicar no mapa
        map.on('click', function(e) {
            var lat = e.latlng.lat.toFixed(8);
            var lng = e.latlng.lng.toFixed(8);
            document.getElementById('coordenadas_novo').value = lat + ', ' + lng;
            document.getElementById('nome_local').focus();
            alert("Coordenadas preenchidas! Agora digite o nome do estabelecimento.");
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>