<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT a.*, c.nom AS categorie_nom FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id WHERE a.id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) {
    flash_set('danger', 'Article introuvable.');
    redirect('liste.php');
}
if ($article['type'] === 'service') {
    flash_set('info', 'Les services (impression, photocopie…) ne gèrent pas de stock.');
    redirect('modifier.php?id=' . $id);
}

$errors = [];
$old = ['type' => 'entree', 'quantite' => '', 'motif' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $type = in_array($_POST['type'] ?? '', ['entree', 'sortie', 'ajustement'], true) ? $_POST['type'] : 'ajustement';
    $quantite = (int)($_POST['quantite'] ?? 0);
    $motif = mb_substr(clean_input($_POST['motif'] ?? ''), 0, 255);
    $old = ['type' => $type, 'quantite' => $_POST['quantite'] ?? '', 'motif' => $motif];

    if ($type !== 'ajustement' && $quantite <= 0) {
        $errors[] = 'La quantité doit être positive.';
    }
    if ($type === 'ajustement' && $quantite < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            // Verrouille l'article : une vente simultanée ne peut pas fausser le calcul
            $lock = $pdo->prepare('SELECT stock FROM articles WHERE id = ? FOR UPDATE');
            $lock->execute([$id]);
            $stockAvant = (int)$lock->fetchColumn();

            if ($type === 'entree') {
                $stockApres = $stockAvant + $quantite;
            } elseif ($type === 'sortie') {
                if ($quantite > $stockAvant) {
                    throw new RuntimeException('Quantité de sortie supérieure au stock disponible (' . $stockAvant . ').');
                }
                $stockApres = $stockAvant - $quantite;
            } else { // ajustement : quantite = nouvelle valeur absolue (inventaire)
                $stockApres = max(0, $quantite);
            }
            if ($stockApres === $stockAvant && $type === 'ajustement') {
                throw new RuntimeException('Le stock est déjà de ' . $stockAvant . ' : aucun ajustement nécessaire.');
            }

            $pdo->prepare('UPDATE articles SET stock = ? WHERE id = ?')->execute([$stockApres, $id]);
            $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, user_id) VALUES (?,?,?,?,?,?,?)')
                ->execute([$id, $type, abs($stockApres - $stockAvant), $stockAvant, $stockApres, $motif ?: null, $_SESSION['user_id']]);
            $pdo->commit();

            log_activity('stock_ajustement', "{$article['nom']} : $stockAvant → $stockApres" . ($motif ? " ($motif)" : ''));
            flash_set('success', 'Stock de « ' . $article['nom'] . ' » mis à jour : ' . $stockAvant . ' → ' . $stockApres . '.');
            redirect('stock_ajuster.php?id=' . $id);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('stock adjust: ' . $e->getMessage());
            $errors[] = 'Erreur lors de l\'ajustement du stock.';
        }
    }
}

$historique = $pdo->prepare('SELECT m.*, u.full_name FROM mouvements_stock m LEFT JOIN users u ON u.id = m.user_id WHERE article_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT 30');
$historique->execute([$id]);
$mouvements = $historique->fetchAll();
$stock = (int)$article['stock'];
$seuil = (int)$article['seuil_alerte'];
$img = article_image_url($article['image']);
$typeMouv = ['entree' => ['Entrée', 'success', 'fa-arrow-down'], 'sortie' => ['Sortie', 'danger', 'fa-arrow-up'], 'ajustement' => ['Ajustement', 'info', 'fa-scale-balanced']];

