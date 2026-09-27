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

/** Vérifie qu'une plage de quantités ne chevauche aucun palier existant (hors $ignoreId). */
function palier_chevauche(array $paliers, int $min, ?int $max, int $ignoreId = 0): ?array
{
    foreach ($paliers as $p) {
        if ((int)$p['id'] === $ignoreId) continue;
        $pMin = (int)$p['quantite_min'];
        $pMax = $p['quantite_max'] !== null ? (int)$p['quantite_max'] : PHP_INT_MAX;
        if ($min <= $pMax && $pMin <= ($max ?? PHP_INT_MAX)) {
            return $p;
        }
    }
    return null;
}

$errors = [];
$paliers = get_paliers($pdo, $id);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $palierId = (int)($_POST['palier_id'] ?? 0);
        $qteMin = (int)($_POST['quantite_min'] ?? 0);
        $qteMax = trim((string)($_POST['quantite_max'] ?? ''));
        $qteMax = $qteMax === '' ? null : (int)$qteMax;
        $prix = trim((string)($_POST['prix_unitaire'] ?? '')) === '' ? -1 : (float)$_POST['prix_unitaire'];

        if ($qteMin < 1) {
            $errors[] = 'La quantité minimale doit être au moins 1.';
        }
        if ($qteMax !== null && $qteMax < $qteMin) {
            $errors[] = 'La quantité maximale doit être supérieure ou égale à la quantité minimale.';
        }
        if ($prix < 0) {
            $errors[] = 'Le prix unitaire du palier est obligatoire.';
        }
        if (empty($errors) && ($conflit = palier_chevauche($paliers, $qteMin, $qteMax, $action === 'update' ? $palierId : 0))) {
            $errors[] = 'Cette plage chevauche le palier existant « de ' . (int)$conflit['quantite_min']
                . ($conflit['quantite_max'] !== null ? ' à ' . (int)$conflit['quantite_max'] : ' et plus') . ' ». Ajustez les quantités.';
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire) VALUES (?,?,?,?)')
                    ->execute([$id, $qteMin, $qteMax, $prix]);
                log_activity('palier_creation', "Palier ajouté pour {$article['nom']} : à partir de $qteMin");
                flash_set('success', 'Palier de prix ajouté.');
            } else {
                $pdo->prepare('UPDATE paliers_prix SET quantite_min = ?, quantite_max = ?, prix_unitaire = ? WHERE id = ? AND article_id = ?')
                    ->execute([$qteMin, $qteMax, $prix, $palierId, $id]);
                log_activity('palier_modification', "Palier modifié pour {$article['nom']} : à partir de $qteMin");
                flash_set('success', 'Palier de prix mis à jour.');
            }
            redirect('paliers.php?id=' . $id);
        }
    } elseif ($action === 'delete') {
        $palierId = (int)($_POST['palier_id'] ?? 0);
        $pdo->prepare('DELETE FROM paliers_prix WHERE id = ? AND article_id = ?')->execute([$palierId, $id]);
        log_activity('palier_suppression', "Palier supprimé pour {$article['nom']}");
        flash_set('success', 'Palier supprimé.');
        redirect('paliers.php?id=' . $id);
    }
}

$prixBase = (float)$article['prix_vente'];
$suggestionMin = 1;
foreach ($paliers as $p) {
    $suggestionMin = max($suggestionMin, $p['quantite_max'] !== null ? (int)$p['quantite_max'] + 1 : (int)$p['quantite_min'] + 1);
}
if (empty($paliers)) $suggestionMin = 51;

