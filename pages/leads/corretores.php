<?php
// corretores.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==========================================
// LÓGICA AJAX (PROCESSAMENTO)
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if ($_POST['action'] == 'save') {
        $nome = $_POST['nome'];
        $creci = $_POST['creci'];
        $telefone = $_POST['telefone'];
        $email = $_POST['email'];
        $status = $_POST['status'];
        $codigo_acesso = isset($_POST['codigo_acesso']) ? strtoupper(trim($_POST['codigo_acesso'])) : '';

        // Validação: 4 letras
        if (!preg_match('/^[A-Z]{4}$/', $codigo_acesso)) {
            echo json_encode(['status' => 'error', 'message' => 'Código de acesso deve conter exatamente 4 letras maiúsculas.']);
            exit;
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE corretores SET nome=?, creci=?, telefone=?, email=?, status=?, codigo_acesso=? WHERE id=?");
            $ok = $stmt->execute([$nome, $creci, $telefone, $email, $status, $codigo_acesso, $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO corretores (nome, creci, telefone, email, status, codigo_acesso) VALUES (?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$nome, $creci, $telefone, $email, $status, $codigo_acesso]);
        }
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }
    
    if ($_POST['action'] == 'get') {
        $stmt = $conn->prepare("SELECT * FROM corretores WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    if ($_POST['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM corretores WHERE id = ?");
        echo json_encode(['status' => $stmt->execute([$id]) ? 'success' : 'error']);
    }
    exit;
}

// Consulta Principal
$corretores = $conn->query("SELECT * FROM corretores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">Gestão de Corretores</h2>
            <p class="text-muted small mb-0"><?= count($corretores) ?> profissionais cadastrados</p>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="novoCorretor()">
            <i class="bi bi-plus-lg"></i> Novo Corretor
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-uppercase small fw-bold">
                        <tr>
                            <th class="ps-3">Código</th>
                            <th>Nome</th>
                            <th>CRECI</th>
                            <th>Contato</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
<tbody>
    <?php foreach ($corretores as $c): ?>
    <tr>
        <td class="ps-3">
            <!-- Link no próprio código -->
            <a href="lista_leads_corretores.php?status=<?= $c['codigo_acesso'] ?>" target="_blank" class="text-decoration-none">
                <span class="badge bg-secondary" title="Clique para ver a página do corretor">
                    <?= htmlspecialchars($c['codigo_acesso']) ?> <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                </span>
            </a>
        </td>
        <td>
            <div class="fw-bold text-dark"><?= htmlspecialchars($c['nome']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($c['email']) ?></div>
        </td>
        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['creci']) ?></span></td>
        <td><?= htmlspecialchars($c['telefone']) ?></td>
        <td class="text-center">
            <span class="badge <?= $c['status'] == 'Ativo' ? 'bg-success' : 'bg-danger' ?>">
                <?= $c['status'] ?>
            </span>
        </td>
        <td class="text-end pe-3">
            <!-- Novo botão: Visualizar Leads do Corretor -->
            <a href="lista_leads_corretores.php?status=<?= $c['codigo_acesso'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver lista de leads deste corretor">
                <i class="bi bi-eye"></i>
            </a>
            <button class="btn btn-sm btn-outline-secondary" onclick="editarCorretor(<?= $c['id'] ?>)" title="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="excluirCorretor(<?= $c['id'] ?>)" title="Excluir">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal CRUD -->
<div class="modal fade" id="modalCorretor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Dados do Corretor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCorretor">
                <input type="hidden" id="corretor_id" name="id">
                <input type="hidden" name="action" value="save">
                <div class="modal-body bg-light">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nome Completo</label>
                            <input type="text" class="form-control" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Código de Acesso</label>
                            <input type="text" class="form-control" name="codigo_acesso" maxlength="4" placeholder="4 letras maiúsculas" pattern="[A-Za-z]{4}" title="Digite exatamente 4 letras" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                            <small class="text-muted">Exatamente 4 letras (será salvo em maiúsculo).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">CRECI</label>
                            <input type="text" class="form-control" name="creci">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status">
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Telefone/WhatsApp</label>
                            <input type="text" class="form-control" name="telefone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">E-mail</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Salvar Corretor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let modalCorretor;
    try {
        const modalElement = document.getElementById('modalCorretor');
        if (modalElement) {
            modalCorretor = new bootstrap.Modal(modalElement);
        } else {
            console.error('Modal não encontrado!');
        }
    } catch(e) {
        console.error('Bootstrap não carregado:', e);
    }

    window.novoCorretor = function() {
        console.log('novoCorretor chamado');
        $('#formCorretor')[0].reset();
        $('#corretor_id').val('');
        // Gera um código aleatório de 4 letras (exemplo: ABCD)
        const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // sem letras ambíguas (I, O)
        let codigo = '';
        for (let i = 0; i < 4; i++) {
            codigo += letras.charAt(Math.floor(Math.random() * letras.length));
        }
        $('[name="codigo_acesso"]').val(codigo);
        if (modalCorretor) modalCorretor.show();
        else $('#modalCorretor').modal('show');
    };

    window.editarCorretor = function(id) {
        console.log('editarCorretor', id);
        $.post('corretores.php', { action: 'get', id: id }, function(data) {
            if (data) {
                $('[name="id"]').val(data.id);
                $('[name="nome"]').val(data.nome);
                $('[name="codigo_acesso"]').val(data.codigo_acesso);
                $('[name="creci"]').val(data.creci);
                $('[name="telefone"]').val(data.telefone);
                $('[name="email"]').val(data.email);
                $('[name="status"]').val(data.status);
                if (modalCorretor) modalCorretor.show();
                else $('#modalCorretor').modal('show');
            }
        }, 'json').fail(function() {
            alert('Erro ao carregar dados do corretor.');
        });
    };

    window.excluirCorretor = function(id) {
        if (confirm('Deseja realmente excluir este corretor?')) {
            $.post('corretores.php', { action: 'delete', id: id }, function(res) {
                if (res.status === 'success') location.reload();
                else alert('Erro ao excluir.');
            }, 'json');
        }
    };

    $('#formCorretor').on('submit', function(e) {
        e.preventDefault();
        const codigo = $('[name="codigo_acesso"]').val().trim().toUpperCase();
        if (!/^[A-Z]{4}$/.test(codigo)) {
            alert('O código de acesso deve conter exatamente 4 letras (A-Z).');
            $('[name="codigo_acesso"]').focus();
            return;
        }
        // Força maiúsculo antes de enviar
        $('[name="codigo_acesso"]').val(codigo);
        
        $.post('corretores.php', $(this).serialize(), function(res) {
            if (res.status === 'success') location.reload();
            else alert(res.message || 'Erro ao salvar. Verifique os dados.');
        }, 'json').fail(function() {
            alert('Erro de comunicação com o servidor.');
        });
    });
});
</script>
<!--  -->