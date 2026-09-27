<?php
require_once __DIR__ . '/../includes/auth.php';
require_api_login(['admin', 'vendeur']);
require_api_csrf();

$input = read_json_body();
$venteId = (int)($input['vente_id'] ?? 0);
$mode = in_array($input['mode_paiement'] ?? '', ['especes', 'mobile_money', 'carte', 'virement', 'autre'], true)
    ? $input['mode_paiement'] : null;
$montant = round((float)($input['montant'] ?? 0), 2);
$note = trim((string)($input['note'] ?? ''));
if ($note !== '') { $note = mb_substr($note, 0, 150); }

if (!$venteId || !$mode || $montant <= 0) {
    json_response(['success' => false, 'message' => 'Données de versement invalides.']);
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM ventes WHERE id = ? FOR UPDATE');
    $stmt->execute([$venteId]);
    $vente = $stmt->fetch();

    if (!$vente) {
        throw new RuntimeException('Vente introuvable.');
    }
    if ($vente['statut'] !== 'validee') {
        throw new RuntimeException('Cette vente est annulée, aucun versement ne peut y être ajouté.');
    }
    $resteAPayer = round((float)$vente['montant_total'] - (float)$vente['montant_paye'], 2);
    if ($resteAPayer <= 0.009) {
        throw new RuntimeException('Cette vente est déjà intégralement payée.');
    }
    if ($montant > $resteAPayer + 0.009) {
        throw new RuntimeException('Le montant du versement dépasse le reste à payer (' . fmt_money($resteAPayer) . ').');
    }

    $pdo->prepare('INSERT INTO vente_paiements (vente_id, mode_paiement, montant, user_id, note) VALUES (?,?,?,?,?)')
        ->execute([$venteId, $mode, $montant, $_SESSION['user_id'], $note ?: null]);

    $nouveauPaye = round((float)$vente['montant_paye'] + $montant, 2);
    $nouveauStatut = $nouveauPaye >= (float)$vente['montant_total'] - 0.009 ? 'payee' : 'partielle';

    $pdo->prepare('UPDATE ventes SET montant_paye = ?, statut_paiement = ? WHERE id = ?')
        ->execute([$nouveauPaye, $nouveauStatut, $venteId]);

    $pdo->commit();
    log_activity('vente_versement', "Versement de " . fmt_money($montant) . " sur la vente {$vente['numero_facture']}");

    json_response([
        'success' => true,
        'montant_paye' => $nouveauPaye,
        'reste_a_payer' => max(0, round((float)$vente['montant_total'] - $nouveauPaye, 2)),
        'statut_paiement' => $nouveauStatut,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('versement_ajouter: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Erreur lors de l\'enregistrement du versement.']);
}
