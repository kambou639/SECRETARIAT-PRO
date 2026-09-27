<?php
/**
 * Partiel de formulaire article. Attend : $article (array|null), $categories, $errors, $mode ('add'|'edit')
 */
$a = $article ?? [];
$devise = get_param('devise', 'FCFA');
$type = ($a['type'] ?? 'produit') === 'service' ? 'service' : 'produit';
$imageActuelle = $mode === 'edit' ? article_image_url($a['image'] ?? null) : null;
?>
<form method="post" enctype="multipart/form-data" novalidate data-sp-dirty-check id="articleForm">
    <?= csrf_field() ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-solid fa-circle-info"></i>Informations</div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="sp-choice">
                            <input type="radio" name="type" value="produit" <?= $type === 'produit' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-box"></i></span>
                                <span><span class="sp-choice-title d-block">Produit</span><span class="sp-choice-sub d-block">Fournitures, spirales… avec gestion du stock</span></span></span>
                        </label>
                    </div>
                    <div class="col-sm-6">
                        <label class="sp-choice tone-info">
                            <input type="radio" name="type" value="service" <?= $type === 'service' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-print"></i></span>
                                <span><span class="sp-choice-title d-block">Service</span><span class="sp-choice-sub d-block">Impression, photocopie… facturé à l'unité, sans stock</span></span></span>
                        </label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="fNom">Nom de l'article <span class="sp-required">*</span></label>
                        <input type="text" id="fNom" name="nom" class="form-control" required maxlength="150" value="<?= e($a['nom'] ?? '') ?>" placeholder="Ex. : Impression N&amp;B A4 (recto)">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fCode">Code article <span class="sp-required">*</span></label>
                        <div class="input-group">
                            <input type="text" id="fCode" name="code" class="form-control" required maxlength="30" style="text-transform:uppercase;"
                                   value="<?= e($a['code'] ?? ('ART-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)))) ?>">
                            <button type="button" class="btn btn-outline-secondary" id="btnGenCode" title="Générer un nouveau code"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                        </div>
                        <div class="form-text">Unique · sert au code-barres et au scan en caisse.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="fDesc">Description</label>
                        <textarea id="fDesc" name="description" class="form-control" rows="2" data-sp-autosize placeholder="Détails utiles (format, couleur, fournisseur…)"><?= e($a['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fCat">Catégorie</label>
                        <select id="fCat" name="categorie_id" class="form-select">
                            <option value="">- Aucune -</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (isset($a['categorie_id']) && (int)$a['categorie_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="uniteInput">Unité de vente</label>
                        <input type="text" name="unite" id="uniteInput" class="form-control" list="unitesList" maxlength="20" value="<?= e($a['unite'] ?? 'pièce') ?>">
                        <datalist id="unitesList">
                            <?php foreach (['pièce', 'page', 'feuille', 'rame', 'paquet', 'boîte', 'lot', 'mètre', 'heure', 'forfait'] as $un): ?><option value="<?= $un ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
            </div>

            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-solid fa-tags"></i>Prix &amp; marge</div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="fPa">Prix d'achat / coût</label>
                        <div class="input-group">
                            <input type="number" id="fPa" step="0.01" min="0" name="prix_achat" class="form-control" value="<?= e((string)($a['prix_achat'] ?? '0')) ?>">
                            <span class="input-group-text"><?= e($devise) ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fPv">Prix de vente <span class="sp-required">*</span></label>
                        <div class="input-group">
                            <input type="number" id="fPv" step="0.01" min="0" name="prix_vente" class="form-control" required value="<?= e((string)($a['prix_vente'] ?? '0')) ?>">
                            <span class="input-group-text"><?= e($devise) ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="sp-price-preview py-2" id="margeBox">
                            <div class="small-caps">Marge unitaire</div>
                            <div class="fw-bold fs-5 font-display" id="margeValeur">—</div>
                            <div class="pp-detail" id="margePct"></div>
                        </div>
                    </div>
                    <div class="col-12"><div class="form-text m-0" id="prixVenteHint"><i class="fa-solid fa-circle-info me-1"></i>Pour un service, indiquez le prix par unité (ex. par page). Des tarifs dégressifs peuvent être ajoutés ensuite.</div></div>
                </div>
            </div>

            <div class="sp-form-section" id="stockSection">
                <div class="sp-form-section-title"><i class="fa-solid fa-boxes-stacked"></i>Stock</div>
                <div class="row g-3">
                    <div class="col-md-6" id="stockField">
                        <label class="form-label" for="fStock">Stock <?= $mode === 'add' ? 'initial' : 'actuel' ?></label>
                        <input type="number" id="fStock" min="0" name="stock" class="form-control" value="<?= e((string)($a['stock'] ?? '0')) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?>>
                        <?php if ($mode === 'edit'): ?><div class="form-text"><a href="stock_ajuster.php?id=<?= (int)$a['id'] ?>"><i class="fa-solid fa-arrows-rotate me-1"></i>Enregistrer une entrée / sortie de stock</a></div><?php endif; ?>
                    </div>
                    <div class="col-md-6" id="seuilField">
                        <label class="form-label" for="fSeuil">Seuil d'alerte</label>
                        <input type="number" id="fSeuil" min="0" name="seuil_alerte" class="form-control" value="<?= e((string)($a['seuil_alerte'] ?? '5')) ?>">
                        <div class="form-text">Une alerte apparaît quand le stock atteint ce niveau.</div>
                    </div>
                </div>
            </div>

            <?php if ($mode === 'edit'): ?>
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="actif" id="actif" value="1" <?= !empty($a['actif']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="actif">Article actif (visible en caisse et dans le catalogue)</label>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="sp-form-section-title"><i class="fa-solid fa-image"></i>Image</div>
            <label class="sp-dropzone mb-2<?= $imageActuelle ? ' has-file' : '' ?>">
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" aria-label="Image de l'article">
                <span class="dz-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <span class="dz-text"><?php if ($imageActuelle): ?><strong>Image actuelle</strong>Déposez une nouvelle image pour la remplacer<?php else: ?><strong>Glissez une image ici</strong>ou cliquez pour parcourir · JPG, PNG, WebP · 5 Mo max<?php endif; ?></span>
                <img class="dz-preview" alt="" <?= $imageActuelle ? 'src="' . e($imageActuelle) . '"' : '' ?>>
            </label>
            <?php if ($imageActuelle): ?>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="supprimer_image" id="supprimerImage" value="1">
                    <label class="form-check-label small" for="supprimerImage">Retirer l'image actuelle</label>
                </div>
            <?php endif; ?>

            <div class="small-caps mt-4 mb-2">Aperçu en caisse</div>
            <div style="max-width:220px;">
                <div class="pos-article-card" id="previewCard" aria-hidden="true">
                    <div class="pos-icon" id="previewIcon"><?php if ($imageActuelle): ?><img src="<?= e($imageActuelle) ?>" alt=""><?php else: ?><i class="fa-solid fa-box"></i><?php endif; ?></div>
                    <div class="pos-meta">
                        <div class="pos-name" id="previewName">Nom de l'article</div>
                        <div class="pos-code" id="previewCode"></div>
                    </div>
                    <div class="pos-bottom">
                        <div class="pos-price"><span id="previewPrice">0</span> <small><?= e($devise) ?>/<span id="previewUnit">pièce</span></small></div>
                        <span class="pos-stock" id="previewStock">Stock 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sp-form-actions">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <?php if ($mode === 'add'): ?>
            <button type="submit" name="apres" value="nouveau" class="btn btn-outline-secondary"><i class="fa-solid fa-plus me-1"></i>Enregistrer et en ajouter un autre</button>
        <?php endif; ?>
        <a href="liste.php" class="btn btn-ghost">Annuler</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('articleForm');
    var $ = function (id) { return document.getElementById(id); };
    function isService() { var r = form.querySelector('input[name="type"]:checked'); return r && r.value === 'service'; }
    function refresh() {
        var svc = isService();
        $('stockSection').style.display = svc ? 'none' : '';
        $('prixVenteHint').style.display = svc ? '' : 'none';
        if (svc && ['', 'pièce'].indexOf($('uniteInput').value.trim()) > -1 && !$('uniteInput').dataset.touched) $('uniteInput').value = 'page';
        if (!svc && $('uniteInput').value.trim() === 'page' && !$('uniteInput').dataset.touched) $('uniteInput').value = 'pièce';
        var pa = parseFloat($('fPa').value) || 0, pv = parseFloat($('fPv').value) || 0;
        var box = $('margeBox');
        if (pv > 0 && pa > 0) {
            var m = pv - pa, pct = m / pv * 100;
            $('margeValeur').textContent = SP.money(m);
            $('margePct').textContent = SP.num(pct, 1) + ' % du prix de vente · coef. ' + SP.num(pv / pa, 2);
            box.style.borderColor = pct >= 30 ? 'var(--sp-success)' : (pct >= 10 ? 'var(--sp-warning)' : 'var(--sp-danger)');
        } else {
            $('margeValeur').textContent = '—';
            $('margePct').textContent = pv > 0 ? 'Indiquez le coût pour calculer la marge' : '';
            box.style.borderColor = '';
        }
        // Aperçu de la carte en caisse
        var card = $('previewCard');
        card.classList.toggle('is-service', svc);
        $('previewName').textContent = $('fNom').value.trim() || 'Nom de l\'article';
        $('previewCode').textContent = $('fCode').value.trim().toUpperCase();
        $('previewPrice').textContent = SP.num(pv);
        $('previewUnit').textContent = $('uniteInput').value.trim() || 'pièce';
        var st = $('previewStock');
        if (svc) { st.className = 'pos-stock svc'; st.innerHTML = '<i class="fa-solid fa-infinity"></i> Service'; }
        else {
            var stock = parseInt($('fStock').value, 10) || 0, seuil = parseInt($('fSeuil').value, 10) || 0;
            st.className = 'pos-stock' + (stock <= seuil ? ' low' : '');
            st.textContent = stock <= 0 ? 'Rupture' : 'Stock ' + stock;
        }
        var icon = $('previewIcon');
        if (!icon.querySelector('img')) icon.innerHTML = '<i class="fa-solid ' + (svc ? 'fa-print' : 'fa-box') + '"></i>';
    }
    var iconOrigine = $('previewIcon').innerHTML;
    form.addEventListener('input', refresh);
    form.addEventListener('change', function (e) {
        if (e.target.name === 'image' && e.target.files && e.target.files[0] && window.URL) {
            $('previewIcon').innerHTML = '<img src="' + URL.createObjectURL(e.target.files[0]) + '" alt="">';
        }
        if (e.target.id === 'supprimerImage') {
            $('previewIcon').innerHTML = e.target.checked ? '' : iconOrigine;
        }
        refresh();
    });
    $('uniteInput').addEventListener('input', function () { this.dataset.touched = '1'; });
    $('btnGenCode').addEventListener('click', function () {
        var prefix = isService() ? 'SRV-' : 'ART-';
        var rnd = Math.random().toString(16).slice(2, 7).toUpperCase();
        $('fCode').value = prefix + rnd;
        $('fCode').classList.remove('sp-anim-bump'); void $('fCode').offsetWidth; $('fCode').classList.add('sp-anim-bump');
        refresh();
    });
    refresh();
});
</script>
