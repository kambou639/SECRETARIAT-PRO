<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$type = clean_input($_GET['type'] ?? '');
$search = clean_input($_GET['q'] ?? '');

$sql = "SELECT c.*, u.full_name AS user_nom FROM courriers c LEFT JOIN users u ON u.id=c.user_id WHERE 1=1";
$params = [];
if ($type !== '' && in_array($type, ['entrant','sortant'], true)) {
    $sql .= " AND c.type = ?";
    $params[] = $type;
}
if ($search !== '') {
    $sql .= " AND (c.objet LIKE ? OR c.numero LIKE ? OR c.expediteur LIKE ? OR c.destinataire LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql .= " ORDER BY c.date_enregistrement DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courriers = $stmt->fetchAll();

$statutLabels = [
    'recu' => ['Reçu', 'bg-info'], 'en_traitement' => ['En traitement', 'bg-warning text-dark'],
    'traite' => ['Traité', 'bg-success'], 'archive' => ['Archivé', 'bg-secondary'], 'envoye' => ['Envoyé', 'bg-primary'],
];

$pageTitle = 'Courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= e($search) ?>" style="width:220px;">
        <select name="type" class="form-select" style="width:170px;">
            <option value="">Tous types</option>
            <option value="entrant" <?= $type==='entrant'?'selected':'' ?>>Entrant</option>
            <option value="sortant" <?= $type==='sortant'?'selected':'' ?>>Sortant</option>
        </select>
        <button class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouveau courrier</a>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6>Courriers (<?= count($courriers) ?>)</h6>
        <button class="btn btn-sm btn-outline-secondary" data-fullscreen-toggle="fs_courrier"><i class="fa-solid fa-expand"></i></button>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($courriers)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-envelope-open-text"></i><p>Aucun courrier enregistré.</p></div>
        <?php else: ?>
        <table class="table table-sp mb-0">
            <thead><tr><th>Type</th><th>N°</th><th>Objet</th><th>Expéditeur/Dest.</th><th>Date</th><th>Statut</th><th class="text-end no-print">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($courriers as $c): $sl = $statutLabels[$c['statut']] ?? ['-','bg-secondary']; ?>
                <tr>
                    <td><?= $c['type']==='entrant' ? '<i class="fa-solid fa-arrow-down text-success"></i> Entrant' : '<i class="fa-solid fa-arrow-up text-primary"></i> Sortant' ?></td>
                    <td><code><?= e($c['numero']) ?></code></td>
                    <td><?= e($c['objet']) ?></td>
                    <td><?= e($c['type']==='entrant' ? ($c['expediteur'] ?: '-') : ($c['destinataire'] ?: '-')) ?></td>
                    <td><?= fmt_date($c['date_courrier']) ?></td>
                    <td><span class="badge <?= $sl[1] ?>"><?= e($sl[0]) ?></span></td>
                    <td class="text-end no-print">
                        <a href="voir.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Détails"><i class="fa-solid fa-eye"></i></a>
                        <a href="modifier.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" data-confirm="Supprimer ce courrier ?"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
