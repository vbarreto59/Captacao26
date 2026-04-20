<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar' : 'Novo';

$proprietario = [
    'nome'     => '',
    'telefone' => '',
    'email'    => '',
    'cpf'      => '',
    'endereco' => ''
];

$erro    = '';
$sucesso = '';

// Carrega dados se for edição
if ($id > 0) {
    $stmt = $conn->prepare("SELECT nome, telefone, email, cpf, endereco FROM proprietarios WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $proprietario = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if (empty($nome)) {
        $erro = "O campo **Nome** é obrigatório.";
    } else {
        try {
            $conn->beginTransaction();

            if ($id > 0) {
                // UPDATE
                $sql = "UPDATE proprietarios SET nome = ?, telefone = ?, email = ?, cpf = ?, endereco = ?, updated_at = NOW() WHERE id = ?";
                $conn->prepare($sql)->execute([$nome, $telefone, $email, $cpf, $endereco, $id]);
                $sucesso = "Dados atualizados com sucesso!";
            } else {
                // INSERT
                $sql = "INSERT INTO proprietarios (nome, telefone, email, cpf, endereco, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $conn->prepare($sql)->execute([$nome, $telefone, $email, $cpf, $endereco]);
                $id = $conn->lastInsertId();
                
                // Redirecionamos após o commit para evitar reenvio de formulário
                $finalizar_novo = true;
            }

            $conn->commit();

            if (isset($finalizar_novo)) {
                header("Location: list.php?msg=sucesso");
                exit;
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            // Se o erro for a Foreign Key do Log, vamos avisar de forma amigável
            if (str_contains($e->getMessage(), '1452')) {
                $erro = "Erro técnico: O sistema de log tentou vincular este proprietário a um imóvel inexistente. O cadastro foi cancelado para evitar erros no banco.";
            } else {
                $erro = "Erro ao salvar: " . $e->getMessage();
            }
        }
    }
    $proprietario = ['nome'=>$nome, 'telefone'=>$telefone, 'email'=>$email, 'cpf'=>$cpf, 'endereco'=>$endereco];
}

require_once '../../includes/header.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-primary fw-bold mb-0"><?= $modo ?> Proprietário</h2>
            <small class="text-muted">Preencha os dados de contato do proprietário</small>
        </div>
        <a href="list.php" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger shadow-sm border-start border-4 border-danger">
            <i class="bi bi-x-octagon-fill me-2"></i> <?= $erro ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success shadow-sm border-start border-4 border-success">
            <i class="bi bi-check-lg me-2"></i> <?= $sucesso ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold small">Nome Completo *</label>
                    <input type="text" name="nome" class="form-control form-control-lg" value="<?= htmlspecialchars($proprietario['nome']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold small">WhatsApp / Telefone</label>
                    <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($proprietario['telefone']) ?>" placeholder="(81) 9.0000-0000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold small">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($proprietario['email']) ?>" placeholder="email@exemplo.com">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold small">CPF</label>
                    <input type="text" name="cpf" class="form-control" value="<?= htmlspecialchars($proprietario['cpf']) ?>" placeholder="000.000.000-00">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold small">Endereço Residencial</label>
                    <textarea name="endereco" class="form-control" rows="2"><?= htmlspecialchars($proprietario['endereco']) ?></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <hr>
                    <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
                        <i class="bi bi-save me-2"></i>SALVAR PROPRIETÁRIO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>