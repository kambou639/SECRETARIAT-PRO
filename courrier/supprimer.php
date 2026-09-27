<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_post_csrf('liste.php');
$pdo = Database::getConnection();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courriers WHERE id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();

if ($c) {
    $pdo->prepare('DELETE FROM courriers WHERE id = ?')->execute([$id]);
    delete_upload(UPLOAD_COURRIERS, $c['fichier_joint']);
    log_activity('courrier_suppression', "Courrier supprimé : {$c['numero']}");
    flash_set('success', 'Courrier supprimé.');
} else {
    flash_set('danger', 'Courrier introuvable.');
}
redirect_back('liste.php', ['liste.php']);
