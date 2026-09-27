<?php
/**
 * Partiel de formulaire article. Attend : $article (array|null), $categories, $errors, $mode ('add'|'edit')
 */
$a = $article ?? [];
?>
<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Code article <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control" required maxlength="30"
                   value="<?= e($a['code'] ?? ('ART-' . strtoupper(substr(bin2hex(random_bytes(3)),0,5)))) ?>">
            <div class="form-text">Utilisé pour le code-barres. Unique.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nom de l'article <span class="text-danger">*</span></label>
            <input type="text" name="nom" class="form-control" required maxlength="150" value="<?= e($a['nom'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <select name="type" id="typeSelect" class="form-select" onchange="toggleStockFields()">
                <option value="produit" <?= ($a['type'] ?? 'produit') === 'produit' ? 'selected' : '' ?>>Produit (stock géré)</option>
                <option value="service" <?= ($a['type'] ?? '') === 'service' ? 'selected' : '' ?>>Service (impression, façonnage... - pas de stock)</option>
            </select>
            <div class="form-text">Ex : « Impression N&amp;B A4 » = service facturé à la page. « Spirale » = produit avec stock.</div>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2"><?= e($a['description'] ?? '') ?></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Catégorie</label>
            <select name="categorie_id" class="form-select">
                <option value="">- Aucune -</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (isset($a['categorie_id']) && $a['categorie_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Unité</label>
            <input type="text" name="unite" id="uniteInput" class="form-control" value="<?= e($a['unite'] ?? 'pièce') ?>">
            <div class="form-text">Ex : pièce, page, feuille, mètre...</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Image</label>
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold">Prix d'achat / coût</label>
            <div class="input-group">
                <input type="number" step="0.01" min="0" name="prix_achat" class="form-control" value="<?= e((string)($a['prix_achat'] ?? '0')) ?>">
                <span class="input-group-text"><?= e(get_param('devise','FCFA')) ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Prix de vente <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" step="0.01" min="0" name="prix_vente" class="form-control" required value="<?= e((string)($a['prix_vente'] ?? '0')) ?>">
                <span class="input-group-text"><?= e(get_param('devise','FCFA')) ?></span>
            </div>
            <div class="form-text" id="prixVenteHint">Pour un service : prix par unité (ex. par page).</div>
        </div>
        <div class="col-md-3" id="stockField">
            <label class="form-label fw-semibold">Stock <?= $mode === 'add' ? 'initial' : 'actuel' ?></label>
            <input type="number" min="0" name="stock" class="form-control" value="<?= e((string)($a['stock'] ?? '0')) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?>>
            <?php if ($mode === 'edit'): ?><div class="form-text">Utilisez un mouvement de stock pour ajuster la quantité.</div><?php endif; ?>
        </div>
        <div class="col-md-3" id="seuilField">
            <label class="form-label fw-semibold">Seuil d'alerte</label>
            <input type="number" min="0" name="seuil_alerte" class="form-control" value="<?= e((string)($a['seuil_alerte'] ?? '5')) ?>">
        </div>

        <?php if ($mode === 'edit'): ?>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="actif" id="actif" value="1" <?= !empty($a['actif']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="actif">Article actif (visible en caisse et catalogue)</label>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleStockFields() {
        const isService = document.getElementById('typeSelect').value === 'service';
        document.getElementById('stockField').style.display = isService ? 'none' : '';
        document.getElementById('seuilField').style.display = isService ? 'none' : '';
        document.getElementById('prixVenteHint').style.display = isService ? '' : 'none';
        if (isService && !document.getElementById('uniteInput').value.trim()) {
            document.getElementById('uniteInput').value = 'page';
        }
    }
    document.addEventListener('DOMContentLoaded', toggleStockFields);
    </script>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
