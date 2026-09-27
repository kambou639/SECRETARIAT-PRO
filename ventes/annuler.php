<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM ventes WHERE id = ? AND statut = "validee"');
$stmt->execute([$id]);
$vente = $stmt->fetch();

if (!$vente) {
    flash_set('danger', 'Vente introuvable ou déjà annulée.');
    redirect('historique.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT d.*, a.type AS article_type FROM vente_details d JOIN articles a ON a.id = d.article_id WHERE d.vente_id = ?');
    $stmt->execute([$id]);
    $lignes = $stmt->fetchAll();

    $stmtArt = $pdo->prepare('SELECT stock FROM articles WHERE id = ?');
    $stmtUpd = $pdo->prepare('UPDATE articles SET stock = stock + ? WHERE id = ?');
    $stmtMouv = $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, reference, user_id) VALUES (?,"entree",?,?,?,?,?,?)');

    foreach ($lignes as $l) {
        if ($l['article_type'] === 'service') {
            continue; // les services ne suivent pas de stock, rien à remettre
        }
        $stmtArt->execute([$l['article_id']]);
        $stockAvant = (int)$stmtArt->fetchColumn();
        $stmtUpd->execute([$l['quantite'], $l['article_id']]);
        $stmtMouv->execute([
            $l['article_id'], $l['quantite'], $stockAvant, $stockAvant + $l['quantite'],
            'Annulation vente ' . $vente['numero_facture'], $vente['numero_facture'], $_SESSION['user_id'],
        ]);
    }

    $pdo->prepare('UPDATE ventes SET statut = "annulee" WHERE id = ?')->execute([$id]);
    $pdo->commit();

    log_activity('vente_annulation', 'Vente annulée : ' . $vente['numero_facture']);
    flash_set('success', 'Vente ' . $vente['numero_facture'] . ' annulée. Stock remis à jour.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('vente annuler: ' . $e->getMessage());
    flash_set('danger', 'Erreur lors de l\'annulation.');
}

redirect('historique.php');
