<?php
/**
 * Fonctions utilitaires globales
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Version de l'application (sert aussi au rafraîchissement du cache des CSS/JS).
// Définie ici plutôt que dans config.php pour qu'une mise à jour fonctionne
// même si l'on conserve son ancien fichier de configuration.
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

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
// Chemins & ressources statiques
// ---------------------------------------------------------------

/**
 * Calcule le préfixe relatif ("../" x N) pour retrouver la racine
 * de l'application depuis un script situé dans un sous-dossier.
 */
function require_root_depth(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $root = str_replace('\\', '/', (string)realpath(APP_ROOT));
    $rel = trim(str_replace($root, '', dirname($scriptPath)), '/');
    $cache = $rel === '' ? '' : str_repeat('../', count(explode('/', $rel)));
    return $cache;
}

/**
 * URL relative d'une ressource (CSS, JS, image...) avec un paramètre de version
 * basé sur la date de modification du fichier : le navigateur recharge
 * automatiquement les fichiers mis à jour, sans vider son cache à la main.
 */
function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = APP_ROOT . '/' . $path;
    $version = is_file($file) ? (string)filemtime($file) : APP_VERSION;
    return require_root_depth() . $path . '?v=' . $version;
}

/** URL relative d'un fichier téléversé (ou null s'il n'existe plus). */
function upload_url(string $subdir, ?string $file): ?string
{
    if (empty($file)) {
        return null;
    }
    $file = basename($file);
    if (!is_file(UPLOAD_DIR . '/' . $subdir . '/' . $file)) {
        return null;
    }
    return require_root_depth() . 'uploads/' . $subdir . '/' . rawurlencode($file);
}

function logo_url(): ?string
{
    return upload_url('logo', get_param('logo'));
}

function article_image_url(?string $image): ?string
{
    return upload_url('articles', $image);
}

/** Supprime un fichier téléversé en toute sécurité (nom de fichier uniquement). */
function delete_upload(string $dir, ?string $file): void
{
    if (empty($file)) {
        return;
    }
    $path = rtrim($dir, '/') . '/' . basename($file);
    if (is_file($path)) {
        @unlink($path);
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

/** Corps JSON de la requête (décodé une seule fois). */
function read_json_body(): array
{
    static $body = null;
    if ($body === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $body = is_array($decoded) ? $decoded : [];
    }
    return $body;
}

/**
 * Vérifie le jeton CSRF envoyé dans le formulaire, dans l'en-tête
 * X-CSRF-Token (requêtes AJAX) ou dans un corps JSON.
 */
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $token = (string)(read_json_body()['csrf_token'] ?? '');
    }
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('Requête invalide (jeton de sécurité manquant ou expiré). Merci de recharger la page et réessayer.');
    }
}

/**
 * Pour les actions qui modifient des données (suppression, annulation...) :
 * exige une requête POST avec un jeton CSRF valide, sinon retour à $redirectTo.
 * Empêche qu'un simple lien piégé déclenche une suppression à l'insu de l'utilisateur.
 */
function require_post_csrf(string $redirectTo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
        flash_set('danger', 'Action refusée : requête invalide ou expirée. Merci de réessayer depuis la page.');
        redirect($redirectTo);
    }
}

