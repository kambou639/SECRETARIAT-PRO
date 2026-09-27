<?php
/**
 * Changement rapide du statut d'un rendez-vous depuis l'agenda.
 * POST JSON : { csrf_token, id, statut }
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login(['admin', 'secretaire']);
require_api_csrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);
$statut = (string)($input['statut'] ?? '');
if (!in_array($statut, ['planifie', 'confirme', 'annule', 'termine'], true)) {
    json_response(['success' => false, 'message' => 'Statut invalide.']);
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT titre FROM rendezvous WHERE id = ?');
$stmt->execute([$id]);
$rdv = $stmt->fetch();
if (!$rdv) {
    json_response(['success' => false, 'message' => 'Rendez-vous introuvable.'], 404);
}

$pdo->prepare('UPDATE rendezvous SET statut = ? WHERE id = ?')->execute([$statut, $id]);
$meta = statut_rdv_meta($statut);
log_activity('rdv_statut', "RDV « {$rdv['titre']} » : {$meta[0]}");

json_response(['success' => true, 'statut' => $statut, 'label' => $meta[0], 'badge' => badge_html($meta)]);
