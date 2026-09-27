<?php
/**
 * Header commun. Variables attendues avant inclusion :
 *   $pageTitle    (string) - titre de la page
 *   $activeMenu   (string) - clé du menu actif pour la sidebar
 * Variables optionnelles :
 *   $pageSubtitle (string) - sous-titre discret sous le titre
 *   $bodyClass    (string) - classes CSS ajoutées sur <body>
 * require_login() ou require_role() doit avoir été appelé avant.
 */
require_once __DIR__ . '/auth.php';
$ROOT = require_root_depth();
$pageTitle = $pageTitle ?? APP_NAME;
$pageSubtitle = $pageSubtitle ?? '';
$activeMenu = $activeMenu ?? '';
$bodyClass = $bodyClass ?? '';
$user = current_user();
$flashes = flash_get();
$entrepriseNom = get_param('nom_entreprise', APP_NAME);
$menuSections = app_menu_for_role($user['role'] ?? '');
$quickActions = app_quick_actions($user['role'] ?? '');

// Fil d'Ariane : section du menu contenant la page active
$breadcrumbSection = null;
$breadcrumbItem = null;
foreach ($menuSections as $section) {
    foreach ($section['items'] as $item) {
        if ($item['key'] === $activeMenu) {
            $breadcrumbSection = $section['section'];
            $breadcrumbItem = $item;
        }
    }
}

