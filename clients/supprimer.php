<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$check = $pdo->prepare('SELECT COUNT(*) c FROM ventes WHERE client_id = ?');
$check->execute([$id]);

if ((int)$check->fetch()['c'] > 0) {
    flash_set('warning', 'Ce client a un historique de ventes : suppression impossible (intégrité des données).');
} else {
    $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
    log_activity('client_suppression', "Client #$id supprimé");
    flash_set('success', 'Client supprimé.');
}
redirect('liste.php');
