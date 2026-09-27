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

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $code = mb_strtoupper(clean_input($_POST['code'] ?? ''));
    $nom = clean_input($_POST['nom'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $type = ($_POST['type'] ?? '') === 'service' ? 'service' : 'produit';
    $categorieId = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;
    $unite = mb_substr(clean_input($_POST['unite'] ?? 'pièce'), 0, 20) ?: 'pièce';
    $prixAchat = (float)($_POST['prix_achat'] ?? 0);
    $prixVente = (float)($_POST['prix_vente'] ?? 0);
    $seuil = $type === 'service' ? 0 : max(0, (int)($_POST['seuil_alerte'] ?? 5));
    $actif = !empty($_POST['actif']) ? 1 : 0;

    if ($code === '' || $nom === '') {
        $errors[] = 'Le code et le nom sont obligatoires.';
    }
    if ($prixVente < 0 || $prixAchat < 0) {
        $errors[] = 'Les prix ne peuvent pas être négatifs.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM articles WHERE code = ? AND id != ?');
        $stmt->execute([$code, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce code article est déjà utilisé par un autre article.';
        }
    }

    $imageName = $article['image'];
    $ancienneImage = null;
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        try {
            $newImage = handle_upload($_FILES['image'], UPLOAD_ARTICLES, ['jpg', 'jpeg', 'png', 'webp']);
            if ($newImage) {
                $ancienneImage = $imageName;
                $imageName = $newImage;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif (empty($errors) && !empty($_POST['supprimer_image']) && $imageName) {
        $ancienneImage = $imageName;
        $imageName = null;
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('UPDATE articles SET code=?, nom=?, description=?, categorie_id=?, type=?, prix_achat=?, prix_vente=?, seuil_alerte=?, unite=?, image=?, actif=? WHERE id=?');
            $stmt->execute([$code, $nom, $description, $categorieId, $type, $prixAchat, $prixVente, $seuil, $unite, $imageName, $actif, $id]);
            delete_upload(UPLOAD_ARTICLES, $ancienneImage);

            log_activity('article_modification', "Article modifié : $nom ($code)");
            flash_set('success', 'Article « ' . $nom . ' » mis à jour.');
            redirect('liste.php');
        } catch (Exception $e) {
            error_log('article edit: ' . $e->getMessage());
            $errors[] = 'Erreur lors de la mise à jour.';
        }
    } else {
        $article = array_merge($article, $_POST);
    }
}

// Informations complémentaires
$paliers = get_paliers($pdo, $id);
$stmt = $pdo->prepare('SELECT m.*, u.full_name FROM mouvements_stock m LEFT JOIN users u ON u.id = m.user_id WHERE m.article_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT 5');
$stmt->execute([$id]);
$mouvements = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT COALESCE(SUM(d.quantite),0) qte, COALESCE(SUM(d.sous_total),0) total, COUNT(DISTINCT d.vente_id) nb
    FROM vente_details d JOIN ventes v ON v.id = d.vente_id WHERE d.article_id = ? AND v.statut = 'validee' AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$id]);
$ventes30 = $stmt->fetch();

$pageTitle = 'Modifier l\'article';
$activeMenu = 'articles';
$mode = 'edit';
include __DIR__ . '/../includes/header.php';
$typeMouv = ['entree' => ['Entrée', 'success', 'fa-arrow-down'], 'sortie' => ['Sortie', 'danger', 'fa-arrow-up'], 'ajustement' => ['Ajustement', 'info', 'fa-scale-balanced']];
?>
<div class="sp-card mb-3">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon"><i class="fa-solid fa-pen"></i></span><?= e($article['nom']) ?> <code class="ms-1"><?= e($article['code']) ?></code></h6>
        <a href="liste.php" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Catalogue</a>
    </div>
    <div class="sp-card-body">
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
</div>

<div class="row g-3">
    <?php if ($article['type'] !== 'service'): ?>
    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-boxes-stacked"></i></span>Stock</h6>
                <a href="stock_ajuster.php?id=<?= $id ?>" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-arrows-rotate me-1"></i>Ajuster</a>
            </div>
            <div class="sp-card-body">
                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="sp-stat-value"><?= (int)$article['stock'] ?></span><span class="text-muted"><?= e($article['unite']) ?>(s) en stock</span>
                </div>
                <?php if (empty($mouvements)): ?>
                    <p class="text-muted small mb-0">Aucun mouvement enregistré.</p>
                <?php else: ?>
                <ul class="sp-timeline">
                    <?php foreach ($mouvements as $m): $tm = $typeMouv[$m['type']] ?? ['?', 'accent', 'fa-circle']; ?>
                        <li>
                            <span class="tl-dot tone-<?= $tm[1] ?>"><i class="fa-solid <?= $tm[2] ?>"></i></span>
                            <div class="tl-title"><?= e($tm[0]) ?> · <?= (int)$m['stock_avant'] ?> → <?= (int)$m['stock_apres'] ?></div>
                            <div class="tl-meta"><?= e($m['motif'] ?: '-') ?> · <?= e(fmt_relative($m['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-layer-group"></i></span>Tarifs dégressifs</h6>
                <a href="paliers.php?id=<?= $id ?>" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-sliders me-1"></i>Gérer</a>
            </div>
            <div class="sp-card-body">
                <?php if (empty($paliers)): ?>
                    <p class="text-muted small mb-0">Aucun palier : le prix de base (<?= fmt_money((float)$article['prix_vente']) ?>) s'applique à toutes les quantités.</p>
                <?php else: ?>
                    <div class="sp-tiers">
                        <?php if ((int)$paliers[0]['quantite_min'] > 1): ?>
                            <div class="sp-tier"><strong><?= fmt_money((float)$article['prix_vente']) ?></strong>1 – <?= (int)$paliers[0]['quantite_min'] - 1 ?></div>
                        <?php endif; ?>
                        <?php foreach ($paliers as $p): ?>
                            <div class="sp-tier is-active"><strong><?= fmt_money((float)$p['prix_unitaire']) ?></strong><?= (int)$p['quantite_min'] ?><?= $p['quantite_max'] !== null ? ' – ' . (int)$p['quantite_max'] : ' et +' ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-qrcode"></i></span>Étiquette &amp; ventes</h6>
                <a href="etiquettes.php?id=<?= $id ?>" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-print me-1"></i>Imprimer</a>
            </div>
            <div class="sp-card-body d-flex gap-3 align-items-center">
                <img src="code_image.php?id=<?= $id ?>&amp;type=qr" alt="QR code" width="96" height="96" style="image-rendering:pixelated;border-radius:8px;border:1px solid var(--sp-border);background:#fff;">
                <div>
                    <div class="small-caps">30 derniers jours</div>
                    <div class="fw-bold font-display fs-5"><?= fmt_money((float)$ventes30['total']) ?></div>
                    <div class="text-muted small"><?= fmt_number((float)$ventes30['qte']) ?> <?= e($article['unite']) ?>(s) · <?= (int)$ventes30['nb'] ?> vente(s)</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
