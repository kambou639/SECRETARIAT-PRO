<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
require_post_csrf('agenda.php');
$pdo = Database::getConnection();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM rendezvous WHERE id = ?');
$stmt->execute([$id]);
$rdv = $stmt->fetch();

if ($rdv) {
    // On marque annulé plutôt que de supprimer, pour garder une trace dans l'agenda
    $pdo->prepare("UPDATE rendezvous SET statut = 'annule' WHERE id = ?")->execute([$id]);
    log_activity('rdv_annulation', "RDV annulé : {$rdv['titre']}");
    flash_set('success', 'Rendez-vous « ' . $rdv['titre'] . ' » annulé.');
    redirect('agenda.php?mois=' . substr($rdv['date_rdv'], 0, 7));
}
flash_set('danger', 'Rendez-vous introuvable.');
redirect('agenda.php');
