<?php
/**
 * Fonctions utilitaires globales
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ---------------------------------------------------------------
// Session sécurisée
// ---------------------------------------------------------------
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SPRO_SESSID');
    session_start();

    // Expiration par inactivité
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();

    // Régénération périodique de l'ID de session (anti fixation)
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - $_SESSION['created_at'] > 900) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('Requête invalide (jeton de sécurité manquant ou expiré). Merci de recharger la page et réessayer.');
    }
}

// ---------------------------------------------------------------
// Assainissement / affichage
// ---------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_input(?string $value): string
{
    return trim($value ?? '');
}

// ---------------------------------------------------------------
// Messages flash
// ---------------------------------------------------------------
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------
// Redirection (chemin relatif à la racine de l'application)
// ---------------------------------------------------------------
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// ---------------------------------------------------------------
// Formatage
// ---------------------------------------------------------------
function fmt_money(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' ' . get_param('devise', 'FCFA');
}

function fmt_date(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function fmt_datetime(?string $date): string
{
    return fmt_date($date, 'd/m/Y H:i');
}

/** Formatage de date long en français (PHP date() n'étant pas localisé). */
function fmt_date_fr_long(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) return '-';
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return $jours[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// ---------------------------------------------------------------
// Tarifs dégressifs (paliers de prix par quantité)
// ---------------------------------------------------------------

/**
 * Retourne le prix unitaire applicable pour un article et une quantité donnée,
 * en tenant compte des paliers de prix dégressifs éventuellement définis.
 * Si aucun palier ne correspond à la quantité, le prix de base fourni est utilisé.
 */
function get_prix_effectif(PDO $pdo, int $articleId, int $qte, float $prixBase): float
{
    $stmt = $pdo->prepare(
        'SELECT prix_unitaire FROM paliers_prix
         WHERE article_id = ? AND quantite_min <= ? AND (quantite_max IS NULL OR quantite_max >= ?)
         ORDER BY quantite_min DESC LIMIT 1'
    );
    $stmt->execute([$articleId, $qte, $qte]);
    $row = $stmt->fetch();
    return $row ? (float)$row['prix_unitaire'] : $prixBase;
}

/** Retourne tous les paliers de prix d'un article, triés par quantité croissante. */
function get_paliers(PDO $pdo, int $articleId): array
{
    $stmt = $pdo->prepare('SELECT * FROM paliers_prix WHERE article_id = ? ORDER BY quantite_min ASC');
    $stmt->execute([$articleId]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------
// Paramètres application (table parametres, cache statique)
// ---------------------------------------------------------------
function get_param(string $cle, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT cle, valeur FROM parametres');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['cle']] = $row['valeur'];
            }
        } catch (Exception $e) {
            error_log('get_param: ' . $e->getMessage());
        }
    }
    return $cache[$cle] ?? $default;
}

// ---------------------------------------------------------------
// Génération de références
// ---------------------------------------------------------------
function generate_reference(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

// ---------------------------------------------------------------
// Journal d'activité
// ---------------------------------------------------------------
function log_activity(string $action, string $details = ''): void
{
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log('log_activity: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// Upload sécurisé
// ---------------------------------------------------------------
function handle_upload(array $file, string $destDir, array $allowedExt = ['jpg', 'jpeg', 'png', 'pdf', 'webp']): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors du téléversement du fichier.');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Fichier trop volumineux (max 5 Mo).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Type de fichier non autorisé.');
    }

    // Vérification du type MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMime = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];
    if (isset($allowedMime[$ext]) && $mime !== $allowedMime[$ext]) {
        throw new RuntimeException('Le contenu du fichier ne correspond pas à son extension.');
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Impossible d\'enregistrer le fichier.');
    }
    return $filename;
}

// ---------------------------------------------------------------
// Pagination simple
// ---------------------------------------------------------------
function paginate(int $total, int $page, int $perPage = 20): array
{
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return compact('total', 'page', 'perPage', 'totalPages', 'offset');
}
