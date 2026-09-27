<?php
/**
 * Calcule le prix unitaire effectif d'un article pour une quantité donnée,
 * en tenant compte des tarifs dégressifs (paliers_prix). Utilisé par la
 * caisse pour afficher le prix en temps réel avant validation.
 * Usage : tarif_calc.php?id=5&qte=45
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
    exit;
}

$pdo = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);
$qte = max(1, (int)($_GET['qte'] ?? 1));

$stmt = $pdo->prepare('SELECT id, nom, type, prix_vente, stock, unite FROM articles WHERE id = ? AND actif = 1');
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Article introuvable.']);
    exit;
}

$prixBase = (float)$article['prix_vente'];
$prixUnitaire = get_prix_effectif($pdo, $id, $qte, $prixBase);
$paliers = get_paliers($pdo, $id);

echo json_encode([
    'success' => true,
    'article_id' => $id,
    'nom' => $article['nom'],
    'type' => $article['type'],
    'unite' => $article['unite'],
    'stock' => (int)$article['stock'],
    'qte' => $qte,
    'prix_base' => $prixBase,
    'prix_unitaire' => $prixUnitaire,
    'degressif' => $prixUnitaire < $prixBase,
    'sous_total' => $prixUnitaire * $qte,
    'paliers' => array_map(fn($p) => [
        'quantite_min' => (int)$p['quantite_min'],
        'quantite_max' => $p['quantite_max'] !== null ? (int)$p['quantite_max'] : null,
        'prix_unitaire' => (float)$p['prix_unitaire'],
    ], $paliers),
]);
