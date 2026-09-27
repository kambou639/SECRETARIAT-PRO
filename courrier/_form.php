<?php
$c = $courrier ?? [];
?>
<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <select name="type" class="form-select" required>
                <option value="entrant" <?= ($c['type'] ?? '')==='entrant'?'selected':'' ?>>Courrier entrant</option>
                <option value="sortant" <?= ($c['type'] ?? '')==='sortant'?'selected':'' ?>>Courrier sortant</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Numéro / Référence <span class="text-danger">*</span></label>
            <input type="text" name="numero" class="form-control" required value="<?= e($c['numero'] ?? generate_reference('CR')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Date du courrier <span class="text-danger">*</span></label>
            <input type="date" name="date_courrier" class="form-control" required value="<?= e($c['date_courrier'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Objet <span class="text-danger">*</span></label>
            <input type="text" name="objet" class="form-control" required value="<?= e($c['objet'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Expéditeur</label>
            <input type="text" name="expediteur" class="form-control" value="<?= e($c['expediteur'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Destinataire</label>
            <input type="text" name="destinataire" class="form-control" value="<?= e($c['destinataire'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Statut</label>
            <select name="statut" class="form-select">
                <?php foreach (['recu'=>'Reçu','en_traitement'=>'En traitement','traite'=>'Traité','archive'=>'Archivé','envoye'=>'Envoyé'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= ($c['statut'] ?? 'recu')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Pièce jointe (PDF/Image)</label>
            <input type="file" name="fichier_joint" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($c['fichier_joint'])): ?>
                <div class="form-text">Fichier actuel : <a href="../uploads/courriers/<?= e($c['fichier_joint']) ?>" target="_blank"><?= e($c['fichier_joint']) ?></a></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Observations</label>
            <textarea name="observations" class="form-control" rows="3"><?= e($c['observations'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
