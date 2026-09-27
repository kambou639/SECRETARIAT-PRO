<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_post_csrf('liste.php');
$pdo = Database::getConnection();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();

if ($article) {
    try {
        // Empêche la suppression si l'article a déjà été vendu (intégrité des historiques)
        $check = $pdo->prepare('SELECT COUNT(*) c FROM vente_details WHERE article_id = ?');
        $check->execute([$id]);
        if ((int)$check->fetch()['c'] > 0) {
            $pdo->prepare('UPDATE articles SET actif = 0 WHERE id = ?')->execute([$id]);
            log_activity('article_desactivation', "Article désactivé : {$article['nom']}");
            flash_set('warning', 'Cet article a un historique de ventes : il a été désactivé plutôt que supprimé.');
        } else {
            $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
            delete_upload(UPLOAD_ARTICLES, $article['image']);
            log_activity('article_suppression', "Article supprimé : {$article['nom']}");
            flash_set('success', 'Article « ' . $article['nom'] . ' » supprimé.');
        }
    } catch (Exception $e) {
        error_log('article delete: ' . $e->getMessage());
        flash_set('danger', 'Erreur lors de la suppression.');
    }
} else {
    flash_set('danger', 'Article introuvable.');
}

redirect_back('liste.php', ['liste.php']);
