<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$typeFilter = in_array($_GET['type'] ?? '', ['produit', 'service'], true) ? $_GET['type'] : '';
$stockFilter = in_array($_GET['stock'] ?? '', ['alerte', 'rupture', 'ok'], true) ? $_GET['stock'] : '';
$actifFilter = in_array($_GET['actif'] ?? '', ['1', '0'], true) ? $_GET['actif'] : '';

$sql = "SELECT a.*, c.nom AS categorie_nom,
        (SELECT COUNT(*) FROM paliers_prix p WHERE p.article_id = a.id) AS nb_paliers
        FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $sql .= " AND (a.nom LIKE ? OR a.code LIKE ? OR a.description LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($catFilter > 0) { $sql .= " AND a.categorie_id = ?"; $params[] = $catFilter; }
if ($typeFilter !== '') { $sql .= " AND a.type = ?"; $params[] = $typeFilter; }
if ($stockFilter === 'alerte') { $sql .= " AND a.type = 'produit' AND a.stock <= a.seuil_alerte"; }
elseif ($stockFilter === 'rupture') { $sql .= " AND a.type = 'produit' AND a.stock <= 0"; }
elseif ($stockFilter === 'ok') { $sql .= " AND (a.type = 'service' OR a.stock > a.seuil_alerte)"; }
if ($actifFilter !== '') { $sql .= " AND a.actif = ?"; $params[] = (int)$actifFilter; }
$sql .= " ORDER BY a.actif DESC, a.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$stats = $pdo->query("SELECT
        SUM(actif = 1) AS actifs, SUM(actif = 0) AS inactifs,
        SUM(CASE WHEN actif = 1 AND type = 'produit' THEN stock * prix_achat ELSE 0 END) AS valeur_achat,
        SUM(CASE WHEN actif = 1 AND type = 'produit' THEN stock * prix_vente ELSE 0 END) AS valeur_vente,
        SUM(CASE WHEN actif = 1 AND type = 'produit' AND stock <= seuil_alerte AND stock > 0 THEN 1 ELSE 0 END) AS alertes,
        SUM(CASE WHEN actif = 1 AND type = 'produit' AND stock <= 0 THEN 1 ELSE 0 END) AS ruptures,
        SUM(CASE WHEN type = 'service' THEN 1 ELSE 0 END) AS services
    FROM articles")->fetch();
$gere = has_role('admin', 'secretaire');
$filtresActifs = ($search !== '') + ($catFilter > 0) + ($typeFilter !== '') + ($stockFilter !== '') + ($actifFilter !== '');

$pageTitle = 'Catalogue articles';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="sp-stat tone-primary"><div class="sp-stat-top"><span class="sp-stat-label">Articles actifs</span><span class="sp-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['actifs'] ?>"><?= (int)$stats['actifs'] ?></div><div class="sp-stat-sub"><?= (int)$stats['services'] ?> service(s) · <?= (int)$stats['inactifs'] ?> inactif(s)</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat"><div class="sp-stat-top"><span class="sp-stat-label">Valeur du stock</span><span class="sp-stat-icon"><i class="fa-solid fa-warehouse"></i></span></div><div class="sp-stat-value" data-countup="<?= (float)$stats['valeur_achat'] ?>" data-format="money"><?= fmt_money_html((float)$stats['valeur_achat']) ?></div><div class="sp-stat-sub">au prix d'achat · <?= fmt_money((float)$stats['valeur_vente']) ?> à la vente</div></div></div>
    <div class="col-6 col-xl-3"><a href="?stock=alerte" class="sp-stat tone-warning d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Stock faible</span><span class="sp-stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['alertes'] ?>"><?= (int)$stats['alertes'] ?></div><div class="sp-stat-sub">sous le seuil d'alerte</div></a></div>
    <div class="col-6 col-xl-3"><a href="?stock=rupture" class="sp-stat tone-danger d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Ruptures</span><span class="sp-stat-icon"><i class="fa-solid fa-box-open"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['ruptures'] ?>"><?= (int)$stats['ruptures'] ?></div><div class="sp-stat-sub">à réapprovisionner</div></a></div>
</div>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form class="row g-2 align-items-end" method="get" data-no-loading>
            <div class="col-md-12 col-xl-4">
                <label class="form-label small" for="aq">Recherche</label>
                <div class="sp-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="aq" name="q" class="form-control" placeholder="Nom, code ou description…" value="<?= e($search) ?>" data-sp-filter="#tableArticles"></div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small" for="acat">Catégorie</label>
                <select id="acat" name="cat" class="form-select" onchange="this.form.submit()">
                    <option value="0">Toutes</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small" for="atype">Type</label>
                <select id="atype" name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="produit" <?= $typeFilter === 'produit' ? 'selected' : '' ?>>Produits</option>
                    <option value="service" <?= $typeFilter === 'service' ? 'selected' : '' ?>>Services</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small" for="astock">Stock</label>
                <select id="astock" name="stock" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="alerte" <?= $stockFilter === 'alerte' ? 'selected' : '' ?>>En alerte</option>
                    <option value="rupture" <?= $stockFilter === 'rupture' ? 'selected' : '' ?>>En rupture</option>
                    <option value="ok" <?= $stockFilter === 'ok' ? 'selected' : '' ?>>Suffisant</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small" for="aactif">Statut</label>
                <select id="aactif" name="actif" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="1" <?= $actifFilter === '1' ? 'selected' : '' ?>>Actifs</option>
                    <option value="0" <?= $actifFilter === '0' ? 'selected' : '' ?>>Inactifs</option>
                </select>
            </div>
        </form>
    </div>
