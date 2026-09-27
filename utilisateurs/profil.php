<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fullName = clean_input($_POST['full_name'] ?? '');
    $username = clean_input($_POST['username'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $telephone = clean_input($_POST['telephone'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Le nom complet est obligatoire.';
    }
    if ($username === '') {
        $errors[] = 'L\'identifiant est obligatoire.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'L\'identifiant doit contenir entre 3 et 50 caractères (lettres, chiffres, points, tirets, underscores).';
    } else {
        $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmtCheck->execute([$username, $id]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'Cet identifiant est déjà utilisé par un autre utilisateur.';
        }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    }

    $changePassword = $newPassword !== '' || $newPasswordConfirm !== '';
    if ($changePassword) {
        if (!password_verify($currentPassword, $u['password_hash'])) {
            $errors[] = 'Mot de passe actuel incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }
    }

    if (empty($errors)) {
        if ($changePassword) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET full_name=?, username=?, email=?, telephone=?, password_hash=? WHERE id=?')
                ->execute([$fullName, $username, $email, $telephone, $hash, $id]);
        } else {
            $pdo->prepare('UPDATE users SET full_name=?, username=?, email=?, telephone=? WHERE id=?')
                ->execute([$fullName, $username, $email, $telephone, $id]);
        }
        $_SESSION['full_name'] = $fullName;
        $_SESSION['username'] = $username;
        log_activity('profil_modification', 'Profil personnel mis à jour');
        flash_set('success', 'Profil mis à jour avec succès.');
        redirect('profil.php');
    }
}

$pageTitle = 'Mon profil';
$activeMenu = '';
include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Mon profil</h6></div>
            <div class="sp-card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom complet</label>
                        <input type="text" name="full_name" class="form-control" required value="<?= e($u['full_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Identifiant</label>
                        <input type="text" name="username" class="form-control" required value="<?= e($u['username']) ?>" pattern="[a-zA-Z0-9._-]{3,50}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" name="telephone" class="form-control" value="<?= e($u['telephone']) ?>">
                    </div>
                    <hr>
                    <h6 class="fw-semibold">Changer le mot de passe (optionnel)</h6>
                    <div class="mb-3">
                        <label class="form-label small">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control" minlength="8">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Confirmer</label>
                            <input type="password" name="new_password_confirm" class="form-control" minlength="8">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sp-amber mt-4"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