// ---------------------------------------------------------------
// Réponses JSON (points d'accès AJAX)
// ---------------------------------------------------------------
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Encode une valeur pour l'insérer sans risque dans une balise <script>. */
function js_json($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
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

/** Valide une date AAAA-MM-JJ, sinon renvoie la valeur par défaut. */
function valid_date(?string $date, string $default): string
{
    $date = (string)$date;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        if (checkdate($m, $d, $y)) {
            return $date;
        }
    }
    return $default;
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

/**
 * Revient à la page précédente (avec ses filtres) si elle fait partie des pages
 * autorisées du même dossier ; sinon redirige vers $default. Seuls le nom de
 * fichier et les paramètres sont repris : aucune redirection vers un autre site.
 */
function redirect_back(string $default, array $pages = []): void
{
    $ref = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $file = basename((string)($ref['path'] ?? ''));
    if ($file !== '' && in_array($file, $pages, true)) {
        redirect($file . (!empty($ref['query']) ? '?' . $ref['query'] : ''));
    }
    redirect($default);
}

// ---------------------------------------------------------------
// Formatage
// ---------------------------------------------------------------
function fmt_money(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' ' . get_param('devise', 'FCFA');
}

/** Montant avec la devise en petit (pour les grands chiffres des cartes). */
function fmt_money_html(float $amount): string
{
    return number_format($amount, 0, ',', '&nbsp;') . '<small class="cur">' . e(get_param('devise', 'FCFA')) . '</small>';
}

function fmt_number(float $amount, int $decimals = 0): string
{
    return number_format($amount, $decimals, ',', ' ');
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

function mois_fr(int $mois, bool $court = false): string
{
    $long = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $abr = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return ($court ? $abr : $long)[$mois] ?? '';
}

function jour_fr(int $jourSemaine, bool $court = false): string
{
    $long = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $abr = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
    return ($court ? $abr : $long)[$jourSemaine] ?? '';
}

/** Formatage de date long en français (PHP date() n'étant pas localisé). */
function fmt_date_fr_long(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) return '-';
    return jour_fr((int)date('w', $ts)) . ' ' . (int)date('j', $ts) . ' ' . mois_fr((int)date('n', $ts)) . ' ' . date('Y', $ts);
}

/** Date courte en français : « 27 sept. 2026 ». */
function fmt_date_fr(?string $date, bool $avecAnnee = true): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) return '-';
    return (int)date('j', $ts) . ' ' . mois_fr((int)date('n', $ts), true) . ($avecAnnee ? ' ' . date('Y', $ts) : '');
}

/** Nombre de jours entiers écoulés depuis une date (0 = aujourd'hui). */
function days_since(?string $date): int
{
    $ts = $date ? strtotime(date('Y-m-d', strtotime($date))) : false;
    if (!$ts) return 0;
    return (int)floor((strtotime(date('Y-m-d')) - $ts) / 86400);
}

/** Durée relative lisible : « à l'instant », « il y a 5 min », « hier à 14:05 »... */
function fmt_relative(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) return '-';
    $diff = time() - $ts;
    if ($diff < 0) {
        $diff = -$diff;
        if ($diff < 3600) return 'dans ' . max(1, (int)round($diff / 60)) . ' min';
        if ($diff < 86400) return 'dans ' . (int)round($diff / 3600) . ' h';
        return 'dans ' . (int)ceil($diff / 86400) . ' j';
    }
    if ($diff < 45) return "à l'instant";
    if ($diff < 3600) return 'il y a ' . max(1, (int)round($diff / 60)) . ' min';
    $jours = days_since($datetime);
    if ($jours === 0) return 'il y a ' . (int)floor($diff / 3600) . ' h';
    if ($jours === 1) return 'hier à ' . date('H:i', $ts);
    if ($jours < 7) return 'il y a ' . $jours . ' j';
    return fmt_date_fr($datetime, date('Y', $ts) !== date('Y'));
}

/** Variation en % entre deux valeurs (null si la base est nulle). */
function percent_change(float $current, float $previous): ?float
{
    if (abs($previous) < 0.00001) {
        return null;
    }
    return ($current - $previous) / abs($previous) * 100;
}

/** Pastille de tendance (↑ +12 % / ↓ -5 %). */
function trend_html(float $current, float $previous, string $suffix = ''): string
{
    $pct = percent_change($current, $previous);
    if ($pct === null) {
        return $current > 0 ? '<span class="sp-trend up"><i class="fa-solid fa-arrow-trend-up"></i> nouveau' . e($suffix) . '</span>' : '<span class="sp-trend flat">—</span>';
    }
    $cls = $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : 'flat');
    $icon = $cls === 'up' ? 'fa-arrow-trend-up' : ($cls === 'down' ? 'fa-arrow-trend-down' : 'fa-minus');
    $txt = $pct >= 1000
        ? '×' . number_format($current / $previous, $current / $previous >= 100 ? 0 : 1, ',', ' ')
        : ($pct > 0 ? '+' : '') . number_format($pct, 0, ',', ' ') . ' %';
    return '<span class="sp-trend ' . $cls . '"><i class="fa-solid ' . $icon . '"></i> ' . $txt . e($suffix) . '</span>';
}

