<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$sql = "SELECT id, code, nom, prix_vente FROM articles WHERE actif = 1";
$params = [];
if ($search !== '') {
    $sql .= " AND (nom LIKE ? OR code LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$preselect = array_filter(explode(',', $_GET['ids'] ?? ($_GET['id'] ?? '')));

$pageTitle = 'Étiquettes QR / Codes-barres';
$activeMenu = 'etiquettes';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 no-print">
    <div class="col-lg-5">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Sélection des articles</h6></div>
            <div class="sp-card-body">
                <form method="get" class="mb-3 d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= e($search) ?>">
                    <button class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
                <form id="labelForm" method="get" action="etiquettes_imprimer.php" target="_blank">
                    <div style="max-height:420px; overflow-y:auto;">
                        <?php foreach ($articles as $a): ?>
                            <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                                <input type="checkbox" name="ids[]" value="<?= $a['id'] ?>" class="form-check-input"
                                    <?= in_array((string)$a['id'], $preselect, true) ? 'checked' : '' ?>>
                                <div class="flex-grow-1">
                                    <div class="small fw-semibold"><?= e($a['nom']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem;"><?= e($a['code']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <label class="form-label small">Type d'étiquette</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="qr">QR Code</option>
                                <option value="barcode">Code-barres</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Exemplaires / article</label>
                            <input type="number" name="copies" class="form-control form-control-sm" value="1" min="1" max="50">
                        </div>
                    </div>
                    <button class="btn btn-sp-amber w-100 mt-3"><i class="fa-solid fa-print me-1"></i>Générer et imprimer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Aperçu rapide</h6></div>
            <div class="sp-card-body">
                <p class="text-muted small">Cochez un ou plusieurs articles à gauche puis cliquez sur « Générer et imprimer » pour ouvrir une planche d'étiquettes prête à découper.</p>
                <?php if (!empty($preselect) && count($preselect) === 1):
                    $one = (int)$preselect[array_key_first($preselect)];
                    $stmt2 = $pdo->prepare('SELECT * FROM articles WHERE id=?'); $stmt2->execute([$one]); $art = $stmt2->fetch();
                    if ($art): ?>
                    <div class="border rounded p-3 text-center" style="width:220px;">
                        <img src="code_image.php?id=<?= $one ?>&type=qr" alt="QR" style="width:120px;height:120px;">
                        <div class="fw-semibold small mt-2"><?= e($art['nom']) ?></div>
                        <div class="text-muted small"><?= e($art['code']) ?></div>
                        <div class="fw-bold"><?= fmt_money((float)$art['prix_vente']) ?></div>
                        <img src="code_image.php?id=<?= $one ?>&type=barcode" alt="Code-barres" class="mt-2" style="max-width:100%;">
                    </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
