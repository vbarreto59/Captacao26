<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Lógica de Filtros
// ================================================
$where = "WHERE i.deleted_at IS NULL";
$params = [];

if (!empty($_GET['busca'])) {
    $busca = '%' . trim($_GET['busca']) . '%';
    $where .= " AND (i.titulo LIKE ? OR i.bairro LIKE ? OR i.cidade LIKE ? OR i.endereco LIKE ?)";
    $params = array_merge($params, [$busca, $busca, $busca, $busca]);
}

// ================================================
// Consulta SQL Completa
// ================================================
$sql = "
    SELECT 
        i.*, 
        p.nome as nome_proprietario,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id LIMIT 1) AS foto_principal,
        (SELECT COUNT(*) FROM visitas v WHERE v.imovel_id = i.id) AS total_visitas
    FROM imoveis i
    LEFT JOIN proprietarios p ON i.proprietario_id = p.id
    $where
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>

<style>
    .hover-up:hover { transform: translateY(-8px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.15)!important; }
    .transition { transition: all 0.3s ease; }
    .card-title { min-height: 44px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .feature-icon { font-size: 0.85rem; padding: 4px 10px; border-radius: 50px; border: 1px solid #e9ecef; background: #f8f9fa; color: #495057; display: inline-block; margin-bottom: 4px; }
    .bg-financeiro { background-color: #f0f4f8; border-left: 4px solid #0d6efd; }
</style>

<div class="container-fluid pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="text-primary fw-bold mb-0">Gestão de Captações</h1>
            <p class="text-muted small">Portfólio completo com todas as características técnicas</p>
        </div>
        <a href="form.php" class="btn btn-primary btn-lg shadow">
            <i class="bi bi-plus-circle me-2"></i>Novo Imóvel
        </a>
    </div>

    <?php if (empty($imoveis)): ?>
        <div class="alert alert-light text-center border py-5 shadow-sm">
            <i class="bi bi-building-dash display-1 text-muted"></i>
            <p class="fs-4 mt-3">Nenhum imóvel encontrado.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($imoveis as $im): 
                $foto = $im['foto_principal'] ?: 'https://via.placeholder.com/400x250?text=Sem+Foto';
                $preco = number_format($im['preco'], 2, ',', '.');
                
                $address_encoded = urlencode($im['endereco'] . ', ' . $im['bairro'] . ', ' . $im['cidade']);
                $maps_link = !empty($im['latitude']) 
                    ? "https://www.google.com/maps/search/?api=1&query={$im['latitude']},{$im['longitude']}" 
                    : "https://www.google.com/maps/search/?api=1&query={$address_encoded}";

                $status_color = match($im['status']) {
                    'captado' => 'success',
                    'em_negociacao' => 'warning',
                    'vendido' => 'danger',
                    default => 'secondary'
                };
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 hover-up transition">
                    <div class="position-relative">
                        <img src="<?= htmlspecialchars($foto) ?>" class="card-img-top" style="height: 220px; object-fit: cover;">
                        <span class="position-absolute top-0 end-0 m-2 badge bg-<?= $status_color ?> shadow">
                            <?= ucfirst(str_replace('_', ' ', $im['status'])) ?>
                        </span>
                        <div class="position-absolute bottom-0 start-0 m-2 d-flex gap-1">
                            <?php if($im['andar']): ?> <span class="badge bg-dark"><?= $im['andar'] ?>º Andar</span> <?php endif; ?>
                            <?php if($im['face']): ?> <span class="badge bg-warning text-dark">Face <?= ucfirst($im['face']) ?></span> <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($im['titulo']) ?></h5>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($im['bairro']) ?> | 
                            <a href="<?= $maps_link ?>" target="_blank" class="text-success text-decoration-none fw-bold">Ver no Mapa</a>
                        </p>

                        <div class="bg-financeiro rounded p-3 mb-3">
                            <div class="small text-muted mb-1">Preço de Venda</div>
                            <h4 class="fw-bold text-primary mb-2">R$ <?= $preco ?></h4>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Cond: <b>R$ <?= number_format($im['valor_condominio'], 2, ',', '.') ?></b></span>
                                <span>IPTU: <b>R$ <?= number_format($im['valor_iptu'], 2, ',', '.') ?></b></span>
                            </div>
                        </div>

                        <div class="row text-center g-0 mb-3 border rounded py-2 bg-white small">
                            <div class="col-3 border-end"><b><?= $im['quartos'] ?></b><br>Quartos</div>
                            <div class="col-3 border-end"><b><?= $im['suites'] ?></b><br>Suítes</div>
                            <div class="col-3 border-end"><b><?= $im['vagas_garagem'] ?></b><br>Vagas</div>
                            <div class="col-3"><b><?= $im['area'] ?>m²</b><br>Área</div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-1">
                                <?php if($im['tem_piscina']): ?><span class="feature-icon"><i class="bi bi-water text-info"></i> Piscina</span><?php endif; ?>
                                <?php if($im['tem_academia']): ?><span class="feature-icon"><i class="bi bi-heart-pulse text-danger"></i> Academia</span><?php endif; ?>
                                <?php if($im['tem_salao_festas']): ?><span class="feature-icon"><i class="bi bi-people"></i> Salão Festas</span><?php endif; ?>
                                <?php if($im['tem_espaco_gourmet']): ?><span class="feature-icon"><i class="bi bi-egg-fried"></i> Gourmet</span><?php endif; ?>
                                <?php if($im['tem_playground']): ?><span class="feature-icon"><i class="bi bi-bicycle"></i> Kids</span><?php endif; ?>
                                <?php if($im['possui_elevador']): ?><span class="feature-icon"><i class="bi bi-arrow-up-down"></i> Elevador</span><?php endif; ?>
                                <?php if($im['gas_encanado']): ?><span class="feature-icon"><i class="bi bi-fire text-warning"></i> Gás Encanado</span><?php endif; ?>
                                <?php if($im['possui_moveis_planejados']): ?><span class="feature-icon"><i class="bi bi-cabinet"></i> Planejados</span><?php endif; ?>
                                <?php if($im['mobiliado']): ?><span class="feature-icon"><i class="bi bi-house-door"></i> Mobiliado</span><?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-auto pt-3 border-top">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="view.php?id=<?= $im['id'] ?>" class="btn btn-primary w-100 btn-sm shadow-sm">Detalhes</a>
                                </div>
                                <div class="col-6">
                                    <a href="../despesas/list.php?imovel_id=<?= $im['id'] ?>" class="btn btn-outline-success w-100 btn-sm shadow-sm">
                                        <i class="bi bi-cash-stack"></i> Despesas
                                    </a>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                                    <div class="btn-group">
                                        <a href="form.php?id=<?= $im['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <a href="../visitas/list.php?imovel_id=<?= $im['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Visitas"><i class="bi bi-calendar-check"></i></a>
                                        <button class="btn btn-sm btn-outline-danger btn-excluir" data-id="<?= $im['id'] ?>"><i class="bi bi-trash"></i></button>
                                    </div>
                                    <span class="text-muted small"><i class="bi bi-eye"></i> <?= $im['total_visitas'] ?></span>
                                </div>
                            </div>
                            <!-- Botão para abrir o link_site, se existir -->
                            <?php if(!empty($im['link_site'])): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <a href="<?= htmlspecialchars($im['link_site']) ?>" target="_blank" class="btn btn-outline-info w-100 btn-sm">
                                        <i class="bi bi-link-45deg"></i> Acessar Site do Imóvel
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-octagon text-danger display-4"></i>
                <h4 class="mt-3 fw-bold">Deseja remover este imóvel?</h4>
                <p class="text-muted">A captação será movida para a lixeira.</p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <a id="btnConfirmarExcluir" href="#" class="btn btn-danger px-4 shadow-sm">Remover agora</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-excluir').forEach(btn => {
    btn.onclick = function() {
        const id = this.dataset.id;
        document.getElementById('btnConfirmarExcluir').href = `delete.php?id=${id}`;
        new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>