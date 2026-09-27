<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $champs = ['nom_entreprise', 'slogan', 'adresse', 'telephone', 'email', 'devise', 'prefixe_facture'];
    $stmt = $pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    foreach ($champs as $champ) {
        $stmt->execute([$champ, clean_input($_POST[$champ] ?? '')]);
    }

    if (!empty($_FILES['logo']['name'])) {
        try {
            $logo = handle_upload($_FILES['logo'], UPLOAD_LOGO, ['jpg','jpeg','png','webp']);
            if ($logo) {
                $stmt->execute(['logo', $logo]);
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        log_activity('parametres_modification', 'Paramètres entreprise mis à jour');
        flash_set('success', 'Paramètres mis à jour avec succès.');
        redirect('entreprise.php');
    }
}

$pageTitle = 'Paramètres de l\'entreprise';
$activeMenu = 'parametres';
include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Informations de l'entreprise</h6></div>
            <div class="sp-card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nom de l'entreprise</label>
                            <input type="text" name="nom_entreprise" class="form-control" value="<?= e(get_param('nom_entreprise')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Devise</label>
                            <input type="text" name="devise" class="form-control" value="<?= e(get_param('devise')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Slogan</label>
                            <input type="text" name="slogan" class="form-control" value="<?= e(get_param('slogan')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Adresse</label>
                            <input type="text" name="adresse" class="form-control" value="<?= e(get_param('adresse')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" value="<?= e(get_param('telephone')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e(get_param('email')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Préfixe des factures</label>
                            <input type="text" name="prefixe_facture" class="form-control" value="<?= e(get_param('prefixe_facture')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Logo</label>
                            <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (get_param('logo')): ?>
                                <img src="../uploads/logo/<?= e(get_param('logo')) ?>" style="max-height:50px;" class="mt-2 rounded border p-1">
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sp-amber mt-4"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