</div>

<form id="bulkForm" method="get" action="etiquettes_imprimer.php" target="_blank" data-no-loading>
<div class="sp-card">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon"><i class="fa-solid fa-boxes-stacked"></i></span>Articles <span class="badge bg-secondary" id="articlesCount"><?= count($articles) ?></span></h6>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($filtresActifs): ?><a href="liste.php" class="btn btn-sm btn-ghost"><i class="fa-solid fa-xmark me-1"></i>Effacer les filtres</a><?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-fullscreen-toggle="fs_articles" title="Plein écran"><i class="fa-solid fa-expand"></i></button>
            <?php if (has_role('admin', 'secretaire')): ?><a href="../import_export/index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-excel me-1"></i>Import / Export</a><?php endif; ?>
            <?php if ($gere): ?><a href="ajouter.php" class="btn btn-sm btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouvel article</a><?php endif; ?>
        </div>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($articles)): ?>
            <?= empty_state('fa-box-open', 'Aucun article trouvé', $filtresActifs ? 'Aucun article ne correspond à ces filtres.' : 'Commencez par créer votre catalogue.', $gere ? '<a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouvel article</a>' : '') ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sp mb-0" id="tableArticles" data-sp-sort>
            <thead><tr>
                <th style="width:36px;" class="no-print"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Tout sélectionner"></th>
                <th data-sort="text">Article</th>
                <th class="d-none d-md-table-cell">Type</th>
                <th class="text-end" data-sort="num">Prix de vente</th>
                <th class="text-end d-none d-lg-table-cell" data-sort="num">Marge</th>
                <th data-sort="num" style="min-width:140px;">Stock</th>
                <th class="text-end no-print">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($articles as $a):
                $isService = $a['type'] === 'service';
                $stock = (int)$a['stock'];
                $seuil = (int)$a['seuil_alerte'];
                $etat = $isService ? 'svc' : ($stock <= 0 ? 'out' : ($stock <= $seuil ? 'low' : 'ok'));
                $pv = (float)$a['prix_vente']; $pa = (float)$a['prix_achat'];
                $marge = $pv - $pa;
                $margePct = $pv > 0 && $pa > 0 ? $marge / $pv * 100 : null;
                $img = article_image_url($a['image']);
                $meterPct = $isService ? 0 : min(100, max(3, $stock / max(1, $seuil * 3) * 100)); ?>
                <tr class="<?= !$a['actif'] ? 'is-muted' : '' ?>" data-search="<?= e(mb_strtolower($a['nom'] . ' ' . $a['code'] . ' ' . ($a['categorie_nom'] ?? '') . ' ' . ($a['description'] ?? ''))) ?>">
                    <td class="no-print"><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= (int)$a['id'] ?>" aria-label="Sélectionner <?= e($a['nom']) ?>"></td>
                    <td data-value="<?= e($a['nom']) ?>">
                        <div class="sp-cell-flex">
                            <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="sp-img-thumb" loading="lazy"><?php else: ?><span class="sp-img-thumb placeholder"><i class="fa-solid <?= $isService ? 'fa-print' : 'fa-box' ?>"></i></span><?php endif; ?>
                            <div style="min-width:0;">
                                <div class="sp-cell-title"><?= e($a['nom']) ?>
                                    <?php if (!$a['actif']): ?><span class="sp-badge ms-1">Inactif</span><?php endif; ?>
                                    <?php if ((int)$a['nb_paliers'] > 0): ?><span class="sp-badge is-success ms-1" title="Tarifs dégressifs"><i class="fa-solid fa-layer-group"></i><?= (int)$a['nb_paliers'] ?></span><?php endif; ?>
                                </div>
                                <div class="sp-cell-sub"><code><?= e($a['code']) ?></code> · <?= e($a['categorie_nom'] ?? 'Sans catégorie') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= $isService ? '<span class="sp-badge is-info"><i class="fa-solid fa-print"></i>Service</span>' : '<span class="sp-badge is-outline"><i class="fa-solid fa-box"></i>Produit</span>' ?></td>
                    <td class="text-end text-nowrap" data-value="<?= $pv ?>"><strong><?= fmt_money($pv) ?></strong><div class="sp-cell-sub">/ <?= e($a['unite']) ?></div></td>
                    <td class="text-end d-none d-lg-table-cell text-nowrap" data-value="<?= $margePct ?? -1 ?>">
                        <?php if ($margePct === null): ?><span class="text-muted">—</span>
                        <?php else: ?><span class="sp-badge <?= $margePct >= 30 ? 'is-success' : ($margePct >= 10 ? 'is-warning' : 'is-danger') ?>"><?= number_format($margePct, 0, ',', ' ') ?> %</span><div class="sp-cell-sub"><?= fmt_money($marge) ?></div><?php endif; ?>
                    </td>
                    <td data-value="<?= $isService ? 999999 : $stock ?>">
                        <?php if ($isService): ?>
                            <span class="text-muted small"><i class="fa-solid fa-infinity me-1"></i>Sans stock</span>
                        <?php else: ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="sp-meter flex-grow-1 <?= $etat === 'out' ? 'is-out' : ($etat === 'low' ? 'is-low' : '') ?>"><span style="width:<?= round($meterPct) ?>%"></span></div>
                                <span class="badge <?= $etat === 'out' ? 'badge-stock-out' : ($etat === 'low' ? 'badge-stock-low' : 'badge-stock-ok') ?>"><?= $stock ?></span>
                            </div>
                            <div class="sp-cell-sub">seuil : <?= $seuil ?> <?= e($a['unite']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="td-actions no-print">
                        <a href="etiquettes.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Étiquette QR / code-barres"><i class="fa-solid fa-qrcode"></i></a>
                        <?php if ($gere): ?>
                        <a href="modifier.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" title="Plus d'actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (!$isService): ?><li><a class="dropdown-item" href="stock_ajuster.php?id=<?= (int)$a['id'] ?>"><i class="fa-solid fa-arrows-rotate"></i>Ajuster le stock</a></li><?php endif; ?>
                                <li><a class="dropdown-item" href="paliers.php?id=<?= (int)$a['id'] ?>"><i class="fa-solid fa-layer-group"></i>Tarifs dégressifs</a></li>
                                <?php if (has_role('admin')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="supprimer.php?id=<?= (int)$a['id'] ?>" data-method="post" data-confirm-type="danger"
                                       data-confirm="Supprimer définitivement l'article « <?= e($a['nom']) ?> » ? S'il a déjà été vendu, il sera simplement désactivé."><i class="fa-solid fa-trash"></i>Supprimer</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
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

<!-- Barre d'actions groupées -->
<div class="sp-bulkbar" id="bulkBar" aria-live="polite">
    <input type="hidden" name="prix" value="1">
    <span><strong id="bulkCount">0</strong> article(s) sélectionné(s)</span>
    <div class="sp-seg sp-seg-sm">
        <input type="radio" class="btn-check" name="type" id="bulkQr" value="qr" checked><label class="sp-seg-btn" for="bulkQr"><i class="fa-solid fa-qrcode"></i>QR</label>
        <input type="radio" class="btn-check" name="type" id="bulkBar128" value="barcode"><label class="sp-seg-btn" for="bulkBar128"><i class="fa-solid fa-barcode"></i>Code-barres</label>
    </div>
    <button type="submit" class="btn btn-sm btn-sp-amber"><i class="fa-solid fa-print me-1"></i>Imprimer les étiquettes</button>
    <button type="button" class="btn btn-sm btn-ghost" id="bulkClear" title="Tout désélectionner"><i class="fa-solid fa-xmark"></i></button>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bar = document.getElementById('bulkBar');
    var all = document.getElementById('checkAll');
    function checks() { return Array.prototype.slice.call(document.querySelectorAll('.row-check')); }
    function visibleChecks() { return checks().filter(function (c) { return c.closest('tr').style.display !== 'none'; }); }
    function update() {
        var n = checks().filter(function (c) { return c.checked; }).length;
        var vis = visibleChecks(), nVis = vis.filter(function (c) { return c.checked; }).length;
        document.getElementById('bulkCount').textContent = n;
        bar.classList.toggle('is-visible', n > 0);
        checks().forEach(function (c) { c.closest('tr').classList.toggle('table-active', c.checked); });
        if (all) {
            all.indeterminate = nVis > 0 && nVis < vis.length;
            all.checked = nVis > 0 && nVis === vis.length;
        }
    }
    document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('row-check')) update(); });
    if (all) all.addEventListener('change', function () {
        var on = all.checked;
        visibleChecks().forEach(function (c) { c.checked = on; });
        update();
    });
    document.getElementById('bulkClear').addEventListener('click', function () { checks().forEach(function (c) { c.checked = false; }); update(); });
    var search = document.getElementById('aq');
    if (search) search.addEventListener('sp:filtered', function (e) { document.getElementById('articlesCount').textContent = e.detail.visible; update(); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
