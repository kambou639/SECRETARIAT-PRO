<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$dateDebut = clean_input($_GET['debut'] ?? date('Y-m-d'));
$dateFin = clean_input($_GET['fin'] ?? date('Y-m-d', strtotime($dateDebut . ' +6 days')));

$stmt = $pdo->prepare("
    SELECT r.*, c.nom AS client_nom FROM rendezvous r
    LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.date_rdv BETWEEN ? AND ? AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC, r.heure_debut ASC
");
$stmt->execute([$dateDebut, $dateFin]);
$rdvs = $stmt->fetchAll();

$parJour = [];
foreach ($rdvs as $r) {
    $parJour[$r['date_rdv']][] = $r;
}

$statutColors = ['planifie' => 'bg-info', 'confirme' => 'bg-success', 'termine' => 'bg-secondary', 'annule' => 'bg-danger'];

$pageTitle = 'Agenda';
$activeMenu = 'agenda';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <form class="d-flex gap-2 align-items-center" method="get">
        <label class="small fw-semibold mb-0">Du</label>
        <input type="date" name="debut" class="form-control" value="<?= e($dateDebut) ?>">
        <label class="small fw-semibold mb-0">Au</label>
        <input type="date" name="fin" class="form-control" value="<?= e($dateFin) ?>">
        <button class="btn btn-outline-secondary"><i class="fa-solid fa-filter"></i></button>
    </form>
    <a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouveau rendez-vous</a>
</div>

<?php if (empty($parJour)): ?>
    <div class="sp-card"><div class="sp-empty-state"><i class="fa-solid fa-calendar-xmark"></i><p>Aucun rendez-vous sur cette période.</p></div></div>
<?php else: ?>
    <?php foreach ($parJour as $date => $items): ?>
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <h6><?= e(fmt_date_fr_long($date)) ?></h6>
                <span class="badge bg-secondary"><?= count($items) ?> RDV</span>
            </div>
            <div class="sp-card-body p-0">
                <table class="table table-sp mb-0">
                    <thead><tr><th style="width:110px;">Heure</th><th>Titre</th><th>Client / Contact</th><th>Lieu</th><th>Statut</th><th class="text-end no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $r): ?>
                        <tr>
                            <td><?= substr($r['heure_debut'],0,5) ?><?= $r['heure_fin'] ? ' - ' . substr($r['heure_fin'],0,5) : '' ?></td>
                            <td class="fw-semibold"><?= e($r['titre']) ?></td>
                            <td><?= e($r['client_nom'] ?? $r['contact_nom'] ?? '-') ?></td>
                            <td><?= e($r['lieu'] ?: '-') ?></td>
                            <td><span class="badge <?= $statutColors[$r['statut']] ?? 'bg-secondary' ?>"><?= e(ucfirst($r['statut'])) ?></span></td>
                            <td class="text-end no-print">
                                <a href="modifier.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                                <a href="supprimer.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Annuler ce rendez-vous ?"><i class="fa-solid fa-xmark"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
