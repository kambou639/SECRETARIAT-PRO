<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$type = in_array($_GET['type'] ?? '', ['particulier', 'entreprise'], true) ? $_GET['type'] : '';
$filtre = in_array($_GET['filtre'] ?? '', ['credit', 'nouveaux'], true) ? $_GET['filtre'] : '';

$sql = "SELECT c.*,
        (SELECT COUNT(*) FROM ventes v WHERE v.client_id = c.id AND v.statut = 'validee') AS nb_achats,
        (SELECT COALESCE(SUM(montant_total), 0) FROM ventes v WHERE v.client_id = c.id AND v.statut = 'validee') AS total_achats,
        (SELECT COALESCE(SUM(montant_total - montant_paye), 0) FROM ventes v WHERE v.client_id = c.id AND v.statut = 'validee' AND v.statut_paiement <> 'payee') AS solde_du,
        (SELECT MAX(created_at) FROM ventes v WHERE v.client_id = c.id AND v.statut = 'validee') AS dernier_achat
        FROM clients c WHERE 1=1";
$params = [];
if ($search !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR c.email LIKE ? OR c.ville LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($type !== '') { $sql .= " AND c.type = ?"; $params[] = $type; }
if ($filtre === 'nouveaux') { $sql .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
$sql .= " ORDER BY c.nom ASC, c.prenom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
if ($filtre === 'credit') {
    $clients = array_values(array_filter($clients, fn($c) => (float)$c['solde_du'] > 0.009));
}

$stats = $pdo->query("SELECT COUNT(*) total, SUM(type = 'entreprise') entreprises, SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) nouveaux FROM clients")->fetch();
$nbDebiteurs = (int)$pdo->query("SELECT COUNT(DISTINCT client_id) FROM ventes WHERE statut = 'validee' AND statut_paiement <> 'payee' AND client_id IS NOT NULL")->fetchColumn();
$entreprise = get_param('nom_entreprise', APP_NAME);
$voitVentes = has_role('admin', 'vendeur');

$pageTitle = 'Clients';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><a href="liste.php" class="sp-stat tone-primary d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Clients</span><span class="sp-stat-icon"><i class="fa-solid fa-address-book"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['total'] ?>"><?= (int)$stats['total'] ?></div><div class="sp-stat-sub"><?= (int)$stats['total'] - (int)$stats['entreprises'] ?> particulier(s)</div></a></div>
    <div class="col-6 col-xl-3"><a href="?type=entreprise" class="sp-stat tone-info d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Entreprises</span><span class="sp-stat-icon"><i class="fa-solid fa-building"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['entreprises'] ?>"><?= (int)$stats['entreprises'] ?></div><div class="sp-stat-sub">organisations, écoles, ONG…</div></a></div>
    <div class="col-6 col-xl-3"><a href="?filtre=nouveaux" class="sp-stat tone-success d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Nouveaux</span><span class="sp-stat-icon"><i class="fa-solid fa-user-plus"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['nouveaux'] ?>"><?= (int)$stats['nouveaux'] ?></div><div class="sp-stat-sub">ces 30 derniers jours</div></a></div>
    <div class="col-6 col-xl-3"><a href="?filtre=credit" class="sp-stat tone-danger d-block text-reset"><div class="sp-stat-top"><span class="sp-stat-label">Avec un solde dû</span><span class="sp-stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span></div><div class="sp-stat-value" data-countup="<?= $nbDebiteurs ?>"><?= $nbDebiteurs ?></div><div class="sp-stat-sub">ventes à crédit en cours</div></a></div>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon tone-primary"><i class="fa-solid fa-address-book"></i></span>Fichier clients <span class="badge bg-secondary" id="clientsCount"><?= count($clients) ?></span></h6>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form method="get" class="sp-input-icon" style="width:260px;" data-no-loading>
                <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="q" id="clientSearch" class="form-control form-control-sm" placeholder="Nom, téléphone, ville…" value="<?= e($search) ?>" data-sp-filter="#tableClients" aria-label="Rechercher un client">
            </form>
            <div class="sp-seg sp-seg-sm" data-sp-seg>
                <a href="?<?= e(http_build_query(array_filter(['q' => $search]))) ?>" class="<?= $type === '' && !$filtre ? 'active' : '' ?>">Tous</a>
                <a href="?<?= e(http_build_query(array_filter(['q' => $search, 'type' => 'particulier']))) ?>" class="<?= $type === 'particulier' ? 'active' : '' ?>"><i class="fa-solid fa-user"></i>Particuliers</a>
                <a href="?<?= e(http_build_query(array_filter(['q' => $search, 'type' => 'entreprise']))) ?>" class="<?= $type === 'entreprise' ? 'active' : '' ?>"><i class="fa-solid fa-building"></i>Entreprises</a>
            </div>
            <a href="ajouter.php" class="btn btn-sm btn-sp-amber"><i class="fa-solid fa-user-plus me-1"></i>Nouveau client</a>
        </div>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($clients)): ?>
            <?= empty_state('fa-address-book', 'Aucun client', ($search || $type || $filtre) ? 'Aucun client ne correspond à ces critères.' : 'Enregistrez vos clients pour suivre leurs achats, crédits et rendez-vous.', '<a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-user-plus me-1"></i>Nouveau client</a>') ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sp mb-0" id="tableClients" data-sp-sort>
            <thead><tr>
                <th data-sort="text">Client</th><th class="d-none d-md-table-cell">Contact</th><th class="d-none d-xl-table-cell" data-sort="text">Ville</th>
                <th class="text-center" data-sort="num">Achats</th><th class="text-end" data-sort="num">Total dépensé</th><th class="text-end d-none d-lg-table-cell" data-sort="num">Solde dû</th>
                <th class="text-end no-print">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($clients as $c):
                $nom = trim($c['nom'] . ' ' . ($c['prenom'] ?? ''));
                $wa = whatsapp_url($c['telephone'], 'Bonjour ' . (($c['prenom'] ?? '') ?: $c['nom']) . ', ');
                $tel = tel_href($c['telephone']); ?>
                <tr>
                    <td data-value="<?= e($nom) ?>">
                        <a href="fiche.php?id=<?= (int)$c['id'] ?>" class="sp-cell-flex text-reset">
                            <?= avatar_html($nom) ?>
                            <span style="min-width:0;">
                                <span class="sp-cell-title d-block"><?= e($nom) ?></span>
                                <span class="sp-cell-sub d-block"><i class="fa-solid <?= $c['type'] === 'entreprise' ? 'fa-building' : 'fa-user' ?> me-1"></i><?= $c['type'] === 'entreprise' ? 'Entreprise' : 'Particulier' ?><?= $c['dernier_achat'] ? ' · dernier achat ' . e(fmt_relative($c['dernier_achat'])) : '' ?></span>
                            </span>
                        </a>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($c['telephone']): ?><div class="text-nowrap"><i class="fa-solid fa-phone me-1 text-muted small"></i><?= e($c['telephone']) ?></div><?php endif; ?>
                        <?php if ($c['email']): ?><div class="sp-cell-sub text-truncate" style="max-width:220px;"><i class="fa-solid fa-envelope me-1"></i><?= e($c['email']) ?></div><?php endif; ?>
                        <?php if (!$c['telephone'] && !$c['email']): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="d-none d-xl-table-cell"><?= e($c['ville'] ?: '—') ?></td>
                    <td class="text-center" data-value="<?= (int)$c['nb_achats'] ?>"><?= (int)$c['nb_achats'] ?></td>
                    <td class="text-end fw-semibold tabular text-nowrap" data-value="<?= (float)$c['total_achats'] ?>"><?= fmt_money((float)$c['total_achats']) ?></td>
                    <td class="text-end d-none d-lg-table-cell text-nowrap" data-value="<?= (float)$c['solde_du'] ?>">
                        <?php if ((float)$c['solde_du'] > 0.009): ?><span class="sp-badge is-danger"><?= fmt_money((float)$c['solde_du']) ?></span><?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                    </td>
                    <td class="td-actions no-print">
                        <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-whatsapp" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
                        <?php if ($tel): ?><a href="<?= e($tel) ?>" class="btn btn-sm btn-icon btn-outline-secondary d-none d-md-inline-flex" title="Appeler"><i class="fa-solid fa-phone"></i></a><?php endif; ?>
                        <a href="fiche.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Fiche"><i class="fa-solid fa-eye"></i></a>
                        <a href="modifier.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-danger" title="Supprimer" data-method="post"
                           data-confirm="Supprimer le client « <?= e($nom) ?> » ? Impossible s'il a déjà acheté." data-confirm-type="danger"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var s = document.getElementById('clientSearch');
    if (s) s.addEventListener('sp:filtered', function (e) { document.getElementById('clientsCount').textContent = e.detail.visible; });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
