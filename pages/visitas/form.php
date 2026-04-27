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
    'lead_id'      => null,
    'data_visita'  => date('Y-m-d\TH:i'),  
    'visitante'    => '',
    'observacoes'  => ''
];

$erro    = '';
$sucesso = '';

// Carrega dados se for edição
if ($id > 0) {
    $stmt = $conn->prepare("SELECT imovel_id, lead_id, data_visita, visitante, observacoes FROM visitas WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $visita = $row;
        $visita['data_visita'] = date('Y-m-d\TH:i', strtotime($row['data_visita']));
    } else {
        $erro = "Visita não encontrada.";
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $dados = [
        'imovel_id'   => (int)($_POST['imovel_id'] ?? 0),
        'lead_id'     => !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null,
        'data_visita' => $_POST['data_visita'] ?? '',
        'visitante'   => trim($_POST['visitante'] ?? ''),
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];

    if ($dados['imovel_id'] <= 0) {
        $erro = "Selecione o imóvel.";
    } elseif (empty($dados['visitante'])) {
        $erro = "Informe o nome do visitante (ou selecione um lead).";
    } elseif (empty($dados['data_visita'])) {
        $erro = "Informe a data e hora da visita.";
    } else {
        try {
            if ($id > 0) {
                // Atualizar registro existente
                $sql = "UPDATE visitas SET imovel_id = ?, lead_id = ?, data_visita = ?, visitante = ?, observacoes = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$dados['imovel_id'], $dados['lead_id'], $dados['data_visita'], $dados['visitante'], $dados['observacoes'], $id]);
                $sucesso = "Visita atualizada com sucesso!";
            } else {
                // Inserir nova visita
                $sql = "INSERT INTO visitas (imovel_id, lead_id, data_visita, visitante, observacoes) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$dados['imovel_id'], $dados['lead_id'], $dados['data_visita'], $dados['visitante'], $dados['observacoes']]);
                $novo_id = $conn->lastInsertId();

                // Atualiza o funil do Lead se houver um vinculado
                if ($dados['lead_id']) {
                    $upd = $conn->prepare("UPDATE leads SET fase_funil = 'Visita Realizada', ultima_interacao = NOW() WHERE id = ?");
                    $upd->execute([$dados['lead_id']]);
                }

                // ================================================
                // ENVIO DE E-MAIL COM AGENDA COMPLETA
                // ================================================
                $sql_mail = "
                    SELECT v.data_visita, v.visitante, i.titulo as imovel 
                    FROM visitas v 
                    LEFT JOIN imoveis i ON v.imovel_id = i.id 
                    WHERE DATE(v.data_visita) >= CURDATE()
                    ORDER BY v.data_visita ASC
                ";
                $todas_visitas = $conn->query($sql_mail)->fetchAll(PDO::FETCH_ASSOC);

                $to = "valterpb@hotmail.com";
                $subject = "Agenda Atualizada - " . date('d/m/Y');
                
                $message = "<html><head><style>
                            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                            th { background-color: #0d6efd; color: white; padding: 10px; text-align: left; }
                            td { border: 1px solid #ddd; padding: 10px; }
                            tr:nth-child(even) { background-color: #f9f9f9; }
                            .destaque { background-color: #fff3cd !important; font-weight: bold; }
                            </style></head><body>
                            <h2 style='color: #0d6efd;'>Valter, sua agenda foi atualizada:</h2>
                            <table><thead><tr><th>Data/Hora</th><th>Imóvel</th><th>Visitante</th></tr></thead><tbody>";

                foreach ($todas_visitas as $v) {
                    $data_f = date('d/m/Y H:i', strtotime($v['data_visita']));
                    $classe = ($v['visitante'] == $dados['visitante'] && $v['data_visita'] == $dados['data_visita']) ? "class='destaque'" : "";
                    $message .= "<tr {$classe}><td>{$data_f}</td><td>" . htmlspecialchars($v['imovel']) . "</td><td>" . htmlspecialchars($v['visitante']) . "</td></tr>";
                }
                $message .= "</tbody></table></body></html>";

                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Sistema <sendmail@gabnetweb.com.br>\r\n";
                mail($to, $subject, $message, $headers);
                
                header("Location: form.php?id=$novo_id&msg=sucesso");
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container pb-5">
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-primary fw-bold"><i class="bi bi-calendar-check me-2"></i><?= $modo ?> Visita</h2>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'sucesso'): ?>
        <div class="alert alert-success shadow-sm border-0 animate__animated animate__fadeIn">
            <i class="bi bi-check-circle-fill me-2"></i> Visita registrada e agenda enviada por e-mail!
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger shadow-sm border-0"><?= $erro ?></div>
    <?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-body p-4">
            <form method="post" class="row g-3">
                
                <div class="col-md-12">
                    <label class="form-label fw-bold">Imóvel <span class="text-danger">*</span></label>
                    <select name="imovel_id" class="form-select form-select-lg border-primary" required>
                        <option value="">Selecione o imóvel para visita</option>
                        <?php
                        $imoveis = $conn->query("SELECT id, titulo FROM imoveis WHERE deleted_at IS NULL ORDER BY titulo")->fetchAll();
                        foreach ($imoveis as $im) {
                            $selected = ($visita['imovel_id'] == $im['id']) ? 'selected' : '';
                            echo "<option value='{$im['id']}' $selected>" . htmlspecialchars($im['titulo']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary">Vincular a um Lead Existente</label>
                    <select name="lead_id" id="lead_id" class="form-select form-select-lg border-primary bg-light" onchange="vincularNomeLead(this)">
                        <option value="">-- Cliente não cadastrado --</option>
                        <?php
                        $leads = $conn->query("SELECT id, nome FROM leads ORDER BY nome ASC")->fetchAll();
                        foreach ($leads as $ld) {
                            $selected = ($visita['lead_id'] == $ld['id']) ? 'selected' : '';
                            // Passamos o nome no data-attribute para o JS capturar
                            echo "<option value='{$ld['id']}' $selected data-nome='".htmlspecialchars($ld['nome'])."'>" . htmlspecialchars($ld['nome']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Data e Hora da Visita <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="data_visita" class="form-control form-control-lg" value="<?= htmlspecialchars($visita['data_visita']) ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold">Nome do Visitante <span class="text-danger">*</span></label>
                    <input type="text" name="visitante" id="visitante" class="form-control form-control-lg" value="<?= htmlspecialchars($visita['visitante']) ?>" placeholder="Nome que aparecerá na agenda" required>
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i> Se você selecionar um lead acima, o nome dele será preenchido aqui automaticamente.
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold">Observações / Detalhes</label>
                    <textarea name="observacoes" class="form-control form-control-lg" rows="4" placeholder="Ex: Cliente virá com arquiteto, focar na área de lazer..."><?= htmlspecialchars($visita['observacoes']) ?></textarea>
                </div>

                <div class="col-12 mt-4 text-end">
                    <hr>
                    <a href="list.php" class="btn btn-light btn-lg px-4 border me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
                        <i class="bi bi-save me-2"></i> Salvar e Notificar Agenda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Lógica para automatizar o preenchimento do nome
 */
function vincularNomeLead(select) {
    // Busca o atributo 'data-nome' da opção selecionada
    const option = select.options[select.selectedIndex];
    const nomeDoLead = option.getAttribute('data-nome');
    const inputVisitante = document.getElementById('visitante');
    
    if (nomeDoLead) {
        inputVisitante.value = nomeDoLead;
        // Opcional: Efeito visual para mostrar que foi preenchido
        inputVisitante.classList.add('is-valid');
        setTimeout(() => inputVisitante.classList.remove('is-valid'), 1500);
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>