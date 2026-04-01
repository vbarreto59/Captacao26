<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

$id = $_GET['id'] ?? null;
$imovel_id = $_GET['imovel_id'] ?? null;

// Dados iniciais vazios
$dados = [
    'tipo' => '',
    'valor' => '',
    'data_despesa' => date('Y-m-d'),
    'descricao' => ''
];

// Se for edição, carrega do banco
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM despesas WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $dados = $res;
        $imovel_id = $res['imovel_id'];
    }
}

// Salvar dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $data_despesa = $_POST['data_despesa'];
    $descricao = $_POST['descricao'];

    if ($id) {
        $sql = "UPDATE despesas SET tipo=?, valor=?, data_despesa=?, descricao=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$tipo, $valor, $data_despesa, $descricao, $id]);
    } else {
        $sql = "INSERT INTO despesas (imovel_id, tipo, valor, data_despesa, descricao) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$imovel_id, $tipo, $valor, $data_despesa, $descricao]);
    }

    header("Location: list.php?id=" . $imovel_id);
    exit;
}

require_once '../../includes/header.php';
?>

<div class="container py-4">
    <div class="card shadow border-0 col-md-8 mx-auto">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?= $id ? 'Editar Despesa' : 'Nova Despesa' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Tipo de Despesa</label>
                    <input type="text" name="tipo" class="form-control" value="<?= htmlspecialchars($dados['tipo']) ?>" placeholder="Ex: IPTU, Reforma, Limpeza" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" name="valor" class="form-control" value="<?= $dados['valor'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data_despesa" class="form-control" value="<?= $dados['data_despesa'] ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição/Observações</label>
                    <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($dados['descricao']) ?></textarea>
                </div>
                <div class="text-end">
                    <a href="list.php?id=<?= $imovel_id ?>" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>