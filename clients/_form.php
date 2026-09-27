<?php
$c = $client ?? [];
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Type</label>
            <select name="type" class="form-select">
                <option value="particulier" <?= ($c['type'] ?? '') === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                <option value="entreprise" <?= ($c['type'] ?? '') === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
            <input type="text" name="nom" class="form-control" required value="<?= e($c['nom'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Prénom</label>
            <input type="text" name="prenom" class="form-control" value="<?= e($c['prenom'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Téléphone</label>
            <input type="text" name="telephone" class="form-control" value="<?= e($c['telephone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($c['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Ville</label>
            <input type="text" name="ville" class="form-control" value="<?= e($c['ville'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Adresse</label>
            <input type="text" name="adresse" class="form-control" value="<?= e($c['adresse'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($c['notes'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
