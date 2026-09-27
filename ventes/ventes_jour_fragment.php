<?php
/**
 * Renvoie le HTML actualisé de la carte "Ventes du jour" de la Caisse,
 * pour un rafraîchissement AJAX sans recharger toute la page.
 */
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || !has_role('admin', 'vendeur')) {
    http_response_code(403);
    exit;
}

$pdo = Database::getConnection();

$stmtJour = $pdo->prepare("
    SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.remise_montant, v.mode_paiement, v.statut, v.statut_paiement,
           u.full_name AS vendeur_nom, c.nom AS client_nom
    FROM ventes v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN clients c ON c.id = v.client_id
    WHERE DATE(v.created_at) = CURDATE()
    ORDER BY v.created_at DESC
    LIMIT 30
");
$stmtJour->execute();
$ventesJour = $stmtJour->fetchAll();
$totalJour = 0;
foreach ($ventesJour as $vj) {
    if ($vj['statut'] === 'validee') $totalJour += (float)$vj['montant_total'];
}

include __DIR__ . '/../includes/_ventes_jour_content.php';
