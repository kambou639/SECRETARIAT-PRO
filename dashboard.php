<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = Database::getConnection();
$role = current_user()['role'];

// --- KPI ---
$kpi = [
    'ventes_jour' => 0, 'nb_ventes_jour' => 0,
    'articles_actifs' => 0, 'articles_alerte' => 0,
    'clients' => 0,
];

$stmt = $pdo->query("SELECT COALESCE(SUM(montant_total),0) AS total, COUNT(*) AS nb FROM ventes WHERE statut='validee' AND DATE(created_at) = CURDATE()");
$row = $stmt->fetch();
$kpi['ventes_jour'] = (float)$row['total'];
$kpi['nb_ventes_jour'] = (int)$row['nb'];

$kpi['articles_actifs'] = (int)$pdo->query("SELECT COUNT(*) c FROM articles WHERE actif=1")->fetch()['c'];
$kpi['articles_alerte'] = (int)$pdo->query("SELECT COUNT(*) c FROM articles WHERE actif=1 AND type='produit' AND stock <= seuil_alerte")->fetch()['c'];
$kpi['clients'] = (int)$pdo->query("SELECT COUNT(*) c FROM clients")->fetch()['c'];

// --- Ventes des 7 derniers jours ---
$stmt = $pdo->query("
    SELECT DATE(created_at) d, SUM(montant_total) total
    FROM ventes
    WHERE statut='validee' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
$ventesParJour = [];
foreach ($stmt->fetchAll() as $r) {
    $ventesParJour[$r['d']] = (float)$r['total'];
}
$labelsChart = []; $dataChart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labelsChart[] = date('d/m', strtotime($d));
    $dataChart[] = $ventesParJour[$d] ?? 0;
}

// --- Articles en alerte de stock ---
$articlesAlerte = $pdo->query("SELECT nom, code, stock, seuil_alerte FROM articles WHERE actif=1 AND type='produit' AND stock <= seuil_alerte ORDER BY stock ASC LIMIT 6")->fetchAll();

$pageTitle = 'Tableau de bord';
$activeMenu = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="kpi-card kpi-amber">
            <div><div class="kpi-value"><?= fmt_money($kpi['ventes_jour']) ?></div><div class="kpi-label"><?= $kpi['nb_ventes_jour'] ?> vente(s) aujourd'hui</div></div>
            <div class="kpi-icon"><i class="fa-solid fa-cash-register"></i></div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="kpi-card kpi-navy">
            <div><div class="kpi-value"><?= $kpi['articles_actifs'] ?></div><div class="kpi-label"><?= $kpi['articles_alerte'] ?> en alerte stock</div></div>
            <div class="kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="kpi-card kpi-success">
            <div><div class="kpi-value"><?= $kpi['clients'] ?></div><div class="kpi-label">Clients enregistrés</div></div>
            <div class="kpi-icon"><i class="fa-solid fa-address-book"></i></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Ventes des 7 derniers jours</h6></div>
            <div class="sp-card-body"><canvas id="chartVentes" height="110"></canvas></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="sp-card">
            <div class="sp-card-header"><h6><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Alertes de stock</h6><a href="articles/liste.php" class="small">Gérer</a></div>
            <div class="sp-card-body">
                <?php if (empty($articlesAlerte)): ?>
                    <p class="text-muted mb-0 small">Aucune alerte de stock actuellement.</p>
                <?php else: ?>
                    <?php foreach ($articlesAlerte as $a): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold small"><?= e($a['nom']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= e($a['code']) ?></div>
                            </div>
                            <span class="badge <?= $a['stock'] == 0 ? 'badge-stock-out' : 'badge-stock-low' ?>">
                                Stock : <?= (int)$a['stock'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= $ROOT ?>assets/js/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartVentes'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsChart) ?>,
        datasets: [{
            label: 'Ventes (<?= e(get_param('devise','FCFA')) ?>)',
            data: <?= json_encode($dataChart) ?>,
            backgroundColor: '#B8863B',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
