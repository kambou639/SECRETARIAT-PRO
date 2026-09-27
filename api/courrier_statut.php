<?php
/**
 * Changement rapide du statut d'un courrier (liste et fiche du courrier).
 * POST JSON : { csrf_token, id, statut }
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login(['admin', 'secretaire']);
require_api_csrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);
$statut = (string)($input['statut'] ?? '');
if (!in_array($statut, ['recu', 'en_traitement', 'traite', 'archive', 'envoye'], true)) {
    json_response(['success' => false, 'message' => 'Statut invalide.']);
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT numero, objet FROM courriers WHERE id = ?');
$stmt->execute([$id]);
$courrier = $stmt->fetch();
if (!$courrier) {
    json_response(['success' => false, 'message' => 'Courrier introuvable.'], 404);
}

$pdo->prepare('UPDATE courriers SET statut = ? WHERE id = ?')->execute([$statut, $id]);
[$label, $tone, $icon] = statut_courrier_meta($statut);
log_activity('courrier_statut', "Courrier {$courrier['numero']} : $label");

json_response(['success' => true, 'statut' => $statut, 'label' => $label, 'tone' => $tone, 'icon' => $icon, 'badge' => badge_html(statut_courrier_meta($statut))]);
