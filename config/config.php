<?php
/**
 * Secrétariat Pro - Configuration principale
 * Renseignez vos identifiants MySQL ci-dessous.
 */

// ---------------- Base de données ----------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'secretariat_pro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------- Application ----------------
define('APP_NAME', 'Secrétariat Pro');
define('APP_URL', ''); // ex: http://localhost/secretariat_pro  (laisser vide = auto-détection)
define('APP_ROOT', dirname(__DIR__));

// Dossier des uploads (doit être accessible en écriture par le serveur web)
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('UPLOAD_ARTICLES', UPLOAD_DIR . '/articles');
define('UPLOAD_COURRIERS', UPLOAD_DIR . '/courriers');
define('UPLOAD_LOGO', UPLOAD_DIR . '/logo');

// Taille max upload (octets) - 5 Mo
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// ---------------- Sécurité ----------------
define('SESSION_LIFETIME', 60 * 60 * 4); // 4h d'inactivité max
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---------------- Fuseau horaire ----------------
date_default_timezone_set('Africa/Ouagadougou');

// ---------------- Rapports d'erreurs ----------------
// En production, mettre à 0 et activer la journalisation fichier
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/php_errors.log');
