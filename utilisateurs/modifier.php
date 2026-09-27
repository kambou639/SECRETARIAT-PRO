<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) {
    flash_set('danger', 'Utilisateur introuvable.');
    redirect('liste.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fullName = clean_input($_POST['full_name'] ?? '');
    $username = clean_input($_POST['username'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $telephone = clean_input($_POST['telephone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin','secretaire','vendeur'], true) ? $_POST['role'] : $u['role'];
    $actif = !empty($_POST['actif']) ? 1 : 0;
    $newPassword = (string)($_POST['password'] ?? '');

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
    if ($id == $_SESSION['user_id'] && $actif == 0) {
        $errors[] = 'Vous ne pouvez pas désactiver votre propre compte.';
    }
    if ($id == $_SESSION['user_id'] && $role !== 'admin') {
        $errors[] = 'Vous ne pouvez pas changer votre propre rôle administrateur.';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    }

    if (empty($errors)) {
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET full_name=?, username=?, email=?, telephone=?, role=?, actif=?, password_hash=?, tentatives_echouees=0, bloque_jusqu=NULL WHERE id=?')
                ->execute([$fullName, $username, $email, $telephone, $role, $actif, $hash, $id]);
        } else {
            $pdo->prepare('UPDATE users SET full_name=?, username=?, email=?, telephone=?, role=?, actif=? WHERE id=?')
                ->execute([$fullName, $username, $email, $telephone, $role, $actif, $id]);
        }
        if ($id == $_SESSION['user_id']) {
            $_SESSION['username'] = $username;
        }
        log_activity('utilisateur_modification', "Utilisateur modifié : {$u['username']}");
        flash_set('success', 'Utilisateur mis à jour.');
        redirect('liste.php');
    }
}

$pageTitle = 'Modifier l\'utilisateur';
$activeMenu = 'utilisateurs';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6>Modifier : <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</h6></div>
    <div class="sp-card-body">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($u['full_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Identifiant <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?= e($u['username']) ?>" pattern="[a-zA-Z0-9._-]{3,50}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= e($u['telephone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rôle</label>
                    <select name="role" class="form-select" <?= $id == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                        <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur</option>
                        <option value="secretaire" <?= $u['role']==='secretaire'?'selected':'' ?>>Secrétaire</option>
                        <option value="vendeur" <?= $u['role']==='vendeur'?'selected':'' ?>>Vendeur</option>
                    </select>
                    <?php if ($id == $_SESSION['user_id']): ?><input type="hidden" name="role" value="<?= e($u['role']) ?>"><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nouveau mot de passe</label>
                    <input type="password" name="password" class="form-control" minlength="8" placeholder="Laisser vide pour ne pas changer">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="actif" id="actif" value="1" <?= $u['actif'] ? 'checked' : '' ?> <?= $id == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="actif">Compte actif</label>
                    </div>
                    <?php if ($id == $_SESSION['user_id']): ?><input type="hidden" name="actif" value="1"><?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
                <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
