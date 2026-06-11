<?php
session_start();
ini_set('display_errors', 1); // remova em produção
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../conn_cap.php';
require_once __DIR__ . '/../../includes/header.php';

if (!isset($conn) || $conn === false) {
    die("Erro: conexão com o banco de dados não foi estabelecida.");
}

// 1. Mapeia e agrupa as faixas que REALMENTE possuem imóveis (Apenas Oficiais)
$faixasExistentes = [];
try {
    // ADICIONADO: categoria_registro = 'oficial'
    $stmtPrecos = $conn->query("
        SELECT preco 
        FROM imoveis 
        WHERE deleted_at IS NULL 
          AND preco IS NOT NULL 
          AND preco > 0
          AND categoria_registro = 'oficial'
    ");
    while ($reg = $stmtPrecos->fetch(PDO::FETCH_ASSOC)) {
        $preco = (float)$reg['preco'];
        
        $piso = floor($preco / 100000) * 100000;
        $teto = $piso + 100000;
        $chave = "{$piso}-{$teto}";
        
        if (isset($faixasExistentes[$chave])) {
            $faixasExistentes[$chave]['quantidade']++;
        } else {
            $faixasExistentes[$chave] = [
                'min' => $piso,
                'max' => $teto,
                'quantidade' => 1
            ];
        }
    }
    ksort($faixasExistentes);
    
} catch (PDOException $e) {
    // Mantém a array vazia caso a tabela falhe
}

function formatarRotuloFaixa($valor) {
    if ($valor >= 1000000) {
        return number_format($valor / 1000000, 1, ',', '') . ' Mi';
    }
    return ($valor / 1000) . ' Mil';
}
?>
<h2>Mapa de Todos os Imóveis</h2>

<div id="filtros" style="padding: 15px; background: #f5f5f5; margin-bottom: 15px; border-radius: 5px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
    <div>
        <label for="filtro-quartos"><b>Quartos:</b></label>
        <select id="filtro-quartos" onchange="filtrarImoveis()" style="padding: 5px; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="1">1 quarto</option>
            <option value="2">2 quartos</option>
            <option value="3">3 quartos</option>
            <option value="4">4+ quartos</option>
        </select>
    </div>
    <div>
        <label for="filtro-status"><b>Status:</b></label>
        <select id="filtro-status" onchange="filtrarImoveis()" style="padding: 5px; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="disponivel">Disponíveis</option>
            <option value="reservado">Reservados</option>
        </select>
    </div>
    <div>
        <label for="filtro-faixa-preco"><b>Faixa de Preço:</b></label>
        <select id="filtro-faixa-preco" onchange="filtrarImoveis()" style="padding: 5px; border-radius: 4px;">
            <option value="">Todas as faixas</option>
            <?php
            foreach ($faixasExistentes as $chave => $dadosFaixa) {
                $labelMin = formatarRotuloFaixa($dadosFaixa['min']);
                $labelMax = formatarRotuloFaixa($dadosFaixa['max']);
                $qtd = $dadosFaixa['quantidade'];
                
                echo "<option value=\"{$chave}\">R$ {$labelMin} a R$ {$labelMax} ({$qtd})</option>\n";
            }
            ?>
        </select>
    </div>
</div>

<div id="map" style="height:80vh; width:100%; min-height:500px;"></div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map = L.map('map').setView([-8.05, -34.9], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
}).addTo(map);

var marcadores = [];

<?php
function abreviarPreco($valor) {
    if ($valor >= 1000000) {
        return number_format($valor / 1000000, 1, ',', '') . 'M';
    } elseif ($valor >= 1000) {
        return number_format($valor / 1000, 0, ',', '') . 'K';
    } else {
        return (string)$valor;
    }
}

try {
    // ADICIONADO: AND categoria_registro = 'oficial' na listagem do mapa também
    $stmt = $conn->query("
        SELECT id, titulo, preco, latitude, longitude, quartos, reservado
        FROM imoveis 
        WHERE deleted_at IS NULL 
          AND latitude IS NOT NULL 
          AND longitude IS NOT NULL 
          AND latitude != 0 
          AND longitude != 0
          AND categoria_registro = 'oficial'
    ");
    
    echo "var defaultIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });\n";
    
    echo "var redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });\n";
    
    $count = 0;
    while ($i = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lat = (float)$i['latitude'];
        $lng = (float)$i['longitude'];
        $titulo = htmlspecialchars($i['titulo'], ENT_QUOTES, 'UTF-8');
        $quartos = (int)$i['quartos'];
        $precoRaw = (float)$i['preco'];
        $precoAbreviado = abreviarPreco($i['preco']);
        $precoOriginal = number_format($i['preco'], 2, ',', '.');
        $reservado = (bool)$i['reservado'];
        
        $prefixo = $reservado ? "🔴 RESERVADO - " : "";
        $tooltipText = $prefixo . "{$titulo} | {$quartos} quartos | R$ {$precoAbreviado}";
        
        $popupText = ($reservado ? "<span style='color:red; font-weight:bold'>🔴 RESERVADO</span><br>" : "") 
                   . "<b>{$titulo}</b><br>Quartos: {$quartos}<br>Valor: R$ {$precoOriginal}<br>"
                   . "<a href=\"../imoveis/view.php?id={$i['id']}\" target=\"_blank\">Ver detalhes</a>";
        
        $icone = $reservado ? "redIcon" : "defaultIcon";
        $reservadoJs = $reservado ? "true" : "false";
        
        echo "var m = L.marker([{$lat}, {$lng}], {icon: {$icone}}).addTo(map);\n";
        echo "m.bindPopup(" . json_encode($popupText) . ");\n";
        echo "m.bindTooltip(" . json_encode($tooltipText) . ", {permanent: false, direction: 'top'});\n";
        
        echo "m.dadosImovel = { quartos: {$quartos}, preco: {$precoRaw}, reservado: {$reservadoJs} };\n";
        echo "marcadores.push(m);\n";
        $count++;
    }
    
    echo "console.log('{$count} imóveis oficiais carregados no mapa.');\n";
    if ($count > 0) {
        echo "var elementosIniciais = marcadores.filter(function(m) { return true; });\n";
        echo "if(elementosIniciais.length > 0) { var group = L.featureGroup(elementosIniciais); map.fitBounds(group.getBounds()); }\n";
    }
    
} catch (PDOException $e) {
    echo "console.error('Erro na consulta: " . addslashes($e->getMessage()) . "');\n";
}
?>

function filtrarImoveis() {
    var qtdQuartos = document.getElementById('filtro-quartos').value;
    var status = document.getElementById('filtro-status').value;
    var faixaPreco = document.getElementById('filtro-faixa-preco').value;

    marcadores.forEach(function(m) {
        var dados = m.dadosImovel;
        var exibir = true;

        if (qtdQuartos !== "") {
            if (qtdQuartos === "4") {
                if (dados.quartos < 4) exibir = false;
            } else {
                if (dados.quartos !== parseInt(qtdQuartos)) exibir = false;
            }
        }

        if (status !== "") {
            if (status === "reservado" && !dados.reservado) exibir = false;
            if (status === "disponivel" && dados.reservado) exibir = false;
        }

        if (faixaPreco !== "") {
            var limites = faixaPreco.split('-');
            var precoMinimo = parseFloat(limites[0]);
            var precoMaximo = parseFloat(limites[1]);

            if (dados.preco < precoMinimo || dados.preco > precoMaximo) {
                exibir = false;
            }
        }

        if (exibir) {
            m.addTo(map);
        } else {
            m.remove();
        }
    });
}
</script>