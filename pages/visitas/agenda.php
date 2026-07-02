<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// Define fuso horário para Recife
date_default_timezone_set('America/Recife');
$hoje_inicio = date('Y-m-d 00:00:00');

// ==========================================
// FUNÇÃO PARA COMPOR NOME DO LEAD
// ==========================================
function comporNomeLead($nome, $primeiro_nome) {
    if (empty($primeiro_nome)) {
        return $nome;
    }
    $prefixo = substr($nome, 0, 5);
    return $prefixo . ' ' . $primeiro_nome;
}

// ==========================================
// FUNÇÃO PARA FORMATAR DATA COM DIA DA SEMANA
// ==========================================
function formatarDataComDia($data) {
    $dias_semana = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'];
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp) . ' (' . $dias_semana[date('w', $timestamp)] . ')';
}

// ==========================================
// VERIFICA E CRIA COLUNAS (se não existirem)
// ==========================================
try {
    $conn->exec("ALTER TABLE agenda_geral ADD COLUMN IF NOT EXISTS lead_id INT(11) DEFAULT NULL");
    $conn->exec("ALTER TABLE agenda_geral ADD COLUMN IF NOT EXISTS imovel_id INT(11) DEFAULT NULL");
    $conn->exec("ALTER TABLE agenda_geral ADD COLUMN IF NOT EXISTS corretor_id INT(11) DEFAULT NULL");
} catch (PDOException $e) {
    // colunas já existem ou erro de permissão – ignorar
}

// ==========================================
// FILTRO POR CORRETOR
// ==========================================
$filtro_corretor = isset($_GET['corretor_id']) && $_GET['corretor_id'] > 0 ? (int)$_GET['corretor_id'] : null;

// ==========================================
// LÓGICA DE PROCESSAMENTO (POST/GET)
// ==========================================

// 0. ENVIAR AGENDA POR EMAIL
if (isset($_POST['enviar_email_agenda'])) {
    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
    $subject = "Agenda Geral - " . strtoupper($_SESSION['Usuario'] ?? 'SISTEMA') . " - " . date('d/m/Y');
    
    $sql_email = "
        SELECT a.data_evento, a.titulo COLLATE utf8mb4_unicode_ci AS titulo, 
               a.descricao COLLATE utf8mb4_unicode_ci AS descricao,
               CONCAT_WS(' - ', c.nome COLLATE utf8mb4_unicode_ci, 
                   CONCAT(SUBSTRING(l.nome, 1, 5), ' ', l.primeiro_nome), 
                   i.titulo COLLATE utf8mb4_unicode_ci, 
                   i.endereco COLLATE utf8mb4_unicode_ci, 
                   i.preco) as associados
        FROM agenda_geral a
        LEFT JOIN corretores c ON a.corretor_id = c.id
        LEFT JOIN leads l ON a.lead_id = l.id
        LEFT JOIN imoveis i ON a.imovel_id = i.id
        WHERE a.status = 'Pendente'
        UNION ALL
        SELECT v.data_visita as data_evento, 
               CONCAT('VISITA: ', v.visitante) COLLATE utf8mb4_unicode_ci AS titulo, 
               v.descricao COLLATE utf8mb4_unicode_ci AS descricao, 
               NULL as associados
        FROM visitas v WHERE v.status = 'pendente' AND v.data_visita >= ?
        ORDER BY data_evento ASC";
    
    $stmt_email = $conn->prepare($sql_email);
    $stmt_email->execute([$hoje_inicio]);
    $compromissos = $stmt_email->fetchAll(PDO::FETCH_ASSOC);
    
    $message = "AGENDA GERAL - RESUMO DE PENDÊNCIAS\n";
    $message .= "Data: " . date('d/m/Y H:i:s') . "\n------------------------------------------\n\n";
    
    if (empty($compromissos)) {
        $message .= "Nenhum compromisso pendente.";
    } else {
        foreach ($compromissos as $item) {
            // Data com dia da semana
            $data_formatada = formatarDataComDia($item['data_evento']);
            $message .= "Data: " . $data_formatada . "\n";
            $message .= "Título: " . $item['titulo'] . "\n";
            if (!empty($item['associados'])) {
                $message .= "Associados: " . $item['associados'] . "\n";
            }
            $message .= "Descrição: " . ($item['descricao'] ?: "-") . "\n";
            $message .= "------------------------------------------\n";
        }
    }
    
    $headers = "From: sendmail@gabnetweb.com.br\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    mail($to, $subject, $message, $headers);
    header("Location: agenda.php?msg=email_ok");
    exit;
}

