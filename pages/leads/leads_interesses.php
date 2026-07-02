<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// PROCESSAR AÇÕES (SALVAR OU LIMPAR TODOS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lead_id']) && !isset($_POST['limpar_todos'])) {
        $lead_id = (int)$_POST['lead_id'];
        $imoveis_selecionados = isset($_POST['imoveis']) ? array_map('intval', $_POST['imoveis']) : [];

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("DELETE FROM lead_imoveis WHERE lead_id = ?");
            $stmt->execute([$lead_id]);

            if (!empty($imoveis_selecionados)) {
                $stmt = $conn->prepare("INSERT INTO lead_imoveis (lead_id, imovel_id) VALUES (?, ?)");
                foreach ($imoveis_selecionados as $imovel_id) {
                    $stmt->execute([$lead_id, $imovel_id]);
                }
            }
            $conn->commit();
            $_SESSION['flash_msg'] = "Imóveis do lead #{$lead_id} atualizados com sucesso!";
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['flash_msg'] = "Erro ao atualizar: " . $e->getMessage();
        }
        header("Location: ?");
        exit;
    }

    if (isset($_POST['limpar_todos']) && $_POST['limpar_todos'] == '1') {
        try {
            $conn->exec("DELETE FROM lead_imoveis");
            $_SESSION['flash_msg'] = "Todos os imóveis foram removidos de todos os leads.";
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = "Erro ao limpar: " . $e->getMessage();
        }
        header("Location: ?");
        exit;
    }
}

// ==========================================
// EXIBIR MENSAGEM FLASH
// ==========================================
$msg = '';
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// ==========================================
// FILTRO DE BUSCA (LEADS)
// ==========================================
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// ==========================================
// CONSULTA PRINCIPAL (LEADS + IMÓVEIS ASSOCIADOS)
// ==========================================
$sql = "
    SELECT 
        l.id AS lead_id,
        l.nome,
        l.telefone,
        l.email,
        l.tipo_desejo,
        l.fase_funil,
        GROUP_CONCAT(li.imovel_id ORDER BY li.imovel_id SEPARATOR ',') AS imoveis_ids,
        COUNT(li.imovel_id) AS total_imoveis
    FROM leads l
    LEFT JOIN lead_imoveis li ON l.id = li.lead_id
    WHERE l.deleted_at IS NULL
";
if (!empty($busca)) {
    $sql .= " AND (l.nome LIKE :busca OR l.telefone LIKE :busca OR l.email LIKE :busca)";
}
$sql .= " GROUP BY l.id ORDER BY l.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($busca)) {
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt->execute();
}
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mapeia lead_id => array de imóveis_ids
$lead_imoveis_map = [];
foreach ($leads as $lead) {
    $lead_imoveis_map[$lead['lead_id']] = $lead['imoveis_ids'] 
        ? array_map('intval', explode(',', $lead['imoveis_ids'])) 
        : [];
}