/** Mini-graphique SVG (courbe + aire) pour les cartes statistiques. */
function sparkline_svg(array $values, int $width = 140, int $height = 46): string
{
    $values = array_values(array_map('floatval', $values));
    $n = count($values);
    if ($n < 2) {
        return '';
    }
    $max = max($values);
    $min = min($values);
    $range = ($max - $min) ?: 1;
    $pts = [];
    foreach ($values as $i => $v) {
        $x = round($i / ($n - 1) * $width, 1);
        $y = round($height - 4 - (($v - $min) / $range) * ($height - 10), 1);
        $pts[] = $x . ',' . $y;
    }
    $line = 'M' . implode(' L', $pts);
    $area = $line . ' L' . $width . ',' . $height . ' L0,' . $height . ' Z';
    return '<svg class="sp-sparkline" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" aria-hidden="true">'
        . '<path class="area" d="' . $area . '"/><path class="line" d="' . $line . '"/></svg>';
}

// ---------------------------------------------------------------
// Montant en toutes lettres (factures)
// ---------------------------------------------------------------
function _nombre_dizaines(int $n, bool $final): string
{
    static $u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    static $d = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];
    if ($n <= 16) {
        return $u[$n];
    }
    if ($n < 20) {
        return 'dix-' . $u[$n - 10];
    }
    $dizaine = intdiv($n, 10);
    $unite = $n % 10;
    if ($dizaine <= 6) {
        if ($unite === 0) return $d[$dizaine];
        if ($unite === 1) return $d[$dizaine] . ' et un';
        return $d[$dizaine] . '-' . $u[$unite];
    }
    if ($dizaine === 7) {
        return $unite === 1 ? 'soixante et onze' : 'soixante-' . _nombre_dizaines(10 + $unite, $final);
    }
    if ($n === 80) {
        return 'quatre-vingt' . ($final ? 's' : '');
    }
    return 'quatre-vingt-' . _nombre_dizaines($n - 80, $final);
}

function _nombre_centaines(int $n, bool $final): string
{
    static $u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
    $c = intdiv($n, 100);
    $r = $n % 100;
    $out = '';
    if ($c === 1) {
        $out = 'cent';
    } elseif ($c > 1) {
        $out = $u[$c] . ' cent' . ($r === 0 && $final ? 's' : '');
    }
    if ($r > 0) {
        $out .= ($out !== '' ? ' ' : '') . _nombre_dizaines($r, $final);
    }
    return $out;
}

/** Écrit un entier en lettres (orthographe traditionnelle). */
function nombre_en_lettres(int $n): string
{
    if ($n === 0) {
        return 'zéro';
    }
    if ($n < 0) {
        return 'moins ' . nombre_en_lettres(-$n);
    }
    $parts = [];
    $milliards = intdiv($n, 1000000000);
    $n %= 1000000000;
    $millions = intdiv($n, 1000000);
    $n %= 1000000;
    $milliers = intdiv($n, 1000);
    $reste = $n % 1000;
    if ($milliards > 0) {
        $parts[] = _nombre_centaines($milliards, true) . ' milliard' . ($milliards > 1 ? 's' : '');
    }
    if ($millions > 0) {
        $parts[] = _nombre_centaines($millions, true) . ' million' . ($millions > 1 ? 's' : '');
    }
    if ($milliers > 0) {
        $parts[] = ($milliers === 1 ? '' : _nombre_centaines($milliers, false) . ' ') . 'mille';
    }
    if ($reste > 0) {
        $parts[] = _nombre_centaines($reste, true);
    }
    return trim(implode(' ', $parts));
}

