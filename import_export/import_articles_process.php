<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role('admin', 'secretaire')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Jeton de sécurité invalide, merci de recharger la page.']);
    exit;
}

$rows = $input['rows'] ?? [];
if (!is_array($rows) || empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'Aucune ligne à importer.']);
    exit;
}

$pdo = Database::getConnection();
$created = 0; $updated = 0; $skipped = 0;

// Cache des catégories (création à la volée si nom inconnu)
$catStmt = $pdo->query('SELECT id, nom FROM categories');
$catMap = [];
foreach ($catStmt->fetchAll() as $c) {
    $catMap[mb_strtolower(trim($c['nom']))] = $c['id'];
}
$insertCat = $pdo->prepare('INSERT INTO categories (nom) VALUES (?)');

try {
    $pdo->beginTransaction();

    $checkStmt = $pdo->prepare('SELECT id FROM articles WHERE code = ?');
    $insertStmt = $pdo->prepare('INSERT INTO articles (code, nom, description, categorie_id, type, prix_achat, prix_vente, stock, seuil_alerte, unite, actif)
        VALUES (?,?,?,?,?,?,?,?,?,?,1)');
    $updateStmt = $pdo->prepare('UPDATE articles SET nom=?, categorie_id=?, type=?, prix_achat=?, prix_vente=?, seuil_alerte=?, unite=? WHERE code=?');

    foreach ($rows as $row) {
        $code = trim((string)($row['code'] ?? ''));
        $nom = trim((string)($row['nom'] ?? ''));
        if ($code === '' || $nom === '') {
            $skipped++;
            continue;
        }

        $type = mb_strtolower(trim((string)($row['type'] ?? 'produit'))) === 'service' ? 'service' : 'produit';

        $categorieNom = trim((string)($row['categorie'] ?? ''));
        $categorieId = null;
        if ($categorieNom !== '') {
            $key = mb_strtolower($categorieNom);
            if (!isset($catMap[$key])) {
                $insertCat->execute([$categorieNom]);
                $catMap[$key] = (int)$pdo->lastInsertId();
            }
            $categorieId = $catMap[$key];
        }

        $prixAchat = (float)($row['prix_achat'] ?? 0);
        $prixVente = (float)($row['prix_vente'] ?? 0);
        $stock = $type === 'service' ? 0 : max(0, (int)($row['stock'] ?? 0));
        $seuil = $type === 'service' ? 0 : max(0, (int)($row['seuil_alerte'] ?? 5));
        $unite = trim((string)($row['unite'] ?? 'pièce')) ?: 'pièce';

        $checkStmt->execute([$code]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $updateStmt->execute([$nom, $categorieId, $type, $prixAchat, $prixVente, $seuil, $unite, $code]);
            $updated++;
        } else {
            $insertStmt->execute([$code, $nom, '', $categorieId, $type, $prixAchat, $prixVente, $stock, $seuil, $unite]);
            $newId = (int)$pdo->lastInsertId();
            if ($type === 'produit' && $stock > 0) {
                $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, user_id) VALUES (?,"entree",?,0,?,?,?)')
                    ->execute([$newId, $stock, $stock, 'Import Excel', $_SESSION['user_id']]);
            }
            $created++;
        }
    }

    $pdo->commit();
    log_activity('import_articles', "$created créés, $updated mis à jour, $skipped ignorés");
    echo json_encode(['success' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('import_articles: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'import : ' . $e->getMessage()]);
}
