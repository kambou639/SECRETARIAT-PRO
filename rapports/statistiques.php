<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$debut = clean_input($_GET['debut'] ?? date('Y-m-01'));
$fin = clean_input($_GET['fin'] ?? date('Y-m-d'));

// Ventes par jour sur la période
$stmt = $pdo->prepare("SELECT DATE(created_at) d, SUM(montant_total) total, COUNT(*) nb
    FROM ventes WHERE statut='validee' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d");
$stmt->execute([$debut, $fin]);
$ventesJour = $stmt->fetchAll();

// Top articles vendus
$stmt = $pdo->prepare("
    SELECT a.nom, SUM(d.quantite) qte, SUM(d.sous_total) total
    FROM vente_details d JOIN ventes v ON v.id=d.vente_id JOIN articles a ON a.id=d.article_id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY d.article_id ORDER BY qte DESC LIMIT 8
");
$stmt->execute([$debut, $fin]);
$topArticles = $stmt->fetchAll();

// Répartition par mode de paiement
$stmt = $pdo->prepare("SELECT mode_paiement, SUM(montant_total) total FROM ventes WHERE statut='validee' AND DATE(created_at) BETWEEN ? AND ? GROUP BY mode_paiement");
$stmt->execute([$debut, $fin]);
$paiements = $stmt->fetchAll();

// Répartition par catégorie
$stmt = $pdo->prepare("
    SELECT COALESCE(c.nom,'Sans catégorie') nom, SUM(d.sous_total) total
    FROM vente_details d JOIN ventes v ON v.id=d.vente_id JOIN articles a ON a.id=d.article_id
    LEFT JOIN categories c ON c.id=a.categorie_id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total DESC
");
$stmt->execute([$debut, $fin]);
$parCategorie = $stmt->fetchAll();

$totalPeriode = array_sum(array_column($ventesJour, 'total'));
$nbVentesPeriode = array_sum(array_column($ventesJour, 'nb'));

$pageTitle = 'Statistiques';
$activeMenu = 'rapports';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small fw-semibold">Du</label><input type="date" name="debut" class="form-control" value="<?= e($debut) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Au</label><input type="date" name="fin" class="form-control" value="<?= e($fin) ?>"></div>
            <div class="col-md-3"><button class="btn btn-sp-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrer</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="kpi-card kpi-amber"><div><div class="kpi-value"><?= fmt_money($totalPeriode) ?></div><div class="kpi-label">Chiffre d'affaires période</div></div><div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-navy"><div><div class="kpi-value"><?= $nbVentesPeriode ?></div><div class="kpi-label">Ventes réalisées</div></div><div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-info"><div><div class="kpi-value"><?= $nbVentesPeriode > 0 ? fmt_money($totalPeriode / $nbVentesPeriode) : fmt_money(0) ?></div><div class="kpi-label">Panier moyen</div></div><div class="kpi-icon"><i class="fa-solid fa-basket-shopping"></i></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6>Évolution des ventes</h6></div>
            <div class="sp-card-body"><canvas id="chartEvol" height="100"></canvas></div>
        </div>
        <div class="sp-card">
            <div class="sp-card-header"><h6>Top articles vendus</h6></div>
            <div class="sp-card-body p-0">
                <table class="table table-sp mb-0">
                    <thead><tr><th>Article</th><th class="text-end">Qté vendue</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($topArticles as $a): ?>
                        <tr><td><?= e($a['nom']) ?></td><td class="text-end"><?= (int)$a['qte'] ?></td><td class="text-end"><?= fmt_money((float)$a['total']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($topArticles)): ?><tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée sur la période.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6>Par mode de paiement</h6></div>
            <div class="sp-card-body"><canvas id="chartPaiement"></canvas></div>
        </div>
        <div class="sp-card">
            <div class="sp-card-header"><h6>Par catégorie</h6></div>
            <div class="sp-card-body"><canvas id="chartCategorie"></canvas></div>
        </div>
    </div>
</div>

<script src="<?= $ROOT ?>assets/js/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartEvol'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $ventesJour)) ?>,
        datasets: [{ label: 'CA', data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $ventesJour)) ?>, borderColor: '#6E1423', backgroundColor: 'rgba(110,20,35,.1)', fill: true, tension: .3 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('chartPaiement'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r) => ucfirst(str_replace('_',' ',$r['mode_paiement'])), $paiements)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $paiements)) ?>, backgroundColor: ['#B8863B','#6E1423','#4A6E82','#3F5A45','#B3261E'] }]
    }
});
new Chart(document.getElementById('chartCategorie'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nom'], $parCategorie)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $parCategorie)) ?>, backgroundColor: ['#B8863B','#6E1423','#4A6E82','#3F5A45','#B3261E','#6B6259','#A3762F'] }]
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
