<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();
$u = current_user();

$date = valid_date($_GET['date'] ?? null, date('Y-m-d'));

// Un vendeur ne voit que son propre rapport ; l'admin peut filtrer par vendeur (ou voir tout le monde).
$vendeurId = null;
if (!has_role('admin')) {
    $vendeurId = (int)$u['id'];
} elseif (!empty($_GET['vendeur_id'])) {
    $vendeurId = (int)$_GET['vendeur_id'];
}

$vendeurs = has_role('admin') ? $pdo->query("SELECT id, full_name FROM users WHERE actif=1 ORDER BY full_name")->fetchAll() : [];

// --- Ventes validées créées ce jour ---
$sql = "SELECT v.*, c.nom AS client_nom, c.prenom AS client_prenom FROM ventes v LEFT JOIN clients c ON c.id = v.client_id
        WHERE DATE(v.created_at) = ? AND v.statut = 'validee'";
$params = [$date];
if ($vendeurId) { $sql .= " AND v.user_id = ?"; $params[] = $vendeurId; }
$sql .= " ORDER BY v.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventesJour = $stmt->fetchAll();

$nbVentes = count($ventesJour);
$totalBrut = 0; $totalRemise = 0; $totalNet = 0; $nbCredits = 0; $totalCreances = 0;
foreach ($ventesJour as $v) {
    $totalBrut += (float)$v['montant_brut'];
    $totalRemise += (float)$v['remise_montant'];
    $totalNet += (float)$v['montant_total'];
    if ($v['statut_paiement'] !== 'payee') {
        $nbCredits++;
        $totalCreances += (float)$v['montant_total'] - (float)$v['montant_paye'];
    }
}

// --- Encaissements du jour (paiements + versements), par mode de paiement ---
$sql = "SELECT vp.mode_paiement, SUM(vp.montant) AS total, COUNT(*) AS nb
        FROM vente_paiements vp
        JOIN ventes v ON v.id = vp.vente_id
        WHERE DATE(vp.created_at) = ? AND v.statut = 'validee'";
$params = [$date];
if ($vendeurId) { $sql .= " AND vp.user_id = ?"; $params[] = $vendeurId; }
$sql .= " GROUP BY vp.mode_paiement ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$encaissements = $stmt->fetchAll();
$totalEncaisse = (float)array_sum(array_column($encaissements, 'total'));

// --- Recouvrements : versements reçus ce jour sur des ventes à crédit des jours précédents ---
$sql = "SELECT vp.montant, vp.mode_paiement, vp.created_at, vp.note, v.numero_facture, v.id AS vente_id, c.nom AS client_nom, c.prenom AS client_prenom
        FROM vente_paiements vp JOIN ventes v ON v.id = vp.vente_id LEFT JOIN clients c ON c.id = v.client_id
        WHERE DATE(vp.created_at) = ? AND DATE(v.created_at) < ? AND v.statut = 'validee'";
$params = [$date, $date];
if ($vendeurId) { $sql .= " AND vp.user_id = ?"; $params[] = $vendeurId; }
$sql .= " ORDER BY vp.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recouvrements = $stmt->fetchAll();
$totalRecouvre = (float)array_sum(array_column($recouvrements, 'montant'));

