<?php
$c = $client ?? [];
$type = ($c['type'] ?? 'particulier') === 'entreprise' ? 'entreprise' : 'particulier';
?>
<form method="post" novalidate data-sp-dirty-check id="clientForm">
    <?= csrf_field() ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div>
    <?php endif; ?>

    <div class="sp-form-section">
        <div class="sp-form-section-title"><i class="fa-solid fa-id-card"></i>Identité</div>
        <div class="row g-2 mb-3">
            <div class="col-sm-6 col-lg-4">
                <label class="sp-choice"><input type="radio" name="type" value="particulier" <?= $type === 'particulier' ? 'checked' : '' ?>>
                    <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-user"></i></span><span><span class="sp-choice-title d-block">Particulier</span><span class="sp-choice-sub d-block">Client individuel</span></span></span></label>
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="sp-choice tone-info"><input type="radio" name="type" value="entreprise" <?= $type === 'entreprise' ? 'checked' : '' ?>>
                    <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-building"></i></span><span><span class="sp-choice-title d-block">Entreprise</span><span class="sp-choice-sub d-block">Société, école, ONG, administration</span></span></span></label>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="cNom" id="cNomLabel">Nom <span class="sp-required">*</span></label>
                <input type="text" id="cNom" name="nom" class="form-control" required maxlength="100" value="<?= e($c['nom'] ?? '') ?>">
            </div>
            <div class="col-md-6" id="prenomField">
                <label class="form-label" for="cPrenom">Prénom</label>
                <input type="text" id="cPrenom" name="prenom" class="form-control" maxlength="100" value="<?= e($c['prenom'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="sp-form-section">
        <div class="sp-form-section-title"><i class="fa-solid fa-address-card"></i>Coordonnées</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="cTel">Téléphone</label>
                <div class="sp-input-icon"><i class="fa-solid fa-phone"></i><input type="tel" id="cTel" name="telephone" class="form-control" maxlength="30" placeholder="Ex. 70 12 34 56" value="<?= e($c['telephone'] ?? '') ?>"></div>
                <div class="form-text">Permet l'appel direct et la relance par WhatsApp.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cEmail">Email</label>
                <div class="sp-input-icon"><i class="fa-solid fa-at"></i><input type="email" id="cEmail" name="email" class="form-control" maxlength="150" value="<?= e($c['email'] ?? '') ?>"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cVille">Ville</label>
                <div class="sp-input-icon"><i class="fa-solid fa-city"></i><input type="text" id="cVille" name="ville" class="form-control" maxlength="100" list="villesList" value="<?= e($c['ville'] ?? '') ?>"></div>
                <datalist id="villesList"><?php foreach (['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Ouahigouya', 'Banfora', 'Kaya', 'Tenkodogo', 'Fada N\'Gourma', 'Dédougou'] as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cAdresse">Adresse</label>
                <div class="sp-input-icon"><i class="fa-solid fa-location-dot"></i><input type="text" id="cAdresse" name="adresse" class="form-control" maxlength="255" placeholder="Quartier, secteur, rue…" value="<?= e($c['adresse'] ?? '') ?>"></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="cNotes">Notes</label>
                <textarea id="cNotes" name="notes" class="form-control" rows="2" data-sp-autosize placeholder="Préférences, informations utiles…"><?= e($c['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="sp-form-actions">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="<?= !empty($c['id']) ? 'fiche.php?id=' . (int)$c['id'] : 'liste.php' ?>" class="btn btn-ghost">Annuler</a>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('clientForm');
    function refresh() {
        var ent = form.querySelector('input[name="type"]:checked').value === 'entreprise';
        document.getElementById('prenomField').style.display = ent ? 'none' : '';
        document.getElementById('cNomLabel').firstChild.textContent = ent ? 'Raison sociale ' : 'Nom ';
    }
    form.addEventListener('change', refresh);
    refresh();
});
</script>
