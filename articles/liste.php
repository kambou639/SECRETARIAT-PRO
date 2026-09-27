<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$typeFilter = in_array($_GET['type'] ?? '', ['produit','service'], true) ? $_GET['type'] : '';

$sql = "SELECT a.*, c.nom AS categorie_nom FROM articles a
        LEFT JOIN categories c ON c.id = a.categorie_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (a.nom LIKE ? OR a.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter > 0) {
    $sql .= " AND a.categorie_id = ?";
    $params[] = $catFilter;
}
if ($typeFilter !== '') {
    $sql .= " AND a.type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY a.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

$pageTitle = 'Catalogue articles';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control" placeholder="Rechercher un article ou un code..." value="<?= e($search) ?>" style="width:260px;">
        <select name="cat" class="form-select" style="width:200px;">
            <option value="0">Toutes catégories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-select" style="width:160px;">
            <option value="">Tous types</option>
            <option value="produit" <?= $typeFilter==='produit'?'selected':'' ?>>Produits</option>
            <option value="service" <?= $typeFilter==='service'?'selected':'' ?>>Services</option>
        </select>
        <button class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <?php if (has_role('admin', 'secretaire')): ?>
    <a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouvel article</a>
    <?php endif; ?>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6>Articles (<?= count($articles) ?>)</h6>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" data-fullscreen-toggle="fs_articles" title="Plein écran"><i class="fa-solid fa-expand"></i></button>
            <a href="../import_export/index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-excel me-1"></i>Import/Export</a>
        </div>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($articles)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-box-open"></i><p>Aucun article trouvé.</p></div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sp mb-0">
            <thead><tr>
                <th>Code</th><th>Nom</th><th>Catégorie</th><th>Type</th><th class="text-end">Prix vente</th>
                <th class="text-center">Stock</th><th>Unité</th><th class="text-end no-print">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($articles as $a):
                $badgeClass = $a['stock'] == 0 ? 'badge-stock-out' : ($a['stock'] <= $a['seuil_alerte'] ? 'badge-stock-low' : 'badge-stock-ok');
            ?>
                <tr>
                    <td><code><?= e($a['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($a['nom']) ?></td>
                    <td><?= e($a['categorie_nom'] ?? '-') ?></td>
                    <td><?= $a['type'] === 'service' ? '<span class="badge bg-info-subtle text-info-emphasis"><i class="fa-solid fa-print me-1"></i>Service</span>' : '<span class="badge bg-light text-dark border">Produit</span>' ?></td>
                    <td class="text-end"><?= fmt_money((float)$a['prix_vente']) ?> <span class="text-muted" style="font-size:.72rem;">/ <?= e($a['unite']) ?></span></td>
                    <td class="text-center">
                        <?php if ($a['type'] === 'service'): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            <span class="badge <?= $badgeClass ?>"><?= (int)$a['stock'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($a['unite']) ?></td>
                    <td class="text-end no-print">
                        <a href="etiquettes.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Étiquette"><i class="fa-solid fa-qrcode"></i></a>
                        <?php if (has_role('admin', 'secretaire')): ?>
                        <a href="modifier.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" data-confirm="Supprimer définitivement l'article « <?= e($a['nom']) ?> » ?"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
