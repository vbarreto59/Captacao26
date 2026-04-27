<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$msg_sucesso = false;

// ==========================================
// BUSCAR PRÓXIMO ID (AUTO_INCREMENT)
// ==========================================
$proximo_id_bruto = "?";
$proximo_id_formatado = "?";
try {
    $stmt_status = $conn->query("SHOW TABLE STATUS LIKE 'leads'");
    $status = $stmt_status->fetch(PDO::FETCH_ASSOC);
    $proximo_id_bruto = $status['Auto_increment'];
    $proximo_id_formatado = "L" . str_pad($proximo_id_bruto, 3, '0', STR_PAD_LEFT);
} catch (Exception $e) {}

// ==========================================
// LÓGICA DE NAVEGAÇÃO
// ==========================================
$first_id = null; $prev_id = null; $next_id = null; $last_id = null;
try {
    $first_id = $conn->query("SELECT MIN(id) FROM leads")->fetchColumn();
    $last_id  = $conn->query("SELECT MAX(id) FROM leads")->fetchColumn();
} catch (Exception $e) {}

if ($id) {
    $stmt_prev = $conn->prepare("SELECT id FROM leads WHERE id < ? ORDER BY id DESC LIMIT 1");
    $stmt_prev->execute([$id]);
    $prev_id = $stmt_prev->fetchColumn();

    $stmt_next = $conn->prepare("SELECT id FROM leads WHERE id > ? ORDER BY id ASC LIMIT 1");
    $stmt_next->execute([$id]);
    $next_id = $stmt_next->fetchColumn();
}

// Dados iniciais
$data = [
    'nome' => '', 
    'primeiro_nome' => '', 
    'email' => '', 
    'telefone' => '', 
    'genero' => '', 
    'tipo_desejo' => 'Compra',
    'fase_funil' => 'Novo', 
    'valor_max' => 0, 
    'quartos_min' => 0,
    'mobiliado' => 0, 
    'preferencia_localizacao' => '',
    'origem_lead' => 'Direto',
    'observacoes' => ''
];

$imoveis_selecionados = [];

// Carregar dados se ID existir
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $data = $res;
        $stmt_im = $conn->prepare("SELECT imovel_id FROM lead_imoveis WHERE lead_id = ?");
        $stmt_im->execute([$id]);
        $imoveis_selecionados = $stmt_im->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ==========================================
// PROCESSAMENTO SALVAR
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome          = trim($_POST['nome'] ?? '');
    $primeiro_nome = trim($_POST['primeiro_nome'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $telefone      = trim($_POST['telefone'] ?? '');
    $genero        = !empty($_POST['genero']) ? $_POST['genero'] : NULL;
    $origem        = $_POST['origem_lead'] ?? 'Direto';
    $tipo_desejo   = $_POST['tipo_desejo'] ?? 'Compra';
    $fase_funil    = $_POST['fase_funil'] ?? 'Novo';
    $observacoes   = trim($_POST['observacoes'] ?? '');
    
    $valor_bruto   = $_POST['valor_max_formatado'] ?? '0';
    $valor_max     = (float)str_replace(['.', ','], ['', '.'], $valor_bruto);
    
    $quartos_min   = (int)($_POST['quartos_min'] ?? 0);
    $mobiliado     = isset($_POST['mobiliado']) ? 1 : 0;
    $localizacao   = trim($_POST['preferencia_localizacao'] ?? '');

    try {
        $conn->beginTransaction();

        if ($id) {
            $sql = "UPDATE leads SET 
                        nome=?, primeiro_nome=?, email=?, telefone=?, genero=?, origem_lead=?, 
                        tipo_desejo=?, fase_funil=?, valor_max=?, quartos_min=?, 
                        mobiliado=?, preferencia_localizacao=?, observacoes=?, updated_at=NOW() 
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $primeiro_nome, $email, $telefone, $genero, $origem, $tipo_desejo, $fase_funil, $valor_max, $quartos_min, $mobiliado, $localizacao, $observacoes, $id]);
        } else {
            $sql = "INSERT INTO leads (nome, primeiro_nome, email, telefone, genero, origem_lead, tipo_desejo, fase_funil, valor_max, quartos_min, mobiliado, preferencia_localizacao, observacoes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $primeiro_nome, $email, $telefone, $genero, $origem, $tipo_desejo, $fase_funil, $valor_max, $quartos_min, $mobiliado, $localizacao, $observacoes]);
            $id = $conn->lastInsertId();
            
            $nomeComID = "L" . str_pad($id, 3, '0', STR_PAD_LEFT) . "-" . $nome;
            $conn->prepare("UPDATE leads SET nome = ? WHERE id = ?")->execute([$nomeComID, $id]);
        }

        $conn->prepare("DELETE FROM lead_imoveis WHERE lead_id = ?")->execute([$id]);
        if (!empty($_POST['imoveis'])) {
            foreach ($_POST['imoveis'] as $im_id) {
                $conn->prepare("INSERT INTO lead_imoveis (lead_id, imovel_id) VALUES (?, ?)")->execute([$id, (int)$im_id]);
            }
        }

        $conn->commit();
        // Redireciona para a mesma página com o ID (evita reenvio de formulário ao dar F5)
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id . "&status=saved");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $erro = "Erro: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
?>

