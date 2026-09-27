<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
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
    die('Vente introuvable. <a href="historique.php">Retour</a>');
}

$stmt = $pdo->prepare("SELECT d.*, a.nom AS article_nom, a.code AS article_code FROM vente_details d
    LEFT JOIN articles a ON a.id = d.article_id WHERE d.vente_id = ?");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT mode_paiement, montant, created_at FROM vente_paiements WHERE vente_id = ? ORDER BY created_at ASC, id ASC");
$stmt->execute([$id]);
$paiements = $stmt->fetchAll();
$resteAPayer = round((float)$vente['montant_total'] - (float)$vente['montant_paye'], 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture <?= e($vente['numero_facture']) ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/app.css">
<style>
  body{ background:var(--sp-bg); }
  .invoice-box{ max-width:720px; margin:20px auto; background:#fff; padding:30px; border-radius:10px; }
  @media print{ body{ background:#fff; } .invoice-box{ margin:0; box-shadow:none; } }
</style>
</head>
<body onload="window.print()">
<div class="invoice-box">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color:var(--sp-navy);"><?= e(get_param('nom_entreprise', APP_NAME)) ?></h4>
            <div class="text-muted small"><?= e(get_param('adresse','')) ?></div>
            <div class="text-muted small"><?= e(get_param('telephone','')) ?> <?= get_param('email') ? ' - ' . e(get_param('email')) : '' ?></div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold">FACTURE</h5>
            <div class="small">N° <?= e($vente['numero_facture']) ?></div>
            <div class="small text-muted"><?= fmt_datetime($vente['created_at']) ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="text-muted small text-uppercase fw-semibold">Client</div>
            <div><?= $vente['client_nom'] ? e($vente['client_nom'] . ' ' . $vente['client_prenom']) : 'Client de passage' ?></div>
            <?php if ($vente['client_tel']): ?><div class="text-muted small"><?= e($vente['client_tel']) ?></div><?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <div class="text-muted small text-uppercase fw-semibold">Vendu par</div>
            <div><?= e($vente['vendeur_nom']) ?></div>
            <div class="text-muted small">Paiement : <?= e(ucfirst(str_replace('_',' ',$vente['mode_paiement']))) ?></div>
        </div>
    </div>

    <table class="table table-sp">
        <thead><tr><th>Article</th><th class="text-center">Qté</th><th class="text-end">P.U.</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= e($l['article_nom']) ?><div class="text-muted" style="font-size:.72rem;"><?= e($l['article_code']) ?></div></td>
                <td class="text-center"><?= (int)$l['quantite'] ?></td>
                <td class="text-end"><?= fmt_money((float)$l['prix_unitaire']) ?></td>
                <td class="text-end"><?= fmt_money((float)$l['sous_total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-end">
        <table class="table table-borderless w-auto">
            <?php if ((float)$vente['remise_montant'] > 0): ?>
            <tr><td class="text-muted">Sous-total</td><td class="text-end"><?= fmt_money((float)$vente['montant_brut']) ?></td></tr>
            <tr><td class="text-muted">Remise<?= $vente['remise_type'] === 'pourcentage' ? ' (' . rtrim(rtrim(sprintf('%.2f', (float)$vente['remise_valeur']), '0'), '.') . '%)' : '' ?></td><td class="text-end">- <?= fmt_money((float)$vente['remise_montant']) ?></td></tr>
            <?php endif; ?>
            <tr><td class="text-muted">Total</td><td class="text-end fw-bold fs-5"><?= fmt_money((float)$vente['montant_total']) ?></td></tr>
            <tr><td class="text-muted">Montant payé</td><td class="text-end"><?= fmt_money((float)$vente['montant_paye']) ?></td></tr>
            <?php if ($resteAPayer > 0.009): ?>
            <tr><td class="text-muted">Reste à payer</td><td class="text-end fw-bold text-danger"><?= fmt_money($resteAPayer) ?></td></tr>
            <?php endif; ?>
            <tr><td class="text-muted">Monnaie rendue</td><td class="text-end"><?= fmt_money((float)$vente['monnaie_rendue']) ?></td></tr>
        </table>
    </div>

    <?php if (count($paiements) > 1): ?>
    <div class="mt-2">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Détail des paiements</div>
        <table class="table table-sm w-auto ms-auto">
            <?php foreach ($paiements as $p): ?>
                <tr>
                    <td class="text-muted small"><?= fmt_date($p['created_at'], 'd/m/Y H:i') ?></td>
                    <td class="small"><?= e(ucfirst(str_replace('_',' ',$p['mode_paiement']))) ?></td>
                    <td class="text-end small fw-semibold"><?= fmt_money((float)$p['montant']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="text-center text-muted small mt-4">Merci pour votre confiance !</div>

    <div class="text-center mt-3 no-print">
        <button class="btn btn-sp-amber btn-sm" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer</button>
        <a href="historique.php" class="btn btn-outline-secondary btn-sm">Retour à l'historique</a>
    </div>
</div>
</body>
</html>
