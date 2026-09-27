<?php
require_once __DIR__ . '/../includes/auth.php';
require_api_login(['admin', 'vendeur']);

$input = read_json_body();
if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$input['csrf_token'])) {
    json_response(['success' => false, 'message' => 'Jeton de sécurité invalide. Merci de recharger la page.'], 403);
}

$items = $input['items'] ?? [];
$clientId = !empty($input['client_id']) ? (int)$input['client_id'] : null;
$remiseType = in_array($input['remise_type'] ?? '', ['pourcentage', 'montant'], true) ? $input['remise_type'] : 'aucune';
$remiseValeur = max(0, (float)($input['remise_valeur'] ?? 0));
$observations = trim((string)($input['observations'] ?? ''));
$observations = $observations === '' ? null : mb_substr($observations, 0, 255);

// Paiements : liste de {mode, montant} - permet le paiement multiple (espèces + mobile money, etc.)
// et la vente à crédit (liste vide ou montant partiel).
$paiementsInput = is_array($input['paiements'] ?? null) ? $input['paiements'] : [];
$modesValides = ['especes', 'mobile_money', 'carte', 'virement', 'autre'];
$paiements = [];
foreach ($paiementsInput as $p) {
    $mode = in_array($p['mode'] ?? '', $modesValides, true) ? $p['mode'] : null;
    $montant = round((float)($p['montant'] ?? 0), 2);
    if ($mode && $montant > 0) {
        $paiements[] = ['mode' => $mode, 'montant' => $montant];
    }
}

