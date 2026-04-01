<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Determina se é edição ou novo cadastro
// ================================================
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar' : 'Nova';

$visita = [
    'imovel_id'    => '',
    'data_visita'  => date('Y-m-d\TH:i'),  // data e hora atual
    'visitante'    => '',
    'observacoes'  => ''
];

$erro    = '';
$sucesso = '';

// Carrega dados se for edição
if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT imovel_id, data_visita, visitante, observacoes 
        FROM visitas 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $visita = $row;
        // Formata data para input datetime-local
        $visita['data_visita'] = date('Y-m-d\TH:i', strtotime($row['data_visita']));
    } else {
        $erro = "Visita não encontrada.";
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $dados = [
        'imovel_id'   => (int)($_POST['imovel_id'] ?? 0),
        'data_visita' => $_POST['data_visita'] ?? '',
        'visitante'   => trim($_POST['visitante'] ?? ''),
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];

    // Validações básicas
    if ($dados['imovel_id'] <= 0) {
        $erro = "Selecione o imóvel.";
    } elseif (empty($dados['visitante'])) {
        $erro = "Informe o nome do visitante.";
    } elseif (empty($dados['data_visita'])) {
        $erro = "Informe a data e hora da visita.";
    } else {
        try {
            if ($id > 0) {
                // Atualizar
                $sql = "
                    UPDATE visitas 
                    SET imovel_id = ?, data_visita = ?, visitante = ?, observacoes = ?
                    WHERE id = ?
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $dados['imovel_id'],
                    $dados['data_visita'],
                    $dados['visitante'],
                    $dados['observacoes'],
                    $id
                ]);

                $sucesso = "Visita atualizada com sucesso!";
            } else {
                // Inserir nova visita
                $sql = "
                    INSERT INTO visitas (imovel_id, data_visita, visitante, observacoes)
                    VALUES (?, ?, ?, ?)
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $dados['imovel_id'],
                    $dados['data_visita'],
                    $dados['visitante'],
                    $dados['observacoes']
                ]);

                $novo_id = $conn->lastInsertId();
                $sucesso = "Visita registrada com sucesso!";

                // Redireciona para edição do registro recém-criado
                header("Location: form.php?id=$novo_id");
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao salvar no banco: " . $e->getMessage();
        }
    }

    $visita = $dados; // mantém valores em caso de erro
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col">
        <h2 class="text-primary"><?= $modo ?> Visita</h2>
        <p class="text-muted">
            <?= $modo === 'Nova' ? 'Registre uma nova visita ao imóvel' : 'Edite os dados da visita' ?>
        </p>
    </div>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($sucesso) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow border-primary">
    <div class="card-body">
        <form method="post" class="row g-3 needs-validation" novalidate>

            <!-- Imóvel -->
            <div class="col-md-12">
                <label class="form-label fw-bold">Imóvel <span class="text-danger">*</span></label>
                <select name="imovel_id" class="form-select form-select-lg" required>
                    <option value="">Selecione o imóvel</option>
                    <?php
                    $imoveis = $conn->query("
                        SELECT id, titulo 
                        FROM imoveis 
                        WHERE deleted_at IS NULL 
                        ORDER BY titulo
                    ")->fetchAll();
                    foreach ($imoveis as $im) {
                        $selected = ($visita['imovel_id'] == $im['id']) ? 'selected' : '';
                        echo "<option value='{$im['id']}' $selected>" . htmlspecialchars($im['titulo']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Data e Hora da Visita -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Data e Hora da Visita <span class="text-danger">*</span></label>
                <input type="datetime-local" name="data_visita" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($visita['data_visita']) ?>" required>
            </div>

            <!-- Visitante -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Nome do Visitante <span class="text-danger">*</span></label>
                <input type="text" name="visitante" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($visita['visitante']) ?>" 
                       placeholder="Nome completo do visitante" required>
            </div>

            <!-- Observações -->
            <div class="col-md-12">
                <label class="form-label">Observações / Detalhes da Visita</label>
                <textarea name="observacoes" class="form-control form-control-lg" rows="6" 
                          placeholder="Descreva como foi a visita, interesse do cliente, observações importantes..."><?= htmlspecialchars($visita['observacoes']) ?></textarea>
            </div>

            <!-- Botões -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-save me-2"></i> Salvar Visita
                </button>
                
                <a href="list.php" class="btn btn-outline-secondary btn-lg ms-3">
                    <i class="bi bi-arrow-left me-2"></i> Voltar para lista
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>