// --- Ventes annulées ce jour ---
$sql = "SELECT COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS total FROM ventes WHERE DATE(created_at) = ? AND statut = 'annulee'";
$params = [$date];
if ($vendeurId) { $sql .= " AND user_id = ?"; $params[] = $vendeurId; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$annulees = $stmt->fetch();

$vNom = null;
if ($vendeurId) {
    foreach ($vendeurs as $vv) {
        if ((int)$vv['id'] === $vendeurId) $vNom = $vv['full_name'];
    }
    $vNom = $vNom ?? $u['full_name'];
}
$jourPrec = date('Y-m-d', strtotime($date . ' -1 day'));
$jourSuiv = date('Y-m-d', strtotime($date . ' +1 day'));
$lienJour = function (string $d) use ($vendeurId): string {
    return '?' . http_build_query(array_filter(['date' => $d, 'vendeur_id' => has_role('admin') ? $vendeurId : null]));
};

$pageTitle = 'Rapport de caisse';
$activeMenu = 'rapport_caisse';
$pageScripts = ['assets/js/chart.umd.min.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end" data-no-loading>
            <div class="btn-group" role="group" aria-label="Navigation par jour">
                <a href="<?= e($lienJour($jourPrec)) ?>" class="btn btn-outline-secondary" title="Jour précédent"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="<?= e($lienJour(date('Y-m-d'))) ?>" class="btn btn-outline-secondary <?= $date === date('Y-m-d') ? 'active' : '' ?>">Aujourd'hui</a>
                <a href="<?= e($lienJour($jourSuiv)) ?>" class="btn btn-outline-secondary <?= $date >= date('Y-m-d') ? 'disabled' : '' ?>" title="Jour suivant"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            <div>
                <label class="form-label small" for="rdate">Date</label>
                <input type="date" id="rdate" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
            </div>
            <?php if (has_role('admin')): ?>
            <div>
                <label class="form-label small" for="rvendeur">Vendeur</label>
                <select id="rvendeur" name="vendeur_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les vendeurs</option>
                    <?php foreach ($vendeurs as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= $vendeurId === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="ms-auto">
                <button type="button" class="btn btn-sp-primary" data-sp-print><i class="fa-solid fa-print me-1"></i>Imprimer le rapport</button>
            </div>
        </form>
    </div>
</div>

<div id="ticketZ">
    <div class="text-center mb-4">
        <div class="small-caps">Rapport de caisse · Ticket Z</div>
        <h2 class="mb-1" style="font-size:1.7rem;"><?= e(get_param('nom_entreprise', APP_NAME)) ?></h2>
        <div class="text-muted"><?= e(fmt_date_fr_long($date)) ?><?= $vNom ? ' · Vendeur : ' . e($vNom) : ' · Tous les vendeurs' ?></div>
        <hr class="gold-rule mx-auto mt-2 mb-0">
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-amber"><div><div class="kpi-value" data-countup="<?= $nbVentes ?>"><?= $nbVentes ?></div><div class="kpi-label">Ventes du jour</div></div><div class="kpi-icon"><i class="fa-solid fa-cash-register"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-navy"><div><div class="kpi-value" data-countup="<?= $totalNet ?>" data-format="money"><?= fmt_money_html($totalNet) ?></div><div class="kpi-label">Chiffre d'affaires net</div></div><div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-success"><div><div class="kpi-value" data-countup="<?= $totalEncaisse ?>" data-format="money"><?= fmt_money_html($totalEncaisse) ?></div><div class="kpi-label">Total encaissé</div></div><div class="kpi-icon"><i class="fa-solid fa-coins"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-info"><div><div class="kpi-value" data-countup="<?= $totalCreances ?>" data-format="money"><?= fmt_money_html($totalCreances) ?></div><div class="kpi-label"><?= $nbCredits ?> vente(s) à crédit</div></div><div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="sp-card mb-3">
                <div class="sp-card-header"><h6><span class="sp-card-icon"><i class="fa-solid fa-calculator"></i></span>Détail du chiffre d'affaires</h6></div>
                <div class="sp-card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="text-muted">Sous-total brut</td><td class="text-end tabular"><?= fmt_money($totalBrut) ?></td></tr>
                        <tr><td class="text-muted">Remises accordées</td><td class="text-end text-danger tabular">- <?= fmt_money($totalRemise) ?></td></tr>
                        <tr class="border-top"><td class="fw-bold">Total net</td><td class="text-end fw-bold fs-5 font-display tabular"><?= fmt_money($totalNet) ?></td></tr>
                        <tr><td class="text-muted">dont non encaissé (crédit)</td><td class="text-end tabular"><?= fmt_money($totalCreances) ?></td></tr>
                        <tr><td class="text-muted">Recouvrements (anciennes créances)</td><td class="text-end text-success tabular">+ <?= fmt_money($totalRecouvre) ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="sp-card">
                <div class="sp-card-header"><h6><span class="sp-card-icon tone-danger"><i class="fa-solid fa-ban"></i></span>Ventes annulées</h6></div>
                <div class="sp-card-body">
                    <p class="mb-0"><?= (int)$annulees['nb'] ?> vente(s) annulée(s) ce jour, pour un montant de <strong><?= fmt_money((float)$annulees['total']) ?></strong>.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="sp-card h-100">
                <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-wallet"></i></span>Encaissements par mode de paiement</h6></div>
                <div class="sp-card-body">
                    <?php if (empty($encaissements)): ?>
                        <?= empty_state('fa-coins', 'Aucun encaissement', 'Aucun paiement n\'a été reçu ce jour.', '', 'py-3') ?>
                    <?php else: ?>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="sp-chart-wrap" style="height:190px;"><canvas id="chartModes" aria-label="Répartition des encaissements"></canvas></div>
                        </div>
                        <div class="col-md-7">
                            <table class="table table-sp table-sm mb-0">
                                <thead><tr><th>Mode</th><th class="text-center">Nb</th><th class="text-end">Montant</th></tr></thead>
                                <tbody>
                                <?php foreach ($encaissements as $i => $enc): ?>
                                    <tr>
                                        <td><i class="fa-solid <?= e(mode_paiement_icon($enc['mode_paiement'])) ?> me-2 text-muted"></i><?= e(mode_paiement_label($enc['mode_paiement'])) ?></td>
                                        <td class="text-center"><?= (int)$enc['nb'] ?></td>
                                        <td class="text-end fw-semibold tabular"><?= fmt_money((float)$enc['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot><tr><td>Total</td><td></td><td class="text-end tabular"><?= fmt_money($totalEncaisse) ?></td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recouvrements)): ?>
    <div class="sp-card mt-3">
        <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-hand-holding-dollar"></i></span>Recouvrements du jour (<?= count($recouvrements) ?>)</h6></div>
        <div class="sp-card-body p-0">
            <div class="table-responsive">
            <table class="table table-sp mb-0">
                <thead><tr><th>Heure</th><th>Facture</th><th>Client</th><th>Mode</th><th class="text-end">Montant</th></tr></thead>
                <tbody>
                <?php foreach ($recouvrements as $r): ?>
                    <tr>
                        <td class="tabular"><?= fmt_date($r['created_at'], 'H:i') ?></td>
                        <td><code><?= e($r['numero_facture']) ?></code></td>
                        <td><?= e(trim(($r['client_nom'] ?? '-') . ' ' . ($r['client_prenom'] ?? ''))) ?><?php if ($r['note']): ?><div class="sp-cell-sub"><?= e($r['note']) ?></div><?php endif; ?></td>
                        <td><?= e(mode_paiement_label($r['mode_paiement'])) ?></td>
                        <td class="text-end fw-semibold text-success tabular"><?= fmt_money((float)$r['montant']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sp-card mt-3">
        <div class="sp-card-header"><h6><span class="sp-card-icon"><i class="fa-solid fa-receipt"></i></span>Détail des ventes du jour (<?= $nbVentes ?>)</h6></div>
        <div class="sp-card-body p-0">
            <?php if (empty($ventesJour)): ?>
                <?= empty_state('fa-receipt', 'Aucune vente ce jour', '', '', 'py-3') ?>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sp mb-0">
                <thead><tr><th>N° facture</th><th>Heure</th><th>Client</th><th class="text-end">Montant</th><th>Paiement</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($ventesJour as $v): ?>
                    <tr>
                        <td><code><?= e($v['numero_facture']) ?></code></td>
                        <td class="tabular"><?= fmt_date($v['created_at'], 'H:i') ?></td>
                        <td><?= e($v['client_nom'] ? trim($v['client_nom'] . ' ' . ($v['client_prenom'] ?? '')) : 'Client de passage') ?></td>
                        <td class="text-end fw-semibold tabular"><?= fmt_money((float)$v['montant_total']) ?></td>
                        <td><?= e(mode_paiement_label($v['mode_paiement'])) ?></td>
                        <td><?= vente_statut_badges($v) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3">Total</td><td class="text-end tabular"><?= fmt_money($totalNet) ?></td><td colspan="2"></td></tr></tfoot>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="print-only mt-4 small text-muted text-center">Rapport édité le <?= date('d/m/Y à H:i') ?> par <?= e($u['full_name']) ?> — Signature : ____________________</div>
</div>

<?php if (!empty($encaissements)): ?>
<script type="application/json" id="rcData"><?= js_json(array_map(fn($e) => ['label' => mode_paiement_label($e['mode_paiement']), 'total' => (float)$e['total']], $encaissements)) ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var data = JSON.parse(document.getElementById('rcData').textContent);
    SP.charts.create(document.getElementById('chartModes'), function (c) {
        return {
            type: 'doughnut',
            data: { labels: data.map(function (d) { return d.label; }), datasets: [{ data: data.map(function (d) { return d.total; }), backgroundColor: SP.charts.palette(), borderColor: c.surface, borderWidth: 3, hoverOffset: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ' : ' + SP.money(ctx.parsed); } } } } }
        };
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
