<?php
// primeiro_nome_obs_parceiros_agrupado.php - Apenas leads compartilhados, agrupado por Compra/Aluguel, com ID L999
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Garantir que a coluna compartilhado_parceiro exista
try {
    $check = $conn->query("SHOW COLUMNS FROM leads LIKE 'compartilhado_parceiro'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE leads ADD COLUMN compartilhado_parceiro TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    // ignora
}

// ==========================================
// CONSULTA PRINCIPAL (compartilhado_parceiro = 1)
// ==========================================
$busca = $_GET['busca'] ?? '';

$where = "WHERE 1=1 AND compartilhado_parceiro = 1";
$params = [];

if (!empty($busca)) {
    $where .= " AND (primeiro_nome LIKE ? OR obs_parceiros LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql = "SELECT id, nome, primeiro_nome, obs_parceiros, tipo_desejo 
        FROM leads $where 
        ORDER BY 
            CASE tipo_desejo WHEN 'Compra' THEN 1 WHEN 'Aluguel' THEN 2 ELSE 3 END,
            primeiro_nome ASC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separar os arrays por tipo_desejo
$compra = [];
$aluguel = [];
$outros = [];
foreach ($leads as $lead) {
    $tipo = $lead['tipo_desejo'] ?? '';
    if ($tipo == 'Compra') {
        $compra[] = $lead;
    } elseif ($tipo == 'Aluguel') {
        $aluguel[] = $lead;
    } else {
        $outros[] = $lead;
    }
}

require_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Lista para WhatsApp - Compartilhados com Parceiros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .container {
            max-width: 1400px;
        }
        .texto-plano {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            font-family: 'Segoe UI', 'Courier New', monospace;
            font-size: 1rem;
            line-height: 1.6;
            border: 1px solid #ced4da;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-height: 70vh;
            max-height: none;
            overflow-y: visible;
        }
        .texto-plano pre {
            margin: 0;
            font-family: inherit;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .btn-copiar {
            font-size: 1.1rem;
            padding: 10px 24px;
        }
        @media (max-width: 768px) {
            .texto-plano {
                padding: 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">
                <i class="bi bi-whatsapp me-2"></i>Leads Compartilhados com Parceiros
            </h2>
            <p class="text-muted">Separado por Compra / Aluguel | Apenas leads com compartilhamento ativo</p>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="busca" class="form-control" placeholder="Filtrar nome ou observação..." value="<?= htmlspecialchars($busca) ?>" style="min-width: 200px;">
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            </form>
            <a href="leads3.php" class="btn btn-outline-secondary">Voltar</a>
        </div>
    </div>

    <div class="card border-0 shadow-lg">
        <div class="card-body p-0">
            <div class="texto-plano" id="textoParaCopiar">
                <pre><?php
                $total = count($compra) + count($aluguel) + count($outros);
                if ($total === 0) {
                    echo "Nenhum lead compartilhado com parceiros encontrado.";
                } else {
                    // Função para imprimir um grupo
                    function imprimirGrupo($titulo, $lista) {
                        if (empty($lista)) return;
                        $linhaDupla = str_repeat('═', 20);
                        $linhaSimples = str_repeat('─', 20);
                        
                        echo "\n\n{$linhaDupla}\n";
                        echo "🏷️  {$titulo} (" . count($lista) . " leads)\n";
                        echo "{$linhaDupla}\n\n";
                        
                        foreach ($lista as $lead) {
                            // Formata ID como L999 (ex: L001, L042, L123)
                            $id_formatado = 'L' . str_pad($lead['id'], 3, '0', STR_PAD_LEFT);
                            $primeiro_nome = trim($lead['primeiro_nome']) ?: '(nome não informado)';
                            $obs = trim($lead['obs_parceiros'] ?? '');
                            $obs_formatada = $obs ?: '(sem observação)';
                            
                            echo "🆔 {$id_formatado} | 👤 {$primeiro_nome}\n";
                            echo "📝 OBSERVAÇÃO:\n";
                            echo $obs_formatada . "\n";
                            echo "{$linhaSimples}\n";
                        }
                    }
                    
                    imprimirGrupo("COMPRA", $compra);
                    imprimirGrupo("ALUGUEL", $aluguel);
                    imprimirGrupo("OUTROS (não definido)", $outros);
                }
                ?></pre>
            </div>
        </div>
    </div>
    <div class="mt-4 text-center">
        <button id="copiarBtn" class="btn btn-success btn-copiar shadow">
            <i class="bi bi-clipboard-check fs-5 me-2"></i> Copiar tudo para o WhatsApp
        </button>
    </div>
    <div class="mt-3 text-muted small text-center">
        <i class="bi bi-info-circle"></i> Após copiar, abra o WhatsApp e cole (Ctrl+V ou toque longo > colar).
    </div>
</div>

<script>
document.getElementById('copiarBtn')?.addEventListener('click', async function() {
    try {
        const texto = document.getElementById('textoParaCopiar').innerText;
        await navigator.clipboard.writeText(texto);
        alert('✅ Texto copiado! Agora cole no WhatsApp.');
    } catch (err) {
        alert('❌ Não foi possível copiar automaticamente. Selecione o texto manualmente (Ctrl+A) e copie.');
    }
});
</script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>