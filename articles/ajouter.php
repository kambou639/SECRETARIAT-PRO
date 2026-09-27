<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

$errors = [];
$article = $_POST;

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
    $stock = $type === 'service' ? 0 : max(0, (int)($_POST['stock'] ?? 0));
    $seuil = $type === 'service' ? 0 : max(0, (int)($_POST['seuil_alerte'] ?? 5));

    if ($code === '' || $nom === '') {
        $errors[] = 'Le code et le nom sont obligatoires.';
    }
    if ($prixVente < 0 || $prixAchat < 0) {
        $errors[] = 'Les prix ne peuvent pas être négatifs.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM articles WHERE code = ?');
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $errors[] = 'Ce code article existe déjà.';
            }
        } catch (Exception $e) {
            $errors[] = 'Erreur de vérification du code.';
        }
    }

    $imageName = null;
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        try {
            $imageName = handle_upload($_FILES['image'], UPLOAD_ARTICLES, ['jpg','jpeg','png','webp']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO articles (code, nom, description, categorie_id, type, prix_achat, prix_vente, stock, seuil_alerte, unite, image, actif)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,1)');
            $stmt->execute([$code, $nom, $description, $categorieId, $type, $prixAchat, $prixVente, $stock, $seuil, $unite, $imageName]);
            $articleId = (int)$pdo->lastInsertId();

            if ($type === 'produit' && $stock > 0) {
                $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, user_id) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$articleId, 'entree', $stock, 0, $stock, 'Stock initial', $_SESSION['user_id']]);
            }

            log_activity('article_creation', "Article créé : $nom ($code)");
            flash_set('success', 'Article « ' . $nom . ' » créé avec succès.');
            redirect('liste.php');
        } catch (Exception $e) {
            error_log('article add: ' . $e->getMessage());
            $errors[] = 'Erreur lors de l\'enregistrement.';
        }
    }
}

$pageTitle = 'Nouvel article';
$activeMenu = 'articles';
$mode = 'add';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6>Nouvel article</h6></div>
    <div class="sp-card-body">
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
