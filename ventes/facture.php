<?php
/**
 * Facture (A4) ou ticket de caisse (80 mm) d'une vente.
 * Paramètres : id, format=a4|ticket, print=1 (impression automatique à l'ouverture)
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/QRHelper.php';
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$format = ($_GET['format'] ?? get_param('format_recu', 'a4')) === 'ticket' ? 'ticket' : 'a4';
$autoPrint = !empty($_GET['print']);

$stmt = $pdo->prepare("
    SELECT v.*, u.full_name AS vendeur_nom, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel,
           c.email AS client_email, c.adresse AS client_adresse, c.ville AS client_ville, c.type AS client_type
    FROM ventes v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN clients c ON c.id = v.client_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$vente = $stmt->fetch();
if (!$vente) {
    flash_set('danger', 'Vente introuvable.');
    redirect(has_role('admin', 'vendeur') ? 'historique.php' : '../dashboard.php');
}

$stmt = $pdo->prepare("SELECT d.*, a.nom AS article_nom, a.code AS article_code, a.unite FROM vente_details d
    LEFT JOIN articles a ON a.id = d.article_id WHERE d.vente_id = ? ORDER BY d.id ASC");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT mode_paiement, montant, note, created_at FROM vente_paiements WHERE vente_id = ? ORDER BY created_at ASC, id ASC");
$stmt->execute([$id]);
$paiements = $stmt->fetchAll();

$total = (float)$vente['montant_total'];
$resteAPayer = round($total - (float)$vente['montant_paye'], 2);
$annulee = $vente['statut'] === 'annulee';
$devise = get_param('devise', 'FCFA');
$entreprise = get_param('nom_entreprise', APP_NAME);
$slogan = get_param('slogan');
$adresse = get_param('adresse');
$telephone = get_param('telephone');
$email = get_param('email');
$ifu = get_param('ifu');
$rccm = get_param('rccm');
$message = get_param('message_facture', 'Merci pour votre confiance !');
$logo = logo_url();
$clientNom = $vente['client_nom'] ? trim($vente['client_nom'] . ' ' . ($vente['client_prenom'] ?? '')) : 'Client de passage';

if ($annulee) {
    $stamp = ['Annulée', 'is-cancelled'];
} else {
    $stamp = ['payee' => ['Payée', 'is-paid'], 'partielle' => ['Acompte versé', 'is-partial'], 'impayee' => ['Non payée', 'is-unpaid']][$vente['statut_paiement']] ?? ['Payée', 'is-paid'];
}

$qrPayload = "FACTURE {$vente['numero_facture']}\n$entreprise\nDate : " . fmt_datetime($vente['created_at'])
    . "\nTotal : " . number_format($total, 0, ',', ' ') . " $devise\nStatut : " . $stamp[0];
$qrDataUri = QRHelper::generateDataUri($qrPayload, 4, 1);
$remiseLabel = $vente['remise_type'] === 'pourcentage' ? ' (' . rtrim(rtrim(sprintf('%.2f', (float)$vente['remise_valeur']), '0'), '.') . ' %)' : '';
$retour = has_role('admin', 'vendeur') ? 'historique.php' : ($vente['client_id'] ? '../clients/fiche.php?id=' . (int)$vente['client_id'] : '../dashboard.php');
$titreDoc = ($format === 'ticket' ? 'Ticket ' : 'Facture ') . $vente['numero_facture'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titreDoc) ?> - <?= e($entreprise) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('assets/img/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/inter.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<style>
<?php if ($format === 'ticket'): ?>
  @page { size: 80mm auto; margin: 2mm; }
<?php else: ?>
  @page { size: A4; margin: 8mm; }
<?php endif; ?>
</style>
</head>
<body class="inv-page">

<div class="inv-toolbar no-print" <?= $format === 'ticket' ? 'style="max-width:520px;"' : '' ?>>
    <a href="<?= e($retour) ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    <div class="sp-seg sp-seg-sm">
        <a href="?id=<?= $id ?>&amp;format=a4" class="<?= $format === 'a4' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice"></i>A4</a>
        <a href="?id=<?= $id ?>&amp;format=ticket" class="<?= $format === 'ticket' ? 'active' : '' ?>"><i class="fa-solid fa-receipt"></i>Ticket</a>
    </div>
    <span class="ms-auto"></span>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPdf"><i class="fa-solid fa-file-pdf me-1"></i>PDF</button>
    <button type="button" class="btn btn-sm btn-sp-amber" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer</button>
</div>

<?php if ($format === 'ticket'): ?>
<div class="tk-box" id="invoice">
    <div class="tk-center">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="tk-logo"><br><?php endif; ?>
        <div class="tk-shop"><?= e($entreprise) ?></div>
        <?php if ($adresse): ?><div><?= e($adresse) ?></div><?php endif; ?>
        <?php if ($telephone): ?><div>Tél : <?= e($telephone) ?></div><?php endif; ?>
        <?php if ($ifu): ?><div>IFU : <?= e($ifu) ?></div><?php endif; ?>
    </div>
    <div class="tk-sep"></div>
    <div class="tk-row"><span>Ticket</span><span><?= e($vente['numero_facture']) ?></span></div>
    <div class="tk-row"><span>Date</span><span><?= fmt_datetime($vente['created_at']) ?></span></div>
    <div class="tk-row"><span>Vendeur</span><span><?= e($vente['vendeur_nom']) ?></span></div>
    <div class="tk-row"><span>Client</span><span><?= e($clientNom) ?></span></div>
    <?php if ($annulee): ?><div class="tk-center fw-bold mt-1">*** VENTE ANNULÉE ***</div><?php endif; ?>
    <div class="tk-sep"></div>
    <?php foreach ($lignes as $l): ?>
        <div class="tk-item-name"><?= e($l['article_nom'] ?? 'Article supprimé') ?></div>
        <div class="tk-row"><span><?= fmt_number((float)$l['quantite']) ?> <?= e($l['unite'] ?? '') ?> × <?= fmt_number((float)$l['prix_unitaire']) ?></span><span><?= fmt_number((float)$l['sous_total']) ?></span></div>
    <?php endforeach; ?>
    <div class="tk-sep"></div>
    <?php if ((float)$vente['remise_montant'] > 0): ?>
        <div class="tk-row"><span>Sous-total</span><span><?= fmt_number((float)$vente['montant_brut']) ?></span></div>
        <div class="tk-row"><span>Remise<?= e($remiseLabel) ?></span><span>-<?= fmt_number((float)$vente['remise_montant']) ?></span></div>
    <?php endif; ?>
    <div class="tk-row tk-total"><span>TOTAL</span><span><?= fmt_money($total) ?></span></div>
    <?php foreach ($paiements as $p): ?>
        <div class="tk-row"><span><?= e(mode_paiement_label($p['mode_paiement'])) ?><?= date('Y-m-d', strtotime($p['created_at'])) !== date('Y-m-d', strtotime($vente['created_at'])) ? ' (' . fmt_date($p['created_at'], 'd/m') . ')' : '' ?></span><span><?= fmt_number((float)$p['montant']) ?></span></div>
    <?php endforeach; ?>
    <?php if ((float)$vente['monnaie_rendue'] > 0): ?><div class="tk-row"><span>Monnaie rendue</span><span><?= fmt_number((float)$vente['monnaie_rendue']) ?></span></div><?php endif; ?>
    <?php if (!$annulee && $resteAPayer > 0.009): ?><div class="tk-row fw-bold"><span>RESTE À PAYER</span><span><?= fmt_money($resteAPayer) ?></span></div><?php endif; ?>
    <?php if ($vente['observations']): ?><div class="tk-sep"></div><div>Note : <?= e($vente['observations']) ?></div><?php endif; ?>
    <div class="tk-sep"></div>
    <div class="tk-center">
        <img src="<?= $qrDataUri ?>" alt="QR code de vérification" class="tk-qr">
        <div class="mt-1"><?= e($message) ?></div>
        <div>À bientôt !</div>
    </div>
</div>
<?php else: ?>
<div class="inv-box" id="invoice">
    <div class="inv-head">
        <div class="inv-brand">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="inv-logo"><?php else: ?><div class="inv-logo-fallback"><i class="fa-solid fa-briefcase"></i></div><?php endif; ?>
            <div>
                <div class="inv-company"><?= e($entreprise) ?></div>
                <?php if ($slogan): ?><div class="inv-slogan"><?= e($slogan) ?></div><?php endif; ?>
                <div class="inv-small">
                    <?php if ($adresse): ?><?= e($adresse) ?><br><?php endif; ?>
                    <?= e(implode(' · ', array_filter([$telephone ? 'Tél. ' . $telephone : '', $email]))) ?>
                    <?php if ($ifu || $rccm): ?><br><?= e(implode(' · ', array_filter([$ifu ? 'IFU : ' . $ifu : '', $rccm ? 'RCCM : ' . $rccm : '']))) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="inv-title">
            <h1>FACTURE</h1>
            <div class="num">N° <?= e($vente['numero_facture']) ?></div>
            <div class="inv-small">Émise le <?= e(fmt_date_fr($vente['created_at'])) ?> à <?= fmt_date($vente['created_at'], 'H:i') ?></div>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <div class="lbl">Facturé à</div>
            <div class="val"><?= e($clientNom) ?></div>
            <div class="inv-small">
                <?= e(implode(' · ', array_filter([$vente['client_tel'], $vente['client_email']]))) ?>
                <?php $adr = trim(($vente['client_adresse'] ?? '') . ' ' . ($vente['client_ville'] ?? '')); if ($adr !== ''): ?><br><?= e($adr) ?><?php endif; ?>
            </div>
        </div>
        <div class="inv-party">
            <div class="lbl">Informations</div>
            <div class="inv-small" style="color:#241A16;">
                Vendeur : <strong><?= e($vente['vendeur_nom']) ?></strong><br>
                Règlement : <strong><?= e(mode_paiement_label($vente['mode_paiement'])) ?></strong><br>
                Statut : <strong><?= e($stamp[0]) ?></strong>
            </div>
        </div>
    </div>

    <table class="inv-table">
        <thead><tr><th style="width:36px;">#</th><th>Désignation</th><th class="c">Quantité</th><th class="r">Prix unitaire</th><th class="r">Montant</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $i => $l): ?>
            <tr>
                <td style="color:#988D81;"><?= $i + 1 ?></td>
                <td><strong><?= e($l['article_nom'] ?? 'Article supprimé') ?></strong><div class="inv-small"><?= e($l['article_code'] ?? '') ?></div></td>
                <td class="c"><?= fmt_number((float)$l['quantite']) ?> <?= e($l['unite'] ?? '') ?></td>
                <td class="r"><?= fmt_money((float)$l['prix_unitaire']) ?></td>
                <td class="r"><strong><?= fmt_money((float)$l['sous_total']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="inv-bottom">
        <div>
            <div class="inv-words">Arrêtée la présente facture à la somme de : <strong><?= e(montant_en_lettres($total, $devise)) ?></strong> (<?= fmt_money($total) ?>).</div>
            <?php if (!empty($paiements)): ?>
                <div class="mt-3">
                    <div class="inv-party" style="background:#fff;">
                        <div class="lbl">Règlements reçus</div>
                        <?php foreach ($paiements as $p): ?>
                            <div class="d-flex justify-content-between inv-small" style="color:#241A16;">
                                <span class="me-2"><?= fmt_date($p['created_at'], 'd/m/Y H:i') ?> · <?= e(mode_paiement_label($p['mode_paiement'])) ?><?= $p['note'] ? ' — ' . e($p['note']) : '' ?></span>
                                <strong class="text-nowrap"><?= fmt_money((float)$p['montant']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($vente['observations']): ?>
                <div class="inv-small mt-3"><strong>Observations :</strong> <?= e($vente['observations']) ?></div>
            <?php endif; ?>
        </div>
        <table class="inv-totals">
            <?php if ((float)$vente['remise_montant'] > 0): ?>
            <tr><td>Sous-total</td><td><?= fmt_money((float)$vente['montant_brut']) ?></td></tr>
            <tr><td>Remise<?= e($remiseLabel) ?></td><td>- <?= fmt_money((float)$vente['remise_montant']) ?></td></tr>
            <?php endif; ?>
            <tr class="grand"><td>Total</td><td><?= fmt_money($total) ?></td></tr>
            <tr><td>Montant payé</td><td><?= fmt_money((float)$vente['montant_paye']) ?></td></tr>
            <?php if (!$annulee && $resteAPayer > 0.009): ?>
            <tr><td style="color:#B3261E;font-weight:700;">Reste à payer</td><td style="color:#B3261E;font-weight:700;"><?= fmt_money($resteAPayer) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)$vente['monnaie_rendue'] > 0): ?>
            <tr><td>Monnaie rendue</td><td><?= fmt_money((float)$vente['monnaie_rendue']) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="inv-foot">
        <div class="inv-stamp <?= e($stamp[1]) ?>"><?= e($stamp[0]) ?></div>
        <div>
            <div class="inv-thanks"><?= e($message) ?></div>
            <div class="inv-small mt-1">Scannez le code pour vérifier les informations de cette facture.</div>
        </div>
        <img src="<?= $qrDataUri ?>" alt="QR code de vérification" class="inv-qr">
    </div>
    <div class="inv-legal"><?= e($entreprise) ?><?= $adresse ? ' · ' . e($adresse) : '' ?><?= $ifu ? ' · IFU ' . e($ifu) : '' ?> — Document édité le <?= date('d/m/Y à H:i') ?></div>
</div>
<?php endif; ?>

<script>
(function () {
    var format = <?= js_json($format) ?>;
    var fichier = <?= js_json(($format === 'ticket' ? 'Ticket-' : 'Facture-') . $vente['numero_facture'] . '.pdf') ?>;
    function load(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src; s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    var btn = document.getElementById('btnPdf');
    btn.addEventListener('click', function () {
        var label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Génération…';
        var ready = window.html2canvas ? Promise.resolve() : load('<?= asset_url('assets/js/html2canvas.min.js') ?>');
        ready.then(function () { return window.jspdf ? null : load('<?= asset_url('assets/js/jspdf.umd.min.js') ?>'); })
            .then(function () { return html2canvas(document.getElementById('invoice'), { scale: 2, backgroundColor: '#ffffff', useCORS: true }); })
            .then(function (canvas) {
                var jsPDF = window.jspdf.jsPDF, pdf, img;
                if (format === 'ticket') {
                    var w = 80, h = canvas.height * w / canvas.width;
                    pdf = new jsPDF({ unit: 'mm', format: [w, h] });
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, w, h);
                } else {
                    pdf = new jsPDF({ unit: 'mm', format: 'a4' });
                    var margin = 8, iw = 210 - margin * 2, maxH = 297 - margin * 2;
                    var pageHpx = Math.floor(maxH * canvas.width / iw);
                    for (var y = 0, page = 0; y < canvas.height; y += pageHpx, page++) {
                        var slice = document.createElement('canvas');
                        slice.width = canvas.width;
                        slice.height = Math.min(pageHpx, canvas.height - y);
                        slice.getContext('2d').drawImage(canvas, 0, y, canvas.width, slice.height, 0, 0, canvas.width, slice.height);
                        if (page > 0) pdf.addPage();
                        pdf.addImage(slice.toDataURL('image/jpeg', 0.95), 'JPEG', margin, margin, iw, slice.height * iw / canvas.width);
                    }
                }
                pdf.save(fichier);
            })
            .catch(function () { alert('La génération du PDF a échoué. Utilisez « Imprimer » puis « Enregistrer au format PDF ».'); })
            .then(function () { btn.disabled = false; btn.innerHTML = label; });
    });
<?php if ($autoPrint): ?>
    window.addEventListener('load', function () {
        var go = function () { setTimeout(function () { window.print(); }, 250); };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
    });
<?php endif; ?>
})();
</script>
</body>
</html>