if (empty($items) || !is_array($items)) {
    json_response(['success' => false, 'message' => 'Le panier est vide.']);
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    if ($clientId) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $chk->execute([$clientId]);
        if (!$chk->fetch()) {
            throw new RuntimeException('Le client sélectionné n\'existe plus. Merci de le choisir à nouveau.');
        }
    }

    // Regroupe les éventuelles lignes en double d'un même article
    $quantites = [];
    foreach ($items as $item) {
        $articleId = (int)($item['id'] ?? 0);
        $qte = (int)($item['qte'] ?? 0);
        if ($articleId > 0 && $qte > 0) {
            $quantites[$articleId] = ($quantites[$articleId] ?? 0) + $qte;
        }
    }

    $montantTotal = 0;
    $lignes = [];

    foreach ($quantites as $articleId => $qte) {
        // Verrouille la ligne pour éviter les ventes concurrentes en survente
        $stmt = $pdo->prepare('SELECT id, nom, type, prix_vente, stock FROM articles WHERE id = ? AND actif = 1 FOR UPDATE');
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();

        if (!$article) {
            throw new RuntimeException('Un article du panier n\'existe plus ou a été désactivé.');
        }
        if ($article['type'] !== 'service' && $qte > (int)$article['stock']) {
            throw new RuntimeException('Stock insuffisant pour : ' . $article['nom'] . ' (disponible : ' . (int)$article['stock'] . ').');
        }

        $prixUnitaire = get_prix_effectif($pdo, $articleId, $qte, (float)$article['prix_vente']); // prix serveur (avec paliers dégressifs), jamais celui envoyé par le client
        $sousTotal = $prixUnitaire * $qte;
        $montantTotal += $sousTotal;

        $lignes[] = [
            'article_id' => $articleId,
            'type' => $article['type'],
            'qte' => $qte,
            'prix_unitaire' => $prixUnitaire,
            'sous_total' => $sousTotal,
            'stock_avant' => (int)$article['stock'],
        ];
    }

    if (empty($lignes)) {
        throw new RuntimeException('Panier invalide.');
    }

    // Remise recalculée côté serveur - on ne fait jamais confiance à un montant réduit envoyé par le client.
    $montantBrut = $montantTotal;
    $remiseMontant = 0.0;
    if ($remiseType === 'pourcentage') {
        $remiseValeur = min($remiseValeur, 100);
        $remiseMontant = round($montantBrut * $remiseValeur / 100, 2);
    } elseif ($remiseType === 'montant') {
        $remiseMontant = min($remiseValeur, $montantBrut);
    } else {
        $remiseValeur = 0;
    }
    if ($remiseMontant <= 0) {
        $remiseType = 'aucune';
        $remiseValeur = 0;
        $remiseMontant = 0.0;
    }
    $montantTotal = round($montantBrut - $remiseMontant, 2);

    // Montant payé = somme des paiements saisis (jamais confiance dans un total envoyé par le client).
    $totalSaisi = round(array_sum(array_column($paiements, 'montant')), 2);
    $monnaieRendue = 0.0;
    if ($totalSaisi > $montantTotal + 0.009) {
        // Sur-paiement (ex: client tend un billet plus gros) : l'excédent est rendu en monnaie,
        // on réduit la dernière ligne de paiement pour que la somme enregistrée corresponde au montant dû.
        $monnaieRendue = round($totalSaisi - $montantTotal, 2);
        $excedent = $monnaieRendue;
        for ($i = count($paiements) - 1; $i >= 0 && $excedent > 0; $i--) {
            $reduction = min($paiements[$i]['montant'], $excedent);
            $paiements[$i]['montant'] = round($paiements[$i]['montant'] - $reduction, 2);
            $excedent = round($excedent - $reduction, 2);
        }
        $paiements = array_values(array_filter($paiements, fn($p) => $p['montant'] > 0));
    }
    $montantPaye = round(array_sum(array_column($paiements, 'montant')), 2);

    if ($montantPaye <= 0.0 && $montantTotal > 0) {
        $statutPaiement = 'impayee';
    } elseif ($montantPaye < $montantTotal - 0.009) {
        $statutPaiement = 'partielle';
    } else {
        $statutPaiement = 'payee';
    }

    // Une vente non intégralement payée est une créance : un client identifié est obligatoire pour la suivre.
    if ($statutPaiement !== 'payee' && !$clientId) {
        throw new RuntimeException('Un client doit être sélectionné pour une vente à crédit (paiement partiel ou différé).');
    }

    $modesDistincts = array_values(array_unique(array_column($paiements, 'mode')));
    if (count($modesDistincts) > 1) {
        $modePaiement = 'mixte';
    } elseif (count($modesDistincts) === 1) {
        $modePaiement = $modesDistincts[0];
    } else {
        $modePaiement = 'especes'; // vente à crédit sans paiement initial : valeur par défaut neutre
    }

    $numeroFacture = generate_reference(get_param('prefixe_facture', 'FAC'));

    $stmt = $pdo->prepare('INSERT INTO ventes (numero_facture, client_id, user_id, montant_brut, remise_type, remise_valeur, remise_montant, montant_total, montant_paye, monnaie_rendue, mode_paiement, statut_paiement, statut, observations)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"validee",?)');
    $stmt->execute([$numeroFacture, $clientId, $_SESSION['user_id'], $montantBrut, $remiseType, $remiseValeur, $remiseMontant, $montantTotal, $montantPaye, $monnaieRendue, $modePaiement, $statutPaiement, $observations]);
    $venteId = (int)$pdo->lastInsertId();

    if (!empty($paiements)) {
        $stmtPaiement = $pdo->prepare('INSERT INTO vente_paiements (vente_id, mode_paiement, montant, user_id) VALUES (?,?,?,?)');
        foreach ($paiements as $p) {
            $stmtPaiement->execute([$venteId, $p['mode'], $p['montant'], $_SESSION['user_id']]);
        }
    }

    $stmtDetail = $pdo->prepare('INSERT INTO vente_details (vente_id, article_id, quantite, prix_unitaire, sous_total) VALUES (?,?,?,?,?)');
    $stmtStock = $pdo->prepare('UPDATE articles SET stock = stock - ? WHERE id = ?');
    $stmtMouv = $pdo->prepare('INSERT INTO mouvements_stock (article_id, type, quantite, stock_avant, stock_apres, motif, reference, user_id) VALUES (?,"sortie",?,?,?,?,?,?)');

    foreach ($lignes as $l) {
        $stmtDetail->execute([$venteId, $l['article_id'], $l['qte'], $l['prix_unitaire'], $l['sous_total']]);
        if ($l['type'] !== 'service') {
            $stmtStock->execute([$l['qte'], $l['article_id']]);
            $stmtMouv->execute([
                $l['article_id'], $l['qte'], $l['stock_avant'], $l['stock_avant'] - $l['qte'],
                'Vente ' . $numeroFacture, $numeroFacture, $_SESSION['user_id'],
            ]);
        }
    }

    $pdo->commit();
    log_activity('vente', "Vente $numeroFacture - " . fmt_money($montantTotal));

    json_response([
        'success' => true,
        'vente_id' => $venteId,
        'numero' => $numeroFacture,
        'montant_total' => $montantTotal,
        'montant_paye' => $montantPaye,
        'monnaie_rendue' => $monnaieRendue,
        'reste_a_payer' => round($montantTotal - $montantPaye, 2),
        'statut_paiement' => $statutPaiement,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('vente valider: ' . $e->getMessage());
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Erreur lors de l\'enregistrement de la vente.';
    json_response(['success' => false, 'message' => $message]);
}
