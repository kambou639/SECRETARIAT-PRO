<?php
$c = $courrier ?? [];
$type = ($c['type'] ?? ($_GET['type'] ?? 'entrant')) === 'sortant' ? 'sortant' : 'entrant';
$statutActuel = $c['statut'] ?? ($type === 'sortant' ? 'envoye' : 'recu');
$fichierUrl = !empty($c['fichier_joint']) ? upload_url('courriers', $c['fichier_joint']) : null;
?>
<form method="post" enctype="multipart/form-data" novalidate data-sp-dirty-check id="courrierForm">
    <?= csrf_field() ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-solid fa-envelope"></i>Courrier</div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="sp-choice tone-success"><input type="radio" name="type" value="entrant" <?= $type === 'entrant' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-arrow-down"></i></span><span><span class="sp-choice-title d-block">Courrier entrant</span><span class="sp-choice-sub d-block">Reçu par la structure</span></span></span></label>
                    </div>
                    <div class="col-sm-6">
                        <label class="sp-choice"><input type="radio" name="type" value="sortant" <?= $type === 'sortant' ? 'checked' : '' ?>>
                            <span class="sp-choice-body"><span class="sp-choice-icon"><i class="fa-solid fa-arrow-up"></i></span><span><span class="sp-choice-title d-block">Courrier sortant</span><span class="sp-choice-sub d-block">Envoyé par la structure</span></span></span></label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="cObjet">Objet <span class="sp-required">*</span></label>
                        <input type="text" id="cObjet" name="objet" class="form-control" required maxlength="255" value="<?= e($c['objet'] ?? '') ?>" placeholder="Ex. : Demande de devis pour impression de brochures">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cNumero">Numéro / référence <span class="sp-required">*</span></label>
                        <input type="text" id="cNumero" name="numero" class="form-control" required maxlength="50" value="<?= e($c['numero'] ?? generate_reference('CR')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cDate">Date du courrier <span class="sp-required">*</span></label>
                        <input type="date" id="cDate" name="date_courrier" class="form-control" required max="<?= date('Y-m-d', strtotime('+1 year')) ?>" value="<?= e($c['date_courrier'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6" id="fieldExp">
                        <label class="form-label" for="cExp">Expéditeur</label>
                        <div class="sp-input-icon"><i class="fa-solid fa-user-pen"></i><input type="text" id="cExp" name="expediteur" class="form-control" maxlength="150" value="<?= e($c['expediteur'] ?? '') ?>" placeholder="Personne ou organisme"></div>
                    </div>
                    <div class="col-md-6" id="fieldDest">
                        <label class="form-label" for="cDest">Destinataire</label>
                        <div class="sp-input-icon"><i class="fa-solid fa-user-tag"></i><input type="text" id="cDest" name="destinataire" class="form-control" maxlength="150" value="<?= e($c['destinataire'] ?? '') ?>" placeholder="Personne ou service"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cObs">Observations</label>
                        <textarea id="cObs" name="observations" class="form-control" rows="3" data-sp-autosize placeholder="Suite à donner, personne en charge, délai…"><?= e($c['observations'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="sp-form-section-title"><i class="fa-solid fa-list-check"></i>Statut</div>
            <div class="d-grid gap-2 mb-4" id="statutChoices">
                <?php foreach (['recu', 'en_traitement', 'traite', 'envoye', 'archive'] as $k): [$label, $tone, $icon] = statut_courrier_meta($k); ?>
                    <label class="sp-choice <?= in_array($tone, ['success', 'info', 'danger'], true) ? 'tone-' . $tone : '' ?>" data-statut="<?= $k ?>">
                        <input type="radio" name="statut" value="<?= $k ?>" <?= $statutActuel === $k ? 'checked' : '' ?>>
                        <span class="sp-choice-body py-2"><span class="sp-choice-icon" style="width:30px;height:30px;"><i class="fa-solid <?= e($icon) ?>"></i></span><span class="sp-choice-title"><?= e($label) ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="sp-form-section-title"><i class="fa-solid fa-paperclip"></i>Pièce jointe</div>
            <label class="sp-dropzone mb-2">
                <input type="file" name="fichier_joint" accept=".pdf,.jpg,.jpeg,.png,.webp" aria-label="Pièce jointe">
                <span class="dz-icon"><i class="fa-solid fa-file-arrow-up"></i></span>
                <span class="dz-text"><strong>Scan ou PDF du courrier</strong>Glissez le fichier ici · PDF, JPG, PNG · 5 Mo max</span>
                <img class="dz-preview" alt="">
            </label>
            <?php if ($fichierUrl): ?>
                <div class="d-flex align-items-center justify-content-between gap-2 small">
                    <a href="<?= e($fichierUrl) ?>" target="_blank" rel="noopener" class="sp-attach"><i class="fa-solid <?= str_ends_with(strtolower($c['fichier_joint']), '.pdf') ? 'fa-file-pdf' : 'fa-file-image' ?>"></i>Fichier actuel</a>
                    <div class="form-check m-0"><input type="checkbox" class="form-check-input" name="supprimer_fichier" value="1" id="supprFichier"><label class="form-check-label" for="supprFichier">Retirer</label></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sp-form-actions">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="<?= !empty($c['id']) ? 'voir.php?id=' . (int)$c['id'] : 'liste.php' ?>" class="btn btn-ghost">Annuler</a>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('courrierForm');
    function refresh(changedType) {
        var entrant = form.querySelector('input[name="type"]:checked').value === 'entrant';
        document.getElementById('fieldExp').style.order = entrant ? 0 : 1;
        document.querySelector('#fieldExp .form-label').textContent = entrant ? 'Expéditeur' : 'Expéditeur (service interne)';
        document.querySelector('#fieldDest .form-label').textContent = entrant ? 'Destinataire (service interne)' : 'Destinataire';
        form.querySelector('[data-statut="recu"]').style.display = entrant ? '' : 'none';
        form.querySelector('[data-statut="envoye"]').style.display = entrant ? 'none' : '';
        var checked = form.querySelector('input[name="statut"]:checked');
        if (changedType || !checked || checked.closest('label').style.display === 'none') {
            form.querySelector('input[name="statut"][value="' + (entrant ? 'recu' : 'envoye') + '"]').checked = true;
        }
    }
    form.addEventListener('change', function (e) { refresh(e.target.name === 'type'); });
    refresh(false);
});
</script>
