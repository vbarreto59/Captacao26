<?php
// contatos_hoje.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// ==================== PROCESSAMENTO AJAX ====================

// Atualizar Observações
if (isset($_POST['action']) && $_POST['action'] == 'update_obs') {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $obs  = trim($_POST['observacoes'] ?? '');

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE leads SET observacoes = ? WHERE id = ?");
        $success = $stmt->execute([$obs, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

// Atualizar Temperatura
if (isset($_POST['action']) && $_POST['action'] == 'update_temperatura') {
    header('Content-Type: application/json');
    $id   = (int)($_POST['id'] ?? 0);
    $temp = trim($_POST['temperatura'] ?? '');

    if ($id > 0 && in_array($temp, ['Quente', 'Morno', 'Frio'])) {
        $stmt = $conn->prepare("UPDATE leads SET temperatura = ? WHERE id = ?");
        $success = $stmt->execute([$temp, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    }
    exit;
}

// Alternar / Desativar status de Hoje (Toggle Switch)
if (isset($_POST['action']) && $_POST['action'] == 'toggle_hoje') {
    header('Content-Type: application/json');
    $id    = (int)($_POST['id'] ?? 0);
    $ativo = (int)($_POST['ativo'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = ? WHERE id = ?");
        $success = $stmt->execute([$ativo, $id]);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}

// Limpar lista de hoje
if (isset($_POST['action']) && $_POST['action'] == 'limpar_hoje') {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("UPDATE leads SET contatar_hoje = 0 WHERE contatar_hoje = 1");
    echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
    exit;
}

// ====================== CONSULTA ======================
$sql = "SELECT l.*,
        COALESCE(DATEDIFF(NOW(), l.ultima_interacao), 0) as dias_parado
        FROM leads l
        WHERE l.contatar_hoje = 1
        ORDER BY l.nome ASC, l.id DESC";

$stmt = $conn->query($sql);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contatos de Hoje - Gestão de Leads</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .table-lead-hoje {
            background-color: #ffffff;
        }
        .table-lead-hoje tbody tr {
            transition: background-color 0.3s ease, opacity 0.4s ease;
        }
        .table-lead-hoje tbody tr:hover {
            background-color: #fdfdfd;
        }
        .obs-text {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .obs-text:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        
        /* Indicadores de Temperatura na borda lateral */
        .temp-border { transition: border-left 0.3s ease; }
        .temp-Quente { border-left: 5px solid #dc3545 !important; }
        .temp-Morno { border-left: 5px solid #ffc107 !important; }
        .temp-Frio { border-left: 5px solid #0dcaf0 !important; }
        
        /* WhatsApp Cor Padrão */
        .btn-whatsapp {
            background-color: #25d366;
            color: #ffffff;
            border: none;
            transition: background-color 0.2s ease;
        }
        .btn-whatsapp:hover {
            background-color: #128c7e;
            color: #ffffff;
        }
        .item-lead-fila {
            transition: background-color 0.6s ease;
        }
        .form-check-input {
            cursor: pointer;
            width: 2.5em;
            height: 1.25em;
        }
        .select-temp {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.25rem 1.8rem 0.25rem 0.5rem;
            border-radius: 30px;
            width: 110px;
            margin: 0 auto;
        }

        /* ====================================================================
           ADAPTAÇÃO MOBILE COMPLETA (RESPONSIVIDADE DESTRUTIVA)
           ==================================================================== */
        @media (max-width: 768px) {
            /* Esconde o cabeçalho original da tabela no mobile */
            .table-lead-hoje thead {
                display: none;
            }
            
            /* Transforma as linhas estruturais da tabela em cards empilhados */
            .table-lead-hoje tbody, 
            .table-lead-hoje tr {
                display: block;
                width: 100%;
            }
            
            .table-lead-hoje tr {
                background: #ffffff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                border-radius: 12px;
                margin-bottom: 16px;
                padding: 16px;
                border: 1px solid #e9ecef !important;
                position: relative;
            }

            /* Força as células a se comportarem como blocos de conteúdo */
            .table-lead-hoje td {
                display: block;
                width: 100% !important;
                padding: 6px 0 !important;
                text-align: left !important;
                border: none !important;
            }

            /* Oculta o indexador sequencial numérico (#) no mobile */
            .table-lead-hoje td.index-col {
                display: none;
            }

            /* Transforma a borda de temperatura em uma tag fixada no topo esquerdo do card */
            .table-lead-hoje td.temp-border {
                position: absolute;
                top: 0;
                left: 0;
                width: 6px !important;
                height: 100%;
                border-top-left-radius: 12px;
                border-bottom-left-radius: 12px;
                padding: 0 !important;
            }

            /* Estilização para identificação de rótulos inline na exibição mobile */
            .table-lead-hoje td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.75rem;
                text-uppercase: true;
                font-weight: 700;
                color: #a0aec0;
                margin-bottom: 2px;
            }
            
            /* Remove o rótulo inline do nome e das ações para manter o layout limpo */
            .table-lead-hoje td:nth-child(3)::before,
            .table-lead-hoje td:last-child::before {
                display: none;
            }

            /* Alinhamentos específicos e ajustes de largura dos seletores */
            .select-temp {
                margin: 0;
                width: 130px;
            }

            .obs-text {
                max-width: 100%;
                white-space: normal;
                overflow: visible;
                background-color: #f8f9fa;
                padding: 8px 12px !important;
                border-radius: 8px;
                border: 1px dashed #dee2e6;
            }

            /* Alinha o Toggle Switch de forma harmoniosa */
            .form-switch {
                padding-left: 2.5em;
                margin-top: 4px;
            }

            /* Transforma as ações rápidas em uma barra de botões inteiriça na base do card */
            .table-lead-hoje td:last-child {
                padding-top: 12px !important;
                margin-top: 8px;
                border-top: 1px solid #f1f3f5 !important;
            }

            .btn-group {
                display: flex;
                width: 100%;
                box-shadow: none !important;
            }

            .btn-group .btn {
                flex: 1;
                padding: 10px 4px;
                font-size: 0.9rem;
                justify-content: center;
            }
            
            /* Remove fundos de tabelas para os cards flutuarem melhor */
            #painel-tabela {
                background: transparent !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container my-3 my-md-5">
    <!-- Cabeçalho Principal -->
    <div class="card border-0 shadow-sm bg-primary bg-gradient text-white mb-4 rounded-3">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start">
            <div class="mb-3 mb-md-0">
                <h2 class="fw-bold mb-1 d-flex align-items-center justify-content-center justify-content-md-start gap-2 fs-3 fs-md-2">
                    <i class="bi bi-megaphone-fill"></i> Contatos de Hoje
                </h2>
                <p class="mb-0 opacity-75 fs-6">
                    Você tem <span id="contador-leads" class="badge bg-white text-primary fw-bold fs-6"><?= count($leads) ?></span> lead<?= count($leads) !== 1 ? 's' : '' ?> na fila.
                </p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                <?php if (count($leads) > 0): ?>
                    <button id="btnLimparTudo" class="btn btn-outline-light d-flex align-items-center justify-content-center gap-1 shadow-sm py-2">
                        <i class="bi bi-trash3-fill"></i> Limpar Lista
                    </button>
                <?php endif; ?>
                <a href="leads.php" class="btn btn-light fw-semibold text-primary d-flex align-items-center justify-content-center gap-1 shadow-sm py-2">
                    <i class="bi bi-arrow-left-short fs-5"></i> Voltar à Gestão
                </a>
            </div>
        </div>
    </div>

    <!-- Estado Vazio -->
    <div id="estado-vazio" class="card border-0 shadow-sm py-5 rounded-3 <?= empty($leads) ? '' : 'd-none' ?>">
        <div class="card-body text-center py-4">
            <div class="display-1 text-success mb-3">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h3 class="fw-bold text-dark">Tudo em dia!</h3>
            <p class="text-muted lead mb-4 px-3">Não há nenhum contato agendado ou na fila de execução para o dia de hoje.</p>
            <a href="leads.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> Selecionar Novos Leads
            </a>
        </div>
    </div>

    <?php if (!empty($leads)): ?>
        <!-- Painel da Tabela / Listagem de Cards -->
        <div id="painel-tabela" class="card border-0 shadow-sm rounded-3 overflow-hidden mb-5">
            <div class="table-responsive-md">
                <table class="table table-hover table-lead-hoje align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr class="text-secondary small text-uppercase">
                            <th scope="col" class="ps-3" style="width: 50px;">#</th>
                            <th scope="col" style="width: 6px;"></th>
                            <th scope="col">Nome completo</th>
                            <th scope="col" class="text-center" style="width: 140px;">Temperatura</th>
                            <th scope="col">Status de Tempo</th>
                            <th scope="col">Observações (Clique para Editar)</th>
                            <th scope="col" class="text-center" style="width: 90px;">Hoje?</th>
                            <th scope="col" class="text-end pe-4" style="width: 220px;">Ações rápidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $contador = 1;
                        foreach ($leads as $l):
                            $tel = preg_replace('/\D/', '', $l['telefone']);
                            $temp = $l['temperatura'] ?: 'Morno';
                            $classeTemp = 'temp-' . $temp;

                            $selectClass = 'bg-warning-subtle text-warning-emphasis border-warning';
                            if ($temp === 'Quente') $selectClass = 'bg-danger-subtle text-danger-emphasis border-danger';
                            if ($temp === 'Frio')   $selectClass = 'bg-info-subtle text-info-emphasis border-info';
                        ?>
                        <tr id="linha-lead-<?= $l['id'] ?>" class="item-lead-fila border-bottom">
                            <td class="text-muted ps-3 fw-medium small index-col"><?= $contador++ ?></td>
                            
                            <td id="borda-temp-<?= $l['id'] ?>" class="temp-border <?= $classeTemp ?>"></td>
                            
                            <td>
                                <div class="fw-bold text-dark mb-0 fs-5 fs-md-6"><?= htmlspecialchars($l['nome']) ?></div>
                                <small class="text-muted d-block"><i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($l['telefone']) ?></small>
                            </td>
                            
                            <td data-label="Temperatura" class="text-center">
                                <select class="form-select select-temp change-temperatura <?= $selectClass ?>" 
                                        data-id="<?= $l['id'] ?>">
                                    <option value="Quente" <?= $temp == 'Quente' ? 'selected' : '' ?>>Quente</option>
                                    <option value="Morno" <?= $temp == 'Morno' ? 'selected' : '' ?>>Morno</option>
                                    <option value="Frio" <?= $temp == 'Frio' ? 'selected' : '' ?>>Frio</option>
                                </select>
                            </td>
                            
                            <td data-label="Status de Tempo">
                                <span class="badge bg-light text-secondary border fw-normal py-1.5 px-2">
                                    <i class="bi bi-clock-history me-1 text-primary"></i> Parado há <b><?= $l['dias_parado'] ?></b> dias
                                </span>
                            </td>
                            
                            <td data-label="Observações (Toque para Editar)" 
                                class="obs-text trigger-modal-obs small" 
                                id="celula-obs-<?= $l['id'] ?>"
                                title="<?= htmlspecialchars($l['observacoes'] ?? '') ?>"
                                data-id="<?= $l['id'] ?>"
                                data-obs="<?= htmlspecialchars($l['observacoes'] ?? '') ?>">
                                <?php if (!empty($l['observacoes'])): ?>
                                    <i class="bi bi-chat-left-text me-1 opacity-50"></i> <?= htmlspecialchars($l['observacoes']) ?>
                                <?php else: ?>
                                    <em class="text-muted opacity-50">Sem observações...</em>
                                <?php endif; ?>
                            </td>

                            <td data-label="Manter na fila de hoje?" class="text-md-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input toggle-hoje-switch" 
                                           type="checkbox" 
                                           role="switch" 
                                           checked 
                                           data-id="<?= $l['id'] ?>">
                                </div>
                            </td>
                            
                            <td class="text-end pe-md-4">
                                <div class="btn-group border rounded">
                                    <a href="https://wa.me/55<?= $tel ?>" target="_blank" class="btn btn-whatsapp btn-sm fw-bold px-3 d-flex align-items-center gap-1">
                                        <i class="bi bi-whatsapp"></i> <span>WhatsApp</span>
                                    </a>
                                    <button class="btn btn-light btn-sm text-secondary border-start trigger-modal-obs" 
                                            data-id="<?= $l['id'] ?>" 
                                            data-obs="<?= htmlspecialchars($l['observacoes'] ?? '') ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <a href="lead_view.php?id=<?= $l['id'] ?>" class="btn btn-light btn-sm text-primary border-start">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ====================== WINDOW MODAL BOOTSTRAP ====================== -->
<div class="modal fade" id="modalObservacoes" tabindex="-1" aria-labelledby="modalObsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0 rounded-3">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalObsLabel">
                    <i class="bi bi-pencil-square text-primary"></i> Atualizar Notas do Lead
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="mb-3">
                    <label for="txtObservacoes" class="form-label text-secondary small fw-semibold">Inserir ou Modificar Histórico:</label>
                    <textarea id="txtObservacoes" class="form-control border-secondary-subtle" rows="6" placeholder="Escreva observações comerciais relevantes..."></textarea>
                </div>
                <input type="hidden" id="modalLeadId">
            </div>
            <div class="modal-footer bg-light border-top p-3">
                <button type="button" class="btn btn-outline-secondary fw-medium px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarObs" class="btn btn-primary fw-semibold px-4 shadow-sm">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts Core: JQuery + Bootstrap Bundle -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    
    function recalcularContadores() {
        let totalLeads = 0;
        $('.item-lead-fila:visible').each(function(index) {
            $(this).find('.index-col').text(index + 1);
            totalLeads++;
        });
        
        $('#contador-leads').text(totalLeads);

        if (totalLeads === 0) {
            $('#painel-tabela').addClass('d-none');
            $('#btnLimparTudo').addClass('d-none');
            $('#estado-vazio').removeClass('d-none');
        }
    }

    // Alterar Temperatura Dinamicamente
    $('.change-temperatura').on('change', function() {
        const select = $(this);
        const leadId = select.data('id');
        const novaTemp = select.val();
        const celulaBorda = $(`#borda-temp-${leadId}`);

        select.prop('disabled', true);

        $.post('contatos_hoje.php', {
            action: 'update_temperatura',
            id: leadId,
            temperatura: novaTemp
        }, function(res) {
            if (res.status === 'success') {
                celulaBorda.removeClass('temp-Quente temp-Morno temp-Frio').addClass('temp-' + novaTemp);
                select.removeClass('bg-danger-subtle text-danger-emphasis border-danger bg-warning-subtle text-warning-emphasis border-warning bg-info-subtle text-info-emphasis border-info');
                
                if (novaTemp === 'Quente') {
                    select.addClass('bg-danger-subtle text-danger-emphasis border-danger');
                } else if (novaTemp === 'Morno') {
                    select.addClass('bg-warning-subtle text-warning-emphasis border-warning');
                } else if (novaTemp === 'Frio') {
                    select.addClass('bg-info-subtle text-info-emphasis border-info');
                }

                const row = $(`#linha-lead-${leadId}`);
                row.addClass('table-primary');
                setTimeout(() => row.removeClass('table-primary'), 600);
            } else {
                alert('Erro ao atualizar a temperatura.');
            }
        }, 'json')
        .fail(function() {
            alert('Erro de rede ao atualizar a temperatura.');
        })
        .always(function() {
            select.prop('disabled', false);
        });
    });

    // Remover da lista de Hoje (Toggle Switch)
    $('.toggle-hoje-switch').on('change', function() {
        const switchBtn = $(this);
        const leadId = switchBtn.data('id');
        const estaAtivo = switchBtn.is(':checked') ? 1 : 0;
        const linha = $(`#linha-lead-${leadId}`);

        switchBtn.prop('disabled', true);

        $.post('contatos_hoje.php', {
            action: 'toggle_hoje',
            id: leadId,
            ativo: estaAtivo
        }, function(res) {
            if (res.status === 'success') {
                if (estaAtivo === 0) {
                    linha.css('opacity', '0.4').addClass('table-light');
                    setTimeout(function() {
                        linha.fadeOut(300, function() {
                            $(this).remove();
                            recalcularContadores();
                        });
                    }, 200);
                }
            } else {
                alert('Erro ao modificar status do lead.');
                switchBtn.prop('checked', !estaAtivo);
            }
        }, 'json')
        .fail(function() {
            alert('Erro de comunicação.');
            switchBtn.prop('checked', !estaAtivo);
        })
        .always(function() {
            switchBtn.prop('disabled', false);
        });
    });
    
    // Abrir Modal Notas
    $('.trigger-modal-obs').on('click', function(e) {
        if ($(this).closest('tr').css('opacity') < 1) return;

        const target = $(this);
        const id = target.data('id');
        
        const celulaObs = $(`#celula-obs-${id}`);
        const obsAtual = celulaObs.data('obs') || '';
        
        $('#modalLeadId').val(id);
        $('#txtObservacoes').val(obsAtual);
        $('#modalObservacoes').modal('show');
    });

    // Salvar Notas via AJAX
    $('#btnSalvarObs').on('click', function() {
        const obs = $('#txtObservacoes').val().trim();
        const id  = $('#modalLeadId').val();

        if (!id) return;

        const btn = $(this);
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>...');

        $.post('contatos_hoje.php', {
            action: 'update_obs',
            id: id,
            observacoes: obs
        }, function(res) {
            if (res.status === 'success') {
                const celula = $(`#celula-obs-${id}`);
                
                if(obs) {
                    celula.html('<i class="bi bi-chat-left-text me-1 opacity-50"></i> ' + obs);
                } else {
                    celula.html('<em class="text-muted opacity-50">Sem observações...</em>');
                }
                
                celula.data('obs', obs).attr('title', obs);
                $('#modalObservacoes').modal('hide');
                
                const row = $(`#linha-lead-${id}`);
                row.addClass('table-success');
                setTimeout(() => row.removeClass('table-success'), 1200);
            } else {
                alert('Erro interno.');
            }
        }, 'json')
        .fail(function() {
            alert('Falha de comunicação.');
        })
        .always(() => {
            btn.prop('disabled', false).html(originalText);
        });
    });

    // Limpar lista completa
    $('#btnLimparTudo').on('click', function() {
        if (confirm('Deseja retirar TODOS os leads da lista de hoje?')) {
            $.post('contatos_hoje.php', { action: 'limpar_hoje' }, function(res) {
                if (res.status === 'success') location.reload();
            }, 'json');
        }
    });
});
</script>

</body>
</html>