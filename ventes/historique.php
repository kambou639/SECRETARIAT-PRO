<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

$dateDebut = clean_input($_GET['debut'] ?? date('Y-m-01'));
$dateFin = clean_input($_GET['fin'] ?? date('Y-m-d'));
$statut = clean_input($_GET['statut'] ?? '');

$sql = "SELECT v.*, u.full_name AS vendeur_nom, c.nom AS client_nom
        FROM ventes v LEFT JOIN users u ON u.id=v.user_id LEFT JOIN clients c ON c.id=v.client_id
        WHERE DATE(v.created_at) BETWEEN ? AND ?";
$params = [$dateDebut, $dateFin];
if ($statut !== '') {
    $sql .= " AND v.statut = ?";
    $params[] = $statut;
}
$sql .= " ORDER BY v.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventes = $stmt->fetchAll();

$totalPeriode = 0;
foreach ($ventes as $v) {
    if ($v['statut'] === 'validee') $totalPeriode += (float)$v['montant_total'];
}

$pageTitle = 'Historique des ventes';
$activeMenu = 'ventes';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Du</label>
                <input type="date" name="debut" class="form-control" value="<?= e($dateDebut) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Au</label>
                <input type="date" name="fin" class="form-control" value="<?= e($dateFin) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <option value="validee" <?= $statut==='validee'?'selected':'' ?>>Validée</option>
                    <option value="annulee" <?= $statut==='annulee'?'selected':'' ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sp-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6>Ventes (<?= count($ventes) ?>) - Total période : <?= fmt_money($totalPeriode) ?></h6>
        <a href="../import_export/export_ventes.php?debut=<?= urlencode($dateDebut) ?>&fin=<?= urlencode($dateFin) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-file-excel me-1"></i>Export Excel
        </a>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($ventes)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-receipt"></i><p>Aucune vente sur cette période.</p></div>
        <?php else: ?>
        <table class="table table-sp mb-0">
            <thead><tr><th>N° Facture</th><th>Date</th><th>Client</th><th>Vendeur</th><th class="text-end">Montant</th><th>Paiement</th><th>Statut</th><th class="text-end no-print">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($ventes as $v): ?>
                <tr>
                    <td><code><?= e($v['numero_facture']) ?></code></td>
                    <td><?= fmt_datetime($v['created_at']) ?></td>
                    <td><?= e($v['client_nom'] ?? 'Client de passage') ?></td>
                    <td><?= e($v['vendeur_nom']) ?></td>
                    <td class="text-end fw-semibold">
                        <?= fmt_money((float)$v['montant_total']) ?>
                        <?php if ((float)($v['remise_montant'] ?? 0) > 0): ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis ms-1" title="Remise appliquée : -<?= fmt_money((float)$v['remise_montant']) ?>"><i class="fa-solid fa-tag"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(ucfirst(str_replace('_',' ',$v['mode_paiement']))) ?></td>
                    <td>
                        <?php if ($v['statut'] === 'validee'): ?>
                            <span class="badge bg-success">Validée</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Annulée</span>
                        <?php endif; ?>
                        <?php if ($v['statut'] === 'validee' && ($v['statut_paiement'] ?? 'payee') !== 'payee'): ?>
                            <?php if ($v['statut_paiement'] === 'partielle'): ?>
                                <span class="badge bg-warning text-dark">Partiel</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Impayée</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end no-print">
                        <button type="button" class="btn btn-sm btn-outline-primary" title="Voir les détails" onclick="showVenteDetails(<?= $v['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                        <a href="facture.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Facture" target="_blank"><i class="fa-solid fa-print"></i></a>
                        <?php if ($v['statut'] === 'validee' && has_role('admin')): ?>
                        <a href="annuler.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-danger" title="Annuler" data-confirm="Annuler cette vente et remettre le stock ?"><i class="fa-solid fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
