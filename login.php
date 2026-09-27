<?php
require_once __DIR__ . '/includes/auth.php';

// Déjà connecté ? -> tableau de bord
if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Session expirée, merci de réessayer.';
    } else {
        $username = mb_substr(clean_input($_POST['username'] ?? ''), 0, 50);
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
                    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
                    }

                    $next = login_next_url($_SESSION['login_next'] ?? null);
                    unset($_SESSION['login_next'], $_SESSION['session_expiree']);
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $u['id'];
                    $_SESSION['username']  = $u['username'];
                    $_SESSION['full_name'] = $u['full_name'];
                    $_SESSION['role']      = $u['role'];

                    log_activity('connexion', 'Connexion réussie');
                    if (!$u['derniere_connexion']) {
                        flash_set('info', 'Bienvenue ' . $u['full_name'] . ' ! Pensez à personnaliser votre mot de passe depuis « Mon profil ».');
                    }
                    redirect($next ?? 'dashboard.php');
                }
            } catch (Exception $e) {
                error_log('login: ' . $e->getMessage());
                $errors[] = 'Erreur technique. Vérifiez la configuration de la base de données (config/config.php).';
            }
        }
    }
}

// Indication du compte par défaut : uniquement tant que « admin » ne s'est jamais connecté
$afficherCompteDefaut = false;
$baseInaccessible = false;
try {
    $afficherCompteDefaut = (bool)Database::getConnection()
        ->query("SELECT COUNT(*) FROM users WHERE username = 'admin' AND derniere_connexion IS NULL")->fetchColumn();
} catch (Exception $e) {
    $baseInaccessible = true;
}

