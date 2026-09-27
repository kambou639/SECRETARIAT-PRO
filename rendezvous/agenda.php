<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$vue = ($_GET['vue'] ?? 'mois') === 'liste' ? 'liste' : 'mois';
$avecAnnules = !empty($_GET['annules']);
$aujourdhui = date('Y-m-d');

if ($vue === 'mois') {
    $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['mois'] ?? '') ? $_GET['mois'] : date('Y-m');
    $premierTs = strtotime($mois . '-01');
    $debut = date('Y-m-d', strtotime('-' . ((int)date('N', $premierTs) - 1) . ' days', $premierTs));
    $finMoisTs = strtotime(date('Y-m-t', $premierTs));
    $fin = date('Y-m-d', strtotime('+' . (7 - (int)date('N', $finMoisTs)) . ' days', $finMoisTs));
    $moisPrec = date('Y-m', strtotime('-1 month', $premierTs));
    $moisSuiv = date('Y-m', strtotime('+1 month', $premierTs));
} else {
    $debut = valid_date($_GET['debut'] ?? null, $aujourdhui);
    $fin = valid_date($_GET['fin'] ?? null, date('Y-m-d', strtotime($debut . ' +13 days')));
    if ($fin < $debut) [$debut, $fin] = [$fin, $debut];
}

$sql = "SELECT r.*, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel
    FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.date_rdv BETWEEN ? AND ?" . ($avecAnnules ? '' : " AND r.statut <> 'annule'") . "
    ORDER BY r.date_rdv ASC, r.heure_debut ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$debut, $fin]);
$rdvs = $stmt->fetchAll();
$parJour = [];
foreach ($rdvs as $r) {
    $parJour[$r['date_rdv']][] = $r;
}

// Panneau latéral : aujourd'hui et prochains jours
$stmt = $pdo->prepare("SELECT r.*, c.nom AS client_nom, c.prenom AS client_prenom FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.date_rdv = ? AND r.statut <> 'annule' ORDER BY r.heure_debut");
$stmt->execute([$aujourdhui]);
$rdvJour = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT r.*, c.nom AS client_nom FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id
    WHERE r.date_rdv > ? AND r.date_rdv <= DATE_ADD(?, INTERVAL 7 DAY) AND r.statut IN ('planifie','confirme') ORDER BY r.date_rdv, r.heure_debut LIMIT 6");
$stmt->execute([$aujourdhui, $aujourdhui]);
$prochains = $stmt->fetchAll();
$stats = $pdo->query("SELECT SUM(statut = 'planifie') planifies, SUM(statut = 'confirme') confirmes FROM rendezvous WHERE date_rdv >= CURDATE()")->fetch();

$maintenant = date('H:i:s');
$qsBase = array_filter(['vue' => $vue === 'liste' ? 'liste' : null, 'annules' => $avecAnnules ? 1 : null]);

