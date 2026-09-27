<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$vues = [
    'tous'    => ['Tous', 'fa-layer-group', '1=1'],
    'entrant' => ['Entrants', 'fa-arrow-down', "c.type = 'entrant'"],
    'sortant' => ['Sortants', 'fa-arrow-up', "c.type = 'sortant'"],
    'attente' => ['À traiter', 'fa-hourglass-half', "c.statut IN ('recu','en_traitement')"],
    'traite'  => ['Traités / envoyés', 'fa-check', "c.statut IN ('traite','envoye')"],
    'archive' => ['Archivés', 'fa-box-archive', "c.statut = 'archive'"],
];
// Compatibilité avec l'ancien paramètre ?type=entrant|sortant
$vue = $_GET['vue'] ?? ($_GET['type'] ?? 'tous');
if (!isset($vues[$vue])) $vue = 'tous';
$search = clean_input($_GET['q'] ?? '');
$du = valid_date($_GET['du'] ?? null, '');
$au = valid_date($_GET['au'] ?? null, '');

$where = [$vues[$vue][2]];
$params = [];
if ($search !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $where[] = "(c.objet LIKE ? OR c.numero LIKE ? OR c.expediteur LIKE ? OR c.destinataire LIKE ? OR c.observations LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($du !== '') { $where[] = 'c.date_courrier >= ?'; $params[] = $du; }
if ($au !== '') { $where[] = 'c.date_courrier <= ?'; $params[] = $au; }
$whereSql = implode(' AND ', $where);

$counts = $pdo->query("SELECT COUNT(*) tous, SUM(type='entrant') entrant, SUM(type='sortant') sortant,
    SUM(statut IN ('recu','en_traitement')) attente, SUM(statut IN ('traite','envoye')) traite, SUM(statut='archive') archive FROM courriers")->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM courriers c WHERE $whereSql");
$stmt->execute($params);
$pg = paginate((int)$stmt->fetchColumn(), (int)($_GET['page'] ?? 1), 30);
$stmt = $pdo->prepare("SELECT c.*, u.full_name AS user_nom FROM courriers c LEFT JOIN users u ON u.id = c.user_id
    WHERE $whereSql ORDER BY c.date_enregistrement DESC, c.id DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$courriers = $stmt->fetchAll();

$statuts = ['recu' => 'Reçu', 'en_traitement' => 'En traitement', 'traite' => 'Traité', 'envoye' => 'Envoyé', 'archive' => 'Archivé'];

$pageTitle = 'Courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-page-head">
    <div class="sp-seg" data-sp-seg>
        <?php foreach ($vues as $k => [$label, $icon]): ?>
            <a href="?<?= e(http_build_query(array_filter(['vue' => $k === 'tous' ? '' : $k, 'q' => $search, 'du' => $du, 'au' => $au]))) ?>" class="<?= $vue === $k ? 'active' : '' ?>"><i class="fa-solid <?= $icon ?>"></i><?= e($label) ?> <span class="count"><?= (int)$counts[$k] ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="ajouter.php?type=entrant" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-down me-1 text-success"></i>Courrier reçu</a>
        <a href="ajouter.php?type=sortant" class="btn btn-sp-amber"><i class="fa-solid fa-arrow-up me-1"></i>Courrier envoyé</a>
    </div>
</div>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" class="row g-2 align-items-end" data-no-loading>
            <?php if ($vue !== 'tous'): ?><input type="hidden" name="vue" value="<?= e($vue) ?>"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label small" for="cq">Recherche</label>
                <div class="sp-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="cq" name="q" class="form-control" placeholder="Objet, numéro, expéditeur, destinataire…" value="<?= e($search) ?>"></div>
            </div>
            <div class="col-6 col-md-2"><label class="form-label small" for="cdu">Du</label><input type="date" id="cdu" name="du" class="form-control" value="<?= e($du) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small" for="cau">Au</label><input type="date" id="cau" name="au" class="form-control" value="<?= e($au) ?>"></div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sp-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
                <?php if ($search || $du || $au): ?><a href="?<?= e(http_build_query(array_filter(['vue' => $vue === 'tous' ? '' : $vue]))) ?>" class="btn btn-outline-secondary" title="Effacer"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-envelope-open-text"></i></span><?= e($vues[$vue][0]) ?></h6>
        <button class="btn btn-sm btn-outline-secondary" data-fullscreen-toggle="fs_courrier" title="Plein écran"><i class="fa-solid fa-expand"></i></button>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($courriers)): ?>
            <?= empty_state('fa-envelope-open-text', 'Aucun courrier', 'Aucun courrier ne correspond à cette vue.', '<a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Enregistrer un courrier</a>') ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sp mb-0" data-sp-sort>
            <thead><tr><th>Type</th><th data-sort="text">Objet</th><th class="d-none d-lg-table-cell" data-sort="text">Correspondant</th><th data-sort="text">Date</th><th>Statut</th><th class="text-end no-print">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($courriers as $c):
                $entrant = $c['type'] === 'entrant';
                $corresp = $entrant ? $c['expediteur'] : $c['destinataire'];
                $age = days_since($c['date_enregistrement']);
                $enAttente = in_array($c['statut'], ['recu', 'en_traitement'], true); ?>
                <tr data-courrier="<?= (int)$c['id'] ?>">
                    <td><span class="sp-card-icon <?= $entrant ? 'tone-success' : 'tone-primary' ?>" title="<?= $entrant ? 'Entrant' : 'Sortant' ?>"><i class="fa-solid <?= $entrant ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></span></td>
                    <td data-value="<?= e($c['objet']) ?>">
                        <a href="voir.php?id=<?= (int)$c['id'] ?>" class="sp-cell-title d-block"><?= e($c['objet']) ?></a>
                        <div class="sp-cell-sub"><code><?= e($c['numero']) ?></code>
                            <?php if ($c['fichier_joint']): ?> · <i class="fa-solid fa-paperclip" title="Pièce jointe"></i><?php endif; ?>
                            <?php if ($c['observations']): ?> · <span class="text-truncate d-inline-block align-bottom" style="max-width:260px;"><?= e($c['observations']) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell" data-value="<?= e($corresp ?? '') ?>"><?= e($corresp ?: '—') ?></td>
                    <td class="text-nowrap" data-value="<?= e($c['date_courrier']) ?>"><?= fmt_date($c['date_courrier']) ?>
                        <div class="sp-cell-sub"><?= $enAttente && $age > 3 ? '<span class="text-danger fw-semibold">en attente depuis ' . $age . ' j</span>' : e(fmt_relative($c['date_enregistrement'])) ?></div></td>
                    <td>
                        <div class="dropdown">
                            <button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" title="Changer le statut" data-status-btn>
                                <?= badge_html(statut_courrier_meta($c['statut'])) ?> <i class="fa-solid fa-caret-down small text-muted"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Changer le statut</h6></li>
                                <?php foreach ($statuts as $k => $label): if (!$entrant && $k === 'recu') continue; if ($entrant && $k === 'envoye') continue; ?>
                                    <li><button type="button" class="dropdown-item<?= $c['statut'] === $k ? ' active' : '' ?>" data-set-statut="<?= $k ?>"><i class="fa-solid <?= e(statut_courrier_meta($k)[2]) ?>"></i><?= e($label) ?></button></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </td>
                    <td class="td-actions no-print">
                        <a href="voir.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir"><i class="fa-solid fa-eye"></i></a>
                        <a href="modifier.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-icon btn-outline-danger" title="Supprimer" data-method="post" data-confirm-type="danger"
                           data-confirm="Supprimer définitivement le courrier « <?= e($c['objet']) ?> » et sa pièce jointe ?"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= pagination_html($pg, 'courriers') ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        var item = e.target.closest('[data-set-statut]');
        if (!item) return;
        var row = item.closest('tr[data-courrier]');
        var statut = item.getAttribute('data-set-statut');
        SP.fetchJSON(SP.url('api/courrier_statut.php'), { body: { id: parseInt(row.getAttribute('data-courrier'), 10), statut: statut } })
            .then(function (data) {
                if (!data.success) { SP.toast({ type: 'danger', message: data.message || 'Modification impossible.' }); return; }
                var btn = row.querySelector('[data-status-btn]');
                btn.innerHTML = data.badge + ' <i class="fa-solid fa-caret-down small text-muted"></i>';
                row.querySelectorAll('[data-set-statut]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-set-statut') === statut); });
                row.classList.remove('is-new'); void row.offsetWidth; row.classList.add('is-new');
                SP.toast({ type: 'success', title: 'Statut mis à jour', message: data.label, duration: 2500 });
                if (SP.notif) SP.notif.invalidate();
            })
            .catch(function () { SP.toast({ type: 'danger', message: 'Erreur réseau.' }); });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
