<?php
session_start();
require_once '../../conn_cap.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
// CONEXÃO COM BANCO DE DADOS
// ============================================================
try {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        throw new Exception("Conexão com o banco não disponível.");
    }
} catch (Exception $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// ============================================================
// PROCESSAMENTO ASSÍNCRONO (AJAX / FETCH API)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json');
    $leadId = intval($_POST['lead_id'] ?? 0);
    $valor = intval($_POST['valor'] ?? 0);

    if ($leadId > 0) {
        try {
            if ($_POST['acao'] === 'atualizar_contatar_hoje') {
                $sql = "UPDATE leads SET contatar_hoje = :valor WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $sucesso = $stmt->execute(['valor' => $valor, 'id' => $leadId]);
                echo json_encode(['sucesso' => $sucesso]);
                exit();
            } 
            elseif ($_POST['acao'] === 'atualizar_compartilhado_parceiro') {
                $sql = "UPDATE leads SET compartilhado_parceiro = :valor WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $sucesso = $stmt->execute(['valor' => $valor, 'id' => $leadId]);
                echo json_encode(['sucesso' => $sucesso]);
                exit();
            }
            elseif ($_POST['acao'] === 'atualizar_favorito') {
                $sql = "UPDATE leads SET favorito = :valor WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $sucesso = $stmt->execute(['valor' => $valor, 'id' => $leadId]);
                echo json_encode(['sucesso' => $sucesso]);
                exit();
            }
        } catch (Exception $e) {
            echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
            exit();
        }
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'ID inválido']);
        exit();
    }
}

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

function formatMoney($value) {
    if ($value === null || $value === '') return 'Não definido';
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function isImovelPresented($pdo, $leadId, $imovelId) {
    $sql = "SELECT 1 FROM lead_imovel_apresentado WHERE lead_id = :lead_id AND imovel_id = :imovel_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lead_id' => $leadId, 'imovel_id' => $imovelId]);
    return $stmt->fetch() !== false;
}

function markImovelPresented($pdo, $leadId, $imovelId, $observacao = null) {
    if (isImovelPresented($pdo, $leadId, $imovelId)) {
        return false;
    }
    $sql = "INSERT INTO lead_imovel_apresentado (lead_id, imovel_id, observacao) VALUES (:lead_id, :imovel_id, :observacao)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'lead_id' => $leadId,
        'imovel_id' => $imovelId,
        'observacao' => $observacao
    ]);
}

function unmarkImovelPresented($pdo, $leadId, $imovelId) {
    $sql = "DELETE FROM lead_imovel_apresentado WHERE lead_id = :lead_id AND imovel_id = :imovel_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['lead_id' => $leadId, 'imovel_id' => $imovelId]);
}

function updateLeadObservacoes($pdo, $leadId, $observacoes) {
    $sql = "UPDATE leads SET observacoes = :observacoes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['observacoes' => $observacoes, 'id' => $leadId]);
}

