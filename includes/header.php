<?php
/**
 * Header commun. Variables attendues avant inclusion :
 *   $pageTitle  (string) - titre de la page
 *   $activeMenu (string) - clé du menu actif pour la sidebar
 * require_login() ou require_role() doit avoir été appelé avant.
 */
if (!function_exists('require_root_depth')) {
    require_once __DIR__ . '/auth.php';
}
$ROOT = require_root_depth();
$pageTitle = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$user = current_user();
$flashes = flash_get();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(get_param('nom_entreprise', APP_NAME)) ?></title>
    <link rel="stylesheet" href="<?= $ROOT ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $ROOT ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= $ROOT ?>assets/css/inter.css">
    <link rel="stylesheet" href="<?= $ROOT ?>assets/css/app.css">
    <script src="<?= $ROOT ?>assets/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="sp-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="sp-main">
        <header class="sp-topbar no-print">
            <button class="toggle-sidebar" type="button" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="page-title flex-grow-1"><?= e($pageTitle) ?></h1>

            <div class="dropdown">
                <div class="sp-user-menu" data-bs-toggle="dropdown" role="button">
                    <div class="sp-user-avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1))) ?></div>
                    <div class="d-none d-md-block">
                        <div class="fw-semibold" style="font-size:.85rem;"><?= e($user['full_name'] ?? '') ?></div>
                        <span class="sp-role-badge <?= e($user['role'] ?? '') ?>"><?= e(ucfirst($user['role'] ?? '')) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down small text-muted"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $ROOT ?>utilisateurs/profil.php"><i class="fa-solid fa-user me-2"></i>Mon profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $ROOT ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Déconnexion</a></li>
                </ul>
            </div>
        </header>

        <div class="sp-content">
            <?php if (!empty($flashes)): ?>
                <?php foreach ($flashes as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show alert-auto-dismiss no-print" role="alert">
                        <?= e($f['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