<div class="container pb-5">
    <?php if (isset($_GET['status']) && $_GET['status'] == 'saved'): ?>
        <div id="alert-success" class="alert alert-success border-0 shadow-sm mt-3 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i> Alterações salvas com sucesso!
        </div>
        <script>
            setTimeout(() => { 
                const alert = document.getElementById('alert-success');
                if(alert) alert.style.display = 'none';
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="row mt-3">
        <div class="col-12">
            <div class="alert alert-light border shadow-sm d-flex justify-content-between align-items-center py-2">
                <span class="text-muted small fw-bold text-uppercase"><i class="bi bi-database-fill-gear me-1"></i> Status do Sistema</span>
                <span class="badge bg-dark">Próximo Código: <?= $proximo_id_formatado ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h2 class="text-primary fw-bold mb-0">
                    <?= $id ? "Editar Lead #$id" : "Novo Lead" ?>
                </h2>
                <p class="text-muted mb-0 small">Cadastre ou atualize as informações do cliente.</p>
            </div>

            <?php if ($id): ?>
            <div class="btn-group shadow-sm ms-2">
                <a href="?id=<?= $first_id ?>" class="btn btn-outline-secondary btn-sm <?= ($id <= $first_id) ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-left"></i></a>
                <a href="?id=<?= $prev_id ?>" class="btn btn-outline-secondary btn-sm <?= !$prev_id ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
                <a href="?id=<?= $next_id ?>" class="btn btn-outline-secondary btn-sm <?= !$next_id ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
                <a href="?id=<?= $last_id ?>" class="btn btn-outline-secondary btn-sm <?= ($id >= $last_id) ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="d-flex gap-2">
            <?php if($id): ?>
                <a href="lead_view.php?id=<?= $id ?>" class="btn btn-info btn-sm text-white px-3 fw-bold shadow-sm">Ver Lead</a>
            <?php endif; ?>
            <a href="leads.php" class="btn btn-outline-secondary shadow-sm btn-sm px-3">Voltar à Lista</a>
        </div>
    </div>

    <?php if(isset($erro)): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Dados Pessoais</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Primeiro Nome</label>
                            <input type="text" name="primeiro_nome" class="form-control" value="<?= htmlspecialchars($data['primeiro_nome']) ?>" placeholder="Ex: João">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($data['nome']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Gênero</label>
                            <select name="genero" class="form-select">
                                <option value="">Não informado</option>
                                <option value="Masculino" <?= $data['genero']=='Masculino'?'selected':'' ?>>Masculino</option>
                                <option value="Feminino" <?= $data['genero']=='Feminino'?'selected':'' ?>>Feminino</option>
                                <option value="Outro" <?= $data['genero']=='Outro'?'selected':'' ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">WhatsApp / Telefone *</label>
                            <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($data['telefone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white fw-bold"><i class="bi bi-search me-2"></i>Perfil de Interesse</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Desejo</label>
                            <select name="tipo_desejo" class="form-select">
                                <option value="Compra" <?= $data['tipo_desejo']=='Compra'?'selected':'' ?>>Compra</option>
                                <option value="Aluguel" <?= $data['tipo_desejo']=='Aluguel'?'selected':'' ?>>Aluguel</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Valor Máx (R$)</label>
                            <input type="text" id="valor_mascara" name="valor_max_formatado" class="form-control" value="<?= number_format($data['valor_max'], 2, ',', '.') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Mín. Quartos</label>
                            <input type="number" name="quartos_min" class="form-control" value="<?= $data['quartos_min'] ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Localização Preferencial</label>
                            <input type="text" name="preferencia_localizacao" class="form-control" value="<?= htmlspecialchars($data['preferencia_localizacao']) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-danger">Observações / Notas do Lead</label>
                            <textarea name="observacoes" class="form-control" rows="4"><?= htmlspecialchars($data['observacoes']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch bg-light p-2 rounded">
                                <input class="form-check-input ms-1 me-2" type="checkbox" name="mobiliado" id="mob" <?= $data['mobiliado'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="mob">Exige Mobiliado</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-3 border-start border-4 border-primary">
                    <div class="card-body">
                        <label class="form-label fw-bold text-primary">Status Funil</label>
                        <select name="fase_funil" class="form-select mb-3 fw-bold">
                            <?php
                            $fases = ["Novo", "Contato Feito", "Tentativa de Contato", "Visita Agendada", "Visita Realizada", "Analisando", "Proposta", "Fechado", "Perdido"];
                            foreach($fases as $f) {
                                $sel = ($data['fase_funil'] == $f) ? 'selected' : '';
                                echo "<option value=\"$f\" $sel>$f</option>";
                            }
                            ?>
                        </select>
                        <label class="form-label small fw-bold">Origem</label>
                        <select name="origem_lead" class="form-select mb-3">
                            <?php 
                            $origens = ["Direto", "Instagram", "Facebook", "Site", "WhatsApp", "ZAP Imóveis", "OLX", "Indicação"];
                            foreach($origens as $o) {
                                $sel = ($data['origem_lead'] == $o) ? 'selected' : '';
                                echo "<option value=\"$o\" $sel>$o</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm">SALVAR ALTERAÇÕES</button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold small">Vincular Imóveis</div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $imoveis = $conn->query("SELECT id, titulo, bairro FROM imoveis ORDER BY titulo ASC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach($imoveis as $im):
                            $checked = in_array($im['id'], $imoveis_selecionados) ? 'checked' : '';
                        ?>
                            <label class="list-group-item d-flex align-items-center p-3 border-bottom cursor-pointer">
                                <input class="form-check-input me-3" type="checkbox" name="imoveis[]" value="<?= $im['id'] ?>" <?= $checked ?>>
                                <div>
                                    <span class="d-block fw-bold small"><?= htmlspecialchars($im['titulo']) ?></span>
                                    <span class="text-muted small"><?= htmlspecialchars($im['bairro']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('valor_mascara').addEventListener('input', function(e) {
    let val = e.target.value.replace(/\D/g, '');
    if (val === '') val = '0';
    val = (parseInt(val) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    e.target.value = val;
});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.list-group-item:hover { background-color: #f8f9fa; }
</style>

<?php require_once '../../includes/footer.php'; ?>