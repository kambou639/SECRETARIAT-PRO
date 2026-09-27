<?php
/**
 * Sidebar de navigation. Variables attendues : $ROOT (préfixe relatif), $activeMenu
 */
$user = current_user();
$role = $user['role'] ?? '';

function nav_active(string $key, string $active): string
{
    return $key === $active ? ' active' : '';
}
?>
<aside class="sp-sidebar" id="spSidebar">
    <div class="brand">
        <div class="logo-badge"><i class="fa-solid fa-briefcase"></i></div>
        <div class="brand-text">
            <div class="brand-name"><?= e(get_param('nom_entreprise', APP_NAME)) ?></div>
            <div class="brand-sub">Secrétariat &amp; Ventes</div>
        </div>
        <button type="button" class="sidebar-collapse-toggle d-none d-lg-flex" id="sidebarCollapseToggle" title="Réduire / agrandir le menu">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>
    <nav class="sp-nav">
        <div class="nav-section-title">Général</div>
        <a href="<?= $ROOT ?>dashboard.php" class="nav-link<?= nav_active('dashboard', $activeMenu) ?>" title="Tableau de bord">
            <i class="fa-solid fa-gauge-high"></i> <span class="nav-label">Tableau de bord</span>
        </a>

        <?php if (has_role('admin', 'vendeur')): ?>
        <div class="nav-section-title">Ventes</div>
        <a href="<?= $ROOT ?>ventes/caisse.php" class="nav-link<?= nav_active('caisse', $activeMenu) ?>" title="Caisse">
            <i class="fa-solid fa-cash-register"></i> <span class="nav-label">Caisse</span>
        </a>
        <a href="<?= $ROOT ?>ventes/historique.php" class="nav-link<?= nav_active('ventes', $activeMenu) ?>" title="Historique des ventes">
            <i class="fa-solid fa-receipt"></i> <span class="nav-label">Historique des ventes</span>
        </a>
        <a href="<?= $ROOT ?>ventes/credits.php" class="nav-link<?= nav_active('credits', $activeMenu) ?>" title="Ventes à crédit">
            <i class="fa-solid fa-hand-holding-dollar"></i> <span class="nav-label">Ventes à crédit</span>
        </a>
        <a href="<?= $ROOT ?>ventes/rapport_caisse.php" class="nav-link<?= nav_active('rapport_caisse', $activeMenu) ?>" title="Rapport de caisse">
            <i class="fa-solid fa-file-invoice-dollar"></i> <span class="nav-label">Rapport de caisse</span>
        </a>
        <?php endif; ?>

        <div class="nav-section-title">Articles</div>
        <a href="<?= $ROOT ?>articles/liste.php" class="nav-link<?= nav_active('articles', $activeMenu) ?>" title="Catalogue articles">
            <i class="fa-solid fa-boxes-stacked"></i> <span class="nav-label">Catalogue articles</span>
        </a>
        <a href="<?= $ROOT ?>articles/categories.php" class="nav-link<?= nav_active('categories', $activeMenu) ?>" title="Catégories">
            <i class="fa-solid fa-tags"></i> <span class="nav-label">Catégories</span>
        </a>
        <a href="<?= $ROOT ?>articles/etiquettes.php" class="nav-link<?= nav_active('etiquettes', $activeMenu) ?>" title="Étiquettes QR / Codes-barres">
            <i class="fa-solid fa-qrcode"></i> <span class="nav-label">Étiquettes QR / Codes-barres</span>
        </a>

        <div class="nav-section-title">Clients</div>
        <a href="<?= $ROOT ?>clients/liste.php" class="nav-link<?= nav_active('clients', $activeMenu) ?>" title="Clients">
            <i class="fa-solid fa-address-book"></i> <span class="nav-label">Clients</span>
        </a>

        <?php if (has_role('admin')): ?>
        <div class="nav-section-title">Administration</div>
        <a href="<?= $ROOT ?>rapports/statistiques.php" class="nav-link<?= nav_active('rapports', $activeMenu) ?>" title="Statistiques">
            <i class="fa-solid fa-chart-line"></i> <span class="nav-label">Statistiques</span>
        </a>
        <a href="<?= $ROOT ?>import_export/index.php" class="nav-link<?= nav_active('import_export', $activeMenu) ?>" title="Import / Export">
            <i class="fa-solid fa-file-excel"></i> <span class="nav-label">Import / Export</span>
        </a>
        <a href="<?= $ROOT ?>utilisateurs/liste.php" class="nav-link<?= nav_active('utilisateurs', $activeMenu) ?>" title="Utilisateurs">
            <i class="fa-solid fa-users-gear"></i> <span class="nav-label">Utilisateurs</span>
        </a>
        <a href="<?= $ROOT ?>parametres/entreprise.php" class="nav-link<?= nav_active('parametres', $activeMenu) ?>" title="Paramètres">
            <i class="fa-solid fa-gear"></i> <span class="nav-label">Paramètres</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
