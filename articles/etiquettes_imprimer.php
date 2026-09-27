<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$ids = array_values(array_filter(array_map('intval', (array)($_GET['ids'] ?? []))));
$type = in_array($_GET['type'] ?? '', ['qr', 'barcode', 'both'], true) ? $_GET['type'] : 'qr';
$taille = in_array($_GET['taille'] ?? '', ['sm', 'md', 'lg'], true) ? $_GET['taille'] : 'md';
$copies = max(1, min(50, (int)($_GET['copies'] ?? 1)));
$avecPrix = !isset($_GET['type']) || !empty($_GET['prix']);
$avecBoutique = !empty($_GET['boutique']);

if (empty($ids)) {
    flash_set('warning', 'Aucun article sélectionné pour l\'impression des étiquettes.');
    redirect('etiquettes.php');
}

$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id IN ($in) ORDER BY nom");
$stmt->execute($ids);
$articles = $stmt->fetchAll();
$boutique = get_param('nom_entreprise', APP_NAME);
$qrSize = ['sm' => 20, 'md' => 26, 'lg' => 32][$taille];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Étiquettes (<?= count($articles) * $copies ?>) - <?= e($boutique) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('assets/img/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/inter.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<style>
  @page { size: A4; margin: 8mm; }
  body{ background:var(--sp-bg); }
  .sheet{ max-width:210mm; margin:0 auto 2rem; background:#fff; padding:8mm; box-shadow:var(--sp-shadow-3); border-radius:4px; }
  .label-item img.qr{ image-rendering:pixelated; }
  @media print{ .sheet{ box-shadow:none; padding:0; margin:0; max-width:none; } body{ background:#fff; } }
</style>
</head>
<body class="inv-page">
<div class="inv-toolbar no-print" style="max-width:210mm;">
    <a href="etiquettes.php?ids=<?= e(implode(',', $ids)) ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Modifier la sélection</a>
    <span class="small text-muted"><?= count($articles) ?> article(s) × <?= $copies ?> = <strong><?= count($articles) * $copies ?></strong> étiquette(s)</span>
    <span class="ms-auto"></span>
    <button class="btn btn-sm btn-sp-amber" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer</button>
</div>
<div class="sheet">
    <div class="label-sheet">
        <?php foreach ($articles as $a): for ($i = 0; $i < $copies; $i++): ?>
            <div class="label-item size-<?= $taille ?>">
                <?php if ($avecBoutique): ?><div class="lb-shop"><?= e($boutique) ?></div><?php endif; ?>
                <?php if ($type === 'qr' || $type === 'both'): ?>
                    <img class="qr" src="code_image.php?id=<?= (int)$a['id'] ?>&amp;type=qr" alt="QR <?= e($a['code']) ?>" style="width:<?= $qrSize ?>mm;height:<?= $qrSize ?>mm;">
                <?php endif; ?>
                <?php if ($type === 'barcode' || $type === 'both'): ?>
                    <img src="code_image.php?id=<?= (int)$a['id'] ?>&amp;type=barcode" alt="Code-barres <?= e($a['code']) ?>" style="max-width:100%;display:block;margin:1mm auto 0;">
                <?php endif; ?>
                <div class="lb-name"><?= e($a['nom']) ?></div>
                <div class="lb-code"><?= e($a['code']) ?></div>
                <?php if ($avecPrix): ?><div class="lb-price"><?= fmt_money((float)$a['prix_vente']) ?><?= $a['type'] === 'service' ? ' / ' . e($a['unite']) : '' ?></div><?php endif; ?>
            </div>
        <?php endfor; endforeach; ?>
    </div>
</div>
<script>
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>
