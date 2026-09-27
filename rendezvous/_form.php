<?php
$r = $rdv ?? [];
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-semibold">Titre <span class="text-danger">*</span></label>
            <input type="text" name="titre" class="form-control" required value="<?= e($r['titre'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" name="date_rdv" class="form-control" required value="<?= e($r['date_rdv'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Heure début <span class="text-danger">*</span></label>
            <input type="time" name="heure_debut" class="form-control" required value="<?= e(substr($r['heure_debut'] ?? '09:00', 0, 5)) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Heure fin</label>
            <input type="time" name="heure_fin" class="form-control" value="<?= e(substr($r['heure_fin'] ?? '', 0, 5)) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Lieu</label>
            <input type="text" name="lieu" class="form-control" value="<?= e($r['lieu'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Client lié (optionnel)</label>
            <select name="client_id" class="form-select">
                <option value="">- Aucun -</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (isset($r['client_id']) && $r['client_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['nom'] . ' ' . $c['prenom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Contact (si pas de client lié)</label>
            <input type="text" name="contact_nom" class="form-control" value="<?= e($r['contact_nom'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Téléphone contact</label>
            <input type="text" name="contact_telephone" class="form-control" value="<?= e($r['contact_telephone'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Statut</label>
            <select name="statut" class="form-select">
                <?php foreach (['planifie'=>'Planifié','confirme'=>'Confirmé','annule'=>'Annulé','termine'=>'Terminé'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= ($r['statut'] ?? 'planifie')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= e($r['description'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="agenda.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