// ==========================================
// TODOS OS IMÓVEIS (para os modais)
// ==========================================
$imoveis = $conn->query("SELECT id, titulo, bairro FROM imoveis ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imóveis de Interesse por Lead - CRM Imobiliário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; font-weight: 600; border: none; }
        .table-hover tbody tr:hover { background-color: #f8f9ff; }
        .badge-imovel { background-color: #e9ecef; color: #0d6efd; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; margin: 2px 4px 2px 0; display: inline-block; }
        .badge-imovel:hover { background-color: #0d6efd; color: white; transition: 0.2s; }
        .search-box { border-radius: 12px; padding: 0.6rem 1rem; border: 1px solid #e0e3e9; }
        .search-box:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); }
        .table th { border-top: none; font-weight: 600; color: #4a5568; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .total-badge { background: rgba(255,255,255,0.2); border-radius: 30px; padding: 0.2rem 1rem; font-size: 0.9rem; }
        .modal-content { border-radius: 16px; }
        .modal-header { border-bottom: 2px solid #f0f2f5; }
        .modal-footer { border-top: 2px solid #f0f2f5; }
        .list-group-item { border-left: 3px solid transparent; transition: 0.2s; }
        .list-group-item:hover { background-color: #f8f9ff; border-left-color: #0d6efd; }
        .btn-limpar-todos { background: #dc3545; color: white; border: none; border-radius: 8px; padding: 0.5rem 1.2rem; }
        .btn-limpar-todos:hover { background: #c82333; }
        .modal-search { border-radius: 20px; padding: 0.4rem 1rem; border: 1px solid #ced4da; }
        .modal-search:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); }
        .filtro-count { font-size: 0.85rem; color: #6c757d; margin-left: 8px; }
        /* Classe para ocultar itens (será aplicada via JS) */
        .item-oculto { display: none !important; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.9rem; } }
    </style>
</head>
<body>

<div class="container py-4">

    <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white rounded-circle p-2 shadow-sm">
                <i class="bi bi-house-heart fs-4 text-primary"></i>
            </div>
            <div>
                <h1 class="display-6 fw-bold mb-0" style="font-size: 1.8rem;">
                    Imóveis de Interesse por Lead
                </h1>
                <p class="text-muted mt-2 mb-0">
                    <i class="bi bi-people"></i> <?= count($leads) ?> leads
                    <?php if(!empty($busca)): ?>
                        <span class="badge bg-secondary ms-2">Filtro: “<?= htmlspecialchars($busca) ?>”</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="post" id="formLimparTodos" style="display: inline;">
                <input type="hidden" name="limpar_todos" value="1">
                <button type="button" class="btn btn-limpar-todos shadow-sm" 
                        onclick="confirmarLimparTodos()">
                    <i class="bi bi-eraser me-1"></i> Limpar todos os leads
                </button>
            </form>
            <a href="../leads.php" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
            <a href="../lead_form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Novo Lead
            </a>
        </div>
    </div>

    <!-- BUSCA POR LEADS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-8 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="busca" class="form-control search-box" 
                               placeholder="Buscar lead por nome, telefone ou e-mail..." 
                               value="<?= htmlspecialchars($busca) ?>">
                    </div>
                </div>
                <div class="col-md-4 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    <?php if(!empty($busca)): ?>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA DE LEADS -->
    <div class="card shadow-sm">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2"></i> Relação de Leads x Imóveis</span>
            <span class="total-badge">
                <i class="bi bi-building"></i> Total de associações: 
                <?= array_sum(array_column($leads, 'total_imoveis')) ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Lead</th>
                            <th>Contato</th>
                            <th>Intenção</th>
                            <th>Fase</th>
                            <th>Imóveis de Interesse</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($leads)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    Nenhum lead encontrado.
                                    <?php if(!empty($busca)): ?>
                                        <br><small>Tente ajustar os termos da busca.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($leads as $lead): 
                                $imoveis_ids = $lead_imoveis_map[$lead['lead_id']] ?? [];
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= $lead['lead_id'] ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= htmlspecialchars($lead['nome']) ?></span>
                                    </td>
                                    <td>
                                        <?php if(!empty($lead['telefone'])): ?>
                                            <i class="bi bi-whatsapp text-success me-1"></i>
                                            <?= htmlspecialchars($lead['telefone']) ?>
                                        <?php endif; ?>
                                        <?php if(!empty($lead['email'])): ?>
                                            <br><small class="text-muted">
                                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($lead['email']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $tipo = $lead['tipo_desejo'] ?? '—';
                                            $cor = match($tipo) {
                                                'Compra' => 'success',
                                                'Aluguel' => 'warning',
                                                'Venda' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $cor ?>"><?= $tipo ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $fase = $lead['fase_funil'] ?? 'Novo';
                                            $cor_fase = match($fase) {
                                                'Novo' => 'info',
                                                'Contato Feito', 'Tentativa de Contato' => 'primary',
                                                'Visita Agendada', 'Visita Realizada' => 'warning',
                                                'Analisando', 'Proposta' => 'orange',
                                                'Fechado' => 'success',
                                                'Perdido' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $cor_fase ?>"><?= $fase ?></span>
                                    </td>
                                    <td>
                                        <?php if($lead['total_imoveis'] > 0): ?>
                                            <div class="d-flex flex-wrap">
                                                <?php 
                                                    $ids = $imoveis_ids;
                                                    if(!empty($ids)) {
                                                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                                        $stmt_im = $conn->prepare("SELECT titulo FROM imoveis WHERE id IN ($placeholders)");
                                                        $stmt_im->execute($ids);
                                                        $titulos = $stmt_im->fetchAll(PDO::FETCH_COLUMN);
                                                        foreach($titulos as $titulo):
                                                ?>
                                                    <span class="badge-imovel">
                                                        <i class="bi bi-house-fill me-1"></i>
                                                        <?= htmlspecialchars($titulo) ?>
                                                    </span>
                                                <?php 
                                                        endforeach;
                                                    }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">
                                                <i class="bi bi-dash-circle"></i> Nenhum
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-gerenciar" 
                                                data-bs-toggle="modal" data-bs-target="#modalLead<?= $lead['lead_id'] ?>"
                                                data-lead-id="<?= $lead['lead_id'] ?>">
                                            <i class="bi bi-pencil-square"></i> Gerenciar
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL PARA ESTE LEAD -->
                                <div class="modal fade" id="modalLead<?= $lead['lead_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <form method="post">
                                                <input type="hidden" name="lead_id" value="<?= $lead['lead_id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="bi bi-person-badge me-2"></i>
                                                        Gerenciar imóveis – <strong><?= htmlspecialchars($lead['nome']) ?></strong>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted small">
                                                        Marque os imóveis que este lead tem interesse.
                                                    </p>
                                                    <?php if(empty($imoveis)): ?>
                                                        <div class="alert alert-warning">
                                                            Nenhum imóvel cadastrado. <a href="../imoveis/imovel_form.php" class="alert-link">Cadastre um imóvel</a> primeiro.
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- CAMPO DE BUSCA DENTRO DO MODAL -->
                                                        <div class="mb-3">
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-white">
                                                                    <i class="bi bi-search"></i>
                                                                </span>
                                                                <input type="text" class="form-control modal-search" 
                                                                       id="buscaImovel_<?= $lead['lead_id'] ?>"
                                                                       placeholder="Filtrar imóveis por nome..." 
                                                                       data-lead-id="<?= $lead['lead_id'] ?>">
                                                            </div>
                                                            <span class="filtro-count" id="contador_<?= $lead['lead_id'] ?>">
                                                                <?= count($imoveis) ?> imóveis
                                                            </span>
                                                        </div>
                                                        <div class="list-group" id="listaImoveis_<?= $lead['lead_id'] ?>">
                                                            <?php foreach($imoveis as $im): 
                                                                $checked = in_array($im['id'], $imoveis_ids) ? 'checked' : '';
                                                                // Armazena o título em minúsculo no dataset (usando data-titulo)
                                                                $tituloLower = mb_strtolower($im['titulo']);
                                                            ?>
                                                                <label class="list-group-item d-flex align-items-start p-3" 
                                                                       data-titulo="<?= htmlspecialchars($tituloLower, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input class="form-check-input me-3 mt-1 check-imovel" type="checkbox" 
                                                                           name="imoveis[]" value="<?= $im['id'] ?>" <?= $checked ?>>
                                                                    <div>
                                                                        <div class="fw-semibold"><?= htmlspecialchars($im['titulo']) ?></div>
                                                                        <small class="text-muted">
                                                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($im['bairro']) ?>
                                                                        </small>
                                                                    </div>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="limparCheckboxes(<?= $lead['lead_id'] ?>)">
                                                        <i class="bi bi-eraser me-1"></i> Limpar todos
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-save me-1"></i> Salvar alterações
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if(!empty($leads)): ?>
            <div class="card-footer bg-white text-muted small py-2 px-4 border-0">
                Exibindo <?= count($leads) ?> lead(s)
                <?php if(!empty($busca)): ?>
                    (filtrados)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================================
    // FUNÇÃO PARA FILTRAR IMÓVEIS (USANDO UMA NOVA ABORDAGEM)
    // ============================================================
    function filtrarImoveis(leadId, termo) {
        console.log('Filtrando lead ' + leadId + ' com termo: "' + termo + '"');
        var container = document.getElementById('listaImoveis_' + leadId);
        if (!container) {
            console.error('Container não encontrado para lead ' + leadId);
            return;
        }
        var itens = container.querySelectorAll('.list-group-item');
        var termoLower = termo.toLowerCase().trim();
        var visiveis = 0;

        // Percorre todos os itens
        for (var i = 0; i < itens.length; i++) {
            var item = itens[i];
            // Obtém o título do dataset (data-titulo)
            var titulo = item.getAttribute('data-titulo');
            // Se não tiver, tenta pegar do texto do elemento .fw-semibold
            if (!titulo) {
                var tituloEl = item.querySelector('.fw-semibold');
                titulo = tituloEl ? tituloEl.textContent.toLowerCase() : '';
            }
            // Se ainda vazio, usa o texto completo do item
            if (!titulo) {
                titulo = item.textContent.toLowerCase();
            }

            // Verifica se o título contém o termo
            if (titulo.indexOf(termoLower) !== -1) {
                // Mostra o item (remove a classe oculta)
                item.classList.remove('item-oculto');
                visiveis++;
            } else {
                // Oculta o item (adiciona a classe oculta)
                item.classList.add('item-oculto');
            }
        }

        // Atualiza o contador
        var contador = document.getElementById('contador_' + leadId);
        if (contador) {
            contador.textContent = visiveis + ' de ' + itens.length + ' imóveis';
        }
        console.log('Visíveis: ' + visiveis + ' de ' + itens.length);
    }

    // ============================================================
    // FUNÇÃO PARA LIMPAR CHECKBOXES
    // ============================================================
    function limparCheckboxes(leadId) {
        var container = document.getElementById('listaImoveis_' + leadId);
        if (!container) return;
        var checkboxes = container.querySelectorAll('input[type="checkbox"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    // ============================================================
    // CONFIRMAR LIMPAR TODOS
    // ============================================================
    function confirmarLimparTodos() {
        if (confirm('ATENÇÃO: Isso removerá TODOS os imóveis de TODOS os leads. Essa ação não pode ser desfeita. Deseja continuar?')) {
            document.getElementById('formLimparTodos').submit();
        }
    }

    // ============================================================
    // INICIALIZAR FILTROS: ATRIBUI EVENTO A TODOS OS CAMPOS DE BUSCA
    // ============================================================
    function inicializarFiltros() {
        var inputs = document.querySelectorAll('.modal-search');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            var leadId = input.getAttribute('data-lead-id');
            if (!leadId) continue;
            // Remove event listeners antigos (se houver) para evitar duplicação
            input.removeEventListener('input', handler);
            input.addEventListener('input', handler);
        }
    }

    // Handler para o evento 'input'
    function handler(e) {
        var input = e.target;
        var leadId = input.getAttribute('data-lead-id');
        if (leadId) {
            filtrarImoveis(leadId, input.value);
        }
    }

    // ============================================================
    // INICIALIZAR QUANDO A PÁGINA CARREGAR E QUANDO MODAIS ABRIREM
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        inicializarFiltros();

        // Quando um modal for aberto, re-inicializa os filtros e reseta contadores
        var modals = document.querySelectorAll('.modal');
        for (var i = 0; i < modals.length; i++) {
            var modal = modals[i];
            modal.addEventListener('shown.bs.modal', function() {
                inicializarFiltros();
                // Reseta contador para mostrar total de itens visíveis
                var leadId = this.id.replace('modalLead', '');
                var container = document.getElementById('listaImoveis_' + leadId);
                var contador = document.getElementById('contador_' + leadId);
                if (container && contador) {
                    var total = container.querySelectorAll('.list-group-item').length;
                    contador.textContent = total + ' imóveis';
                    // Reseta a busca (limpa o campo)
                    var input = document.getElementById('buscaImovel_' + leadId);
                    if (input) {
                        input.value = '';
                        // Garante que todos os itens fiquem visíveis
                        var itens = container.querySelectorAll('.list-group-item');
                        for (var j = 0; j < itens.length; j++) {
                            itens[j].classList.remove('item-oculto');
                        }
                    }
                }
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
</body>
</html>