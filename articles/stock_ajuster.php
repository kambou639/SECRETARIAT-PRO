<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
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
    $type = in_array($_POST['type'] ?? '', ['entree', 'sortie', 'ajustement'], true) ? $_POST['type'] : 'ajustement';
    $quantite = (int)($_POST['quantite'] ?? 0);
    $motif = clean_input($_POST['motif'] ?? '');

    if ($quantite <= 0 && $type !== 'ajustement') {
        $errors[] = 'La quantité doit être positive.';
    }

    if (empty($errors)) {
        $stockAvant = (int)$article['stock'];
        if ($type === 'entree') {
            $stockApres = $stockAvant + $quantite;
        } elseif ($type === 'sortie') {
            if ($quantite > $stockAvant) {
                $errors[] = 'Quantité de sortie supérieure au stock disponible.';
            }
            $stockApres = $stockAvant - $quantite;
        } else { // ajustement : quantite = nouvelle valeur absolue
            $stockApres = max(0, $quantite);
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE articles SET stock = ? WHERE id = ?')->execute([$stockApres, $id]);
                $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, user_id) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$id, $type, abs($stockApres - $stockAvant), $stockAvant, $stockApres, $motif, $_SESSION['user_id']]);
                $pdo->commit();

                log_activity('stock_ajustement', "Article #$id : $stockAvant -> $stockApres ($motif)");
                flash_set('success', 'Stock mis à jour avec succès.');
                redirect('modifier.php?id=' . $id);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('stock adjust: ' . $e->getMessage());
                $errors[] = 'Erreur lors de l\'ajustement du stock.';
            }
        }
    }
}

$historique = $pdo->prepare('SELECT m.*, u.full_name FROM mouvements_stock m LEFT JOIN users u ON u.id = m.user_id WHERE article_id = ? ORDER BY m.created_at DESC LIMIT 20');
$historique->execute([$id]);
$mouvements = $historique->fetchAll();

$pageTitle = 'Ajuster le stock';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="sp-card">
            <div class="sp-card-header"><h6><?= e($article['nom']) ?> - Stock actuel : <?= (int)$article['stock'] ?></h6></div>
            <div class="sp-card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type de mouvement</label>
                        <select name="type" class="form-select" required>
                            <option value="entree">Entrée (réapprovisionnement)</option>
                            <option value="sortie">Sortie (perte, casse...)</option>
                            <option value="ajustement">Ajustement (valeur exacte)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantité</label>
                        <input type="number" name="quantite" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motif</label>
                        <input type="text" name="motif" class="form-control" placeholder="Ex : réception fournisseur, inventaire...">
                    </div>
                    <button class="btn btn-sp-amber"><i class="fa-solid fa-check me-1"></i>Valider</button>
                    <a href="modifier.php?id=<?= $id ?>" class="btn btn-outline-secondary">Retour</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Historique des mouvements</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($mouvements)): ?>
                    <div class="sp-empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p>Aucun mouvement enregistré.</p></div>
                <?php else: ?>
                <table class="table table-sp mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qté</th><th class="text-end">Avant→Après</th><th>Motif</th><th>Par</th></tr></thead>
                    <tbody>
                    <?php foreach ($mouvements as $m): ?>
                        <tr>
                            <td><?= fmt_datetime($m['created_at']) ?></td>
                            <td><span class="badge bg-secondary"><?= e(ucfirst($m['type'])) ?></span></td>
                            <td class="text-end"><?= (int)$m['quantite'] ?></td>
                            <td class="text-end"><?= (int)$m['stock_avant'] ?> → <?= (int)$m['stock_apres'] ?></td>
                            <td><?= e($m['motif'] ?: '-') ?></td>
                            <td><?= e($m['full_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
