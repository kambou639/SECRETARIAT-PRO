<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = Database::getConnection();
$me = current_user();
$role = $me['role'];
$voitVentes = has_role('admin', 'vendeur');
$voitSecretariat = has_role('admin', 'secretaire');

$today = date('Y-m-d');
$hier = date('Y-m-d', strtotime('-1 day'));

// ---------------- Ventes ----------------
$kpi = [];
if ($voitVentes) {
    $stmt = $pdo->prepare("SELECT DATE(created_at) d, COALESCE(SUM(montant_total),0) total, COUNT(*) nb
        FROM ventes WHERE statut = 'validee' AND created_at >= ? GROUP BY DATE(created_at)");
    $stmt->execute([date('Y-m-d', strtotime('-29 days'))]);
    $parJour = [];
    foreach ($stmt->fetchAll() as $r) {
        $parJour[$r['d']] = ['total' => (float)$r['total'], 'nb' => (int)$r['nb']];
    }
    $chartLabels = []; $chartCa = []; $chartNb = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $ts = strtotime($d);
        $chartLabels[] = jour_fr((int)date('w', $ts), true) . ' ' . date('d/m', $ts);
        $chartCa[] = $parJour[$d]['total'] ?? 0;
        $chartNb[] = $parJour[$d]['nb'] ?? 0;
    }
    $kpi['ca_jour'] = $parJour[$today]['total'] ?? 0;
    $kpi['nb_jour'] = $parJour[$today]['nb'] ?? 0;
    $kpi['ca_hier'] = $parJour[$hier]['total'] ?? 0;

    // Mois en cours vs même période du mois précédent
    $debutMois = date('Y-m-01');
    $debutMoisPrec = date('Y-m-01', strtotime('first day of last month'));
    $finMoisPrec = date('Y-m-d', min(strtotime('last day of last month'), strtotime(date('Y-m-d', strtotime('-1 month')))));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant_total),0) total, COUNT(*) nb FROM ventes WHERE statut='validee' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$debutMois, $today]);
    $m = $stmt->fetch();
    $kpi['ca_mois'] = (float)$m['total'];
    $kpi['nb_mois'] = (int)$m['nb'];
    $stmt->execute([$debutMoisPrec, $finMoisPrec]);
    $kpi['ca_mois_prec'] = (float)$stmt->fetch()['total'];

    $row = $pdo->query("SELECT COUNT(*) nb, COALESCE(SUM(montant_total - montant_paye),0) du FROM ventes WHERE statut='validee' AND statut_paiement <> 'payee'")->fetch();
    $kpi['credits_nb'] = (int)$row['nb'];
    $kpi['credits_du'] = (float)$row['du'];

    if ($role === 'vendeur') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant_total),0) total, COUNT(*) nb FROM ventes WHERE statut='validee' AND user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$me['id']]);
        $mine = $stmt->fetch();
        $kpi['mes_ventes_total'] = (float)$mine['total'];
        $kpi['mes_ventes_nb'] = (int)$mine['nb'];
    }

    // Top articles du mois
    $stmt = $pdo->prepare("SELECT a.id, a.nom, a.unite, a.type, SUM(d.quantite) qte, SUM(d.sous_total) total
        FROM vente_details d JOIN ventes v ON v.id = d.vente_id JOIN articles a ON a.id = d.article_id
        WHERE v.statut = 'validee' AND DATE(v.created_at) BETWEEN ? AND ?
        GROUP BY a.id, a.nom, a.unite, a.type ORDER BY total DESC LIMIT 5");
    $stmt->execute([$debutMois, $today]);
    $topArticles = $stmt->fetchAll();
    $topMax = $topArticles ? max(array_map(fn($t) => (float)$t['total'], $topArticles)) : 0;

    // Encaissements du mois par mode de paiement
    $stmt = $pdo->prepare("SELECT vp.mode_paiement, SUM(vp.montant) total FROM vente_paiements vp JOIN ventes v ON v.id = vp.vente_id
        WHERE v.statut = 'validee' AND DATE(vp.created_at) BETWEEN ? AND ? GROUP BY vp.mode_paiement ORDER BY total DESC");
    $stmt->execute([$debutMois, $today]);
    $paiementsMois = $stmt->fetchAll();

    // Dernières ventes
    $stmt = $pdo->prepare("SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.statut, v.statut_paiement, c.nom AS client_nom, u.full_name AS vendeur_nom
        FROM ventes v LEFT JOIN clients c ON c.id = v.client_id LEFT JOIN users u ON u.id = v.user_id
        ORDER BY v.created_at DESC LIMIT 6");
    $stmt->execute();
    $dernieresVentes = $stmt->fetchAll();
}

// ---------------- Stock ----------------
$nbAlertes = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE actif=1 AND type='produit' AND stock <= seuil_alerte")->fetchColumn();
$articlesAlerte = $pdo->query("SELECT id, nom, code, stock, seuil_alerte, unite FROM articles WHERE actif=1 AND type='produit' AND stock <= seuil_alerte ORDER BY stock ASC, nom ASC LIMIT 5")->fetchAll();
$nbArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE actif=1")->fetchColumn();
$nbClients = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// ---------------- Secrétariat ----------------
if ($voitSecretariat) {
    $rdvs = $pdo->query("SELECT r.*, c.nom AS client_nom, c.prenom AS client_prenom FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id
        WHERE r.date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND r.statut IN ('planifie','confirme')
        ORDER BY r.date_rdv ASC, r.heure_debut ASC LIMIT 6")->fetchAll();
    $nbRdvJour = (int)$pdo->query("SELECT COUNT(*) FROM rendezvous WHERE date_rdv = CURDATE() AND statut IN ('planifie','confirme')")->fetchColumn();
    $courriersAttente = $pdo->query("SELECT id, type, numero, objet, expediteur, destinataire, date_enregistrement, statut FROM courriers
        WHERE statut IN ('recu','en_traitement') ORDER BY date_enregistrement ASC LIMIT 5")->fetchAll();
    $nbCourriersAttente = (int)$pdo->query("SELECT COUNT(*) FROM courriers WHERE statut IN ('recu','en_traitement')")->fetchColumn();
    $nbCourriersMois = (int)$pdo->query("SELECT COUNT(*) FROM courriers WHERE date_courrier >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
}

// ---------------- Activité récente (admin) ----------------
if (has_role('admin')) {
    $activites = $pdo->query("SELECT l.action, l.details, l.created_at, u.full_name FROM activity_log l LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.created_at DESC, l.id DESC LIMIT 7")->fetchAll();
}

$heure = (int)date('G');
$salut = ($heure >= 18 || $heure < 5) ? 'Bonsoir' : 'Bonjour';
$prenom = explode(' ', trim($me['full_name']))[0] ?: $me['username'];
$resume = [];
if ($voitVentes) $resume[] = $kpi['nb_jour'] . ' vente' . ($kpi['nb_jour'] > 1 ? 's' : '') . ' aujourd\'hui';
if ($voitSecretariat) {
    $resume[] = $nbRdvJour . ' rendez-vous';
    $resume[] = $nbCourriersAttente . ' courrier' . ($nbCourriersAttente > 1 ? 's' : '') . ' en attente';
}
if ($nbAlertes) $resume[] = $nbAlertes . ' alerte' . ($nbAlertes > 1 ? 's' : '') . ' de stock';

$pageTitle = 'Tableau de bord';
$activeMenu = 'dashboard';
if ($voitVentes) $pageScripts = ['assets/js/chart.umd.min.js'];
include __DIR__ . '/includes/header.php';
?>

<section class="sp-hero">
    <div>
        <div class="sp-hero-date"><i class="fa-regular fa-calendar"></i><?= e(fmt_date_fr_long($today)) ?></div>
        <h2><?= $salut ?>, <em><?= e($prenom) ?></em> !</h2>
        <p><?= e($resume ? ucfirst(implode(' · ', $resume)) . '.' : 'Bonne journée de travail.') ?></p>
    </div>
    <div class="sp-quick-actions">
        <?php foreach (array_slice(app_quick_actions($role), 0, 4) as $qa): ?>
            <a href="<?= e($qa['url']) ?>" class="sp-quick-action"><i class="fa-solid <?= e($qa['icon']) ?>"></i><?= e(mb_convert_case(str_replace(['Nouvelle ', 'Nouveau ', 'Nouvel '], '', $qa['label']), MB_CASE_TITLE)) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<div class="row g-3 mb-4">
    <?php if ($voitVentes): ?>
        <div class="col-6 col-xl-3">
            <div class="kpi-card kpi-amber">
                <div>
                    <div class="kpi-value" data-countup="<?= $kpi['ca_jour'] ?>" data-format="money"><?= fmt_money_html($kpi['ca_jour']) ?></div>
                    <div class="kpi-label">Chiffre du jour</div>
                    <div class="kpi-foot"><?= trend_html($kpi['ca_jour'], $kpi['ca_hier']) ?><span>vs hier</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-cash-register"></i></div>
            </div>
        </div>
        <?php if ($role === 'vendeur'): ?>
        <div class="col-6 col-xl-3">
            <div class="kpi-card kpi-navy">
                <div>
                    <div class="kpi-value" data-countup="<?= $kpi['mes_ventes_total'] ?>" data-format="money"><?= fmt_money_html($kpi['mes_ventes_total']) ?></div>
                    <div class="kpi-label">Mes ventes du jour</div>
                    <div class="kpi-foot"><span class="kpi-trend"><?= $kpi['mes_ventes_nb'] ?> vente(s)</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-user-tag"></i></div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-6 col-xl-3">
            <div class="kpi-card kpi-navy">
                <div>
                    <div class="kpi-value" data-countup="<?= $kpi['ca_mois'] ?>" data-format="money"><?= fmt_money_html($kpi['ca_mois']) ?></div>
                    <div class="kpi-label">Chiffre du mois</div>
                    <div class="kpi-foot"><?= trend_html($kpi['ca_mois'], $kpi['ca_mois_prec']) ?><span>vs mois dernier</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-6 col-xl-3">
            <a href="ventes/credits.php" class="kpi-card kpi-info">
                <div>
                    <div class="kpi-value" data-countup="<?= $kpi['credits_du'] ?>" data-format="money"><?= fmt_money_html($kpi['credits_du']) ?></div>
                    <div class="kpi-label">Créances en cours</div>
                    <div class="kpi-foot"><span class="kpi-trend"><?= $kpi['credits_nb'] ?> vente(s) à crédit</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            </a>
        </div>
    <?php else: ?>
        <div class="col-6 col-xl-3">
            <a href="courrier/liste.php?vue=attente" class="kpi-card kpi-navy">
                <div>
                    <div class="kpi-value" data-countup="<?= $nbCourriersAttente ?>"><?= $nbCourriersAttente ?></div>
                    <div class="kpi-label">Courriers en attente</div>
                    <div class="kpi-foot"><span class="kpi-trend"><?= $nbCourriersMois ?> ce mois-ci</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a href="rendezvous/agenda.php" class="kpi-card kpi-amber">
                <div>
                    <div class="kpi-value" data-countup="<?= $nbRdvJour ?>"><?= $nbRdvJour ?></div>
                    <div class="kpi-label">Rendez-vous aujourd'hui</div>
                    <div class="kpi-foot"><span class="kpi-trend"><?= count($rdvs) ?> sur 7 jours</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-calendar-days"></i></div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a href="clients/liste.php" class="kpi-card kpi-success">
                <div>
                    <div class="kpi-value" data-countup="<?= $nbClients ?>"><?= $nbClients ?></div>
                    <div class="kpi-label">Clients enregistrés</div>
                    <div class="kpi-foot"><span class="kpi-trend">fichier clients</span></div>
                </div>
                <div class="kpi-icon"><i class="fa-solid fa-address-book"></i></div>
            </a>
        </div>
    <?php endif; ?>
    <div class="col-6 col-xl-3">
        <a href="articles/liste.php?stock=alerte" class="kpi-card <?= $nbAlertes ? 'kpi-plum' : 'kpi-success' ?>">
            <div>
                <div class="kpi-value" data-countup="<?= $nbAlertes ?>"><?= $nbAlertes ?></div>
                <div class="kpi-label">Alertes de stock</div>
                <div class="kpi-foot"><span class="kpi-trend"><?= $nbArticles ?> articles actifs</span></div>
            </div>
            <div class="kpi-icon"><i class="fa-solid <?= $nbAlertes ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i></div>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php if ($voitVentes): ?>
    <div class="col-xl-8">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-chart-column"></i></span>Chiffre d'affaires</h6>
                <div class="sp-seg sp-seg-sm" data-sp-seg id="chartRange">
                    <button type="button" data-range="7" class="active">7 jours</button>
                    <button type="button" data-range="14">14 jours</button>
                    <button type="button" data-range="30">30 jours</button>
                </div>
            </div>
            <div class="sp-card-body">
                <div class="d-flex flex-wrap gap-4 mb-3">
                    <div><div class="small-caps">Total période</div><div class="sp-stat-value" id="chartTotal"><?= fmt_money(array_sum(array_slice($chartCa, -7))) ?></div></div>
                    <div><div class="small-caps">Ventes</div><div class="sp-stat-value" id="chartCount"><?= array_sum(array_slice($chartNb, -7)) ?></div></div>
                    <div><div class="small-caps">Moyenne / jour</div><div class="sp-stat-value" id="chartAvg"><?= fmt_money(array_sum(array_slice($chartCa, -7)) / 7) ?></div></div>
                </div>
                <div class="sp-chart-wrap" style="height:280px;"><canvas id="chartVentes" aria-label="Graphique du chiffre d'affaires"></canvas></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($voitSecretariat): ?>
    <div class="<?= $voitVentes ? 'col-xl-4' : 'col-xl-7' ?>">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-primary"><i class="fa-solid fa-calendar-day"></i></span>Agenda à venir</h6>
                <a href="rendezvous/ajouter.php" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-plus me-1"></i>RDV</a>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($rdvs)): ?>
                    <?= empty_state('fa-mug-hot', 'Agenda libre', 'Aucun rendez-vous prévu dans les 7 prochains jours.', '', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($rdvs as $r):
                        $ts = strtotime($r['date_rdv']);
                        $isToday = $r['date_rdv'] === $today; ?>
                        <li>
                            <a class="sp-list-item" href="rendezvous/modifier.php?id=<?= (int)$r['id'] ?>">
                                <span class="sp-mini-cal"><span class="m"><?= e($isToday ? 'Auj.' : mois_fr((int)date('n', $ts), true)) ?></span><span class="d"><?= date('j', $ts) ?></span></span>
                                <span class="li-main">
                                    <span class="li-title d-block"><?= e($r['titre']) ?></span>
                                    <span class="li-sub d-block"><i class="fa-regular fa-clock me-1"></i><?= substr($r['heure_debut'], 0, 5) ?><?= $r['heure_fin'] ? ' – ' . substr($r['heure_fin'], 0, 5) : '' ?>
                                        <?php $who = $r['client_nom'] ? trim($r['client_nom'] . ' ' . $r['client_prenom']) : $r['contact_nom']; if ($who): ?> · <?= e($who) ?><?php endif; ?></span>
                                </span>
                                <span class="li-end"><?= badge_html(statut_rdv_meta($r['statut']), false) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$voitVentes && $voitSecretariat): ?>
    <div class="col-xl-5">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-envelope-open-text"></i></span>Courrier à traiter</h6>
                <a href="courrier/liste.php?vue=attente" class="small fw-semibold">Tout voir</a>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($courriersAttente)): ?>
                    <?= empty_state('fa-envelope-circle-check', 'Tout est traité', 'Aucun courrier en attente.', '', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($courriersAttente as $c): $meta = statut_courrier_meta($c['statut']); ?>
                        <li><a class="sp-list-item" href="courrier/voir.php?id=<?= (int)$c['id'] ?>">
                            <span class="sp-card-icon <?= $c['type'] === 'entrant' ? 'tone-success' : 'tone-primary' ?>"><i class="fa-solid <?= $c['type'] === 'entrant' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></span>
                            <span class="li-main"><span class="li-title d-block"><?= e($c['objet']) ?></span>
                            <span class="li-sub d-block"><?= e(($c['type'] === 'entrant' ? $c['expediteur'] : $c['destinataire']) ?: $c['numero']) ?> · <?= e(fmt_relative($c['date_enregistrement'])) ?></span></span>
                            <span class="li-end"><?= badge_html($meta, false) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <?php if ($voitVentes): ?>
    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-trophy"></i></span>Meilleures ventes du mois</h6>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($topArticles)): ?>
                    <?= empty_state('fa-chart-simple', 'Pas encore de ventes', 'Les articles les plus vendus du mois apparaîtront ici.', '', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($topArticles as $i => $t): $pct = $topMax > 0 ? (float)$t['total'] / $topMax * 100 : 0; ?>
                        <li class="sp-list-item">
                            <span class="sp-rank <?= $i < 3 ? 'r' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
                            <span class="li-main">
                                <span class="li-title d-block"><?= e($t['nom']) ?></span>
                                <span class="d-flex align-items-center gap-2 mt-1">
                                    <span class="sp-meter is-primary flex-grow-1"><span style="width:<?= round($pct, 1) ?>%"></span></span>
                                    <span class="li-sub"><?= fmt_number((float)$t['qte']) ?> <?= e($t['unite']) ?></span>
                                </span>
                            </span>
                            <span class="li-end fw-bold small tabular"><?= fmt_money((float)$t['total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>Alertes de stock</h6>
                <a href="articles/liste.php?stock=alerte" class="small fw-semibold">Gérer</a>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($articlesAlerte)): ?>
                    <?= empty_state('fa-circle-check', 'Stock en ordre', 'Aucun article sous son seuil d\'alerte.', '', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($articlesAlerte as $a):
                        $seuil = max(1, (int)$a['seuil_alerte']);
                        $pct = min(100, max(4, (int)$a['stock'] / ($seuil * 2) * 100));
                        $out = (int)$a['stock'] <= 0; ?>
                        <li>
                            <a class="sp-list-item" href="<?= has_role('admin', 'secretaire') ? 'articles/stock_ajuster.php?id=' . (int)$a['id'] : 'articles/liste.php?stock=alerte' ?>">
                                <span class="li-main">
                                    <span class="li-title d-block"><?= e($a['nom']) ?></span>
                                    <span class="d-flex align-items-center gap-2 mt-1">
                                        <span class="sp-meter <?= $out ? 'is-out' : 'is-low' ?> flex-grow-1"><span style="width:<?= round($pct) ?>%"></span></span>
                                        <span class="li-sub">seuil <?= (int)$a['seuil_alerte'] ?></span>
                                    </span>
                                </span>
                                <span class="li-end"><span class="badge <?= $out ? 'badge-stock-out' : 'badge-stock-low' ?>"><?= $out ? 'Rupture' : (int)$a['stock'] . ' ' . e($a['unite']) ?></span></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($voitVentes): ?>
    <div class="col-lg-12 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-receipt"></i></span>Dernières ventes</h6>
                <a href="ventes/historique.php" class="small fw-semibold">Historique</a>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($dernieresVentes)): ?>
                    <?= empty_state('fa-receipt', 'Aucune vente', 'Les ventes enregistrées en caisse apparaîtront ici.', '<a href="ventes/caisse.php" class="btn btn-sm btn-sp-amber"><i class="fa-solid fa-cash-register me-1"></i>Ouvrir la caisse</a>', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($dernieresVentes as $v): ?>
                        <li class="sp-list-item">
                            <?= avatar_html($v['client_nom'] ?: 'Client passage', 'sm') ?>
                            <span class="li-main">
                                <span class="li-title d-block"><?= e($v['client_nom'] ?: 'Client de passage') ?></span>
                                <span class="li-sub d-block"><?= e($v['numero_facture']) ?> · <?= e(fmt_relative($v['created_at'])) ?></span>
                            </span>
                            <span class="li-end">
                                <span class="d-block fw-bold small tabular <?= $v['statut'] === 'annulee' ? 'text-decoration-line-through text-muted' : '' ?>"><?= fmt_money((float)$v['montant_total']) ?></span>
                                <?php if ($v['statut'] === 'annulee'): ?><span class="sp-badge is-danger mt-1">Annulée</span>
                                <?php elseif ($v['statut_paiement'] !== 'payee'): ?><span class="sp-badge is-warning mt-1">Crédit</span><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($voitVentes && $voitSecretariat): ?>
    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-envelope-open-text"></i></span>Courrier à traiter</h6>
                <a href="courrier/liste.php?vue=attente" class="small fw-semibold">Tout voir</a>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($courriersAttente)): ?>
                    <?= empty_state('fa-envelope-circle-check', 'Tout est traité', 'Aucun courrier en attente.', '', 'py-4') ?>
                <?php else: ?>
                <ul class="sp-list">
                    <?php foreach ($courriersAttente as $c): ?>
                        <li><a class="sp-list-item" href="courrier/voir.php?id=<?= (int)$c['id'] ?>">
                            <span class="sp-card-icon <?= $c['type'] === 'entrant' ? 'tone-success' : 'tone-primary' ?>"><i class="fa-solid <?= $c['type'] === 'entrant' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></span>
                            <span class="li-main"><span class="li-title d-block"><?= e($c['objet']) ?></span>
                            <span class="li-sub d-block"><?= e(($c['type'] === 'entrant' ? $c['expediteur'] : $c['destinataire']) ?: $c['numero']) ?> · <?= e(fmt_relative($c['date_enregistrement'])) ?></span></span>
                            <span class="li-end"><?= badge_html(statut_courrier_meta($c['statut']), false) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($voitVentes && !empty($paiementsMois)): ?>
    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-wallet"></i></span>Encaissements du mois</h6>
            </div>
            <div class="sp-card-body">
                <div class="sp-chart-wrap" style="height:200px;"><canvas id="chartPaiements" aria-label="Répartition des encaissements"></canvas></div>
                <div class="sp-legend justify-content-center mt-3" id="legendPaiements"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_role('admin') && !empty($activites)): ?>
    <div class="col-lg-6 col-xl-4 sp-reveal">
        <div class="sp-card h-100">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-primary"><i class="fa-solid fa-clock-rotate-left"></i></span>Activité récente</h6>
                <a href="utilisateurs/journal.php" class="small fw-semibold">Journal</a>
            </div>
            <div class="sp-card-body">
                <ul class="sp-timeline">
                    <?php foreach ($activites as $act): [$icon, $tone] = activity_meta($act['action']); ?>
                        <li>
                            <span class="tl-dot tone-<?= e($tone === 'neutral' ? 'accent' : $tone) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span>
                            <div class="tl-title"><?= e($act['details'] ?: $act['action']) ?></div>
                            <div class="tl-meta"><?= e($act['full_name'] ?? 'Système') ?> · <?= e(fmt_relative($act['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($voitVentes): ?>
<script type="application/json" id="dashData"><?= js_json([
    'labels' => $chartLabels, 'ca' => $chartCa, 'nb' => $chartNb,
    'paiements' => array_map(fn($p) => ['label' => mode_paiement_label($p['mode_paiement']), 'total' => (float)$p['total']], $paiementsMois),
]) ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var data = JSON.parse(document.getElementById('dashData').textContent);
    var range = 7;
    function slice(arr) { return arr.slice(-range); }
    function updateSummary() {
        var ca = slice(data.ca), nb = slice(data.nb);
        var total = ca.reduce(function (s, v) { return s + v; }, 0);
        document.getElementById('chartTotal').textContent = SP.money(total);
        document.getElementById('chartCount').textContent = SP.num(nb.reduce(function (s, v) { return s + v; }, 0));
        document.getElementById('chartAvg').textContent = SP.money(total / range);
    }
    var chart = SP.charts.create(document.getElementById('chartVentes'), function (c) {
        return {
            data: {
                labels: slice(data.labels),
                datasets: [
                    { type: 'bar', label: 'Chiffre d\'affaires', data: slice(data.ca), yAxisID: 'y', borderRadius: 8, borderSkipped: false, maxBarThickness: 38,
                      backgroundColor: function (ctx) { return SP.charts.gradient(ctx, c.accentRgb, 0.95, 0.45); },
                      hoverBackgroundColor: c.accent },
                    { type: 'line', label: 'Nombre de ventes', data: slice(data.nb), yAxisID: 'y1', tension: 0.35, cubicInterpolationMode: 'monotone', borderWidth: 2.5,
                      borderColor: c.primary, pointBackgroundColor: c.surface, pointBorderColor: c.primary, pointBorderWidth: 2, pointRadius: 3.5, pointHoverRadius: 6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', align: 'end' },
                    tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.dataset.label + ' : ' + (ctx.dataset.yAxisID === 'y' ? SP.money(ctx.parsed.y) : ctx.parsed.y); } } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                    y: { beginAtZero: true, grid: { color: c.border }, border: { display: false }, ticks: { callback: SP.charts.moneyTick } },
                    y1: { beginAtZero: true, position: 'right', grid: { display: false }, border: { display: false }, ticks: { precision: 0 } }
                }
            }
        };
    });
    document.querySelectorAll('#chartRange [data-range]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            range = parseInt(btn.getAttribute('data-range'), 10);
            updateSummary();
            SP.charts.instances.forEach(function (entry) {
                if (entry.canvas.id !== 'chartVentes') return;
                entry.chart.data.labels = slice(data.labels);
                entry.chart.data.datasets[0].data = slice(data.ca);
                entry.chart.data.datasets[1].data = slice(data.nb);
                entry.chart.update();
            });
        });
    });
    // Conserve la période choisie si le graphique est reconstruit (changement de thème)
    document.addEventListener('sp:themechange', function () { setTimeout(function () { var b = document.querySelector('#chartRange .active'); if (b) b.click(); }, 50); });

    var payCanvas = document.getElementById('chartPaiements');
    if (payCanvas && data.paiements.length) {
        SP.charts.create(payCanvas, function (c) {
            var colors = SP.charts.palette();
            var legend = document.getElementById('legendPaiements');
            var total = data.paiements.reduce(function (s, p) { return s + p.total; }, 0);
            legend.innerHTML = data.paiements.map(function (p, i) {
                return '<span><i style="background:' + colors[i % colors.length] + '"></i>' + SP.esc(p.label) + ' · ' + Math.round(p.total / total * 100) + ' %</span>';
            }).join('');
            return {
                type: 'doughnut',
                data: { labels: data.paiements.map(function (p) { return p.label; }), datasets: [{ data: data.paiements.map(function (p) { return p.total; }), backgroundColor: colors, borderColor: c.surface, borderWidth: 3, hoverOffset: 8 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ' : ' + SP.money(ctx.parsed); } } } } }
            };
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
