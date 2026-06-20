<?php
// view_whatsapp.php - Exibe características do imóvel + entorno, formatado para copiar e compartilhar no WhatsApp
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header('Location: list.php');
    exit;
}

// Buscar dados do imóvel
$stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$imovel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$imovel) {
    header('Location: list.php');
    exit;
}

// Buscar fotos (apenas capa ou primeira)
$stmt_foto = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE imovel_id = ? ORDER BY capa DESC, id ASC LIMIT 1");
$stmt_foto->execute([$id]);
$foto = $stmt_foto->fetch(PDO::FETCH_ASSOC);
$foto_url = $foto ? "../../uploads/fotos_imoveis/" . $foto['caminho'] : "";

// Buscar corretores parceiros
$stmt_par = $conn->prepare("SELECT c.nome FROM imovel_parceiros ip JOIN corretores c ON ip.corretor_id = c.id WHERE ip.imovel_id = ?");
$stmt_par->execute([$id]);
$parceiros = $stmt_par->fetchAll(PDO::FETCH_COLUMN);

// ==================== NOVO: Buscar pontos do entorno ====================
$stmt_entorno = $conn->prepare("SELECT nome, coordenadas FROM entorno_estabelecimentos WHERE imovel_id = ? ORDER BY nome");
$stmt_entorno->execute([$id]);
$entorno = $stmt_entorno->fetchAll(PDO::FETCH_ASSOC);

// Formatar texto para WhatsApp
function formatWhatsAppText($imovel, $parceiros, $entorno) {
    $text = "🏢 *IMÓVEL: " . strtoupper($imovel['titulo']) . "*\n\n";
    $text .= "📍 *Localização:* " . $imovel['endereco'] . ", " . $imovel['bairro'] . " - " . $imovel['cidade'] . "/" . $imovel['estado'] . "\n";
    if (!empty($imovel['cep'])) $text .= "CEP: " . $imovel['cep'] . "\n";
    
    $text .= "\n💰 *Preço:* R$ " . number_format($imovel['preco'], 2, ',', '.') . "\n";
    if ($imovel['valor_condominio'] > 0) $text .= "Condomínio: R$ " . number_format($imovel['valor_condominio'], 2, ',', '.') . "\n";
    if ($imovel['valor_iptu'] > 0) $text .= "IPTU: R$ " . number_format($imovel['valor_iptu'], 2, ',', '.') . "\n";
    
    $text .= "\n📐 *Características:*\n";
    if ($imovel['area'] > 0) $text .= "• Área: " . number_format($imovel['area'], 0) . " m²\n";
    if ($imovel['quartos'] > 0) $text .= "• Quartos: " . $imovel['quartos'] . "\n";
    if ($imovel['suites'] > 0) $text .= "• Suítes: " . $imovel['suites'] . "\n";
    if ($imovel['banheiros'] > 0) $text .= "• Banheiros: " . $imovel['banheiros'] . "\n";
    if ($imovel['vagas_garagem'] > 0) $text .= "• Vagas de garagem: " . $imovel['vagas_garagem'] . "\n";
    if ($imovel['andar'] > 0) $text .= "• Andar: " . $imovel['andar'] . "º\n";
    if (!empty($imovel['face'])) $text .= "• Face: " . ucfirst($imovel['face']) . "\n";
    if (!empty($imovel['tipo'])) $text .= "• Tipo: " . ucfirst($imovel['tipo']) . "\n";
    if (!empty($imovel['construtora'])) $text .= "• Construtora: " . $imovel['construtora'] . "\n";
    if (!empty($imovel['ano_entrega'])) $text .= "• Ano de entrega: " . $imovel['ano_entrega'] . "\n";
    
    $text .= "\n🏊 *Comodidades:*\n";
    $comod = [];
    if ($imovel['tem_piscina']) $comod[] = "Piscina";
    if ($imovel['tem_academia']) $comod[] = "Academia";
    if ($imovel['tem_salao_festas']) $comod[] = "Salão de festas";
    if ($imovel['tem_espaco_gourmet']) $comod[] = "Espaço gourmet";
    if ($imovel['tem_playground']) $comod[] = "Playground";
    if ($imovel['possui_elevador']) $comod[] = "Elevador";
    if ($imovel['gas_encanado']) $comod[] = "Gás encanado";
    if ($imovel['mobiliado']) $comod[] = "Mobiliado";
    if ($imovel['possui_moveis_planejados']) $comod[] = "Móveis planejados";
    if ($imovel['agua_inclusa_condominio']) $comod[] = "Água inclusa no condomínio";
    if ($imovel['gas_incluso_condominio']) $comod[] = "Gás incluso no condomínio";
    $text .= (count($comod) > 0) ? "• " . implode("\n• ", $comod) : "Nenhuma informação adicional";
    
    // ==================== NOVO: Entorno / Proximidades ====================
    if (count($entorno) > 0) {
        $text .= "\n\n🌳 *Entorno / Proximidades:*\n";
        foreach ($entorno as $ponto) {
            $text .= "• " . htmlspecialchars($ponto['nome']);
            // Opcional: mostrar coordenadas, mas é mais amigável só o nome
            // $text .= " (" . $ponto['coordenadas'] . ")";
            $text .= "\n";
        }
    } else {
        // Caso não tenha entorno cadastrado, pode manter em branco ou comentar
        // $text .= "\n🌳 *Entorno:* Informações não cadastradas.\n";
    }
    
    // Seção de pagamento (opcional, comentada)
    /*
    $text .= "\n\n💳 *Condições de pagamento:*\n";
    $pag = [];
    if ($imovel['aceita_financiamento']) $pag[] = "Financiamento";
    if ($imovel['aceita_fgts']) $pag[] = "FGTS";
    if ($imovel['aceita_permuta']) $pag[] = "Permuta";
    if ($imovel['aceita_consorcio']) $pag[] = "Consórcio";
    if ($imovel['valor_sinal'] > 0) $text .= "• Sinal: R$ " . number_format($imovel['valor_sinal'], 2, ',', '.') . "\n";
    $text .= (count($pag) > 0) ? "• " . implode("\n• ", $pag) : "Não informado";
    */
    
    if (!empty($imovel['descricao'])) {
        $text .= "\n\n📝 *Descrição:*\n" . $imovel['descricao'] . "\n";
    }
    
    if (!empty($parceiros)) {
        // $text .= "\n🤝 *Corretores parceiros:* " . implode(", ", $parceiros) . "\n";
    }
    
    if (!empty($imovel['link_site'])) {
        $text .= "\n🔗 *Mais informações:* " . $imovel['link_site'] . "\n";
    }
    
    $text .= "\n📞 Contato: Valter Barreto - CRECI-PE: 22003 \n📞 (81) 98675-5592 / 9 8842-1455\n";
    
    return $text;
}