/** « Sept mille cinq cents francs CFA » */
function montant_en_lettres(float $montant, ?string $devise = null): string
{
    $devise = $devise ?? get_param('devise', 'FCFA');
    $entier = (int)floor(abs($montant) + 0.00001);
    $centimes = (int)round((abs($montant) - $entier) * 100);
    $d = mb_strtoupper(trim($devise));
    $pluriel = $entier > 1;
    if (str_contains($d, 'CFA') || $d === 'XOF' || $d === 'XAF') {
        $nomDevise = $pluriel ? 'francs CFA' : 'franc CFA';
    } elseif (in_array($d, ['€', 'EUR', 'EURO', 'EUROS'], true)) {
        $nomDevise = $pluriel ? 'euros' : 'euro';
    } elseif (in_array($d, ['$', 'USD', 'DOLLAR', 'DOLLARS'], true)) {
        $nomDevise = $pluriel ? 'dollars' : 'dollar';
    } elseif (in_array($d, ['GNF', 'FG'], true)) {
        $nomDevise = $pluriel ? 'francs guinéens' : 'franc guinéen';
    } else {
        $nomDevise = trim($devise);
    }
    $texte = nombre_en_lettres($entier) . ' ' . $nomDevise;
    if ($centimes > 0) {
        $texte .= ' et ' . nombre_en_lettres($centimes) . ' centime' . ($centimes > 1 ? 's' : '');
    }
    return mb_strtoupper(mb_substr($texte, 0, 1)) . mb_substr($texte, 1);
}

// ---------------------------------------------------------------
// Libellés & statuts (affichage cohérent dans toute l'application)
// ---------------------------------------------------------------
function modes_paiement(): array
{
    return [
        'especes'      => ['Espèces', 'fa-money-bill-wave'],
        'mobile_money' => ['Mobile Money', 'fa-mobile-screen-button'],
        'carte'        => ['Carte bancaire', 'fa-credit-card'],
        'virement'     => ['Virement', 'fa-building-columns'],
        'autre'        => ['Autre', 'fa-ellipsis'],
        'mixte'        => ['Paiement mixte', 'fa-layer-group'],
    ];
}

function mode_paiement_label(?string $mode): string
{
    return modes_paiement()[$mode][0] ?? ucfirst(str_replace('_', ' ', (string)$mode));
}

function mode_paiement_icon(?string $mode): string
{
    return modes_paiement()[$mode][1] ?? 'fa-coins';
}

function role_label(?string $role): string
{
    return ['admin' => 'Administrateur', 'secretaire' => 'Secrétaire', 'vendeur' => 'Vendeur'][$role] ?? ucfirst((string)$role);
}

/** [libellé, ton (success|danger|warning|info|primary|neutral), icône] */
function statut_courrier_meta(?string $statut): array
{
    return [
        'recu'          => ['Reçu', 'info', 'fa-inbox'],
        'en_traitement' => ['En traitement', 'warning', 'fa-hourglass-half'],
        'traite'        => ['Traité', 'success', 'fa-check'],
        'archive'       => ['Archivé', 'neutral', 'fa-box-archive'],
        'envoye'        => ['Envoyé', 'primary', 'fa-paper-plane'],
    ][$statut] ?? [ucfirst((string)$statut), 'neutral', 'fa-circle'];
}

function statut_rdv_meta(?string $statut): array
{
    return [
        'planifie' => ['Planifié', 'info', 'fa-calendar'],
        'confirme' => ['Confirmé', 'success', 'fa-calendar-check'],
        'termine'  => ['Terminé', 'neutral', 'fa-flag-checkered'],
        'annule'   => ['Annulé', 'danger', 'fa-calendar-xmark'],
    ][$statut] ?? [ucfirst((string)$statut), 'neutral', 'fa-circle'];
}

function statut_paiement_meta(?string $statut): array
{
    return [
        'payee'     => ['Payée', 'success', 'fa-circle-check'],
        'partielle' => ['Paiement partiel', 'warning', 'fa-circle-half-stroke'],
        'impayee'   => ['Impayée', 'danger', 'fa-circle-exclamation'],
    ][$statut] ?? [ucfirst((string)$statut), 'neutral', 'fa-circle'];
}

/** Badge de statut « doux » : <span class="sp-badge is-success">…</span> */
function badge_html(array $meta, bool $withIcon = true): string
{
    [$label, $tone, $icon] = $meta + [null, 'neutral', null];
    $cls = $tone === 'neutral' ? '' : ' is-' . $tone;
    return '<span class="sp-badge' . $cls . '">' . ($withIcon && $icon ? '<i class="fa-solid ' . e($icon) . '"></i>' : '') . e($label) . '</span>';
}