$sessionExpiree = !empty($_SESSION['session_expiree']) && $_SERVER['REQUEST_METHOD'] !== 'POST';
unset($_SESSION['session_expiree']);
$deconnecte = isset($_GET['bye']) && $_SERVER['REQUEST_METHOD'] !== 'POST';
$entrepriseNom = get_param('nom_entreprise', APP_NAME);
$slogan = get_param('slogan', 'Secrétariat & vente');
$logo = $baseInaccessible ? null : logo_url();
$heure = (int)date('G');
$salut = $heure >= 5 && $heure < 12 ? ['Bonjour', 'fa-sun'] : ($heure >= 12 && $heure < 18 ? ['Bon après-midi', 'fa-cloud-sun'] : ['Bonsoir', 'fa-moon']);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#6E1423">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion - <?= e($entrepriseNom) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset_url('assets/img/favicon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/img/apple-touch-icon.png') ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <script>
    (function () {
        try {
            var r = document.documentElement, t = localStorage.getItem('sp-theme') || 'auto';
            var dark = t === 'dark' || (t === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
            r.setAttribute('data-theme', dark ? 'dark' : 'light');
            r.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/inter.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
    <script>window.SP_CONFIG = <?= js_json(['root' => '', 'csrf' => csrf_token(), 'devise' => '', 'role' => '', 'user' => '', 'version' => APP_VERSION, 'nav' => [], 'actions' => []]) ?>;</script>
</head>
<body class="sp-login-body">
<div class="sp-login">
    <aside class="sp-login-brand" aria-hidden="false">
        <span class="sp-orb o1"></span><span class="sp-orb o2"></span><span class="sp-orb o3"></span>
        <div class="sp-login-logo">
            <span class="logo-badge-lg"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><i class="fa-solid fa-briefcase"></i><?php endif; ?></span>
            <div>
                <div class="name"><?= e($entrepriseNom) ?></div>
                <div class="sub"><?= e($slogan) ?></div>
            </div>
        </div>
        <div class="sp-login-pitch">
            <h1>Votre secrétariat, <em>simplement</em> organisé.</h1>
            <p>Caisse, courrier, rendez-vous, clients et stock réunis dans un seul espace de travail, pensé pour aller vite au quotidien.</p>
            <ul class="sp-login-features">
                <li><i class="fa-solid fa-cash-register"></i>Caisse rapide &amp; tickets</li>
                <li><i class="fa-solid fa-envelope-open-text"></i>Suivi du courrier</li>
                <li><i class="fa-solid fa-calendar-check"></i>Agenda &amp; rappels</li>
                <li><i class="fa-solid fa-qrcode"></i>Étiquettes QR &amp; codes-barres</li>
                <li><i class="fa-solid fa-chart-line"></i>Statistiques &amp; rapports</li>
                <li><i class="fa-solid fa-file-excel"></i>Import / export Excel</li>
            </ul>
        </div>
        <div class="sp-login-foot">© <?= date('Y') ?> <?= e($entrepriseNom) ?> · <?= $entrepriseNom !== APP_NAME ? e(APP_NAME) . ' ' : '' ?>v<?= e(APP_VERSION) ?></div>
    </aside>

    <main class="sp-login-panel">
        <div class="sp-login-top">
            <button type="button" class="sp-icon-btn sp-theme-toggle" data-sp-theme-toggle title="Basculer le thème clair / sombre" aria-label="Basculer le thème clair / sombre">
                <i class="fa-solid fa-moon"></i><i class="fa-solid fa-sun"></i>
            </button>
        </div>
        <div class="sp-login-card">
            <div class="sp-login-greet"><i class="fa-solid <?= $salut[1] ?>"></i><?= e($salut[0]) ?></div>
            <h2>Connexion</h2>
            <p class="lead-sub">Accédez à votre espace de travail.</p>

            <?php if ($baseInaccessible): ?>
                <div class="alert alert-warning"><i class="fa-solid fa-database"></i><div><strong>Base de données inaccessible.</strong><br>Vérifiez les identifiants dans <code>config/config.php</code> et importez <code>database/schema.sql</code>.</div></div>
            <?php endif; ?>
            <?php if ($sessionExpiree): ?>
                <div class="alert alert-info"><i class="fa-solid fa-hourglass-end"></i><div>Votre session a expiré après une période d'inactivité. Reconnectez-vous pour reprendre où vous en étiez.</div></div>
            <?php elseif ($deconnecte && empty($errors)): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><div>Vous êtes déconnecté. À bientôt !</div></div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?= e($err) ?></div></div>
            <?php endforeach; ?>

            <form method="post" action="login.php" novalidate id="loginForm">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="lUser">Identifiant</label>
                    <div class="sp-input-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="lUser" name="username" class="form-control" required autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" maxlength="50" value="<?= e($username) ?>" <?= $username === '' ? 'autofocus' : '' ?>>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lPass">Mot de passe</label>
                    <div class="sp-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="lPass" name="password" class="form-control" required autocomplete="current-password" <?= $username !== '' ? 'autofocus' : '' ?>>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" id="lRemember" checked>
                        <label class="form-check-label small" for="lRemember">Mémoriser mon identifiant</label>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 small text-nowrap" id="lForgot">Mot de passe oublié ?</button>
                </div>
                <button type="submit" class="btn btn-sp-primary btn-login w-100"><span>Se connecter</span><i class="fa-solid fa-arrow-right ms-2"></i></button>
            </form>

            <?php if ($afficherCompteDefaut): ?>
                <div class="sp-login-hint">
                    <i class="fa-solid fa-key me-1"></i>Première connexion : identifiant <code>admin</code>, mot de passe <code>Admin@2026</code>.
                    Changez ce mot de passe dès votre arrivée dans « Mon profil ».
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('loginForm');
    var user = document.getElementById('lUser'), pass = document.getElementById('lPass'), remember = document.getElementById('lRemember');
    var KEY = 'sp-login-user';
    var saved = SP.store.raw(KEY);
    remember.checked = SP.store.raw('sp-login-remember') !== '0';
    if (!user.value && saved) {
        user.value = saved;
        pass.focus();
    }
    form.addEventListener('submit', function (e) {
        var missing = !user.value.trim() ? user : (!pass.value ? pass : null);
        if (missing) {
            e.preventDefault();
            missing.classList.add('is-invalid');
            var box = missing.closest('.sp-input-icon');
            box.classList.remove('sp-anim-shake'); void box.offsetWidth; box.classList.add('sp-anim-shake');
            missing.focus();
            return;
        }
        SP.store.setRaw('sp-login-remember', remember.checked ? '1' : '0');
        if (remember.checked) SP.store.setRaw(KEY, user.value.trim()); else SP.store.remove(KEY);
    });
    document.getElementById('lForgot').addEventListener('click', function () {
        SP.toast({ type: 'info', title: 'Mot de passe oublié', message: 'Demandez à un administrateur de le réinitialiser depuis la page « Utilisateurs ».', duration: 7000 });
    });
    [user, pass].forEach(function (f) { f.addEventListener('input', function () { f.classList.remove('is-invalid'); }); });
});
</script>
</body>
</html>
