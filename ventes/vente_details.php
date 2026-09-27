<?php
/**
 * Retourne en JSON les informations complètes d'une vente (en-tête + lignes),
 * utilisé par la modale "Détails" affichée depuis la Caisse et l'Historique des ventes.
 * Usage : vente_details.php?id=12
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role('admin', 'vendeur')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$pdo = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT v.*, u.full_name AS vendeur_nom, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel
    FROM ventes v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN clients c ON c.id = v.client_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$vente = $stmt->fetch();

if (!$vente) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vente introuvable.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.quantite, d.prix_unitaire, d.sous_total, a.nom AS article_nom, a.code AS article_code, a.unite
    FROM vente_details d
    LEFT JOIN articles a ON a.id = d.article_id
    WHERE d.vente_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT vp.mode_paiement, vp.montant, vp.note, vp.created_at
    FROM vente_paiements vp
    WHERE vp.vente_id = ?
    ORDER BY vp.created_at ASC, vp.id ASC
");
$stmt->execute([$id]);
$paiements = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'vente' => [
        'id' => (int)$vente['id'],
        'numero_facture' => $vente['numero_facture'],
        'date' => fmt_datetime($vente['created_at']),
        'client' => $vente['client_nom'] ? trim($vente['client_nom'] . ' ' . $vente['client_prenom']) : 'Client de passage',
        'client_tel' => $vente['client_tel'],
        'vendeur' => $vente['vendeur_nom'],
        'mode_paiement' => ucfirst(str_replace('_', ' ', $vente['mode_paiement'])),
        'statut' => $vente['statut'],
        'statut_paiement' => $vente['statut_paiement'],
        'montant_brut' => (float)$vente['montant_brut'],
        'remise_type' => $vente['remise_type'],
        'remise_valeur' => (float)$vente['remise_valeur'],
        'remise_montant' => (float)$vente['remise_montant'],
        'montant_total' => (float)$vente['montant_total'],
        'montant_paye' => (float)$vente['montant_paye'],
        'reste_a_payer' => round((float)$vente['montant_total'] - (float)$vente['montant_paye'], 2),
        'monnaie_rendue' => (float)$vente['monnaie_rendue'],
        'observations' => $vente['observations'],
    ],
    'lignes' => array_map(fn($l) => [
        'article_nom' => $l['article_nom'],
        'article_code' => $l['article_code'],
        'unite' => $l['unite'],
        'quantite' => (int)$l['quantite'],
        'prix_unitaire' => (float)$l['prix_unitaire'],
        'sous_total' => (float)$l['sous_total'],
    ], $lignes),
    'paiements' => array_map(fn($p) => [
        'mode_paiement' => ucfirst(str_replace('_', ' ', $p['mode_paiement'])),
        'montant' => (float)$p['montant'],
        'note' => $p['note'],
        'date' => fmt_datetime($p['created_at']),
    ], $paiements),
    'devise' => get_param('devise', 'FCFA'),
]);
