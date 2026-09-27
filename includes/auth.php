<?php
/**
 * Authentification & contrôle d'accès
 * À inclure en tout début de chaque page protégée.
 */

require_once __DIR__ . '/functions.php';
secure_session_start();

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
    ];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function has_role(...$roles): bool
{
    $roles = array_map('strval', is_array($roles[0] ?? null) ? $roles[0] : $roles);
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles, true);
}

/**
 * Point d'entrée : exige une connexion active.
 * Chemin racine calculé automatiquement pour rediriger vers login.php.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $depth = require_root_depth();
        redirect($depth . 'login.php');
    }
}

/**
 * Exige un des rôles fournis, sinon redirige avec message d'erreur.
 */
function require_role(...$roles): void
{
    require_login();
    $roles = is_array($roles[0] ?? null) ? $roles[0] : $roles;
    if (!has_role($roles)) {
        $depth = require_root_depth();
        flash_set('danger', 'Accès refusé : vous n\'avez pas les droits nécessaires pour cette action.');
        redirect($depth . 'dashboard.php');
    }
}

/**
 * Variante pour les points d'accès AJAX : répond en JSON (401/403)
 * au lieu de rediriger vers une page HTML.
 */
function require_api_login(array $roles = []): void
{
    if (!is_logged_in()) {
        json_response(['success' => false, 'message' => 'Session expirée, merci de vous reconnecter.', 'login' => true], 401);
    }
    if (!empty($roles) && !has_role($roles)) {
        json_response(['success' => false, 'message' => 'Accès refusé.'], 403);
    }
}

/** Exige un POST avec jeton CSRF valide (réponse JSON sinon). */
function require_api_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
        json_response(['success' => false, 'message' => 'Jeton de sécurité invalide. Merci de recharger la page.'], 403);
    }
}