function vente_statut_badges(array $v): string
{
    if (($v['statut'] ?? '') === 'annulee') {
        return badge_html(['Annulée', 'danger', 'fa-ban']);
    }
    $sp = $v['statut_paiement'] ?? 'payee';
    return badge_html(statut_paiement_meta($sp));
}

// ---------------------------------------------------------------
// Avatars, états vides, pagination
// ---------------------------------------------------------------
function avatar_initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/[\s\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $first = mb_substr($parts[0], 0, 1);
    $second = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0], 1, 1);
    return mb_strtoupper($first . $second);
}

function avatar_html(?string $name, string $size = '', string $extraClass = ''): string
{
    $idx = abs(crc32(mb_strtolower(trim((string)$name)))) % 8;
    return '<span class="sp-avatar av-' . $idx . ($size ? ' ' . e($size) : '') . ($extraClass ? ' ' . e($extraClass) : '') . '" aria-hidden="true">'
        . e(avatar_initials($name)) . '</span>';
}

function empty_state(string $icon, string $title, string $text = '', string $actionHtml = '', string $extraClass = ''): string
{
    return '<div class="sp-empty-state ' . e($extraClass) . '"><i class="fa-solid ' . e($icon) . '"></i>'
        . '<h6>' . e($title) . '</h6>'
        . ($text !== '' ? '<p>' . e($text) . '</p>' : '')
        . $actionHtml . '</div>';
}

// ---------------------------------------------------------------
// Téléphone / WhatsApp
// ---------------------------------------------------------------

/** Numéro au format international (chiffres uniquement), ex. 22670123456. */
function phone_intl_digits(?string $phone): ?string
{
    $phone = trim((string)$phone);
    if ($phone === '') {
        return null;
    }
    $plus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '' || strlen($digits) < 6) {
        return null;
    }
    if (str_starts_with($digits, '00')) {
        return substr($digits, 2);
    }
    if ($plus) {
        return $digits;
    }
    $indicatif = preg_replace('/\D+/', '', get_param('indicatif_pays', '226'));
    if ($indicatif !== '' && str_starts_with($digits, $indicatif) && strlen($digits) > 9) {
        return $digits;
    }
    return $indicatif . ltrim($digits, '0');
}

function tel_href(?string $phone): ?string
{
    $phone = trim((string)$phone);
    if ($phone === '') {
        return null;
    }
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

function whatsapp_url(?string $phone, string $message = ''): ?string
{
    $digits = phone_intl_digits($phone);
    if (!$digits) {
        return null;
    }
    return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
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
    $value = $cache[$cle] ?? null;
    return ($value === null || $value === '') ? $default : (string)$value;
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
            mb_substr($details, 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log('log_activity: ' . $e->getMessage());
    }
}

/** Icône et ton associés à un type d'action du journal. */
function activity_meta(string $action): array
{
    $map = [
        'connexion' => ['fa-right-to-bracket', 'success'], 'deconnexion' => ['fa-right-from-bracket', 'neutral'],
        'vente' => ['fa-cash-register', 'accent'], 'vente_annulation' => ['fa-rotate-left', 'danger'], 'vente_versement' => ['fa-money-bill-wave', 'success'],
        'article' => ['fa-box', 'primary'], 'stock' => ['fa-boxes-stacked', 'info'], 'palier' => ['fa-layer-group', 'info'],
        'client' => ['fa-address-book', 'primary'], 'courrier' => ['fa-envelope', 'info'], 'rdv' => ['fa-calendar', 'info'],
        'utilisateur' => ['fa-user-gear', 'primary'], 'profil' => ['fa-id-badge', 'primary'], 'parametres' => ['fa-gear', 'neutral'],
        'import' => ['fa-file-import', 'accent'], 'sauvegarde' => ['fa-database', 'accent'], 'categorie' => ['fa-tags', 'primary'],
    ];
    if (isset($map[$action])) {
        return $map[$action];
    }
    foreach ($map as $prefix => $meta) {
        if (str_starts_with($action, $prefix)) {
            return $meta;
        }
    }
    return ['fa-circle-info', 'neutral'];
}

// ---------------------------------------------------------------
// Upload sécurisé
// ---------------------------------------------------------------
function handle_upload(array $file, string $destDir, array $allowedExt = ['jpg', 'jpeg', 'png', 'pdf', 'webp']): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Fichier trop volumineux (max ' . (int)(MAX_UPLOAD_SIZE / 1048576) . ' Mo).');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors du téléversement du fichier.');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Fichier trop volumineux (max ' . (int)(MAX_UPLOAD_SIZE / 1048576) . ' Mo).');
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

