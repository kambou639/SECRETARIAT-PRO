<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('admin', 'secretaire');
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = mb_substr(clean_input($_POST['nom'] ?? ''), 0, 100);
        $description = mb_substr(clean_input($_POST['description'] ?? ''), 0, 255);
        if ($nom === '') {
            $errors[] = 'Le nom de la catégorie est obligatoire.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM categories WHERE LOWER(nom) = LOWER(?) AND id <> ?');
            $dup->execute([$nom, $id]);
            if ($dup->fetch()) {
                $errors[] = 'Une catégorie porte déjà ce nom.';
            }
        }
        if (empty($errors)) {
            if ($action === 'create') {
                $pdo->prepare('INSERT INTO categories (nom, description) VALUES (?, ?)')->execute([$nom, $description ?: null]);
                log_activity('categorie_creation', "Catégorie créée : $nom");
                flash_set('success', 'Catégorie « ' . $nom . ' » ajoutée.');
            } else {
                $pdo->prepare('UPDATE categories SET nom = ?, description = ? WHERE id = ?')->execute([$nom, $description ?: null, $id]);
                log_activity('categorie_modification', "Catégorie modifiée : $nom");
                flash_set('success', 'Catégorie « ' . $nom . ' » mise à jour.');
            }
            redirect('categories.php');
        }
    } elseif ($action === 'delete') {
        require_role('admin');
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare('SELECT COUNT(*) c FROM articles WHERE categorie_id = ?');
        $check->execute([$id]);
        if ((int)$check->fetch()['c'] > 0) {
            flash_set('danger', 'Impossible de supprimer : des articles utilisent cette catégorie. Déplacez-les d\'abord vers une autre catégorie.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            log_activity('categorie_suppression', "Catégorie #$id supprimée");
            flash_set('success', 'Catégorie supprimée.');
        }
        redirect('categories.php');
    }
}

$categories = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM articles a WHERE a.categorie_id = c.id) AS nb_articles,
        (SELECT COUNT(*) FROM articles a WHERE a.categorie_id = c.id AND a.actif = 1 AND a.type = 'produit' AND a.stock <= a.seuil_alerte) AS nb_alertes,
        (SELECT COALESCE(SUM(d.sous_total), 0) FROM vente_details d JOIN ventes v ON v.id = d.vente_id JOIN articles a ON a.id = d.article_id
            WHERE a.categorie_id = c.id AND v.statut = 'validee' AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS ca_30j
    FROM categories c ORDER BY c.nom
")->fetchAll();
$maxCa = max(array_merge([1], array_map(fn($c) => (float)$c['ca_30j'], $categories)));
$gere = has_role('admin', 'secretaire');

$pageTitle = 'Catégories d\'articles';
$activeMenu = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <?php if ($gere): ?>
    <div class="col-lg-4">
        <div class="sp-card" style="position:sticky;top:calc(var(--sp-topbar-h) + 14px);">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-plus"></i></span>Nouvelle catégorie</h6></div>
            <div class="sp-card-body">
                <?php if (!empty($errors)): ?><div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="cNom">Nom <span class="sp-required">*</span></label>
                        <input type="text" id="cNom" name="nom" class="form-control" maxlength="100" required placeholder="Ex. : Papeterie">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cDesc">Description</label>
                        <input type="text" id="cDesc" name="description" class="form-control" maxlength="255" placeholder="Courte description (optionnel)">
                    </div>
                    <button class="btn btn-sp-amber w-100"><i class="fa-solid fa-plus me-1"></i>Ajouter la catégorie</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-<?= $gere ? '8' : '12' ?>">
        <div class="sp-card">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-tags"></i></span>Catégories <span class="badge bg-secondary"><?= count($categories) ?></span></h6>
                <div class="sp-input-icon" style="width:220px;"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" placeholder="Filtrer…" data-sp-filter="#tableCategories" aria-label="Filtrer"></div>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($categories)): ?>
                    <?= empty_state('fa-tags', 'Aucune catégorie', 'Les catégories permettent de filtrer rapidement les articles en caisse.') ?>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-sp mb-0" id="tableCategories" data-sp-sort>
                    <thead><tr><th data-sort="text">Catégorie</th><th class="text-center" data-sort="num">Articles</th><th data-sort="num" style="min-width:160px;">Ventes (30 j)</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): $idx = abs(crc32(mb_strtolower($c['nom']))) % 8; ?>
                        <tr>
                            <td data-value="<?= e($c['nom']) ?>">
                                <div class="sp-cell-flex">
                                    <span class="sp-avatar sm av-<?= $idx ?>" style="border-radius:10px;"><i class="fa-solid fa-tag" style="font-size:.75rem;"></i></span>
                                    <div style="min-width:0;">
                                        <div class="sp-cell-title"><?= e($c['nom']) ?></div>
                                        <div class="sp-cell-sub"><?= e($c['description'] ?: 'Pas de description') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center" data-value="<?= (int)$c['nb_articles'] ?>">
                                <a href="liste.php?cat=<?= (int)$c['id'] ?>" class="sp-badge is-primary"><?= (int)$c['nb_articles'] ?></a>
                                <?php if ((int)$c['nb_alertes'] > 0): ?><span class="sp-badge is-warning ms-1" title="Articles en alerte de stock"><i class="fa-solid fa-triangle-exclamation"></i><?= (int)$c['nb_alertes'] ?></span><?php endif; ?>
                            </td>
                            <td data-value="<?= (float)$c['ca_30j'] ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="sp-meter is-primary flex-grow-1"><span style="width:<?= round((float)$c['ca_30j'] / $maxCa * 100) ?>%"></span></div>
                                    <span class="small fw-semibold tabular text-nowrap"><?= fmt_money((float)$c['ca_30j']) ?></span>
                                </div>
                            </td>
                            <td class="td-actions">
                                <a href="liste.php?cat=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir les articles"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($gere): ?>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier" data-bs-toggle="collapse" data-bs-target="#editCat<?= (int)$c['id'] ?>"><i class="fa-solid fa-pen"></i></button>
                                <?php endif; ?>
                                <?php if (has_role('admin')): ?>
                                <form method="post" class="d-inline" data-confirm="Supprimer la catégorie « <?= e($c['nom']) ?> » ?" data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn btn-sm btn-icon btn-outline-danger" title="Supprimer" <?= (int)$c['nb_articles'] > 0 ? 'disabled' : '' ?>><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($gere): ?>
                        <tr class="collapse" id="editCat<?= (int)$c['id'] ?>" data-sp-static>
                            <td colspan="4" style="background:var(--sp-surface-2);">
                                <form method="post" class="row g-2 align-items-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <div class="col-md-4"><label class="form-label small">Nom</label><input type="text" name="nom" class="form-control form-control-sm" maxlength="100" required value="<?= e($c['nom']) ?>"></div>
                                    <div class="col-md-6"><label class="form-label small">Description</label><input type="text" name="description" class="form-control form-control-sm" maxlength="255" value="<?= e($c['description'] ?? '') ?>"></div>
                                    <div class="col-md-2"><button class="btn btn-sm btn-sp-primary w-100"><i class="fa-solid fa-check me-1"></i>OK</button></div>
                                </form>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
