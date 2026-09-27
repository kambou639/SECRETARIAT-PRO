<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id == $_SESSION['user_id']) {
    flash_set('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
    redirect('liste.php');
}

$stmt = $pdo->prepare('SELECT COUNT(*) c FROM ventes WHERE user_id = ?');
$stmt->execute([$id]);
if ((int)$stmt->fetch()['c'] > 0) {
    $pdo->prepare('UPDATE users SET actif = 0 WHERE id = ?')->execute([$id]);
    flash_set('warning', 'Cet utilisateur a un historique de ventes : il a été désactivé plutôt que supprimé.');
} else {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    log_activity('utilisateur_suppression', "Utilisateur #$id supprimé");
    flash_set('success', 'Utilisateur supprimé.');
}
redirect('liste.php');
