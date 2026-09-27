<?php
/**
 * Sidebar de navigation. Variables attendues : $ROOT (préfixe relatif), $activeMenu, $menuSections
 * Les pastilles (stock, crédits, courrier, RDV) sont remplies en JavaScript
 * à partir de api/notifications.php, sans ralentir l'affichage de la page.
 */
$user = current_user();
$menuSections = $menuSections ?? app_menu_for_role($user['role'] ?? '');
$logoUrl = logo_url();

function nav_active(string $key, string $active): string
{
    return $key === $active ? ' active' : '';
}
?>
<aside class="sp-sidebar" id="spSidebar" aria-label="Navigation principale">
    <div class="brand">
        <a href="<?= $ROOT ?>dashboard.php" class="brand-link" title="Tableau de bord">
            <div class="logo-badge">
                <?php if ($logoUrl): ?>
                    <img src="<?= e($logoUrl) ?>" alt="">
                <?php else: ?>
                    <i class="fa-solid fa-briefcase"></i>
                <?php endif; ?>
            </div>
            <div class="brand-text">
                <div class="brand-name"><?= e(get_param('nom_entreprise', APP_NAME)) ?></div>
                <div class="brand-sub">Secrétariat &amp; Ventes</div>
            </div>
        </a>
        <button type="button" class="sidebar-collapse-toggle d-none d-lg-flex" id="sidebarCollapseToggle" title="Réduire / agrandir le menu" aria-label="Réduire ou agrandir le menu">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>
    <nav class="sp-nav">
        <?php foreach ($menuSections as $section): ?>
            <div class="nav-section-title"><?= e($section['section']) ?></div>
            <?php foreach ($section['items'] as $item): ?>
                <a href="<?= $ROOT . e($item['url']) ?>" class="nav-link<?= nav_active($item['key'], $activeMenu) ?>" data-label="<?= e($item['label']) ?>"<?= $item['key'] === $activeMenu ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($item['icon']) ?>"></i> <span class="nav-label"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?><span class="nav-badge" data-nav-badge="<?= e($item['badge']) ?>"></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sp-sidebar-footer">
        <div class="sp-sidebar-user">
            <a href="<?= $ROOT ?>utilisateurs/profil.php" class="d-flex align-items-center gap-2 text-reset flex-grow-1" style="min-width:0;" title="Mon profil">
                <?= avatar_html($user['full_name'] ?? '?', 'sm') ?>
                <div class="meta">
                    <div class="name"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="role"><?= e(role_label($user['role'] ?? '')) ?></div>
                </div>
            </a>
            <a href="<?= $ROOT ?>logout.php" class="logout" title="Déconnexion" aria-label="Déconnexion"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </div>
</aside>
<div class="sp-sidebar-backdrop" id="spSidebarBackdrop"></div>
