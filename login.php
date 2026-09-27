<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

// Déjà connecté ? -> tableau de bord
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Session expirée, merci de réessayer.';
    } else {
        $username = clean_input($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'Veuillez renseigner votre identifiant et votre mot de passe.';
        } else {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $u = $stmt->fetch();

                if (!$u) {
                    // Réponse générique (ne pas révéler si le compte existe)
                    $errors[] = 'Identifiant ou mot de passe incorrect.';
                    usleep(300000);
                } elseif (!empty($u['bloque_jusqu']) && strtotime($u['bloque_jusqu']) > time()) {
                    $minutes = ceil((strtotime($u['bloque_jusqu']) - time()) / 60);
                    $errors[] = "Compte temporairement bloqué suite à plusieurs échecs. Réessayez dans {$minutes} min.";
                } elseif (!$u['actif']) {
                    $errors[] = 'Ce compte a été désactivé. Contactez un administrateur.';
                } elseif (!password_verify($password, $u['password_hash'])) {
                    $attempts = (int)$u['tentatives_echouees'] + 1;
                    $lockUntil = null;
                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
                        $errors[] = 'Trop de tentatives échouées. Compte bloqué ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
                    } else {
                        $errors[] = 'Identifiant ou mot de passe incorrect.';
                    }
                    $upd = $pdo->prepare('UPDATE users SET tentatives_echouees = ?, bloque_jusqu = ? WHERE id = ?');
                    $upd->execute([$attempts, $lockUntil, $u['id']]);
                } else {
                    // Connexion réussie
                    $pdo->prepare('UPDATE users SET tentatives_echouees = 0, bloque_jusqu = NULL, derniere_connexion = NOW() WHERE id = ?')
                        ->execute([$u['id']]);

                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $u['id'];
                    $_SESSION['username']  = $u['username'];
                    $_SESSION['full_name'] = $u['full_name'];
                    $_SESSION['role']      = $u['role'];

                    log_activity('connexion', 'Connexion réussie');
                    redirect('dashboard.php');
                }
            } catch (Exception $e) {
                error_log('login: ' . $e->getMessage());
                $errors[] = 'Erreur technique. Vérifiez la configuration de la base de données (config/config.php).';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/inter.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="sp-login-wrap">
    <div class="sp-login-card">
        <div class="sp-login-side">
            <div class="logo-badge-lg"><i class="fa-solid fa-briefcase"></i></div>
            <h2 class="fw-bold mb-0">Secrétariat Pro</h2>
            <p class="mb-0" style="opacity:.85;">Gestion de secrétariat, courrier, rendez-vous et vente d'articles - plateforme intégrée, multi-utilisateurs.</p>
            <ul class="list-unstyled mt-3" style="opacity:.9; font-size:.9rem;">
                <li class="mb-2"><i class="fa-solid fa-check me-2"></i>Caisse &amp; vente d'articles</li>
                <li class="mb-2"><i class="fa-solid fa-check me-2"></i>Courrier &amp; agenda</li>
                <li class="mb-2"><i class="fa-solid fa-check me-2"></i>Étiquettes QR / codes-barres</li>
                <li class="mb-2"><i class="fa-solid fa-check me-2"></i>Import / export Excel</li>
            </ul>
        </div>
        <div class="sp-login-form">
            <h4 class="fw-bold mb-1" style="color:var(--sp-navy);">Connexion</h4>
            <p class="text-muted mb-4">Accédez à votre espace de travail</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger py-2"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Identifiant</label>
                    <input type="text" name="username" class="form-control form-control-lg" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mot de passe</label>
                    <input type="password" name="password" class="form-control form-control-lg" required>
                </div>
                <button type="submit" class="btn btn-sp-primary btn-lg w-100 fw-semibold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Se connecter
                </button>
            </form>
            <p class="text-muted mt-4 mb-0" style="font-size:.78rem;">
                Compte administrateur par défaut : <code>admin</code> / <code>Admin@2026</code><br>
                Merci de changer ce mot de passe après la première connexion.
            </p>
        </div>
    </div>
</div>
</body>
</html>
