<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();
$u = current_user();

$date = clean_input($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Un vendeur ne voit que son propre rapport ; l'admin peut filtrer par vendeur (ou voir tout le monde).
$vendeurId = null;
if (!has_role('admin')) {
    $vendeurId = (int)$u['id'];
} elseif (!empty($_GET['vendeur_id'])) {
    $vendeurId = (int)$_GET['vendeur_id'];
}

$vendeurs = has_role('admin') ? $pdo->query("SELECT id, full_name FROM users WHERE actif=1 ORDER BY full_name")->fetchAll() : [];

// --- Ventes validées créées ce jour ---
$sql = "SELECT v.*, c.nom AS client_nom FROM ventes v LEFT JOIN clients c ON c.id = v.client_id
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
$totalEncaisse = array_sum(array_column($encaissements, 'total'));

// --- Ventes annulées ce jour ---
$sql = "SELECT COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS total FROM ventes WHERE DATE(created_at) = ? AND statut = 'annulee'";
$params = [$date];
if ($vendeurId) { $sql .= " AND user_id = ?"; $params[] = $vendeurId; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$annulees = $stmt->fetch();

$labelsPaiement = [
    'especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'carte' => 'Carte bancaire',
    'virement' => 'Virement', 'autre' => 'Autre',
];

$pageTitle = 'Rapport de caisse';
$activeMenu = 'rapport_caisse';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            </div>
            <?php if (has_role('admin')): ?>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Vendeur</label>
                <select name="vendeur_id" class="form-select">
                    <option value="">Tous les vendeurs</option>
                    <?php foreach ($vendeurs as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $vendeurId == $v['id'] ? 'selected' : '' ?>><?= e($v['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <button class="btn btn-sp-primary w-100"><i class="fa-solid fa-filter me-1"></i>Afficher</button>
            </div>
        </form>
    </div>
</div>

<div id="ticketZ">
    <div class="text-center mb-4">
        <h4 class="fw-bold mb-0" style="color:var(--sp-navy);"><?= e(get_param('nom_entreprise', APP_NAME)) ?></h4>
        <div class="text-muted">Rapport de caisse - <?= fmt_date($date) ?></div>
        <?php if ($vendeurId): ?>
            <?php $vNom = array_values(array_filter($vendeurs, fn($vv) => $vv['id'] == $vendeurId))[0]['full_name'] ?? $u['full_name']; ?>
            <div class="text-muted small">Vendeur : <?= e($vNom) ?></div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-amber"><div><div class="kpi-value"><?= $nbVentes ?></div><div class="kpi-label">Ventes du jour</div></div><div class="kpi-icon"><i class="fa-solid fa-cash-register"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-navy"><div><div class="kpi-value" style="font-size:1.3rem;"><?= fmt_money($totalNet) ?></div><div class="kpi-label">Chiffre d'affaires net</div></div><div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-success"><div><div class="kpi-value" style="font-size:1.3rem;"><?= fmt_money($totalEncaisse) ?></div><div class="kpi-label">Total encaissé</div></div><div class="kpi-icon"><i class="fa-solid fa-coins"></i></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-info"><div><div class="kpi-value" style="font-size:1.3rem;"><?= fmt_money($totalCreances) ?></div><div class="kpi-label"><?= $nbCredits ?> vente(s) à crédit</div></div><div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="sp-card mb-3">
                <div class="sp-card-header"><h6>Détail du chiffre d'affaires</h6></div>
                <div class="sp-card-body">
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted">Sous-total brut</td><td class="text-end"><?= fmt_money($totalBrut) ?></td></tr>
                        <tr><td class="text-muted">Remises accordées</td><td class="text-end text-danger">- <?= fmt_money($totalRemise) ?></td></tr>
                        <tr class="border-top"><td class="fw-bold">Total net</td><td class="text-end fw-bold fs-5"><?= fmt_money($totalNet) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="sp-card">
                <div class="sp-card-header"><h6>Ventes annulées</h6></div>
                <div class="sp-card-body">
                    <p class="mb-0"><?= (int)$annulees['nb'] ?> vente(s) annulée(s) ce jour, pour un montant de <?= fmt_money((float)$annulees['total']) ?>.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="sp-card">
                <div class="sp-card-header"><h6>Encaissements par mode de paiement</h6></div>
                <div class="sp-card-body p-0">
                    <?php if (empty($encaissements)): ?>
                        <div class="sp-empty-state py-3"><i class="fa-solid fa-coins"></i><p class="mb-0">Aucun encaissement ce jour.</p></div>
                    <?php else: ?>
                    <table class="table table-sp mb-0">
                        <thead><tr><th>Mode de paiement</th><th class="text-center">Nb</th><th class="text-end">Montant</th></tr></thead>
                        <tbody>
                        <?php foreach ($encaissements as $enc): ?>
                            <tr>
                                <td><?= e($labelsPaiement[$enc['mode_paiement']] ?? ucfirst($enc['mode_paiement'])) ?></td>
                                <td class="text-center"><?= (int)$enc['nb'] ?></td>
                                <td class="text-end fw-semibold"><?= fmt_money((float)$enc['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="border-top"><td class="fw-bold">Total</td><td></td><td class="text-end fw-bold fs-5"><?= fmt_money($totalEncaisse) ?></td></tr>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="sp-card mt-3">
        <div class="sp-card-header"><h6>Détail des ventes du jour (<?= $nbVentes ?>)</h6></div>
        <div class="sp-card-body p-0">
            <?php if (empty($ventesJour)): ?>
                <div class="sp-empty-state py-3"><i class="fa-solid fa-receipt"></i><p class="mb-0">Aucune vente ce jour.</p></div>
            <?php else: ?>
            <table class="table table-sp mb-0">
                <thead><tr><th>N° Facture</th><th>Heure</th><th>Client</th><th class="text-end">Montant</th><th>Paiement</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($ventesJour as $v): ?>
                    <tr>
                        <td><code><?= e($v['numero_facture']) ?></code></td>
                        <td><?= fmt_date($v['created_at'], 'H:i') ?></td>
                        <td><?= e($v['client_nom'] ?? 'Client de passage') ?></td>
                        <td class="text-end fw-semibold"><?= fmt_money((float)$v['montant_total']) ?></td>
                        <td><?= e(ucfirst(str_replace('_',' ',$v['mode_paiement']))) ?></td>
                        <td>
                            <?php if ($v['statut_paiement'] === 'partielle'): ?>
                                <span class="badge bg-warning text-dark">Partiel</span>
                            <?php elseif ($v['statut_paiement'] === 'impayee'): ?>
                                <span class="badge bg-secondary">Impayée</span>
                            <?php else: ?>
                                <span class="badge bg-success">Payée</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="text-center mt-3 no-print">
    <button class="btn btn-sp-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer le rapport</button>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
