<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM ventes v WHERE v.client_id=c.id AND v.statut='validee') AS nb_achats,
        (SELECT COALESCE(SUM(montant_total),0) FROM ventes v WHERE v.client_id=c.id AND v.statut='validee') AS total_achats
        FROM clients c WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY c.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <form class="d-flex gap-2" method="get">
        <input type="text" name="q" class="form-control" placeholder="Rechercher un client..." value="<?= e($search) ?>" style="width:280px;">
        <button class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouveau client</a>
</div>

<div class="sp-card">
    <div class="sp-card-header"><h6>Clients (<?= count($clients) ?>)</h6></div>
    <div class="sp-card-body p-0">
        <?php if (empty($clients)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-address-book"></i><p>Aucun client enregistré.</p></div>
        <?php else: ?>
        <table class="table table-sp mb-0">
            <thead><tr><th>Nom</th><th>Type</th><th>Téléphone</th><th>Email</th><th class="text-center">Achats</th><th class="text-end">Total dépensé</th><th class="text-end no-print">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['nom'] . ' ' . ($c['prenom'] ?? '')) ?></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($c['type'])) ?></span></td>
                    <td><?= e($c['telephone'] ?: '-') ?></td>
                    <td><?= e($c['email'] ?: '-') ?></td>
                    <td class="text-center"><?= (int)$c['nb_achats'] ?></td>
                    <td class="text-end"><?= fmt_money((float)$c['total_achats']) ?></td>
                    <td class="text-end no-print">
                        <a href="fiche.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Fiche"><i class="fa-solid fa-eye"></i></a>
                        <a href="modifier.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" data-confirm="Supprimer ce client ?"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
