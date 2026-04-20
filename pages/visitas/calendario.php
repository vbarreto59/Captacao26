<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

// 1. TRATAMENTO DO MÊS E ANO
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$hoje_full = date('Y-m-d');

// 2. CONFIGURAÇÕES DA GRADE
$primeiro_dia_mes = "$ano-$mes-01";
$numero_dias_mes = date('t', strtotime($primeiro_dia_mes));
$dia_semana_inicio = (int)date('N', strtotime($primeiro_dia_mes)) - 1;

// 3. BUSCA DAS VISITAS
$sql_visitas = "SELECT v.*, l.nome as lead_nome, i.titulo as imovel_titulo
        FROM visitas v
        JOIN leads l ON v.lead_id = l.id
        JOIN imoveis i ON v.imovel_id = i.id
        WHERE MONTH(v.data_visita) = ? AND YEAR(v.data_visita) = ?";
$stmt_v = $conn->prepare($sql_visitas);
$stmt_v->execute([$mes, $ano]);
$visitas_db = $stmt_v->fetchAll(PDO::FETCH_ASSOC);

// 4. BUSCA DA AGENDA GERAL
$sql_agenda = "SELECT * FROM agenda_geral 
               WHERE MONTH(data_evento) = ? AND YEAR(data_evento) = ?";
$stmt_a = $conn->prepare($sql_agenda);
$stmt_a->execute([$mes, $ano]);
$agenda_db = $stmt_a->fetchAll(PDO::FETCH_ASSOC);

// 5. ORGANIZAR TUDO POR DIA
$eventos_por_dia = [];

foreach ($visitas_db as $v) {
    $dia = (int)date('d', strtotime($v['data_visita']));
    $eventos_por_dia[$dia][] = [
        'hora' => date('H:i', strtotime($v['data_visita'])),
        'titulo' => explode(' ', $v['lead_nome'])[0] . " - " . $v['imovel_titulo'],
        'link' => '../leads/lead_view.php?id=' . $v['lead_id'],
        'cor' => 'bg-primary'
    ];
}

foreach ($agenda_db as $a) {
    $dia = (int)date('d', strtotime($a['data_evento']));
    $eventos_por_dia[$dia][] = [
        'hora' => date('H:i', strtotime($a['data_evento'])),
        'titulo' => $a['titulo'], // REMOVIDO CATEGORIA AQUI
        'link' => 'agenda.php',
        'cor' => 'bg-success'
    ];
}

$meses_nomes = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