/** Barre de pagination (conserve les autres paramètres de l'URL). */
function pagination_html(array $pg, string $label = 'éléments'): string
{
    $from = $pg['total'] > 0 ? $pg['offset'] + 1 : 0;
    $to = min($pg['total'], $pg['offset'] + $pg['perPage']);
    $html = '<div class="sp-pager no-print"><span>' . $from . '–' . $to . ' sur <strong>' . fmt_number($pg['total']) . '</strong> ' . e($label) . '</span>';
    if ($pg['totalPages'] > 1) {
        $query = $_GET;
        $link = function (int $p) use ($query): string {
            $query['page'] = $p;
            return '?' . http_build_query($query);
        };
        $html .= '<nav aria-label="Pagination"><ul class="pagination pagination-sm">';
        $html .= '<li class="page-item' . ($pg['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $pg['page'] - 1))) . '" aria-label="Précédent"><i class="fa-solid fa-chevron-left"></i></a></li>';
        $window = 2;
        $last = 0;
        for ($p = 1; $p <= $pg['totalPages']; $p++) {
            if ($p === 1 || $p === $pg['totalPages'] || abs($p - $pg['page']) <= $window) {
                if ($last && $p - $last > 1) {
                    $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                $html .= '<li class="page-item' . ($p === $pg['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($p)) . '">' . $p . '</a></li>';
                $last = $p;
            }
        }
        $html .= '<li class="page-item' . ($pg['page'] >= $pg['totalPages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(min($pg['totalPages'], $pg['page'] + 1))) . '" aria-label="Suivant"><i class="fa-solid fa-chevron-right"></i></a></li>';
        $html .= '</ul></nav>';
    }
    return $html . '</div>';
}

// ---------------------------------------------------------------
// Périodes prédéfinies (filtres de dates)
// ---------------------------------------------------------------
function date_presets(): array
{
    $today = date('Y-m-d');
    return [
        'jour'        => ['Aujourd\'hui', $today, $today],
        'hier'        => ['Hier', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
        '7j'          => ['7 jours', date('Y-m-d', strtotime('-6 days')), $today],
        '30j'         => ['30 jours', date('Y-m-d', strtotime('-29 days')), $today],
        'mois'        => ['Ce mois', date('Y-m-01'), $today],
        'mois_dernier'=> ['Mois dernier', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
        'annee'       => ['Cette année', date('Y-01-01'), $today],
    ];
}

/** Boutons de raccourcis de période pour un formulaire de filtre GET. */
function date_presets_html(string $debut, string $fin, array $keys = ['jour', '7j', '30j', 'mois', 'mois_dernier', 'annee']): string
{
    $presets = date_presets();
    $html = '<div class="sp-seg sp-seg-sm" data-sp-seg>';
    foreach ($keys as $k) {
        if (!isset($presets[$k])) continue;
        [$label, $d, $f] = $presets[$k];
        $query = array_merge($_GET, ['debut' => $d, 'fin' => $f]);
        unset($query['page']);
        $active = ($d === $debut && $f === $fin) ? ' active' : '';
        $html .= '<a href="?' . e(http_build_query($query)) . '" class="' . trim($active) . '">' . e($label) . '</a>';
    }
    return $html . '</div>';
}

// ---------------------------------------------------------------
// Menu de navigation (barre latérale, palette de commandes)
// ---------------------------------------------------------------
function app_menu(): array
{
    return [
        ['section' => 'Général', 'roles' => null, 'items' => [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'fa-gauge-high', 'url' => 'dashboard.php'],
        ]],
        ['section' => 'Ventes', 'roles' => ['admin', 'vendeur'], 'items' => [
            ['key' => 'caisse', 'label' => 'Caisse', 'icon' => 'fa-cash-register', 'url' => 'ventes/caisse.php'],
            ['key' => 'ventes', 'label' => 'Historique des ventes', 'icon' => 'fa-receipt', 'url' => 'ventes/historique.php'],
            ['key' => 'credits', 'label' => 'Ventes à crédit', 'icon' => 'fa-hand-holding-dollar', 'url' => 'ventes/credits.php', 'badge' => 'credits'],
            ['key' => 'rapport_caisse', 'label' => 'Rapport de caisse', 'icon' => 'fa-file-invoice-dollar', 'url' => 'ventes/rapport_caisse.php'],
        ]],
        ['section' => 'Secrétariat', 'roles' => ['admin', 'secretaire'], 'items' => [
            ['key' => 'courrier', 'label' => 'Courrier', 'icon' => 'fa-envelope-open-text', 'url' => 'courrier/liste.php', 'badge' => 'courrier'],
            ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'fa-calendar-days', 'url' => 'rendezvous/agenda.php', 'badge' => 'rdv'],
        ]],
        ['section' => 'Articles', 'roles' => null, 'items' => [
            ['key' => 'articles', 'label' => 'Catalogue articles', 'icon' => 'fa-boxes-stacked', 'url' => 'articles/liste.php', 'badge' => 'stock'],
            ['key' => 'categories', 'label' => 'Catégories', 'icon' => 'fa-tags', 'url' => 'articles/categories.php'],
            ['key' => 'etiquettes', 'label' => 'Étiquettes & codes', 'icon' => 'fa-qrcode', 'url' => 'articles/etiquettes.php'],
        ]],
        ['section' => 'Clients', 'roles' => null, 'items' => [
            ['key' => 'clients', 'label' => 'Clients', 'icon' => 'fa-address-book', 'url' => 'clients/liste.php'],
        ]],
        ['section' => 'Administration', 'roles' => ['admin'], 'items' => [
            ['key' => 'rapports', 'label' => 'Statistiques', 'icon' => 'fa-chart-line', 'url' => 'rapports/statistiques.php'],
            ['key' => 'import_export', 'label' => 'Import / Export', 'icon' => 'fa-file-excel', 'url' => 'import_export/index.php'],
            ['key' => 'utilisateurs', 'label' => 'Utilisateurs', 'icon' => 'fa-users-gear', 'url' => 'utilisateurs/liste.php'],
            ['key' => 'journal', 'label' => 'Journal d\'activité', 'icon' => 'fa-clock-rotate-left', 'url' => 'utilisateurs/journal.php'],
            ['key' => 'parametres', 'label' => 'Paramètres', 'icon' => 'fa-gear', 'url' => 'parametres/entreprise.php'],
        ]],
    ];
}

/** Menu filtré selon le rôle de l'utilisateur connecté. */
function app_menu_for_role(?string $role): array
{
    $out = [];
    foreach (app_menu() as $section) {
        if ($section['roles'] !== null && !in_array($role, $section['roles'], true)) {
            continue;
        }
        $out[] = $section;
    }
    return $out;
}

/** Actions de création rapides selon le rôle. */
function app_quick_actions(?string $role): array
{
    $actions = [
        ['label' => 'Nouvelle vente', 'icon' => 'fa-cash-register', 'url' => 'ventes/caisse.php', 'roles' => ['admin', 'vendeur']],
        ['label' => 'Nouveau client', 'icon' => 'fa-user-plus', 'url' => 'clients/ajouter.php', 'roles' => null],
        ['label' => 'Nouvel article', 'icon' => 'fa-box-open', 'url' => 'articles/ajouter.php', 'roles' => ['admin', 'secretaire']],
        ['label' => 'Nouveau courrier', 'icon' => 'fa-envelope-circle-check', 'url' => 'courrier/ajouter.php', 'roles' => ['admin', 'secretaire']],
        ['label' => 'Nouveau rendez-vous', 'icon' => 'fa-calendar-plus', 'url' => 'rendezvous/ajouter.php', 'roles' => ['admin', 'secretaire']],
    ];
    return array_values(array_filter($actions, fn($a) => $a['roles'] === null || in_array($role, $a['roles'], true)));
}
