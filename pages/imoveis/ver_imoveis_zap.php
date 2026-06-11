<?php
require_once '../../conn_cap.php';

header('Content-Type: text/plain; charset=utf-8');

$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';

if (empty($pin)) {
    die("ERRO: Nenhum código de acesso informado.\nUse o link completo enviado pelo administrador.");
}

try {
    $stmt = $conn->prepare("SELECT id, nome, creci FROM corretores WHERE codigo_acesso = ? AND status = 'Ativo' AND deleted_at IS NULL");
    $stmt->execute([$pin]);
    $corretor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$corretor) {
        die("ERRO: Código de acesso inválido ou corretor inativo.");
    }

    $stmtI = $conn->prepare("SELECT * FROM imoveis WHERE corretor_id = ? AND deleted_at IS NULL ORDER BY preco ASC");
    $stmtI->execute([$corretor['id']]);
    $imoveis = $stmtI->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERRO no sistema: " . $e->getMessage());
}

// ==================== CABEÇALHO ====================
echo "==================================================\n";
echo "PORTFÓLIO DE IMÓVEIS - PARCEIRO\n";
echo "Corretor(a): " . $corretor['nome'] . " (CRECI: " . $corretor['creci'] . ")\n";
echo "Total de imóveis: " . count($imoveis) . "\n";
echo "Código de acesso: " . $pin . "\n";
echo "Data: " . date('d/m/Y H:i') . "\n";
echo "==================================================\n\n";

if (empty($imoveis)) {
    echo "Nenhum imóvel encontrado para este corretor.\n";
    exit;
}

// ==================== LISTAGEM DOS IMÓVEIS ====================
foreach ($imoveis as $i) {
    $idFormatado = str_pad($i['id'], 3, '0', STR_PAD_LEFT);
    $excluido = !empty($i['deleted_at']);
    
    echo "[IMÓVEL #{$idFormatado}]";
    if ($excluido) echo " [EXCLUÍDO]";
    echo "\n";
    
    echo "Tipo: " . ($i['tipo'] ?? 'Não informado') . "\n";
    echo "Bairro: " . ($i['bairro'] ?? 'Não informado') . "\n";
    echo "Título: " . ($i['titulo'] ?? 'Sem título') . "\n";
    echo "Preço: R$ " . number_format($i['preco'] ?? 0, 2, ',', '.') . "\n";
    echo "Condomínio: R$ " . number_format($i['valor_condominio'] ?? 0, 2, ',', '.') . "\n";
    echo "IPTU: R$ " . number_format($i['valor_iptu'] ?? 0, 2, ',', '.') . "\n";
    
    // Regime Marinha
    $regime = $i['regime_marinha'] ?? 'nenhum';
    if (!empty($regime) && $regime != 'nenhum') {
        echo "Regime Marinha: " . ucfirst($regime) . "\n";
    }
    
    // Face
    if (!empty($i['face'])) {
        echo "Face: " . ucfirst($i['face']) . "\n";
    }
    
    // Andar
    if (!empty($i['andar']) && $i['andar'] > 0) {
        echo "Andar: {$i['andar']}º\n";
    }
    
    // Entrega de obra (somente se conservacao = 'novo' e data preenchida)
    if (!empty($i['entrega_obra']) && ($i['conservacao'] ?? '') == 'novo') {
        echo "Entrega prevista: " . date('m/Y', strtotime($i['entrega_obra'])) . "\n";
    }
    
    // Área, quartos, etc
    if (!empty($i['area']) && $i['area'] > 0) {
        echo "Área: {$i['area']} m²\n";
    }
    if (!empty($i['quartos']) && $i['quartos'] > 0) {
        echo "Quartos: {$i['quartos']}\n";
    }
    if (!empty($i['suites']) && $i['suites'] > 0) {
        echo "Suítes: {$i['suites']}\n";
    }
    if (!empty($i['banheiros']) && $i['banheiros'] > 0) {
        echo "Banheiros: {$i['banheiros']}\n";
    }
    if (!empty($i['vagas_garagem']) && $i['vagas_garagem'] > 0) {
        echo "Vagas garagem: {$i['vagas_garagem']}\n";
    }
    
    // Comodidades (amenities)
    $comodidades = [];
    if (!empty($i['tem_piscina'])) $comodidades[] = "Piscina";
    if (!empty($i['tem_academia'])) $comodidades[] = "Academia";
    if (!empty($i['tem_salao_festas'])) $comodidades[] = "Salão de Festas";
    if (!empty($i['tem_espaco_gourmet'])) $comodidades[] = "Espaço Gourmet";
    if (!empty($i['tem_playground'])) $comodidades[] = "Playground";
    if (!empty($i['possui_elevador'])) $comodidades[] = "Elevador";
    if (!empty($i['mobiliado'])) $comodidades[] = "Mobiliado";
    
    if (!empty($comodidades)) {
        echo "Comodidades: " . implode(", ", $comodidades) . "\n";
    }
    
    // Observações
    if (!empty($i['observacoes_gerais'])) {
        echo "Observações: " . str_replace(["\r\n", "\n", "\r"], ' ', $i['observacoes_gerais']) . "\n";
    }
    
    // Link do site (se existir) – apenas texto, sem botão
    if (!empty($i['link_site'])) {
        echo "Link: " . $i['link_site'] . "\n";
    }
    
    echo "--------------------------------------------------\n\n";
}
?>