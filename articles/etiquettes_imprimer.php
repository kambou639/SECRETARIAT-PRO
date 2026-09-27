<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$ids = array_filter(array_map('intval', $_GET['ids'] ?? []));
$type = ($_GET['type'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';
$copies = max(1, min(50, (int)($_GET['copies'] ?? 1)));

if (empty($ids)) {
    die('Aucun article sélectionné. <a href="etiquettes.php">Retour</a>');
}

$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id IN ($in) ORDER BY nom");
$stmt->execute($ids);
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Impression étiquettes</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/app.css">
<style>
  body{ padding:10mm; }
  @media print { body{ padding:0; } }
</style>
</head>
<body onload="window.print()">
<div class="mb-3 no-print">
    <button class="btn btn-sp-amber btn-sm" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer</button>
    <a href="etiquettes.php" class="btn btn-outline-secondary btn-sm">Retour</a>
</div>
<div class="label-sheet">
    <?php foreach ($articles as $a): for ($i = 0; $i < $copies; $i++): ?>
        <div class="label-item">
            <?php if ($type === 'qr'): ?>
                <img src="code_image.php?id=<?= $a['id'] ?>&type=qr" style="width:26mm;height:26mm;">
            <?php else: ?>
                <img src="code_image.php?id=<?= $a['id'] ?>&type=barcode" style="max-width:100%;">
            <?php endif; ?>
            <div style="font-size:9px;font-weight:600; margin-top:1mm;"><?= e($a['nom']) ?></div>
            <div style="font-size:8px;"><?= e($a['code']) ?></div>
            <div style="font-size:10px;font-weight:700;"><?= fmt_money((float)$a['prix_vente']) ?></div>
        </div>
    <?php endfor; endforeach; ?>
</div>
</body>
</html>
