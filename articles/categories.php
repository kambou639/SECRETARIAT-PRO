<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('admin', 'secretaire');
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = clean_input($_POST['nom'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        if ($nom === '') {
            $errors[] = 'Le nom de la catégorie est obligatoire.';
        } else {
            $pdo->prepare('INSERT INTO categories (nom, description) VALUES (?, ?)')->execute([$nom, $description]);
            flash_set('success', 'Catégorie ajoutée.');
            redirect('categories.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare('SELECT COUNT(*) c FROM articles WHERE categorie_id = ?');
        $check->execute([$id]);
        if ((int)$check->fetch()['c'] > 0) {
            flash_set('danger', 'Impossible de supprimer : des articles utilisent cette catégorie.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('success', 'Catégorie supprimée.');
        }
        redirect('categories.php');
    }
}

$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.categorie_id = c.id) AS nb_articles
    FROM categories c ORDER BY c.nom
")->fetchAll();

$pageTitle = 'Catégories d\'articles';
$activeMenu = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <?php if (has_role('admin', 'secretaire')): ?>
    <div class="col-lg-4">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Nouvelle catégorie</h6></div>
            <div class="sp-card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <button class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Ajouter</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-<?= has_role('admin', 'secretaire') ? '8' : '12' ?>">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Catégories (<?= count($categories) ?>)</h6></div>
            <div class="sp-card-body p-0">
                <table class="table table-sp mb-0">
                    <thead><tr><th>Nom</th><th>Description</th><th class="text-center">Articles</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($c['nom']) ?></td>
                            <td class="text-muted"><?= e($c['description'] ?: '-') ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= (int)$c['nb_articles'] ?></span></td>
                            <td class="text-end">
                                <?php if (has_role('admin')): ?>
                                <form method="post" class="d-inline" data-confirm="Supprimer cette catégorie ?" data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
