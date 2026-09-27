<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.*, u.full_name AS user_nom FROM courriers c LEFT JOIN users u ON u.id=c.user_id WHERE c.id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash_set('danger', 'Courrier introuvable.');
    redirect('liste.php');
}

$ext = $c['fichier_joint'] ? strtolower(pathinfo($c['fichier_joint'], PATHINFO_EXTENSION)) : null;

$pageTitle = 'Détails courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="sp-card">
            <div class="sp-card-header">
                <h6><?= $c['type']==='entrant' ? '📥 Entrant' : '📤 Sortant' ?> - <?= e($c['numero']) ?></h6>
                <a href="modifier.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
            </div>
            <div class="sp-card-body">
                <h5 class="fw-bold"><?= e($c['objet']) ?></h5>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Date du courrier</dt><dd class="col-8"><?= fmt_date($c['date_courrier']) ?></dd>
                    <dt class="col-4 text-muted">Enregistré le</dt><dd class="col-8"><?= fmt_datetime($c['date_enregistrement']) ?></dd>
                    <dt class="col-4 text-muted"><?= $c['type']==='entrant' ? 'Expéditeur' : 'Destinataire' ?></dt>
                    <dd class="col-8"><?= e($c['type']==='entrant' ? ($c['expediteur'] ?: '-') : ($c['destinataire'] ?: '-')) ?></dd>
                    <dt class="col-4 text-muted">Statut</dt><dd class="col-8"><span class="badge bg-secondary"><?= e(ucfirst(str_replace('_',' ',$c['statut']))) ?></span></dd>
                    <dt class="col-4 text-muted">Enregistré par</dt><dd class="col-8"><?= e($c['user_nom'] ?? '-') ?></dd>
                </dl>
                <?php if ($c['observations']): ?>
                    <hr><p class="text-muted"><?= nl2br(e($c['observations'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="sp-card">
            <div class="sp-card-header"><h6>Pièce jointe</h6></div>
            <div class="sp-card-body text-center">
                <?php if (!$c['fichier_joint']): ?>
                    <p class="text-muted small mb-0">Aucune pièce jointe.</p>
                <?php elseif (in_array($ext, ['jpg','jpeg','png'], true)): ?>
                    <img src="../uploads/courriers/<?= e($c['fichier_joint']) ?>" class="img-fluid rounded border">
                <?php else: ?>
                    <i class="fa-solid fa-file-pdf fa-3x text-danger mb-2"></i>
                    <p><a href="../uploads/courriers/<?= e($c['fichier_joint']) ?>" target="_blank">Ouvrir le document PDF</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
