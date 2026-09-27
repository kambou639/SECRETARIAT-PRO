<?php
/**
 * Création rapide d'un client depuis la caisse (sans quitter la vente en cours).
 * POST JSON : { csrf_token, nom, prenom, telephone, type }
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login();
require_api_csrf();

$input = read_json_body();
$nom = mb_substr(trim((string)($input['nom'] ?? '')), 0, 100);
$prenom = mb_substr(trim((string)($input['prenom'] ?? '')), 0, 100);
$telephone = mb_substr(trim((string)($input['telephone'] ?? '')), 0, 30);
$type = ($input['type'] ?? '') === 'entreprise' ? 'entreprise' : 'particulier';

if ($nom === '') {
    json_response(['success' => false, 'message' => 'Le nom du client est obligatoire.']);
}

$pdo = Database::getConnection();

// Évite les doublons évidents (même nom + même téléphone)
if ($telephone !== '') {
    $stmt = $pdo->prepare('SELECT id, nom, prenom, telephone FROM clients WHERE nom = ? AND telephone = ? LIMIT 1');
    $stmt->execute([$nom, $telephone]);
    if ($existing = $stmt->fetch()) {
        json_response(['success' => true, 'existing' => true, 'client' => [
            'id' => (int)$existing['id'], 'label' => trim($existing['nom'] . ' ' . ($existing['prenom'] ?? '')), 'tel' => $existing['telephone'] ?? '',
        ]]);
    }
}

$stmt = $pdo->prepare('INSERT INTO clients (type, nom, prenom, telephone) VALUES (?,?,?,?)');
$stmt->execute([$type, $nom, $prenom !== '' ? $prenom : null, $telephone !== '' ? $telephone : null]);
$id = (int)$pdo->lastInsertId();
log_activity('client_creation', "Client créé depuis la caisse : $nom");

json_response(['success' => true, 'client' => ['id' => $id, 'label' => trim($nom . ' ' . $prenom), 'tel' => $telephone]]);