$pageTitle = 'Ajuster le stock';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="sp-card mb-3">
            <div class="sp-card-body d-flex gap-3 align-items-center">
                <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="sp-img-thumb" style="width:64px;height:64px;"><?php else: ?><span class="sp-img-thumb placeholder" style="width:64px;height:64px;font-size:1.3rem;"><i class="fa-solid fa-box"></i></span><?php endif; ?>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="sp-cell-title fs-6"><?= e($article['nom']) ?></div>
                    <div class="sp-cell-sub"><code><?= e($article['code']) ?></code> · <?= e($article['categorie_nom'] ?? 'Sans catégorie') ?></div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <div class="sp-meter flex-grow-1 <?= $stock <= 0 ? 'is-out' : ($stock <= $seuil ? 'is-low' : '') ?>"><span style="width:<?= min(100, max(3, $stock / max(1, $seuil * 3) * 100)) ?>%"></span></div>
                        <span class="small text-muted">seuil <?= $seuil ?></span>
                    </div>
                </div>
                <div class="text-center">
                    <div class="small-caps">Stock</div>
                    <div class="sp-stat-value" data-countup="<?= $stock ?>"><?= $stock ?></div>
                    <div class="small text-muted"><?= e($article['unite']) ?>(s)</div>
                </div>
            </div>
        </div>

        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-arrows-rotate"></i></span>Nouveau mouvement</h6></div>
            <div class="sp-card-body">
                <?php if (!empty($errors)): ?><div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div><?php endif; ?>
                <form method="post" id="stockForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="d-grid gap-2 mb-3">
                        <label class="sp-choice tone-success"><input type="radio" name="type" value="entree" <?= $old['type'] === 'entree' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-arrow-down"></i></span><span><span class="sp-choice-title d-block">Entrée</span><span class="sp-choice-sub d-block">Réception fournisseur, retour client…</span></span></span></label>
                        <label class="sp-choice tone-danger"><input type="radio" name="type" value="sortie" <?= $old['type'] === 'sortie' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-arrow-up"></i></span><span><span class="sp-choice-title d-block">Sortie</span><span class="sp-choice-sub d-block">Perte, casse, usage interne…</span></span></span></label>
                        <label class="sp-choice tone-info"><input type="radio" name="type" value="ajustement" <?= $old['type'] === 'ajustement' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-scale-balanced"></i></span><span><span class="sp-choice-title d-block">Inventaire</span><span class="sp-choice-sub d-block">Saisir le stock réel compté</span></span></span></label>
                    </div>
                    <label class="form-label" for="sQte" id="sQteLabel">Quantité</label>
                    <input type="number" id="sQte" name="quantite" class="form-control form-control-lg" min="0" required value="<?= e((string)$old['quantite']) ?>" placeholder="0">
                    <div class="sp-price-preview mt-2" id="sPreview">
                        <div class="pp-detail">Nouveau stock</div>
                        <div class="pp-total"><span id="sAvant"><?= $stock ?></span> <i class="fa-solid fa-arrow-right-long mx-2" style="font-size:.7em;opacity:.5;"></i> <span id="sApres"><?= $stock ?></span></div>
                    </div>
                    <label class="form-label mt-3" for="sMotif">Motif</label>
                    <input type="text" id="sMotif" name="motif" class="form-control" maxlength="255" value="<?= e($old['motif']) ?>" placeholder="Ex. : réception fournisseur, inventaire mensuel…">
                    <div class="sp-qty-quick justify-content-start mt-2" id="motifsRapides">
                        <?php foreach (['Réception fournisseur', 'Inventaire', 'Casse', 'Perte', 'Retour client', 'Usage interne'] as $m): ?><button type="button"><?= e($m) ?></button><?php endforeach; ?>
                    </div>
                    <div class="sp-form-actions">
                        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-check me-1"></i>Enregistrer le mouvement</button>
                        <a href="modifier.php?id=<?= $id ?>" class="btn btn-ghost">Retour à l'article</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>Historique des mouvements</h6><span class="text-muted small">30 derniers</span></div>
            <div class="sp-card-body p-0">
                <?php if (empty($mouvements)): ?>
                    <?= empty_state('fa-clock-rotate-left', 'Aucun mouvement', 'Les entrées, sorties, ventes et inventaires apparaîtront ici.') ?>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-sp mb-0">
                    <thead><tr><th>Date</th><th>Mouvement</th><th class="text-end">Qté</th><th class="text-center">Stock</th><th>Motif</th><th class="d-none d-xl-table-cell">Par</th></tr></thead>
                    <tbody>
                    <?php foreach ($mouvements as $m):
                        $tm = $typeMouv[$m['type']] ?? ['?', 'neutral', 'fa-circle'];
                        $delta = (int)$m['stock_apres'] - (int)$m['stock_avant']; ?>
                        <tr>
                            <td class="text-nowrap"><?= fmt_date($m['created_at']) ?><div class="sp-cell-sub"><?= fmt_date($m['created_at'], 'H:i') ?></div></td>
                            <td><?= badge_html($tm) ?></td>
                            <td class="text-end fw-bold tabular <?= $delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?></td>
                            <td class="text-center tabular text-nowrap"><span class="text-muted"><?= (int)$m['stock_avant'] ?></span> → <strong><?= (int)$m['stock_apres'] ?></strong></td>
                            <td><?= e($m['motif'] ?: '-') ?><?php if ($m['reference']): ?><div class="sp-cell-sub"><code><?= e($m['reference']) ?></code></div><?php endif; ?></td>
                            <td class="d-none d-xl-table-cell text-muted small"><?= e($m['full_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('stockForm'), stock = <?= $stock ?>;
    var qte = document.getElementById('sQte'), apres = document.getElementById('sApres'), label = document.getElementById('sQteLabel');
    function refresh() {
        var type = form.querySelector('input[name="type"]:checked').value;
        var q = Math.max(0, parseInt(qte.value, 10) || 0), val = stock;
        label.textContent = type === 'ajustement' ? 'Stock réel compté' : (type === 'entree' ? 'Quantité reçue' : 'Quantité sortie');
        if (type === 'entree') val = stock + q;
        else if (type === 'sortie') val = stock - q;
        else val = qte.value === '' ? stock : q;
        apres.textContent = val;
        apres.style.color = val < 0 ? 'var(--sp-danger)' : (val > stock ? 'var(--sp-success)' : (val < stock ? 'var(--sp-warning-ink)' : ''));
        apres.classList.remove('sp-anim-bump'); void apres.offsetWidth; apres.classList.add('sp-anim-bump');
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    document.getElementById('motifsRapides').addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        document.getElementById('sMotif').value = b.textContent;
        if (b.textContent === 'Inventaire') { form.querySelector('input[value="ajustement"]').checked = true; refresh(); }
        if (['Casse', 'Perte', 'Usage interne'].indexOf(b.textContent) > -1) { form.querySelector('input[value="sortie"]').checked = true; refresh(); }
        if (['Réception fournisseur', 'Retour client'].indexOf(b.textContent) > -1) { form.querySelector('input[value="entree"]').checked = true; refresh(); }
        qte.focus();
    });
    refresh();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
