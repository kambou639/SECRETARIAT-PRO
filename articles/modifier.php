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
    $code = clean_input($_POST['code'] ?? '');
    $nom = clean_input($_POST['nom'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $type = ($_POST['type'] ?? '') === 'service' ? 'service' : 'produit';
    $categorieId = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;
    $unite = clean_input($_POST['unite'] ?? 'pièce') ?: 'pièce';
    $prixAchat = (float)($_POST['prix_achat'] ?? 0);
    $prixVente = (float)($_POST['prix_vente'] ?? 0);
    $seuil = $type === 'service' ? 0 : max(0, (int)($_POST['seuil_alerte'] ?? 5));
    $actif = !empty($_POST['actif']) ? 1 : 0;

    if ($code === '' || $nom === '') {
        $errors[] = 'Le code et le nom sont obligatoires.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM articles WHERE code = ? AND id != ?');
        $stmt->execute([$code, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce code article est déjà utilisé par un autre article.';
        }
    }

    $imageName = $article['image'];
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        try {
            $newImage = handle_upload($_FILES['image'], UPLOAD_ARTICLES, ['jpg','jpeg','png','webp']);
            if ($newImage) {
                if ($imageName && file_exists(UPLOAD_ARTICLES . '/' . $imageName)) {
                    @unlink(UPLOAD_ARTICLES . '/' . $imageName);
                }
                $imageName = $newImage;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('UPDATE articles SET code=?, nom=?, description=?, categorie_id=?, type=?, prix_achat=?, prix_vente=?, seuil_alerte=?, unite=?, image=?, actif=? WHERE id=?');
            $stmt->execute([$code, $nom, $description, $categorieId, $type, $prixAchat, $prixVente, $seuil, $unite, $imageName, $actif, $id]);

            log_activity('article_modification', "Article modifié : $nom ($code)");
            flash_set('success', 'Article mis à jour avec succès.');
            redirect('liste.php');
        } catch (Exception $e) {
            error_log('article edit: ' . $e->getMessage());
            $errors[] = 'Erreur lors de la mise à jour.';
        }
    } else {
        $article = array_merge($article, $_POST);
    }
}

$pageTitle = 'Modifier l\'article';
$activeMenu = 'articles';
$mode = 'edit';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card mb-3">
    <div class="sp-card-header"><h6>Modifier : <?= e($article['nom']) ?></h6></div>
    <div class="sp-card-body">
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
</div>

<div class="sp-card mb-3">
    <div class="sp-card-header"><h6>Ajustement de stock</h6></div>
    <div class="sp-card-body">
        <p class="text-muted small">Stock actuel : <strong><?= (int)$article['stock'] ?></strong> <?= e($article['unite']) ?></p>
        <a href="stock_ajuster.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrows-rotate me-1"></i>Ajuster le stock</a>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-header"><h6>Tarifs dégressifs</h6></div>
    <div class="sp-card-body">
        <p class="text-muted small">Définissez des prix par palier de quantité (ex. tarif réduit à partir de 50 pages).</p>
        <a href="paliers.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-layer-group me-1"></i>Gérer les paliers de prix</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
