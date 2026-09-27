<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    log_activity('deconnexion', 'Déconnexion');
}

$_SESSION = [];
session_unset();
session_destroy();

// Nettoie le cookie de session côté client
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

redirect('login.php?bye=1');
