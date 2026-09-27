<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_post_csrf('liste.php');
$pdo = Database::getConnection();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT nom, prenom FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    flash_set('danger', 'Client introuvable.');
    redirect('liste.php');
}

$check = $pdo->prepare('SELECT COUNT(*) c FROM ventes WHERE client_id = ?');
$check->execute([$id]);

if ((int)$check->fetch()['c'] > 0) {
    flash_set('warning', 'Ce client a un historique de ventes : suppression impossible (intégrité des données).');
} else {
    $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
    $nom = trim($client['nom'] . ' ' . ($client['prenom'] ?? ''));
    log_activity('client_suppression', "Client supprimé : $nom");
    flash_set('success', 'Client « ' . $nom . ' » supprimé.');
}
redirect_back('liste.php', ['liste.php']);