// 1. INCLUIR (Agenda Geral)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_compromisso'])) {
    $corretor_nome = '';
    $corretor_id = !empty($_POST['corretor_id']) ? (int)$_POST['corretor_id'] : null;
    if ($corretor_id) {
        $stmt_c = $conn->prepare("SELECT nome FROM corretores WHERE id = ?");
        $stmt_c->execute([$corretor_id]);
        $corretor = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if ($corretor) {
            $corretor_nome = $corretor['nome'] . ' - ';
        }
    }
    
    $titulo_final = $corretor_nome . trim($_POST['titulo']);
    
    $stmt = $conn->prepare("INSERT INTO agenda_geral (titulo, descricao, data_evento, categoria, lead_id, imovel_id, corretor_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $titulo_final,
        trim($_POST['descricao']),
        $_POST['data_evento'],
        $_POST['categoria'],
        !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null,
        !empty($_POST['imovel_id']) ? (int)$_POST['imovel_id'] : null,
        $corretor_id
    ]);
    header("Location: agenda.php?msg=add_ok");
    exit;
}

// 2. EDITAR (Agenda Geral)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_compromisso'])) {
    $corretor_nome = '';
    $corretor_id = !empty($_POST['corretor_id']) ? (int)$_POST['corretor_id'] : null;
    if ($corretor_id) {
        $stmt_c = $conn->prepare("SELECT nome FROM corretores WHERE id = ?");
        $stmt_c->execute([$corretor_id]);
        $corretor = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if ($corretor) {
            $corretor_nome = $corretor['nome'] . ' - ';
        }
    }
    
    $titulo_final = $corretor_nome . trim($_POST['titulo']);
    
    $stmt = $conn->prepare("UPDATE agenda_geral SET titulo=?, descricao=?, data_evento=?, categoria=?, status=?, lead_id=?, imovel_id=?, corretor_id=? WHERE id=?");
    $stmt->execute([
        $titulo_final,
        trim($_POST['descricao']),
        $_POST['data_evento'],
        $_POST['categoria'],
        $_POST['status'],
        !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null,
        !empty($_POST['imovel_id']) ? (int)$_POST['imovel_id'] : null,
        $corretor_id,
        (int)$_POST['id']
    ]);
    header("Location: agenda.php?msg=edit_ok");
    exit;
}

