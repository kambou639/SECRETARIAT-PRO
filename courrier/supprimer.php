<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courriers WHERE id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();

if ($c) {
    if ($c['fichier_joint'] && file_exists(UPLOAD_COURRIERS . '/' . $c['fichier_joint'])) {
        @unlink(UPLOAD_COURRIERS . '/' . $c['fichier_joint']);
    }
    $pdo->prepare('DELETE FROM courriers WHERE id = ?')->execute([$id]);
    log_activity('courrier_suppression', "Courrier supprimé : {$c['numero']}");
    flash_set('success', 'Courrier supprimé.');
} else {
    flash_set('danger', 'Courrier introuvable.');
}
redirect('liste.php');