$whatsapp_text = formatWhatsAppText($imovel, $parceiros, $entorno);
$whatsapp_link = "https://wa.me/?text=" . urlencode($whatsapp_text);

require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compartilhar Imóvel - WhatsApp</title>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card-whatsapp {
            max-width: 800px;
            margin: 40px auto;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .preview-text {
            background-color: #e5ddd5;
            padding: 20px;
            border-radius: 16px;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.5;
            color: #075e54;
            max-height: 500px;
            overflow-y: auto;
        }
        .btn-whatsapp-custom {
            background-color: #25d366;
            color: white;
            font-weight: bold;
            border-radius: 40px;
            padding: 12px 24px;
            transition: 0.2s;
        }
        .btn-whatsapp-custom:hover {
            background-color: #128c7e;
            color: white;
        }
        .btn-copy {
            background-color: #6c757d;
            color: white;
        }
        .btn-copy:hover {
            background-color: #5a6268;
            color: white;
        }
        .foto-imovel {
            max-height: 250px;
            object-fit: cover;
            width: 100%;
            border-radius: 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card card-whatsapp border-0 shadow">
        <div class="card-header bg-success text-white text-center py-3">
            <h3 class="mb-0"><i class="bi bi-whatsapp"></i> Compartilhar Imóvel no WhatsApp</h3>
            <p class="mb-0 small">Copie o texto ou envie diretamente</p>
        </div>
        <div class="card-body p-4">
            <?php if ($foto_url && file_exists($foto_url)): ?>
                <img src="<?= $foto_url ?>" class="foto-imovel img-fluid" alt="Foto do imóvel">
            <?php endif; ?>
            
            <div class="mb-4">
                <h5><i class="bi bi-building"></i> <?= htmlspecialchars($imovel['titulo']) ?></h5>
                <p class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($imovel['bairro']) ?> - <?= htmlspecialchars($imovel['cidade']) ?></p>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Clique no botão abaixo para copiar o texto formatado e colar no WhatsApp, ou use o botão verde para abrir o WhatsApp diretamente.
            </div>
            
            <!-- Pré-visualização do texto -->
            <div class="mb-3">
                <label class="form-label fw-bold">Texto que será enviado:</label>
                <div class="preview-text" id="textoPreview"><?= htmlspecialchars($whatsapp_text) ?></div>
            </div>
            
            <div class="d-flex gap-3 flex-wrap justify-content-center">
                <button id="btnCopiarTexto" class="btn btn-copy px-4 py-2">
                    <i class="bi bi-clipboard"></i> Copiar texto
                </button>
                <a href="<?= $whatsapp_link ?>" target="_blank" class="btn btn-whatsapp-custom px-4 py-2">
                    <i class="bi bi-whatsapp"></i> Enviar via WhatsApp
                </a>
                <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary px-4 py-2">
                    <i class="bi bi-arrow-left"></i> Voltar ao imóvel
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnCopiarTexto').addEventListener('click', function() {
    const texto = document.getElementById('textoPreview').innerText;
    navigator.clipboard.writeText(texto).then(() => {
        alert('Texto copiado para a área de transferência!');
    }).catch(() => {
        alert('Erro ao copiar. Selecione manualmente.');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>