// 3. EXCLUIR UNIFICADO (Agenda ou Visitas)
if (isset($_GET['excluir']) && isset($_GET['origem'])) {
    $id = (int)$_GET['excluir'];
    $origem = $_GET['origem'];

    if ($origem === 'agenda') {
        $stmt = $conn->prepare("DELETE FROM agenda_geral WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($origem === 'visitas') {
        $stmt = $conn->prepare("DELETE FROM visitas WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    header("Location: agenda.php?msg=del_ok");
    exit;
}

// ==========================================
// BUSCA DE DADOS UNIFICADA (com JOINS e FILTRO)
// ==========================================
$sql_agenda = "
    SELECT 
        a.id, 
        a.data_evento, 
        a.titulo COLLATE utf8mb4_unicode_ci AS titulo, 
        a.descricao COLLATE utf8mb4_unicode_ci AS descricao, 
        a.status, 
        a.categoria COLLATE utf8mb4_unicode_ci AS categoria, 
        'agenda' as origem,
        l.nome COLLATE utf8mb4_unicode_ci AS lead_nome,
        l.primeiro_nome COLLATE utf8mb4_unicode_ci AS lead_primeiro_nome,
        i.titulo COLLATE utf8mb4_unicode_ci AS imovel_titulo,
        i.endereco COLLATE utf8mb4_unicode_ci AS imovel_endereco,
        i.preco as imovel_preco,
        c.nome COLLATE utf8mb4_unicode_ci AS corretor_nome
    FROM agenda_geral a
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN imoveis i ON a.imovel_id = i.id
    LEFT JOIN corretores c ON a.corretor_id = c.id
";

if ($filtro_corretor) {
    $sql_agenda .= " WHERE a.corretor_id = :corretor_id";
} else {
    $sql_agenda .= " WHERE 1=1";
}

$sql_visitas = "
    SELECT 
        v.id, 
        v.data_visita as data_evento, 
        v.visitante COLLATE utf8mb4_unicode_ci AS titulo, 
        v.descricao COLLATE utf8mb4_unicode_ci AS descricao, 
        CASE 
            WHEN v.status = 'pendente' THEN 'Pendente' 
            WHEN v.status = 'concluido' THEN 'Concluído' 
            ELSE 'Cancelado' 
        END COLLATE utf8mb4_unicode_ci AS status, 
        'Visita' COLLATE utf8mb4_unicode_ci AS categoria, 
        'visitas' as origem,
        NULL AS lead_nome,
        NULL AS lead_primeiro_nome,
        NULL AS imovel_titulo,
        NULL AS imovel_endereco,
        NULL AS imovel_preco,
        NULL AS corretor_nome
    FROM visitas v
    WHERE v.data_visita >= :hoje
";

$sql_unificado = $sql_agenda . " UNION ALL " . $sql_visitas . " ORDER BY data_evento ASC";

$stmt = $conn->prepare($sql_unificado);
if ($filtro_corretor) {
    $stmt->bindParam(':corretor_id', $filtro_corretor, PDO::PARAM_INT);
}
$stmt->bindParam(':hoje', $hoje_inicio);
$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// GERAR MENSAGEM PARA O WHATSAPP (TODOS OS EVENTOS)
// ==========================================
$whatsapp_mensagem_completa = "📅 *AGENDA GERAL - " . date('d/m/Y') . "*\n";
$whatsapp_mensagem_completa .= "========================================\n\n";

if (empty($lista)) {
    $whatsapp_mensagem_completa .= "Nenhum compromisso agendado para hoje ou futuro.";
} else {
    foreach ($lista as $c) {
        $data_formatada = formatarDataComDia($c['data_evento']);
        $whatsapp_mensagem_completa .= "📌 *" . $data_formatada . "*\n";
        $whatsapp_mensagem_completa .= "   Título: " . htmlspecialchars($c['titulo']) . "\n";
        if (!empty($c['corretor_nome'])) {
            $whatsapp_mensagem_completa .= "   Corretor: " . htmlspecialchars($c['corretor_nome']) . "\n";
        }
        if (!empty($c['lead_nome'])) {
            $lead_composto = comporNomeLead($c['lead_nome'], $c['lead_primeiro_nome']);
            $whatsapp_mensagem_completa .= "   Lead: " . htmlspecialchars($lead_composto) . "\n";
        }
        if (!empty($c['imovel_titulo'])) {
            $whatsapp_mensagem_completa .= "   Imóvel: " . htmlspecialchars($c['imovel_titulo']);
            if (!empty($c['imovel_endereco'])) {
                $whatsapp_mensagem_completa .= " - " . htmlspecialchars($c['imovel_endereco']);
            }
            if (!empty($c['imovel_preco'])) {
                $whatsapp_mensagem_completa .= " - R$ " . number_format($c['imovel_preco'], 0, ',', '.');
            }
            $whatsapp_mensagem_completa .= "\n";
        }
        if (!empty($c['descricao'])) {
            $whatsapp_mensagem_completa .= "   Obs: " . htmlspecialchars($c['descricao']) . "\n";
        }
        $whatsapp_mensagem_completa .= "----------------------------------------\n";
    }
}
$whatsapp_msg_js = addslashes($whatsapp_mensagem_completa);

// ==========================================
// CARREGAR LEADS, IMÓVEIS E CORRETORES PARA OS SELECTS
// ==========================================
$leads = $conn->query("SELECT id, nome, primeiro_nome FROM leads WHERE deleted_at IS NULL ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$imoveis = $conn->query("SELECT id, titulo, endereco, preco FROM imoveis WHERE deleted_at IS NULL ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC);
$corretores = $conn->query("SELECT id, nome FROM corretores WHERE status = 'Ativo' AND deleted_at IS NULL ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-primary mb-0">
                <i class="bi bi-calendar3 me-2"></i>Agenda Geral
                <?php if ($filtro_corretor): 
                    $nome_corretor = '';
                    foreach ($corretores as $c) {
                        if ($c['id'] == $filtro_corretor) {
                            $nome_corretor = $c['nome'];
                            break;
                        }
                    }
                ?>
                    <span class="badge bg-info text-dark ms-2">Filtro: <?= htmlspecialchars($nome_corretor) ?></span>
                <?php endif; ?>
            </h2>
            <p class="text-muted small mb-0">Compromissos manuais e Visitas (Hoje + Futuro) – com Lead, Imóvel e Corretor</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- FILTRO POR CORRETOR -->
            <form method="get" class="d-flex gap-2 align-items-center">
                <label for="filtro_corretor" class="fw-semibold small">Corretor:</label>
                <select name="corretor_id" id="filtro_corretor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($corretores as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filtro_corretor == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filtro_corretor): ?>
                    <a href="agenda.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
                <?php endif; ?>
            </form>

            <form method="post">
                <button type="submit" name="enviar_email_agenda" class="btn btn-outline-secondary shadow-sm">
                    <i class="bi bi-envelope"></i> Resumo E-mail
                </button>
            </form>
            <!-- Botão WhatsApp único -->
            <button class="btn btn-success shadow-sm" onclick="copyWhatsApp()" data-mensagem="<?= $whatsapp_msg_js ?>">
                <i class="bi bi-whatsapp"></i> Copiar Tudo WhatsApp
            </button>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
                <i class="bi bi-plus-lg"></i> Novo Compromisso
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Data / Hora</th>
                            <th>Categoria</th>
                            <th>Compromisso (Lead / Imóvel / Corretor)</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $c): 
                            $cor = ($c['status'] == 'Concluído') ? 'success' : (($c['status'] == 'Cancelado') ? 'danger' : 'warning');
                            $is_visita = ($c['origem'] == 'visitas');
                            $lead_nome = $c['lead_nome'] ?? '';
                            $lead_primeiro_nome = $c['lead_primeiro_nome'] ?? '';
                            $lead_composto = comporNomeLead($lead_nome, $lead_primeiro_nome);
                            $imovel_titulo = $c['imovel_titulo'] ?? '';
                            $imovel_endereco = $c['imovel_endereco'] ?? '';
                            $imovel_preco = $c['imovel_preco'] ?? '';
                            $corretor_nome = $c['corretor_nome'] ?? '';
                            $data_formatada = formatarDataComDia($c['data_evento']);
                            
                            $associados = [];
                            if ($corretor_nome) $associados[] = '<i class="bi bi-person-badge"></i> ' . htmlspecialchars($corretor_nome);
                            if ($lead_nome) {
                                $associados[] = '<i class="bi bi-person"></i> ' . htmlspecialchars($lead_composto);
                            }
                            if ($imovel_titulo) {
                                $imovel_info = '<i class="bi bi-house"></i> ' . htmlspecialchars($imovel_titulo);
                                if ($imovel_endereco) $imovel_info .= ' <span class="text-muted">(' . htmlspecialchars($imovel_endereco) . ')</span>';
                                if ($imovel_preco) $imovel_info .= ' <span class="fw-bold text-success">R$ ' . number_format($imovel_preco, 0, ',', '.') . '</span>';
                                $associados[] = $imovel_info;
                            }
                            $associados_html = !empty($associados) ? '<div class="small text-muted">' . implode(' &bull; ', $associados) . '</div>' : '';
                        ?>
                        <tr class="<?= $is_visita ? 'table-info' : '' ?>" style="<?= $is_visita ? '--bs-table-bg: #f0f8ff;' : '' ?>">
                            <td class="ps-3">
                                <div class="fw-bold"><?= $data_formatada ?></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $is_visita ? 'bg-info text-dark' : 'bg-secondary' ?> px-3">
                                    <i class="bi <?= $is_visita ? 'bi-person' : 'bi-tag' ?> me-1"></i><?= $c['categoria'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= $is_visita ? '<span class="text-primary small text-uppercase">Visita:</span> ' : '' ?>
                                    <?= htmlspecialchars($c['titulo']) ?>
                                </div>
                                <?= $associados_html ?>
                                <div class="small text-muted text-truncate" style="max-width: 350px;">
                                    <?= !empty($c['descricao']) ? htmlspecialchars($c['descricao']) : '<span class="opacity-50 italic small">- sem descrição -</span>' ?>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= $cor ?>"><?= $c['status'] ?></span></td>
                            <td class="text-end pe-3">
                                <div class="btn-group shadow-sm">
                                    <?php if (!$is_visita): ?>
                                        <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $c['id'] ?>" title="Editar"><i class="bi bi-pencil text-primary"></i></button>
                                    <?php else: ?>
                                        <a href="../visitas/visitas.php" class="btn btn-sm btn-light border" title="Ver Visitas"><i class="bi bi-eye text-info"></i></a>
                                    <?php endif; ?>
                                    
                                    <a href="?excluir=<?= $c['id'] ?>&origem=<?= $c['origem'] ?>" 
                                       class="btn btn-sm btn-light border text-danger" 
                                       onclick="return confirm('Tem certeza que deseja excluir este registro permanente?')" 
                                       title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL ADICIONAR -->
<!-- ========================================== -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Novo Compromisso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Título</label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Digite o título do compromisso">
                    <small class="text-muted">O nome do corretor será adicionado automaticamente antes do título.</small>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small fw-bold">Data/Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select">
                            <option>Administrativo</option>
                            <option>Pessoal</option>
                            <option>Outros</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Corretor Parceiro (opcional)</label>
                    <select name="corretor_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($corretores as $corretor): ?>
                            <option value="<?= $corretor['id'] ?>"><?= htmlspecialchars($corretor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Lead (opcional)</label>
                    <select name="lead_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($leads as $lead): 
                            $lead_exibicao = comporNomeLead($lead['nome'], $lead['primeiro_nome']);
                        ?>
                            <option value="<?= $lead['id'] ?>"><?= htmlspecialchars($lead_exibicao) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Imóvel (opcional)</label>
                    <select name="imovel_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($imoveis as $imovel): ?>
                            <option value="<?= $imovel['id'] ?>">
                                <?= htmlspecialchars($imovel['titulo']) ?>
                                <?php if ($imovel['endereco']): ?> - <?= htmlspecialchars($imovel['endereco']) ?><?php endif; ?>
                                <?php if ($imovel['preco']): ?> - R$ <?= number_format($imovel['preco'], 0, ',', '.') ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_compromisso" class="btn btn-primary w-100">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAIS EDITAR (dinâmicos) -->
<!-- ========================================== -->
<?php foreach ($lista as $c): if($c['origem'] == 'agenda'): 
    // Recupera lead_id, imovel_id e corretor_id atuais
    $stmt = $conn->prepare("SELECT lead_id, imovel_id, corretor_id FROM agenda_geral WHERE id = ?");
    $stmt->execute([$c['id']]);
    $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
    $lead_id_atual = $assoc['lead_id'] ?? null;
    $imovel_id_atual = $assoc['imovel_id'] ?? null;
    $corretor_id_atual = $assoc['corretor_id'] ?? null;
    
    // Remove o prefixo do corretor do título para exibir no campo de edição
    $titulo_sem_corretor = $c['titulo'];
    if ($corretor_id_atual) {
        $stmt_c = $conn->prepare("SELECT nome FROM corretores WHERE id = ?");
        $stmt_c->execute([$corretor_id_atual]);
        $corretor = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if ($corretor) {
            $prefixo = $corretor['nome'] . ' - ';
            if (strpos($titulo_sem_corretor, $prefixo) === 0) {
                $titulo_sem_corretor = substr($titulo_sem_corretor, strlen($prefixo));
            }
        }
    }
?>
<div class="modal fade" id="modalEdit<?= $c['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Editar Compromisso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Título (sem o nome do corretor)</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($titulo_sem_corretor) ?>" required>
                    <small class="text-muted">O nome do corretor será adicionado automaticamente antes.</small>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label small fw-bold">Data/Hora</label>
                        <input type="datetime-local" name="data_evento" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($c['data_evento'])) ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Pendente" <?= $c['status'] == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="Concluído" <?= $c['status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                            <option value="Cancelado" <?= $c['status'] == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Corretor Parceiro (opcional)</label>
                    <select name="corretor_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($corretores as $corretor): ?>
                            <option value="<?= $corretor['id'] ?>" <?= ($corretor['id'] == $corretor_id_atual) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($corretor['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Lead (opcional)</label>
                    <select name="lead_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($leads as $lead): 
                            $lead_exibicao = comporNomeLead($lead['nome'], $lead['primeiro_nome']);
                        ?>
                            <option value="<?= $lead['id'] ?>" <?= ($lead['id'] == $lead_id_atual) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lead_exibicao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Imóvel (opcional)</label>
                    <select name="imovel_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php foreach ($imoveis as $imovel): ?>
                            <option value="<?= $imovel['id'] ?>" <?= ($imovel['id'] == $imovel_id_atual) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($imovel['titulo']) ?>
                                <?php if ($imovel['endereco']): ?> - <?= htmlspecialchars($imovel['endereco']) ?><?php endif; ?>
                                <?php if ($imovel['preco']): ?> - R$ <?= number_format($imovel['preco'], 0, ',', '.') ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($c['descricao']) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_compromisso" class="btn btn-primary w-100">Atualizar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; endforeach; ?>

<!-- ========================================== -->
<!-- SCRIPT PARA COPIAR WHATSAPP -->
<!-- ========================================== -->
<script>
function copyWhatsApp() {
    var btn = document.querySelector('button[data-mensagem]');
    var msg = btn.getAttribute('data-mensagem');
    var textarea = document.createElement('textarea');
    textarea.value = msg;
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Mensagem copiada para a área de transferência! Cole no WhatsApp.');
    } catch (err) {
        alert('Falha ao copiar. Tente novamente.');
    }
    document.body.removeChild(textarea);
}
</script>

<?php require_once '../../includes/footer.php'; ?>