$pageTitle = 'Tarifs dégressifs';
$activeMenu = 'articles';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-page-head">
    <div>
        <h2><?= e($article['nom']) ?></h2>
        <p>Prix de base : <strong><?= fmt_money($prixBase) ?></strong> / <?= e($article['unite']) ?> — appliqué aux quantités non couvertes par un palier.</p>
    </div>
    <a href="modifier.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour à l'article</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-plus"></i></span>Ajouter un palier</h6></div>
            <div class="sp-card-body">
                <?php if (!empty($errors)): ?><div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="pMin">À partir de</label>
                            <input type="number" id="pMin" name="quantite_min" class="form-control" min="1" required value="<?= e((string)($_POST['quantite_min'] ?? $suggestionMin)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pMax">Jusqu'à <span class="text-muted fw-normal">(optionnel)</span></label>
                            <input type="number" id="pMax" name="quantite_max" class="form-control" min="1" placeholder="Illimité" value="<?= e((string)($_POST['quantite_max'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pPrix">Prix unitaire dans ce palier</label>
                            <div class="input-group">
                                <input type="number" id="pPrix" step="0.01" min="0" name="prix_unitaire" class="form-control" required value="<?= e((string)($_POST['prix_unitaire'] ?? '')) ?>">
                                <span class="input-group-text"><?= e(get_param('devise', 'FCFA')) ?> / <?= e($article['unite']) ?></span>
                            </div>
                            <div class="form-text" id="pRemise"></div>
                        </div>
                    </div>
                    <button class="btn btn-sp-amber mt-3"><i class="fa-solid fa-plus me-1"></i>Ajouter le palier</button>
                </form>
            </div>
        </div>

        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-calculator"></i></span>Simulateur</h6></div>
            <div class="sp-card-body">
                <label class="form-label" for="simQte">Quantité commandée</label>
                <input type="number" id="simQte" class="form-control form-control-lg mb-3" min="1" value="100">
                <div class="sp-price-preview">
                    <div class="pp-total" id="simTotal">0</div>
                    <div class="pp-detail" id="simDetail"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6><span class="sp-card-icon"><i class="fa-solid fa-stairs"></i></span>Échelle de prix</h6></div>
            <div class="sp-card-body">
                <div class="sp-tiers" id="ladder"></div>
            </div>
        </div>
        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-layer-group"></i></span>Paliers actuels</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($paliers)): ?>
                    <?= empty_state('fa-layer-group', 'Aucun palier', 'Le prix de base s\'applique à toutes les quantités. Ajoutez un palier pour récompenser les grosses commandes.') ?>
                <?php else: ?>
                <table class="table table-sp mb-0">
                    <thead><tr><th>Quantités</th><th class="text-end">Prix unitaire</th><th class="text-end">Réduction</th><th class="text-end no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($paliers as $p): $red = $prixBase > 0 ? (1 - (float)$p['prix_unitaire'] / $prixBase) * 100 : 0; ?>
                        <tr>
                            <td>De <strong><?= (int)$p['quantite_min'] ?></strong> <?= $p['quantite_max'] !== null ? 'à <strong>' . (int)$p['quantite_max'] . '</strong>' : 'et plus' ?> <span class="text-muted"><?= e($article['unite']) ?>(s)</span></td>
                            <td class="text-end fw-semibold tabular"><?= fmt_money((float)$p['prix_unitaire']) ?></td>
                            <td class="text-end"><?php if ($red > 0.5): ?><span class="sp-badge is-success">-<?= number_format($red, 0, ',', ' ') ?> %</span><?php elseif ($red < -0.5): ?><span class="sp-badge is-danger">+<?= number_format(-$red, 0, ',', ' ') ?> %</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td class="td-actions no-print">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$p['id'] ?>"><i class="fa-solid fa-pen"></i></button>
                                <form method="post" class="d-inline" data-confirm="Supprimer ce palier de prix ?" data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="palier_id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-icon btn-outline-danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="edit<?= (int)$p['id'] ?>" data-sp-static>
                            <td colspan="4" style="background:var(--sp-surface-2);">
                                <form method="post" class="row g-2 align-items-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="palier_id" value="<?= (int)$p['id'] ?>">
                                    <div class="col-4"><label class="form-label small">À partir de</label><input type="number" name="quantite_min" class="form-control form-control-sm" min="1" value="<?= (int)$p['quantite_min'] ?>"></div>
                                    <div class="col-4"><label class="form-label small">Jusqu'à</label><input type="number" name="quantite_max" class="form-control form-control-sm" min="1" placeholder="Illimité" value="<?= $p['quantite_max'] !== null ? (int)$p['quantite_max'] : '' ?>"></div>
                                    <div class="col-4"><label class="form-label small">Prix</label><input type="number" step="0.01" min="0" name="prix_unitaire" class="form-control form-control-sm" value="<?= e((string)(float)$p['prix_unitaire']) ?>"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-sp-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button></div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <div class="sp-note mt-3 small">
            <i class="fa-solid fa-lightbulb me-1"></i>Exemple : pour une impression à <?= fmt_money($prixBase) ?> la page, ajoutez « de 51 à 200 » à un prix réduit puis « à partir de 201 » (laisser « Jusqu'à » vide) pour un tarif encore plus bas. Les prix sont toujours recalculés par le serveur au moment de la vente.
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var base = <?= js_json($prixBase) ?>, unite = <?= js_json($article['unite']) ?>;
    var tiers = <?= js_json(array_map(fn($p) => ['min' => (int)$p['quantite_min'], 'max' => $p['quantite_max'] !== null ? (int)$p['quantite_max'] : null, 'prix' => (float)$p['prix_unitaire']], $paliers)) ?>;
    function prix(q) {
        var best = null;
        tiers.forEach(function (t) { if (t.min <= q && (t.max === null || t.max >= q) && (!best || t.min > best.min)) best = t; });
        return best ? best.prix : base;
    }
    function ladder(q) {
        var parts = [];
        if (!tiers.length || tiers[0].min > 1) parts.push({ label: '1 – ' + (tiers.length ? tiers[0].min - 1 : '∞'), prix: base, from: 1, to: tiers.length ? tiers[0].min - 1 : Infinity });
        tiers.forEach(function (t) { parts.push({ label: t.min + (t.max !== null ? ' – ' + t.max : ' et +'), prix: t.prix, from: t.min, to: t.max === null ? Infinity : t.max }); });
        var cur = prix(q);
        document.getElementById('ladder').innerHTML = parts.map(function (p) {
            var active = q >= p.from && q <= p.to && p.prix === cur;
            return '<div class="sp-tier' + (active ? ' is-active' : '') + '"><strong>' + SP.esc(SP.money(p.prix)) + '</strong>' + SP.esc(p.label + ' ' + unite) + '</div>';
        }).join('');
    }
    function sim() {
        var q = Math.max(1, parseInt(document.getElementById('simQte').value, 10) || 1);
        var p = prix(q);
        document.getElementById('simTotal').innerHTML = SP.esc(SP.num(p * q)) + '<small class="cur">' + SP.esc(SP.cfg.devise) + '</small>';
        var eco = (base - p) * q;
        document.getElementById('simDetail').innerHTML = SP.esc(SP.money(p) + ' / ' + unite + ' × ' + SP.num(q)) + (eco > 0 ? ' <span class="pos-tier ms-1">économie ' + SP.esc(SP.money(eco)) + '</span>' : '');
        ladder(q);
    }
    document.getElementById('simQte').addEventListener('input', sim);
    var pPrix = document.getElementById('pPrix');
    pPrix.addEventListener('input', function () {
        var v = parseFloat(pPrix.value);
        document.getElementById('pRemise').textContent = v >= 0 && base > 0 ? 'Soit ' + SP.num((1 - v / base) * 100, 1) + ' % par rapport au prix de base.' : '';
    });
    sim();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
