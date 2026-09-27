<?php
/**
 * Rendez-vous d'une journée (formulaire d'agenda : aperçu du jour et détection des chevauchements).
 * GET : date=YYYY-MM-DD [&exclure=ID]
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login(['admin', 'secretaire']);

$date = valid_date($_GET['date'] ?? null, '');
if ($date === '') {
    json_response(['success' => false, 'message' => 'Date invalide.'], 400);
}
$exclure = (int)($_GET['exclure'] ?? 0);

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT r.id, r.titre, r.heure_debut, r.heure_fin, r.lieu, r.statut, r.contact_nom, c.nom AS client_nom, c.prenom AS client_prenom
    FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.date_rdv = ? AND r.statut <> 'annule' AND r.id <> ?
    ORDER BY r.heure_debut");
$stmt->execute([$date, $exclure]);

$items = [];
foreach ($stmt->fetchAll() as $r) {
    $items[] = [
        'id' => (int)$r['id'],
        'titre' => $r['titre'],
        'debut' => substr($r['heure_debut'], 0, 5),
        'fin' => substr(rdv_fin_effective($r['heure_debut'], $r['heure_fin']), 0, 5),
        'fin_saisie' => $r['heure_fin'] !== null,
        'lieu' => $r['lieu'],
        'personne' => rdv_personne($r),
        'statut' => $r['statut'],
    ];
}

json_response(['success' => true, 'date' => $date, 'libelle' => fmt_date_fr_long($date), 'items' => $items]);
