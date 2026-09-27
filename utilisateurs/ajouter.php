<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = clean_input($_POST['username'] ?? '');
    $fullName = clean_input($_POST['full_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $telephone = clean_input($_POST['telephone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin','secretaire','vendeur'], true) ? $_POST['role'] : 'vendeur';
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($username === '' || $fullName === '') {
        $errors[] = 'Identifiant et nom complet sont obligatoires.';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'L\'identifiant doit contenir 3 à 50 caractères (lettres, chiffres, . _ -).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet identifiant est déjà utilisé.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, telephone, role, actif) VALUES (?,?,?,?,?,?,1)');
        $stmt->execute([$username, $hash, $fullName, $email, $telephone, $role]);
        log_activity('utilisateur_creation', "Utilisateur créé : $username ($role)");
        flash_set('success', 'Utilisateur créé avec succès.');
        redirect('liste.php');
    }
}

$pageTitle = 'Nouvel utilisateur';
$activeMenu = 'utilisateurs';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6>Nouvel utilisateur</h6></div>
    <div class="sp-card-body">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Identifiant de connexion <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= e($_POST['telephone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rôle <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="admin">Administrateur</option>
                        <option value="secretaire">Secrétaire</option>
                        <option value="vendeur" selected>Vendeur</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Confirmer <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Créer</button>
                <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