$pageTitle = 'Agenda';
$activeMenu = 'agenda';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-page-head">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="sp-seg" data-sp-seg>
            <a href="?<?= e(http_build_query(array_filter(['annules' => $avecAnnules ? 1 : null]))) ?>" class="<?= $vue === 'mois' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i>Mois</a>
            <a href="?<?= e(http_build_query(array_filter(['vue' => 'liste', 'annules' => $avecAnnules ? 1 : null]))) ?>" class="<?= $vue === 'liste' ? 'active' : '' ?>"><i class="fa-solid fa-list"></i>Liste</a>
        </div>
        <?php if ($vue === 'mois'): ?>
            <div class="btn-group">
                <a href="?<?= e(http_build_query($qsBase + ['mois' => $moisPrec])) ?>" class="btn btn-outline-secondary" title="Mois précédent"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="?<?= e(http_build_query($qsBase)) ?>" class="btn btn-outline-secondary">Aujourd'hui</a>
                <a href="?<?= e(http_build_query($qsBase + ['mois' => $moisSuiv])) ?>" class="btn btn-outline-secondary" title="Mois suivant"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            <h2 class="mb-0 ms-2" style="font-size:1.45rem;"><?= e(ucfirst(mois_fr((int)date('n', $premierTs)))) ?> <?= date('Y', $premierTs) ?></h2>
        <?php else: ?>
            <form method="get" class="d-flex gap-2 align-items-center flex-wrap" data-no-loading>
                <input type="hidden" name="vue" value="liste">
                <?php if ($avecAnnules): ?><input type="hidden" name="annules" value="1"><?php endif; ?>
                <input type="date" name="debut" class="form-control w-auto" value="<?= e($debut) ?>" aria-label="Du">
                <span class="text-muted">au</span>
                <input type="date" name="fin" class="form-control w-auto" value="<?= e($fin) ?>" aria-label="Au">
                <button class="btn btn-outline-secondary" title="Filtrer" aria-label="Filtrer"><i class="fa-solid fa-filter"></i></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="showAnnules" <?= $avecAnnules ? 'checked' : '' ?> onchange="var u=new URL(location.href); if(this.checked) u.searchParams.set('annules','1'); else u.searchParams.delete('annules'); location.href=u.toString();">
            <label class="form-check-label small" for="showAnnules">Afficher les annulés</label>
        </div>
        <a href="ajouter.php" class="btn btn-sp-amber text-nowrap"><i class="fa-solid fa-calendar-plus me-1"></i>Nouveau<span class="d-none d-sm-inline"> rendez-vous</span></a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <?php if ($vue === 'mois'): ?>
        <div class="sp-card sp-cal">
            <div class="sp-cal-head"><?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $j): ?><div><?= $j ?></div><?php endforeach; ?></div>
            <div class="sp-cal-grid">
                <?php for ($d = strtotime($debut); $d <= strtotime($fin); $d = strtotime('+1 day', $d)):
                    $iso = date('Y-m-d', $d);
                    $evts = $parJour[$iso] ?? [];
                    $cls = ['sp-cal-day'];
                    if (date('Y-m', $d) !== $mois) $cls[] = 'is-other';
                    if ($iso === $aujourdhui) $cls[] = 'is-today';
                    if ((int)date('N', $d) >= 6) $cls[] = 'is-weekend'; ?>
                    <div class="<?= implode(' ', $cls) ?>" data-date="<?= $iso ?>" data-count="<?= count($evts) ?>" role="button" tabindex="0" aria-label="<?= e(fmt_date_fr_long($iso)) ?> : <?= count($evts) ?> rendez-vous">
                        <span class="sp-cal-num"><?= date('j', $d) ?></span>
                        <a href="ajouter.php?date=<?= $iso ?>" class="sp-cal-add" title="Nouveau rendez-vous le <?= e(fmt_date($iso)) ?>" aria-label="Ajouter"><i class="fa-solid fa-plus"></i></a>
                        <?php foreach (array_slice($evts, 0, 3) as $r): $who = rdv_personne($r); ?>
                            <a href="modifier.php?id=<?= (int)$r['id'] ?>" class="sp-cal-event st-<?= e($r['statut']) ?>" title="<?= e(substr($r['heure_debut'], 0, 5) . ' — ' . $r['titre'] . ($who ? ' · ' . $who : '') . ($r['lieu'] ? ' · ' . $r['lieu'] : '')) ?>">
                                <span class="t"><?= substr($r['heure_debut'], 0, 5) ?></span><span class="ttl"><?= e($r['titre']) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($evts) > 3): ?><a class="sp-cal-more d-block" href="?<?= e(http_build_query(['vue' => 'liste', 'debut' => $iso, 'fin' => $iso] + ($avecAnnules ? ['annules' => 1] : []))) ?>">+<?= count($evts) - 3 ?> autre(s)</a><?php endif; ?>
                        <?php if ($evts): ?><div class="sp-cal-dots"><?php foreach (array_slice($evts, 0, 5) as $r): ?><i class="st-<?= e($r['statut']) ?>"></i><?php endforeach; ?></div><?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="sp-legend mt-2">
            <span><i style="background:var(--sp-info)"></i>Planifié</span>
            <span><i style="background:var(--sp-success)"></i>Confirmé</span>
            <span><i style="background:var(--sp-text-subtle)"></i>Terminé</span>
            <span><i style="background:var(--sp-danger)"></i>Annulé</span>
            <span class="ms-auto"><i class="fa-regular fa-hand-pointer" style="width:auto;height:auto;background:none;"></i><span class="d-none d-md-inline">Cliquez sur un jour pour y ajouter un rendez-vous</span><span class="d-md-none">Touchez un jour pour voir ou ajouter ses rendez-vous</span></span>
        </div>
        <?php else: ?>
            <?php if (empty($parJour)): ?>
                <div class="sp-card"><?= empty_state('fa-calendar-xmark', 'Aucun rendez-vous', 'Aucun rendez-vous sur cette période.', '<a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-calendar-plus me-1"></i>Planifier un rendez-vous</a>') ?></div>
            <?php endif; ?>
            <?php foreach ($parJour as $date => $items): ?>
                <div class="sp-card sp-agenda-day sp-reveal">
                    <div class="sp-card-header">
                        <h6><span class="sp-mini-cal me-1"><span class="m"><?= e(mois_fr((int)date('n', strtotime($date)), true)) ?></span><span class="d"><?= date('j', strtotime($date)) ?></span></span><?= e(fmt_date_fr_long($date)) ?><?= $date === $aujourdhui ? ' <span class="sp-badge is-primary">Aujourd\'hui</span>' : '' ?></h6>
                        <span class="badge bg-secondary"><?= count($items) ?> RDV</span>
                    </div>
                    <div class="sp-card-body p-0">
                        <?php foreach ($items as $r):
                            $who = rdv_personne($r);
                            $telRdv = $r['contact_telephone'] ?: $r['client_tel'];
                            $enCours = $date === $aujourdhui && $r['heure_debut'] <= $maintenant && ($r['heure_fin'] ?: date('H:i:s', strtotime($r['heure_debut']) + 1800)) >= $maintenant; ?>
                            <div class="sp-rdv st-<?= e($r['statut']) ?><?= $enCours ? ' is-now' : '' ?>" data-rdv="<?= (int)$r['id'] ?>">
                                <div class="rdv-time"><?= substr($r['heure_debut'], 0, 5) ?><?php if ($r['heure_fin']): ?><small><?= substr($r['heure_fin'], 0, 5) ?></small><?php endif; ?></div>
                                <div class="rdv-main">
                                    <div class="rdv-title"><?= e($r['titre']) ?><?= $enCours ? ' <span class="sp-badge is-warning"><span class="sp-dot is-live"></span>En cours</span>' : '' ?></div>
                                    <div class="rdv-meta">
                                        <?php if ($who): ?><span><i class="fa-solid fa-user me-1"></i><?= $r['client_id'] ? '<a href="../clients/fiche.php?id=' . (int)$r['client_id'] . '">' . e($who) . '</a>' : e($who) ?></span><?php endif; ?>
                                        <?php if ($r['lieu']): ?><span><i class="fa-solid fa-location-dot me-1"></i><?= e($r['lieu']) ?></span><?php endif; ?>
                                        <?php if ($telRdv): ?><span><i class="fa-solid fa-phone me-1"></i><a href="<?= e(tel_href($telRdv)) ?>"><?= e($telRdv) ?></a>
                                            <?php if (in_array($r['statut'], ['planifie', 'confirme'], true) && $date >= $aujourdhui && ($wa = whatsapp_url($telRdv, rdv_message_rappel($r)))): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="ms-1 text-success" title="Envoyer un rappel WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?></span><?php endif; ?>
                                        <?php if ($r['description']): ?><span class="text-truncate" style="max-width:320px;"><i class="fa-regular fa-note-sticky me-1"></i><?= e($r['description']) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" data-status-btn title="Changer le statut"><?= badge_html(statut_rdv_meta($r['statut'])) ?> <i class="fa-solid fa-caret-down small text-muted"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach (['planifie', 'confirme', 'termine', 'annule'] as $k): $m = statut_rdv_meta($k); ?>
                                            <li><button type="button" class="dropdown-item<?= $r['statut'] === $k ? ' active' : '' ?>" data-set-statut="<?= $k ?>"><i class="fa-solid <?= e($m[2]) ?>"></i><?= e($m[0]) ?></button></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <a href="modifier.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="col-xl-3">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-primary"><i class="fa-solid fa-sun"></i></span>Aujourd'hui</h6><span class="small text-muted"><?= e(fmt_date_fr($aujourdhui, false)) ?></span></div>
            <div class="sp-card-body p-0">
                <?php if (empty($rdvJour)): ?>
                    <?= empty_state('fa-mug-hot', 'Journée libre', 'Aucun rendez-vous aujourd\'hui.', '', 'py-4') ?>
                <?php else: ?>
                    <?php foreach ($rdvJour as $r):
                        $fini = ($r['heure_fin'] ?: date('H:i:s', strtotime($r['heure_debut']) + 1800)) < $maintenant;
                        $enCours = !$fini && $r['heure_debut'] <= $maintenant; ?>
                        <a class="sp-list-item<?= $fini ? ' opacity-50' : '' ?>" href="modifier.php?id=<?= (int)$r['id'] ?>">
                            <span class="sp-time-chip"><strong><?= substr($r['heure_debut'], 0, 5) ?></strong><small><?= $enCours ? 'en cours' : ($fini ? 'passé' : 'à venir') ?></small></span>
                            <span class="li-main"><span class="li-title d-block"><?= e($r['titre']) ?></span><span class="li-sub d-block"><?= e(rdv_personne($r) ?: ($r['lieu'] ?: '—')) ?></span></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6><span class="sp-card-icon"><i class="fa-solid fa-forward"></i></span>À venir (7 jours)</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($prochains)): ?>
                    <p class="text-muted small p-3 mb-0">Rien de prévu pour les prochains jours.</p>
                <?php else: ?>
                    <?php foreach ($prochains as $r): $ts = strtotime($r['date_rdv']); ?>
                        <a class="sp-list-item" href="modifier.php?id=<?= (int)$r['id'] ?>">
                            <span class="sp-mini-cal"><span class="m"><?= e(jour_fr((int)date('w', $ts), true)) ?></span><span class="d"><?= date('j', $ts) ?></span></span>
                            <span class="li-main"><span class="li-title d-block"><?= e($r['titre']) ?></span><span class="li-sub d-block"><?= substr($r['heure_debut'], 0, 5) ?><?= $r['lieu'] ? ' · ' . e($r['lieu']) : '' ?></span></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-6"><div class="sp-stat tone-info p-3"><div class="sp-stat-label">Planifiés</div><div class="sp-stat-value" data-countup="<?= (int)$stats['planifies'] ?>"><?= (int)$stats['planifies'] ?></div></div></div>
            <div class="col-6"><div class="sp-stat tone-success p-3"><div class="sp-stat-label">Confirmés</div><div class="sp-stat-value" data-countup="<?= (int)$stats['confirmes'] ?>"><?= (int)$stats['confirmes'] ?></div></div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Clic sur une case vide du calendrier : nouveau rendez-vous à cette date
    document.querySelectorAll('.sp-cal-day').forEach(function (day) {
        var open = function (e) {
            if (e.target.closest('a')) return;
            var date = day.getAttribute('data-date');
            SP.progress.start();
            // Sur mobile, les événements ne sont signalés que par des points : on affiche la journée
            if (window.innerWidth < 768 && parseInt(day.getAttribute('data-count'), 10) > 0) {
                window.location.href = '?vue=liste&debut=' + date + '&fin=' + date<?= $avecAnnules ? " + '&annules=1'" : '' ?>;
            } else {
                window.location.href = 'ajouter.php?date=' + date;
            }
        };
        day.addEventListener('click', open);
        day.addEventListener('keydown', function (e) { if (e.key === 'Enter') open(e); });
    });
    // Changement rapide du statut (vue liste)
    document.addEventListener('click', function (e) {
        var item = e.target.closest('[data-set-statut]');
        if (!item) return;
        var row = item.closest('[data-rdv]');
        var statut = item.getAttribute('data-set-statut');
        SP.fetchJSON(SP.url('api/rdv_statut.php'), { body: { id: parseInt(row.getAttribute('data-rdv'), 10), statut: statut } }).then(function (data) {
            if (!data.success) { SP.toast({ type: 'danger', message: data.message || 'Modification impossible.' }); return; }
            row.querySelector('[data-status-btn]').innerHTML = data.badge + ' <i class="fa-solid fa-caret-down small text-muted"></i>';
            row.className = row.className.replace(/\bst-\w+/, 'st-' + statut);
            row.querySelectorAll('[data-set-statut]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-set-statut') === statut); });
            SP.toast({ type: 'success', title: 'Rendez-vous mis à jour', message: data.label, duration: 2500 });
            if (SP.notif) SP.notif.invalidate();
        });
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
