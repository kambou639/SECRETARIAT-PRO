<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) {
    flash_set('danger', 'Article introuvable.');
    redirect('liste.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $qteMin = (int)($_POST['quantite_min'] ?? 0);
        $qteMax = trim((string)($_POST['quantite_max'] ?? ''));
        $qteMax = $qteMax === '' ? null : (int)$qteMax;
        $prix = (float)($_POST['prix_unitaire'] ?? -1);

        if ($qteMin < 1) {
            $errors[] = 'La quantité minimale doit être au moins 1.';
        }
        if ($qteMax !== null && $qteMax < $qteMin) {
            $errors[] = 'La quantité maximale doit être supérieure ou égale à la quantité minimale.';
        }
        if ($prix < 0) {
            $errors[] = 'Le prix unitaire du palier est obligatoire.';
        }

        if (empty($errors)) {
            $pdo->prepare('INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire) VALUES (?,?,?,?)')
                ->execute([$id, $qteMin, $qteMax, $prix]);
            log_activity('palier_creation', "Palier ajouté pour {$article['nom']} : à partir de $qteMin");
            flash_set('success', 'Palier de prix ajouté.');
            redirect('paliers.php?id=' . $id);
        }
    } elseif ($action === 'delete') {
        $palierId = (int)($_POST['palier_id'] ?? 0);
        $pdo->prepare('DELETE FROM paliers_prix WHERE id = ? AND article_id = ?')->execute([$palierId, $id]);
        flash_set('success', 'Palier supprimé.');
        redirect('paliers.php?id=' . $id);
    }
}

$paliers = get_paliers($pdo, $id);

$pageTitle = 'Tarifs dégressifs';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Ajouter un palier - <?= e($article['nom']) ?></h6></div>
            <div class="sp-card-body">
                <p class="text-muted small">
                    Prix de base (quantités non couvertes par un palier) :
                    <strong><?= fmt_money((float)$article['prix_vente']) ?></strong> / <?= e($article['unite']) ?>
                </p>
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">À partir de (quantité)</label>
                            <input type="number" name="quantite_min" class="form-control" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jusqu'à (optionnel)</label>
                            <input type="number" name="quantite_max" class="form-control" min="1" placeholder="Illimité">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Prix unitaire dans ce palier</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="prix_unitaire" class="form-control" required>
                                <span class="input-group-text"><?= e(get_param('devise','FCFA')) ?></span>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sp-amber mt-3"><i class="fa-solid fa-plus me-1"></i>Ajouter le palier</button>
                    <a href="modifier.php?id=<?= $id ?>" class="btn btn-outline-secondary mt-3">Retour à l'article</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Paliers actuels</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($paliers)): ?>
                    <div class="sp-empty-state"><i class="fa-solid fa-layer-group"></i><p>Aucun palier défini. Le prix de base s'applique à toutes les quantités.</p></div>
                <?php else: ?>
                <table class="table table-sp mb-0">
                    <thead><tr><th>Quantité</th><th class="text-end">Prix unitaire</th><th class="text-end no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($paliers as $p): ?>
                        <tr>
                            <td>
                                De <strong><?= (int)$p['quantite_min'] ?></strong>
                                <?= $p['quantite_max'] !== null ? 'à <strong>' . (int)$p['quantite_max'] . '</strong>' : 'et plus' ?>
                                <?= e($article['unite']) ?>(s)
                            </td>
                            <td class="text-end fw-semibold"><?= fmt_money((float)$p['prix_unitaire']) ?></td>
                            <td class="text-end no-print">
                                <form method="post" class="d-inline" data-confirm="Supprimer ce palier de prix ?" data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="palier_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <div class="alert alert-info mt-3 small">
            <i class="fa-solid fa-circle-info me-1"></i>
            Exemple : pour une impression facturée <?= fmt_money((float)$article['prix_vente']) ?> la page en dessous de 50 pages,
            ajoutez un palier « à partir de 51, jusqu'à 200 » à un prix réduit, puis un autre « à partir de 201 » (laisser « Jusqu'à » vide = illimité) pour un tarif encore plus bas.
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
