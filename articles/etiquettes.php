<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$articles = $pdo->query("SELECT a.id, a.code, a.nom, a.prix_vente, a.unite, a.type, c.nom AS categorie_nom
    FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id WHERE a.actif = 1 ORDER BY a.nom ASC")->fetchAll();

$preselect = array_map('intval', array_filter(explode(',', (string)($_GET['ids'] ?? ($_GET['id'] ?? '')))));
$devise = get_param('devise', 'FCFA');

$pageTitle = 'Étiquettes QR / Codes-barres';
$activeMenu = 'etiquettes';
include __DIR__ . '/../includes/header.php';
?>

<form id="labelForm" method="get" action="etiquettes_imprimer.php" target="_blank" data-no-loading>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-list-check"></i></span>Articles <span class="badge bg-primary" id="selCount">0</span></h6>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-ghost" id="selAll">Tout</button>
                    <button type="button" class="btn btn-sm btn-ghost" id="selNone">Aucun</button>
                </div>
            </div>
            <div class="p-3 pb-2 border-bottom">
                <div class="sp-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control" id="labelSearch" placeholder="Rechercher un article…" aria-label="Rechercher un article"></div>
            </div>
            <div style="max-height:420px; overflow-y:auto;" id="pickList">
                <?php foreach ($articles as $a): $checked = in_array((int)$a['id'], $preselect, true); ?>
                    <label class="sp-label-pick<?= $checked ? ' is-checked' : '' ?>" data-filter-item data-search="<?= e(mb_strtolower($a['nom'] . ' ' . $a['code'] . ' ' . ($a['categorie_nom'] ?? ''))) ?>">
                        <input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>" class="form-check-input m-0" <?= $checked ? 'checked' : '' ?>
                               data-nom="<?= e($a['nom']) ?>" data-code="<?= e($a['code']) ?>" data-prix="<?= e(fmt_money((float)$a['prix_vente'])) ?>">
                        <span class="flex-grow-1" style="min-width:0;">
                            <span class="sp-cell-title d-block text-truncate"><?= e($a['nom']) ?></span>
                            <span class="sp-cell-sub d-block"><code><?= e($a['code']) ?></code> · <?= e($a['categorie_nom'] ?? 'Sans catégorie') ?></span>
                        </span>
                        <span class="small fw-semibold tabular text-nowrap"><?= fmt_money((float)$a['prix_vente']) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($articles)): ?><?= empty_state('fa-box-open', 'Aucun article actif', '', '', 'py-4') ?><?php endif; ?>
            </div>
        </div>

        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-sliders"></i></span>Options d'impression</h6></div>
            <div class="sp-card-body">
                <label class="form-label">Type d'étiquette</label>
                <div class="sp-seg mb-3" data-sp-seg>
                    <input type="radio" class="btn-check" name="type" id="tQr" value="qr" checked><label class="sp-seg-btn" for="tQr"><i class="fa-solid fa-qrcode"></i>QR code</label>
                    <input type="radio" class="btn-check" name="type" id="tBar" value="barcode"><label class="sp-seg-btn" for="tBar"><i class="fa-solid fa-barcode"></i>Code-barres</label>
                    <input type="radio" class="btn-check" name="type" id="tBoth" value="both"><label class="sp-seg-btn" for="tBoth">Les deux</label>
                </div>
                <label class="form-label">Taille</label>
                <div class="sp-seg mb-3" data-sp-seg>
                    <input type="radio" class="btn-check" name="taille" id="sSm" value="sm"><label class="sp-seg-btn" for="sSm">Petite (45 mm)</label>
                    <input type="radio" class="btn-check" name="taille" id="sMd" value="md" checked><label class="sp-seg-btn" for="sMd">Moyenne (60 mm)</label>
                    <input type="radio" class="btn-check" name="taille" id="sLg" value="lg"><label class="sp-seg-btn" for="sLg">Grande (80 mm)</label>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-5">
                        <label class="form-label" for="copies">Exemplaires / article</label>
                        <input type="number" id="copies" name="copies" class="form-control" value="1" min="1" max="50">
                    </div>
                    <div class="col-7">
                        <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" name="prix" value="1" id="optPrix" checked><label class="form-check-label" for="optPrix">Afficher le prix</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="boutique" value="1" id="optShop"><label class="form-check-label" for="optShop">Nom de la boutique</label></div>
                    </div>
                </div>
                <button class="btn btn-sp-amber w-100 mt-3" id="btnPrint" disabled><i class="fa-solid fa-print me-1"></i>Générer la planche d'étiquettes</button>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="sp-card" style="position:sticky;top:calc(var(--sp-topbar-h) + 14px);">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-eye"></i></span>Aperçu de la planche</h6>
                <span class="small text-muted" id="previewInfo"></span>
            </div>
            <div class="sp-card-body" style="background:repeating-linear-gradient(45deg, var(--sp-surface-2), var(--sp-surface-2) 10px, var(--sp-surface) 10px, var(--sp-surface) 20px); border-radius:0 0 var(--sp-radius-lg) var(--sp-radius-lg);">
                <div class="sp-label-preview" id="labelPreview"></div>
                <div id="previewEmpty"><?= empty_state('fa-qrcode', 'Aucun article sélectionné', 'Cochez des articles à gauche : l\'aperçu des étiquettes s\'affiche ici en direct.', '', 'py-4') ?></div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('labelForm');
    var shop = <?= js_json(get_param('nom_entreprise', APP_NAME)) ?>;
    SP.filter(document.getElementById('labelSearch'), '#pickList', '[data-filter-item]');
    function checked() { return Array.prototype.slice.call(form.querySelectorAll('input[name="ids[]"]:checked')); }
    function render() {
        var sel = checked();
        var type = form.querySelector('input[name="type"]:checked').value;
        var taille = form.querySelector('input[name="taille"]:checked').value;
        var copies = Math.max(1, Math.min(50, parseInt(document.getElementById('copies').value, 10) || 1));
        var withPrice = document.getElementById('optPrix').checked, withShop = document.getElementById('optShop').checked;
        document.getElementById('selCount').textContent = sel.length;
        document.getElementById('btnPrint').disabled = !sel.length;
        document.getElementById('previewEmpty').style.display = sel.length ? 'none' : '';
        form.querySelectorAll('.sp-label-pick').forEach(function (l) { l.classList.toggle('is-checked', l.querySelector('input').checked); });
        var total = sel.length * copies;
        document.getElementById('previewInfo').textContent = sel.length ? total + ' étiquette(s)' + (sel.length > 12 ? ' · aperçu des 12 premiers articles' : '') : '';
        var html = '';
        sel.slice(0, 12).forEach(function (c) {
            var id = c.value, codes = '';
            if (type === 'qr' || type === 'both') codes += '<img src="code_image.php?id=' + id + '&type=qr" alt="" style="width:' + (taille === 'sm' ? 20 : (taille === 'lg' ? 30 : 24)) + 'mm;height:auto;image-rendering:pixelated;">';
            if (type === 'barcode' || type === 'both') codes += '<img src="code_image.php?id=' + id + '&type=barcode" alt="" style="max-width:100%;display:block;margin:1mm auto 0;">';
            html += '<div class="label-item size-' + taille + '">' + (withShop ? '<div class="lb-shop">' + SP.esc(shop) + '</div>' : '') + codes +
                '<div class="lb-name">' + SP.esc(c.dataset.nom) + '</div><div class="lb-code">' + SP.esc(c.dataset.code) + '</div>' +
                (withPrice ? '<div class="lb-price">' + SP.esc(c.dataset.prix) + '</div>' : '') + '</div>';
        });
        document.getElementById('labelPreview').innerHTML = html;
    }
    form.addEventListener('change', render);
    document.getElementById('copies').addEventListener('input', render);
    document.getElementById('selAll').addEventListener('click', function () {
        form.querySelectorAll('.sp-label-pick').forEach(function (l) { if (l.style.display !== 'none') l.querySelector('input').checked = true; });
        render();
    });
    document.getElementById('selNone').addEventListener('click', function () { checked().forEach(function (c) { c.checked = false; }); render(); });
    render();
    var first = form.querySelector('.sp-label-pick.is-checked');
    if (first) first.scrollIntoView({ block: 'center' });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
