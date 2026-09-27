<?php
/**
 * Sert une image PNG (QR code ou code-barres Code128) pour un article donné.
 * Usage : code_image.php?id=5&type=qr | code_image.php?id=5&type=barcode
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/QRHelper.php';
require_once __DIR__ . '/../includes/Barcode128.php';

$pdo = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);
$type = ($_GET['type'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';

$stmt = $pdo->prepare('SELECT code, nom, prix_vente FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Article introuvable');
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=3600');

if ($type === 'barcode') {
    echo Barcode128::generatePNG($article['code'], 55, 2, true);
} else {
    $payload = $article['code'] . ' | ' . $article['nom'] . ' | ' . number_format((float)$article['prix_vente'], 0, ',', ' ') . ' ' . get_param('devise', 'FCFA');
    echo QRHelper::generatePNG($payload, 5, 3);
}
