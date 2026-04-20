<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Dados padrão para inicializar o formulário
$data = [
    'nome' => '', 
    'email' => '', 
    'telefone' => '', 
    'genero' => '', 
    'tipo_desejo' => 'Compra',
    'fase_funil' => 'Novo', 
    'valor_max' => 0, 
    'quartos_min' => 0,
    'mobiliado' => 0, 
    'preferencia_localizacao' => '',
    'origem_lead' => 'Direto'
];

$imoveis_selecionados = [];

// Se for edição, busca os dados atuais
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $data = $res;
        // Busca IDs dos imóveis vinculados na tabela intermediária
        $stmt_im = $conn->prepare("SELECT imovel_id FROM lead_imoveis WHERE lead_id = ?");
        $stmt_im->execute([$id]);
        $imoveis_selecionados = $stmt_im->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $telefone    = trim($_POST['telefone'] ?? '');
    $genero      = !empty($_POST['genero']) ? $_POST['genero'] : NULL;
    $origem      = $_POST['origem_lead'] ?? 'Direto';
    $tipo_desejo = $_POST['tipo_desejo'] ?? 'Compra';
    $fase_funil  = $_POST['fase_funil'] ?? 'Novo';
    
    // Converte valor formatado para decimal (ex: 1.500,00 -> 1500.00)
    $valor_bruto = $_POST['valor_max_formatado'] ?? '0';
    $valor_max   = (float)str_replace(['.', ','], ['', '.'], $valor_bruto);
    
    $quartos_min = (int)($_POST['quartos_min'] ?? 0);
    $mobiliado   = isset($_POST['mobiliado']) ? 1 : 0;
    $localizacao = trim($_POST['preferencia_localizacao'] ?? '');

    try {
        $conn->beginTransaction();

        if ($id) {
            // UPDATE LEAD
            $sql = "UPDATE leads SET 
                        nome=?, email=?, telefone=?, genero=?, origem_lead=?, 
                        tipo_desejo=?, fase_funil=?, valor_max=?, quartos_min=?, 
                        mobiliado=?, preferencia_localizacao=?, ultima_interacao=NOW(), updated_at=NOW() 
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nome, $email, $telefone, $genero, $origem, 
                $tipo_desejo, $fase_funil, $valor_max, $quartos_min, 
                $mobiliado, $localizacao, $id
            ]);
        } else {
            // INSERT LEAD
            $sql = "INSERT INTO leads 
                    (nome, email, telefone, genero, origem_lead, tipo_desejo, fase_funil, valor_max, quartos_min, mobiliado, preferencia_localizacao, ultima_interacao, created_at) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nome, $email, $telefone, $genero, $origem, 
                $tipo_desejo, $fase_funil, $valor_max, $quartos_min, 
                $mobiliado, $localizacao
            ]);
            $id = $conn->lastInsertId();
        }

        // ATUALIZA VÍNCULOS (TABELA lead_imoveis)
        $conn->prepare("DELETE FROM lead_imoveis WHERE lead_id = ?")->execute([$id]);
        
        if (!empty($_POST['imoveis']) && is_array($_POST['imoveis'])) {
            $stmt_rel = $conn->prepare("INSERT INTO lead_imoveis (lead_id, imovel_id) VALUES (?, ?)");
            foreach ($_POST['imoveis'] as $im_id) {
                $stmt_rel->execute([$id, (int)$im_id]);
            }
        }

        $conn->commit();
        header("Location: leads.php?msg=sucesso");
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <!-- 🔽 TÍTULO MODIFICADO PARA INCLUIR O ID DO LEAD -->
            <h2 class="text-primary fw-bold mb-0">
                <?php if ($id): ?>
                    Editar Lead #<?= $id ?>
                <?php else: ?>
                    Novo Lead
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0">Preencha os dados e vincule múltiplos imóveis.</p>
        </div>
        <a href="leads.php" class="btn btn-outline-secondary shadow-sm">Voltar</a>
    </div>

    <?php if(isset($erro)): ?>
        <div class="alert alert-danger border-start border-4 border-danger"><?= $erro ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Dados Pessoais</div>
                    <div class="card-body row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($data['nome']) ?>" required>
                        </div>
                        <div class="col-md-5">
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

                <div class="card shadow-sm border-0">
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
                            <label class="form-label small fw-bold">Preferência de Localização</label>
                            <input type="text" name="preferencia_localizacao" class="form-control" value="<?= htmlspecialchars($data['preferencia_localizacao']) ?>">
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
                        <label class="form-label fw-bold text-primary">Fase do Funil</label>
                        <select name="fase_funil" class="form-select mb-3 fw-bold">
                            <?php
                            $fases = ["Novo", "Contato Feito", "Tentativa de Contato", "Visita Agendada", "Visita Realizada", "Analisando", "Proposta", "Fechado", "Perdido"];
                            foreach($fases as $f) {
                                $sel = (trim($data['fase_funil']) == $f) ? 'selected' : '';
                                echo "<option value=\"$f\" $sel>$f</option>";
                            }
                            ?>
                        </select>

                        <label class="form-label small fw-bold">Origem do Lead</label>
                        <select name="origem_lead" class="form-select mb-3">
                            <?php 
                            $origens = ["Direto", "Instagram", "Facebook", "Site", "WhatsApp", "ZAP Imóveis", "OLX", "Indicação"];
                            foreach($origens as $o) {
                                $sel = ($data['origem_lead'] == $o) ? 'selected' : '';
                                echo "<option value=\"$o\" $sel>$o</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm">SALVAR LEAD</button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold small">
                        <i class="bi bi-houses me-2"></i>Vincular Imóveis
                    </div>
                    <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;">
                        <?php
                        // Removida a coluna deleted_at que causava o erro
                        $stmt_lista = $conn->query("SELECT id, titulo, bairro FROM imoveis ORDER BY titulo ASC");
                        $imoveis_db = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

                        if (count($imoveis_db) === 0): ?>
                            <div class="p-3 text-muted small">Nenhum imóvel disponível.</div>
                        <?php else: 
                            foreach($imoveis_db as $im):
                                $checked = in_array($im['id'], $imoveis_selecionados) ? 'checked' : '';
                        ?>
                            <label class="list-group-item d-flex align-items-center p-3 border-bottom cursor-pointer hover-bg-light">
                                <input class="form-check-input me-3" type="checkbox" name="imoveis[]" value="<?= $im['id'] ?>" id="im<?= $im['id'] ?>" <?= $checked ?> style="width: 20px; height: 20px;">
                                <div>
                                    <span class="d-block fw-bold small text-dark"><?= htmlspecialchars($im['titulo']) ?></span>
                                    <span class="text-muted small"><?= htmlspecialchars($im['bairro']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; 
                        endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Máscara de moeda simples
document.getElementById('valor_mascara').addEventListener('input', function(e) {
    let val = e.target.value.replace(/\D/g, '');
    if (val === '') val = '0';
    val = (parseInt(val) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    e.target.value = val;
});
</script>

<style>
.hover-bg-light:hover { background-color: #f8f9fa; transition: 0.2s; }
.cursor-pointer { cursor: pointer; }
.list-group-item { border: none; border-bottom: 1px solid #eee; display: flex !important; }
</style>

<?php require_once '../../includes/footer.php'; ?>