// Configuration transmise au JavaScript (palette de commandes, notifications...)
$navForJs = [];
foreach ($menuSections as $section) {
    foreach ($section['items'] as $item) {
        $navForJs[] = ['label' => $item['label'], 'url' => $ROOT . $item['url'], 'icon' => $item['icon'], 'group' => $section['section']];
    }
}
$navForJs[] = ['label' => 'Mon profil', 'url' => $ROOT . 'utilisateurs/profil.php', 'icon' => 'fa-id-badge', 'group' => 'Compte'];
$spConfig = [
    'root'    => $ROOT,
    'csrf'    => csrf_token(),
    'devise'  => get_param('devise', 'FCFA'),
    'role'    => $user['role'] ?? '',
    'user'    => $user['full_name'] ?? '',
    'version' => APP_VERSION,
    'nav'     => $navForJs,
    'actions' => array_map(fn($a) => ['label' => $a['label'], 'url' => $ROOT . $a['url'], 'icon' => $a['icon']], $quickActions),
];
?>
<!DOCTYPE html>
<html lang="fr" class="sp-preload" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#6E1423">
    <title><?= e($pageTitle) ?> - <?= e($entrepriseNom) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset_url('assets/img/favicon.svg') ?>">
    <link rel="manifest" href="<?= $ROOT ?>manifest.webmanifest">
    <script>
    /* Thème et menu appliqués avant l'affichage (évite tout clignotement) */
    (function () {
        try {
            var r = document.documentElement, t = localStorage.getItem('sp-theme') || 'auto';
            var dark = t === 'dark' || (t === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
            r.setAttribute('data-theme', dark ? 'dark' : 'light');
            r.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            if (localStorage.getItem('sp-sidebar-collapsed') === '1') r.classList.add('sp-sidebar-collapsed');
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/inter.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
    <script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script>window.SP_CONFIG = <?= js_json($spConfig) ?>;</script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="sp-skip-link" href="#spContent">Aller au contenu</a>
<div class="sp-progress" id="spProgress" aria-hidden="true"></div>
<div class="sp-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="sp-main">
        <header class="sp-topbar no-print" id="spTopbar">
            <button class="sp-icon-btn toggle-sidebar" type="button" aria-label="Ouvrir le menu" aria-controls="spSidebar">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
            <div class="sp-topbar-title">
                <?php if ($breadcrumbSection && $breadcrumbSection !== 'Général'): ?>
                    <nav class="sp-breadcrumb" aria-label="Fil d'Ariane">
                        <span><?= e($breadcrumbSection) ?></span>
                        <?php if ($breadcrumbItem && $breadcrumbItem['label'] !== $pageTitle): ?>
                            <i class="fa-solid fa-chevron-right"></i>
                            <a href="<?= $ROOT . e($breadcrumbItem['url']) ?>"><?= e($breadcrumbItem['label']) ?></a>
                        <?php endif; ?>
                    </nav>
                <?php elseif ($pageSubtitle !== ''): ?>
                    <div class="sp-breadcrumb"><?= e($pageSubtitle) ?></div>
                <?php endif; ?>
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
            </div>

            <div class="sp-topbar-actions">
                <button type="button" class="sp-search-trigger" data-sp-palette title="Rechercher (Ctrl+K)">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Rechercher…</span>
                    <kbd>Ctrl K</kbd>
                </button>

                <?php if (!empty($quickActions)): ?>
                <div class="dropdown">
                    <button class="sp-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Créer" aria-label="Créer">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Création rapide</h6></li>
                        <?php foreach ($quickActions as $qa): ?>
                            <li><a class="dropdown-item" href="<?= $ROOT . e($qa['url']) ?>"><i class="fa-solid <?= e($qa['icon']) ?>"></i><?= e($qa['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <button type="button" class="sp-icon-btn sp-theme-toggle" data-sp-theme-toggle title="Basculer le thème clair / sombre" aria-label="Basculer le thème">
                    <i class="fa-solid fa-moon"></i><i class="fa-solid fa-sun"></i>
                </button>

                <div class="dropdown">
                    <button class="sp-icon-btn" type="button" id="spNotifBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Notifications" aria-label="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <span class="sp-dot-badge" id="spNotifCount"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end sp-notif-menu">
                        <div class="sp-notif-head">
                            <h6>Notifications</h6>
                            <button type="button" class="btn btn-sm btn-ghost" id="spNotifRefresh" title="Actualiser"><i class="fa-solid fa-arrows-rotate"></i></button>
                        </div>
                        <div class="sp-notif-list" id="spNotifList">
                            <div class="p-3"><span class="sp-skeleton mb-2" style="height:38px"></span><span class="sp-skeleton mb-2" style="height:38px"></span><span class="sp-skeleton" style="height:38px"></span></div>
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <div class="sp-user-menu" data-bs-toggle="dropdown" role="button" aria-expanded="false" tabindex="0">
                        <?= avatar_html($user['full_name'] ?? '?', 'sm') ?>
                        <div class="meta d-none d-md-block" style="line-height:1.15;">
                            <div class="fw-semibold" style="font-size:.84rem;"><?= e($user['full_name'] ?? '') ?></div>
                            <span class="sp-role-badge <?= e($user['role'] ?? '') ?>"><?= e(role_label($user['role'] ?? '')) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down chev"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px;">
                        <li class="px-3 py-2 d-md-none">
                            <div class="fw-semibold"><?= e($user['full_name'] ?? '') ?></div>
                            <span class="sp-role-badge <?= e($user['role'] ?? '') ?>"><?= e(role_label($user['role'] ?? '')) ?></span>
                        </li>
                        <li><a class="dropdown-item" href="<?= $ROOT ?>utilisateurs/profil.php"><i class="fa-solid fa-id-badge"></i>Mon profil</a></li>
                        <li><button class="dropdown-item" type="button" data-sp-theme-toggle><i class="fa-solid fa-circle-half-stroke"></i>Thème clair / sombre</button></li>
                        <li><button class="dropdown-item" type="button" data-sp-shortcuts><i class="fa-solid fa-keyboard"></i>Raccourcis clavier <kbd class="kbd-hint">?</kbd></button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $ROOT ?>logout.php"><i class="fa-solid fa-right-from-bracket"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="sp-content" id="spContent" tabindex="-1">
            <?php if (!empty($flashes)): ?>
                <script type="application/json" id="spFlashData"><?= js_json($flashes) ?></script>
                <noscript>
                    <?php foreach ($flashes as $f): ?>
                        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
                    <?php endforeach; ?>
                </noscript>
            <?php endif; ?>