// ============================================================
// CARREGAMENTO DOS LEADS E IMÓVEIS
// ============================================================
function getLeads($pdo) {
    $sql = "SELECT id, nome, primeiro_nome, email, telefone, 
                   valor_min, valor_max,
                   quartos_min,
                   preferencia_localizacao,
                   temperatura, favorito,
                   tipo_desejo,
                   fase_funil,
                   observacoes,
                   contatar_hoje,
                   compartilhado_parceiro
            FROM leads 
            WHERE deleted_at IS NULL 
              AND (valor_min IS NOT NULL OR valor_max IS NOT NULL)
              AND (tipo_desejo IS NULL OR tipo_desejo != 'Aluguel')
              AND (fase_funil IS NULL OR fase_funil != 'Perdido')
            ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveImoveis($pdo) {
    $sql = "SELECT i.id, i.titulo, i.nome_edificio, i.endereco, i.bairro, i.cidade, 
                   i.preco, i.quartos, i.area, i.tipo, i.status,
                   i.corretor_id, c.nome AS corretor_nome
            FROM imoveis i
            LEFT JOIN corretores c ON i.corretor_id = c.id
            WHERE i.deleted_at IS NULL AND i.status != 'vendido' AND (i.reservado = 0 OR i.reservado IS NULL)
            ORDER BY i.preco ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getImoveisForLead($lead, $imoveis) {
    $minOriginal = $lead['valor_min'] !== null ? (float)$lead['valor_min'] : 0;
    $maxOriginal = $lead['valor_max'] !== null ? (float)$lead['valor_max'] : null;
    $quartosMin = isset($lead['quartos_min']) ? (int)$lead['quartos_min'] : 0;
    $prefLocal = isset($lead['preferencia_localizacao']) ? trim($lead['preferencia_localizacao']) : '';

    if ($maxOriginal === null) return [];

    $limiteInferior = max($minOriginal, $maxOriginal - 110000);
    $limiteSuperior = $maxOriginal + 110000;

    $candidatos = [];
    foreach ($imoveis as $imov) {
        if ((float)$imov['preco'] < $limiteInferior || (float)$imov['preco'] > $limiteSuperior) continue;
        if ($quartosMin > 0 && (int)$imov['quartos'] < $quartosMin) continue;
        if (!empty($prefLocal) && strcasecmp(trim($imov['bairro'] ?? ''), $prefLocal) !== 0) continue;
        $candidatos[] = $imov;
    }
    usort($candidatos, function($a, $b) { return (float)$b['preco'] <=> (float)$a['preco']; });
    return $candidatos;
}

// ============================================================
// PROCESSAMENTO DOS FORMULÁRIOS PADRÃO (POST REDIRECT GET)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['acao']) || !in_array($_POST['acao'], ['atualizar_contatar_hoje', 'atualizar_compartilhado_parceiro', 'atualizar_favorito']))) {
    $acao = $_POST['acao'] ?? '';
    $leadId = intval($_POST['lead_id'] ?? 0);
    $imovelId = intval($_POST['imovel_id'] ?? 0);
    $observacao = trim($_POST['observacao'] ?? '');
    $lead_obs_texto = $_POST['lead_observacoes'] ?? '';

    if ($leadId > 0) {
        if ($acao === 'marcar_apresentado' && $imovelId > 0) {
            markImovelPresented($pdo, $leadId, $imovelId, $observacao);
        } elseif ($acao === 'cancelar_apresentado' && $imovelId > 0) {
            unmarkImovelPresented($pdo, $leadId, $imovelId);
        } elseif ($acao === 'editar_obs_lead') {
            updateLeadObservacoes($pdo, $leadId, trim($lead_obs_texto));
        } elseif ($acao === 'marcar_todos_apresentados') {
            $imoveisIdsRaw = $_POST['imoveis_ids'] ?? '';
            if (!empty($imoveisIdsRaw)) {
                $idsArray = array_filter(array_map('intval', explode(',', $imoveisIdsRaw)));
                if (!empty($idsArray)) {
                    $pdo->beginTransaction();
                    foreach ($idsArray as $idImov) {
                        markImovelPresented($pdo, $leadId, $idImov, "Marcação em lote");
                    }
                    $pdo->commit();
                }
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$leads = getLeads($pdo);
$imoveis = getActiveImoveis($pdo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Match Leads x Imóveis - ±R$ 100k</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 16px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 16px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #f39c12;
            display: inline-block;
            padding-bottom: 5px;
            margin-top: 0;
            font-size: 1.5rem;
        }
        .lead-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 24px;
            overflow-x: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .lead-card.destaque-hoje {
            border: 2px solid #007bff;
            box-shadow: 0 2px 12px rgba(0, 123, 255, 0.15);
        }
        .lead-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-radius: 10px 10px 0 0;
        }
        .lead-header:hover {
            background: #1e2b38;
        }
        .lead-title {
            font-size: 1.2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* Botão favorito otimizado para toque */
        .btn-favorito-toggle {
            background: none !important;
            border: none !important;
            font-size: 1.8rem !important;
            cursor: pointer !important;
            padding: 5px 8px !important;
            margin: 0 !important;
            line-height: 1 !important;
            display: inline-flex !important;
            outline: none !important;
            transition: transform 0.1s ease;
            user-select: none;
            touch-action: manipulation;
        }
        .btn-favorito-toggle.is-favorito {
            color: #ffca28 !important;
            text-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .btn-favorito-toggle.not-favorito {
            color: #e0e0e0 !important;
        }
        .btn-favorito-toggle:active {
            transform: scale(1.2);
        }

        .lead-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }
        .toggle-label {
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 12px;
            border-radius: 40px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            user-select: none;
            touch-action: manipulation;
        }
        .toggle-label:active {
            background: rgba(255, 255, 255, 0.3);
        }
        .toggle-label input {
            cursor: pointer;
            margin: 0;
            width: 18px;
            height: 18px;
            pointer-events: auto;
        }
        .toggle-label.parceiro-ativo {
            background-color: #28a745 !important;
            color: white;
        }
        .lead-info {
            font-size: 0.9rem;
            background: #f8f9fa;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: baseline;
        }
        .lead-info span {
            font-weight: 600;
            color: #2c3e50;
        }
        .observacao-text {
            font-weight: normal;
            color: #6c757d;
            font-style: italic;
            max-width: 100%;
            word-break: break-word;
        }
        .price-range {
            background: #e9ecef;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #000000;
            white-space: nowrap;
        }
        .imoveis-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }
        .imoveis-table th, .imoveis-table td {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }
        .imoveis-table th {
            background-color: #f39c12;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .imoveis-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .no-match {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        .badge {
            background: #f39c12;
            color: #2c3e50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            white-space: nowrap;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-block;
        }
        .btn-marcar, .btn-cancelar, .btn-marcar-todos, .btn-hoje {
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            touch-action: manipulation;
        }
        .btn-marcar { background: #007bff; }
        .btn-marcar:active { background: #0056b3; transform: scale(0.97); }
        .btn-cancelar { background: #dc3545; }
        .btn-cancelar:active { background: #a71d2a; transform: scale(0.97); }
        .btn-hoje { background: #17a2b8; padding: 8px 12px; }
        .btn-hoje:active { background: #138496; transform: scale(0.97); }
        .btn-marcar-todos {
            background: #28a745;
            font-weight: bold;
            padding: 8px 14px;
            border-radius: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .btn-marcar-todos:active { background: #218838; transform: scale(0.97); }
        footer { text-align: center; margin-top: 30px; font-size: 0.8rem; color: #6c757d; }
        
        .temperatura { display: inline-flex; align-items: center; gap: 5px; }
        .temperatura-frio { color: #1e88e5; }
        .temperatura-morno { color: #fb8c00; }
        .temperatura-quente { color: #e53935; }
        
        .wrapper-observacoes {
            width: 100%;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .btn-link-editar {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 6px 8px;
            text-decoration: underline;
            font-weight: bold;
            display: inline-block;
            touch-action: manipulation;
        }
        .btn-link-editar:active { color: #0056b3; }
        .form-edit-obs { display: flex; flex-direction: column; gap: 8px; width: 100%; max-width: 600px; margin-top: 5px; }
        .textarea-obs { width: 100%; height: 80px; padding: 8px; font-size: 0.9rem; font-family: inherit; border: 1px solid #ccc; border-radius: 6px; }
        .group-btn-obs { display: flex; gap: 8px; }
        .btn-salvar-obs { background: #28a745; color: white; border: none; padding: 8px 14px; font-size: 0.85rem; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-cancelar-obs { background: #6c757d; color: white; border: none; padding: 8px 14px; font-size: 0.85rem; border-radius: 6px; cursor: pointer; }

        /* ========== RESPONSIVIDADE MOBILE ========== */
        @media (max-width: 768px) {
            body { padding: 12px; }
            .container { padding: 12px; }
            h1 { font-size: 1.3rem; }
            
            .lead-header {
                flex-direction: column;
                align-items: stretch;
                padding: 12px;
            }
            .lead-title {
                justify-content: space-between;
                width: 100%;
            }
            .lead-header-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }
            .toggle-label {
                justify-content: center;
                width: 100%;
                padding: 10px;
            }
            .price-range {
                text-align: center;
                white-space: normal;
                font-size: 0.75rem;
            }
            .lead-info {
                flex-direction: column;
                gap: 8px;
                padding: 12px;
            }
            .lead-info span {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            .btn-marcar-todos {
                width: 100%;
                text-align: center;
                margin-top: 5px;
            }
            .imoveis-table th, .imoveis-table td {
                padding: 8px 6px;
                font-size: 0.7rem;
            }
            .imoveis-table td form {
                display: flex;
                flex-direction: column;
                gap: 6px;
                align-items: stretch;
            }
            .imoveis-table td form input[type="text"] {
                width: 100% !important;
                font-size: 0.8rem;
                padding: 8px;
            }
            .btn-marcar, .btn-cancelar, .btn-hoje {
                width: 100%;
                text-align: center;
                padding: 10px;
                font-size: 0.8rem;
            }
            .badge-success {
                display: block;
                text-align: center;
                margin-bottom: 6px;
            }
            .btn-link-editar {
                padding: 8px;
                font-size: 0.9rem;
            }
            .wrapper-observacoes {
                margin-top: 8px;
            }
            .btn-favorito-toggle {
                font-size: 2rem !important;
                padding: 4px 12px !important;
            }
        }

        @media (max-width: 480px) {
            .imoveis-table {
                font-size: 0.65rem;
                min-width: 650px;
            }
            .imoveis-table th, .imoveis-table td {
                padding: 6px 4px;
            }
            .badge {
                font-size: 0.7rem;
            }
        }
    </style>
    <script>
        function toggleImoveis(id) {
            const el = document.getElementById('imoveis-' + id);
            if (el.style.display === 'none') {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        }

        function pararPropagacao(event) {
            event.stopPropagation();
        }

        function salvarPosicaoScroll() {
            localStorage.setItem('posicaoScroll', window.scrollY);
        }

        function habilitarEdicaoObs(leadId) {
            document.getElementById('exibicao-obs-' + leadId).style.display = 'none';
            document.getElementById('formulario-obs-' + leadId).style.display = 'flex';
        }

        function cancelarEdicaoObs(leadId) {
            document.getElementById('exibicao-obs-' + leadId).style.display = 'block';
            document.getElementById('formulario-obs-' + leadId).style.display = 'none';
        }

        function toggleDataHoje(inputIdentificador) {
            const input = document.getElementById(inputIdentificador);
            const hoje = new Date();
            const dia = String(hoje.getDate()).padStart(2, '0');
            const mes = String(hoje.getMonth() + 1).padStart(2, '0');
            const dataFormatada = dia + '/' + mes;

            if (input.value === dataFormatada) {
                input.value = '';
            } else if (input.value === '') {
                input.value = dataFormatada;
            }
        }

        function alterarContatarHoje(checkbox, leadId) {
            const valor = checkbox.checked ? 1 : 0;
            const card = document.getElementById('card-lead-' + leadId);
            
            if (card) {
                if (checkbox.checked) {
                    card.classList.add('destaque-hoje');
                } else {
                    card.classList.remove('destaque-hoje');
                }
            }

            const formData = new FormData();
            formData.append('acao', 'atualizar_contatar_hoje');
            formData.append('lead_id', leadId);
            formData.append('valor', valor);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (!data.sucesso) {
                    alert('Erro ao atualizar o status de contato.');
                    checkbox.checked = !checkbox.checked;
                    if (card) card.classList.toggle('destaque-hoje');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                checkbox.checked = !checkbox.checked;
                if (card) card.classList.toggle('destaque-hoje');
            });
        }

        function alterarCompartilhadoParceiro(checkbox, leadId) {
            const valor = checkbox.checked ? 1 : 0;
            const labelContainer = checkbox.closest('.toggle-label');
            
            if (labelContainer) {
                if (checkbox.checked) {
                    labelContainer.classList.add('parceiro-ativo');
                } else {
                    labelContainer.classList.remove('parceiro-ativo');
                }
            }

            const formData = new FormData();
            formData.append('acao', 'atualizar_compartilhado_parceiro');
            formData.append('lead_id', leadId);
            formData.append('valor', valor);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (!data.sucesso) {
                    alert('Erro ao atualizar o status de parceria.');
                    checkbox.checked = !checkbox.checked;
                    if (labelContainer) labelContainer.classList.toggle('parceiro-ativo');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                checkbox.checked = !checkbox.checked;
                if (labelContainer) labelContainer.classList.toggle('parceiro-ativo');
            });
        }

        function alterarFavorito(botao, leadId) {
            const atualmenteFavorito = botao.getAttribute('data-favorito') === '1';
            const novoValor = atualmenteFavorito ? 0 : 1;

            if (novoValor === 1) {
                botao.innerHTML = '⭐';
                botao.setAttribute('data-favorito', '1');
                botao.classList.remove('not-favorito');
                botao.classList.add('is-favorito');
            } else {
                botao.innerHTML = '☆';
                botao.setAttribute('data-favorito', '0');
                botao.classList.remove('is-favorito');
                botao.classList.add('not-favorito');
            }

            const formData = new FormData();
            formData.append('acao', 'atualizar_favorito');
            formData.append('lead_id', leadId);
            formData.append('valor', novoValor);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (!data.sucesso) {
                    alert('Erro ao atualizar favorito.');
                    if (atualmenteFavorito) {
                        botao.innerHTML = '⭐';
                        botao.setAttribute('data-favorito', '1');
                        botao.classList.remove('not-favorito');
                        botao.classList.add('is-favorito');
                    } else {
                        botao.innerHTML = '☆';
                        botao.setAttribute('data-favorito', '0');
                        botao.classList.remove('is-favorito');
                        botao.classList.add('not-favorito');
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro de comunicação.');
                if (atualmenteFavorito) {
                    botao.innerHTML = '⭐';
                    botao.setAttribute('data-favorito', '1');
                    botao.classList.remove('not-favorito');
                    botao.classList.add('is-favorito');
                } else {
                    botao.innerHTML = '☆';
                    botao.setAttribute('data-favorito', '0');
                    botao.classList.remove('is-favorito');
                    botao.classList.add('not-favorito');
                }
            });
        }
    </script>
</head>
<body>
<div class="container">
    <h1>🏠 Imóveis sugeridos para leads <span style="font-size:0.8rem;">(±R$ 100k)</span></h1>
    <p class="text-muted">Imóveis com preço dentro de R$ 100.000 acima ou abaixo do valor máximo desejado.</p>
    
    <?php if (empty($leads)): ?>
        <div class="alert alert-warning">Nenhum lead ativo com faixa de preço foi encontrado.</div>
    <?php else: ?>
        <?php foreach ($leads as $lead): 
            $imoveisMatch = getImoveisForLead($lead, $imoveis);
            $limiteInferior = ($lead['valor_max'] !== null) ? max($lead['valor_min'] ?? 0, $lead['valor_max'] - 100000) : 0;
            $limiteSuperior = ($lead['valor_max'] !== null) ? $lead['valor_max'] + 100000 : 0;
            
            switch (strtolower($lead['temperatura'] ?? '')) {
                case 'frio': $tempClass = 'temperatura-frio'; $tempIcon = '❄️'; break;
                case 'morno': $tempClass = 'temperatura-morno'; $tempIcon = '🌤️'; break;
                case 'quente': $tempClass = 'temperatura-quente'; $tempIcon = '🔥'; break;
                default: $tempClass = ''; $tempIcon = '❓';
            }
            
            $estaFavorito = ((int)$lead['favorito'] === 1);
            $favoritoIcon = $estaFavorito ? '⭐' : '☆';
            $classeFavorito = $estaFavorito ? 'is-favorito' : 'not-favorito';
            
            $quartosMinExib = isset($lead['quartos_min']) && $lead['quartos_min'] > 0 ? $lead['quartos_min'] : 'Não definido';
            $prefLocalExib = !empty($lead['preferencia_localizacao']) ? htmlspecialchars($lead['preferencia_localizacao']) : 'Não definida';
            $observacoesTextoOriginal = $lead['observacoes'] ?? '';
            $observacoesExibicao = !empty($observacoesTextoOriginal) ? nl2br(htmlspecialchars($observacoesTextoOriginal)) : 'Nenhuma observação registrada.';
            
            $stmtPres = $pdo->prepare("SELECT imovel_id FROM lead_imovel_apresentado WHERE lead_id = :lead_id");
            $stmtPres->execute(['lead_id' => $lead['id']]);
            $apresentados = array_column($stmtPres->fetchAll(PDO::FETCH_ASSOC), 'imovel_id');

            $idsNaoApresentados = [];
            foreach ($imoveisMatch as $imov) {
                if (!in_array($imov['id'], $apresentados)) {
                    $idsNaoApresentados[] = $imov['id'];
                }
            }
            $stringIdsNaoApresentados = implode(',', $idsNaoApresentados);
            
            $estaContatarHoje = ((int)$lead['contatar_hoje'] === 1);
            $estaCompartilhadoParceiro = ((int)($lead['compartilhado_parceiro'] ?? 0) === 1);
            
            $classeDestaqueHoje = $estaContatarHoje ? 'destaque-hoje' : '';
            $classeParceiroAtivo = $estaCompartilhadoParceiro ? 'parceiro-ativo' : '';
        ?>
        <div class="lead-card <?= $classeDestaqueHoje ?>" id="card-lead-<?= $lead['id'] ?>">
            <div class="lead-header" onclick="toggleImoveis(<?= $lead['id'] ?>)">
                <div class="lead-title">
                    L<?= str_pad($lead['id'], 3, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($lead['primeiro_nome'] ?: $lead['nome']) ?>
                    
                    <button type="button" class="btn-favorito-toggle <?= $classeFavorito ?>" 
                            data-favorito="<?= $estaFavorito ? '1' : '0' ?>" 
                            onclick="pararPropagacao(event); alterarFavorito(this, <?= $lead['id'] ?>)">
                        <?= $favoritoIcon ?>
                    </button>

                    <span class="badge"><?= count($imoveisMatch) ?> imóveis</span>
                    
                    <?php if (!empty($idsNaoApresentados)): ?>
                        <form method="POST" style="display:inline-block;" onclick="pararPropagacao(event);" onsubmit="salvarPosicaoScroll();">
                            <input type="hidden" name="acao" value="marcar_todos_apresentados">
                            <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                            <input type="hidden" name="imoveis_ids" value="<?= $stringIdsNaoApresentados ?>">
                            <button type="submit" class="btn-marcar-todos">✓ Marcar Todos</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="lead-header-actions">
                    <label class="toggle-label" onclick="pararPropagacao(event);">
                        <input type="checkbox" <?= $estaContatarHoje ? 'checked' : '' ?> 
                               onchange="alterarContatarHoje(this, <?= $lead['id'] ?>)"> 
                        📞 Contatar Hoje
                    </label>

                    <label class="toggle-label <?= $classeParceiroAtivo ?>" onclick="pararPropagacao(event);">
                        <input type="checkbox" <?= $estaCompartilhadoParceiro ? 'checked' : '' ?> 
                               onchange="alterarCompartilhadoParceiro(this, <?= $lead['id'] ?>)"> 
                        🤝 Compartilhado
                    </label>

                    <div class="price-range">
                        💰 Faixa considerada (±100k): <?= formatMoney($limiteInferior) ?> – <?= formatMoney($limiteSuperior) ?>
                    </div>
                </div>
            </div>
            <div class="lead-info">
                <span>📞 <?= htmlspecialchars($lead['telefone'] ?: '—') ?></span>
                <span>✉️ <?= htmlspecialchars($lead['email'] ?: '—') ?></span>
                <span>🆔 Lead #<?= $lead['id'] ?></span>
                <span>💰 Valor máximo desejado: <?= formatMoney($lead['valor_max']) ?></span>
                <span>🛏️ Quartos mín.: <?= $quartosMinExib ?></span>
                <span>📍 Localização preferida: <?= $prefLocalExib ?></span>
                <span class="temperatura <?= $tempClass ?>">🌡️ Temperatura: <?= $tempIcon ?> <?= ucfirst($lead['temperatura'] ?? 'Não definida') ?></span>
                
                <div class="wrapper-observacoes">
                    <div id="exibicao-obs-<?= $lead['id'] ?>">
                        <span>📝 Observações:</span> 
                        <span class="observacao-text"><?= $observacoesExibicao ?></span>
                        <button type="button" class="btn-link-editar" onclick="habilitarEdicaoObs(<?= $lead['id'] ?>)">[Editar]</button>
                    </div>

                    <form method="POST" id="formulario-obs-<?= $lead['id'] ?>" class="form-edit-obs" style="display: none;" onsubmit="salvarPosicaoScroll();">
                        <input type="hidden" name="acao" value="editar_obs_lead">
                        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                        <textarea name="lead_observacoes" class="textarea-obs"><?= htmlspecialchars($observacoesTextoOriginal) ?></textarea>
                        <div class="group-btn-obs">
                            <button type="submit" class="btn-salvar-obs">Salvar</button>
                            <button type="button" class="btn-cancelar-obs" onclick="cancelarEdicaoObs(<?= $lead['id'] ?>)">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="imoveis-<?= $lead['id'] ?>" style="display: block;">
                <?php if (empty($imoveisMatch)): ?>
                    <div class="no-match">❌ Nenhum imóvel disponível.</div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="imoveis-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título / Edifício</th>
                                    <th>Bairro</th>
                                    <th>Preço (R$)</th>
                                    <th>Quartos</th>
                                    <th>Área (m²)</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Corretor</th>
                                    <th>Apresentado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($imoveisMatch as $imov): 
                                    $jaApresentado = in_array($imov['id'], $apresentados);
                                    $inputIdUnico = "obs_" . $lead['id'] . "_" . $imov['id'];
                                ?>
                                <tr>
                                    <td><?= $imov['id'] ?></td>
                                    <td><?= mb_strtoupper(htmlspecialchars($imov['titulo'] ?: $imov['nome_edificio']), 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($imov['bairro'] ?? '—') ?></td>
                                    <td><strong><?= formatMoney($imov['preco']) ?></strong></td>
                                    <td><?= $imov['quartos'] ?? '—' ?></td>
                                    <td><?= $imov['area'] ? number_format($imov['area'], 2, ',', '.') . ' m²' : '—' ?></td>
                                    <td><?= ucfirst($imov['tipo']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $imov['status'])) ?></td>
                                    <td><?= !empty($imov['corretor_nome']) ? htmlspecialchars($imov['corretor_nome']) . " <small>(ID: {$imov['corretor_id']})</small>" : '—' ?></td>
                                    <td style="white-space: normal;">
                                        <?php if ($jaApresentado): ?>
                                            <span class="badge-success">✓ Apresentado</span>
                                            <form method="post" style="display:inline-flex; margin-top:6px;" onsubmit="salvarPosicaoScroll();">
                                                <input type="hidden" name="acao" value="cancelar_apresentado">
                                                <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                                <input type="hidden" name="imovel_id" value="<?= $imov['id'] ?>">
                                                <button type="submit" class="btn-cancelar">Cancelar</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display:flex; flex-direction:column; gap:6px;" onsubmit="salvarPosicaoScroll();">
                                                <input type="hidden" name="acao" value="marcar_apresentado">
                                                <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                                <input type="hidden" name="imovel_id" value="<?= $imov['id'] ?>">
                                                
                                                <input type="text" name="observacao" id="<?= $inputIdUnico ?>" placeholder="Observação" style="width:100%; padding:8px; font-size:0.8rem; border-radius:4px; border:1px solid #ccc;">
                                                <button type="button" class="btn-hoje" onclick="toggleDataHoje('<?= $inputIdUnico ?>')">📅 Hoje</button>
                                                
                                                <button type="submit" class="btn-marcar">Marcar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <footer>Gerado em <?= date('d/m/Y H:i:s') ?></footer>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const posicao = localStorage.getItem('posicaoScroll');
        if (posicao) {
            window.scrollTo(0, parseInt(posicao));
            localStorage.removeItem('posicaoScroll');
        }
    });
</script>
</body>
</html>