require_once '../../includes/header.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">
                <i class="bi bi-calendar3 me-2"></i><?= $meses_nomes[$mes] ?> de <?= $ano ?>
            </h2>
            <div class="d-flex gap-3 mt-1">
                <small class="text-muted"><i class="bi bi-circle-fill text-primary"></i> Visitas</small>
                <small class="text-muted"><i class="bi bi-circle-fill text-success"></i> Agenda Geral</small>
            </div>
        </div>
        <div class="btn-group shadow-sm">
            <?php
                $prev_mes = ($mes == 1) ? 12 : $mes - 1;
                $prev_ano = ($mes == 1) ? $ano - 1 : $ano;
                $next_mes = ($mes == 12) ? 1 : $mes + 1;
                $next_ano = ($mes == 12) ? $ano + 1 : $ano;
            ?>
            <a href="?mes=<?= $prev_mes ?>&ano=<?= $prev_ano ?>" class="btn btn-outline-primary btn-sm">Anterior</a>
            <a href="calendario.php" class="btn btn-primary btn-sm text-white">Hoje</a>
            <a href="?mes=<?= $next_mes ?>&ano=<?= $next_ano ?>" class="btn btn-outline-primary btn-sm">Próximo</a>
        </div>
    </div>

    <div class="card shadow border-0 overflow-hidden">
        <div class="card-body p-0">
            <table class="table table-bordered mb-0 calendar-table" style="table-layout: fixed; width: 100%;">
                <thead class="bg-light text-center text-uppercase small fw-bold">
                    <tr>
                        <th style="width: 14.28%;">Seg</th>
                        <th style="width: 14.28%;">Ter</th>
                        <th style="width: 14.28%;">Qua</th>
                        <th style="width: 14.28%;">Qui</th>
                        <th style="width: 14.28%;">Sex</th>
                        <th style="width: 14.28%;" class="text-primary">Sáb</th>
                        <th style="width: 14.28%;" class="text-danger">Dom</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        for ($i = 0; $i < $dia_semana_inicio; $i++) {
                            echo '<td class="bg-light-subtle"></td>';
                        }

                        for ($dia_atual = 1; $dia_atual <= $numero_dias_mes; $dia_atual++) {
                            if (($dia_semana_inicio + $dia_atual - 1) % 7 == 0 && $dia_atual != 1) {
                                echo '</tr><tr>';
                            }

                            $data_celula = sprintf('%04d-%02d-%02d', $ano, $mes, $dia_atual);
                            $is_passado = ($data_celula < $hoje_full);
                            $is_hoje = ($data_celula == $hoje_full);
                            ?>
                            <td class="calendar-day <?= $is_passado ? 'data-passada' : '' ?> <?= $is_hoje ? 'bg-warning-subtle' : '' ?>">
                                <span class="day-number <?= $is_hoje ? 'text-danger fw-bold' : '' ?>">
                                    <?= $dia_atual ?>
                                </span>
                                
                                <div class="events-container">
                                    <?php if (isset($eventos_por_dia[$dia_atual])): 
                                        usort($eventos_por_dia[$dia_atual], function($a, $b) {
                                            return strcmp($a['hora'], $b['hora']);
                                        });
                                        foreach ($eventos_por_dia[$dia_atual] as $e): 
                                    ?>
                                        <a href="<?= $e['link'] ?>" 
                                           class="event-tag <?= $is_passado ? 'bg-secondary opacity-75' : $e['cor'] ?> text-decoration-none" 
                                           title="<?= htmlspecialchars($e['titulo']) ?>">
                                            <strong><?= $e['hora'] ?></strong> <?= htmlspecialchars($e['titulo']) ?>
                                        </a>
                                    <?php endforeach; endif; ?>
                                </div>
                            </td>
                        <?php }

                        $total_celulas = $dia_semana_inicio + $numero_dias_mes;
                        $restante = (7 - ($total_celulas % 7)) % 7;
                        for ($i = 0; $i < $restante; $i++) {
                            echo '<td class="bg-light-subtle"></td>';
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .calendar-table th { padding: 10px; background: #f8f9fa; border-bottom: 2px solid #dee2e6 !important; font-size: 0.75rem; }
    
    .calendar-day { 
        height: 140px; 
        vertical-align: top; 
        border: 1px solid #dee2e6 !important; 
        padding: 5px !important;
        overflow: hidden;
    }
    
    .data-passada { background-color: #fcfcfc; opacity: 0.7; }
    .day-number { font-size: 0.8rem; color: #adb5bd; display: block; margin-bottom: 4px; }
    
    .events-container { 
        height: 105px; 
        overflow-y: auto; 
        scrollbar-width: none; 
    }
    .events-container::-webkit-scrollbar { display: none; }

    .event-tag { 
        display: block; 
        color: white; 
        font-size: 0.65rem; 
        padding: 3px 5px; 
        border-radius: 3px; 
        margin-bottom: 4px; 
        line-height: 1.2;
        /* Quebra de texto */
        white-space: normal; 
        word-wrap: break-word;
        border-left: 3px solid rgba(0,0,0,0.2);
    }
    
    .bg-primary { background: #0d6efd !important; }
    .bg-success { background: #198754 !important; }
    .bg-secondary { background: #adb5bd !important; }
    .bg-light-subtle { background-color: #fafafa; }
</style>

<?php require_once '../../includes/footer.php'; ?>