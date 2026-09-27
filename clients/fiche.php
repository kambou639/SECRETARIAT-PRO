<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    flash_set('danger', 'Client introuvable.');
    redirect('liste.php');
}
$voitVentes = has_role('admin', 'vendeur');
$voitAgenda = has_role('admin', 'secretaire');

$stmt = $pdo->prepare("SELECT * FROM ventes WHERE client_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$id]);
$ventes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) nb, COALESCE(SUM(montant_total), 0) total, MIN(created_at) premier, MAX(created_at) dernier,
        COALESCE(SUM(CASE WHEN statut_paiement <> 'payee' THEN montant_total - montant_paye ELSE 0 END), 0) solde
    FROM ventes WHERE client_id = ? AND statut = 'validee'");
$stmt->execute([$id]);
$stats = $stmt->fetch();
$soldeDu = (float)$stats['solde'];
$panierMoyen = (int)$stats['nb'] > 0 ? (float)$stats['total'] / (int)$stats['nb'] : 0;

$stmt = $pdo->prepare("SELECT a.id, a.nom, a.unite, a.type, SUM(d.quantite) qte, SUM(d.sous_total) total, COUNT(DISTINCT v.id) fois
    FROM vente_details d JOIN ventes v ON v.id = d.vente_id JOIN articles a ON a.id = d.article_id
    WHERE v.client_id = ? AND v.statut = 'validee' GROUP BY a.id, a.nom, a.unite, a.type ORDER BY total DESC LIMIT 8");
$stmt->execute([$id]);
$articlesPreferes = $stmt->fetchAll();

$rdvs = [];
if ($voitAgenda) {
    $stmt = $pdo->prepare("SELECT * FROM rendezvous WHERE client_id = ? ORDER BY date_rdv DESC, heure_debut DESC LIMIT 15");
    $stmt->execute([$id]);
    $rdvs = $stmt->fetchAll();
}

$nom = trim($client['nom'] . ' ' . ($client['prenom'] ?? ''));
$entreprise = get_param('nom_entreprise', APP_NAME);
$msgRelance = 'Bonjour ' . (($client['prenom'] ?? '') ?: $client['nom']) . ', sauf erreur de notre part, votre solde restant dû s\'élève à '
    . fmt_money($soldeDu) . '. Merci de passer le régler à votre convenance. Cordialement, ' . $entreprise . '.';
$wa = whatsapp_url($client['telephone'], $soldeDu > 0.009 ? $msgRelance : 'Bonjour ' . (($client['prenom'] ?? '') ?: $client['nom']) . ', ');
$tel = tel_href($client['telephone']);

$pageTitle = 'Fiche client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3 overflow-hidden">
    <div class="sp-card-body d-flex flex-wrap gap-4 align-items-center" style="background:linear-gradient(120deg, var(--sp-surface) 55%, var(--sp-accent-soft));">
        <?= avatar_html($nom, 'xl') ?>
        <div class="flex-grow-1" style="min-width:220px;">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h2 class="mb-0" style="font-size:1.6rem;"><?= e($nom) ?></h2>
                <span class="sp-badge <?= $client['type'] === 'entreprise' ? 'is-info' : 'is-primary' ?>"><i class="fa-solid <?= $client['type'] === 'entreprise' ? 'fa-building' : 'fa-user' ?>"></i><?= $client['type'] === 'entreprise' ? 'Entreprise' : 'Particulier' ?></span>
                <?php if ($soldeDu > 0.009): ?><span class="sp-badge is-danger"><i class="fa-solid fa-hand-holding-dollar"></i>Solde dû <?= fmt_money($soldeDu) ?></span><?php endif; ?>
            </div>
            <div class="text-muted mt-1 d-flex flex-wrap gap-3 small">
                <?php if ($client['telephone']): ?><span><i class="fa-solid fa-phone me-1"></i><?= e($client['telephone']) ?></span><?php endif; ?>
                <?php if ($client['email']): ?><span><i class="fa-solid fa-envelope me-1"></i><?= e($client['email']) ?></span><?php endif; ?>
                <?php $adr = trim(($client['adresse'] ?? '') . ' ' . ($client['ville'] ?? '')); if ($adr !== ''): ?><span><i class="fa-solid fa-location-dot me-1"></i><?= e($adr) ?></span><?php endif; ?>
                <span><i class="fa-regular fa-calendar me-1"></i>Client depuis <?= e(fmt_date_fr($client['created_at'])) ?></span>
            </div>
        </div>
        <div class="sp-contact-btns">
            <?php if ($tel): ?><a href="<?= e($tel) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-phone me-1"></i>Appeler</a><?php endif; ?>
            <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp"><i class="fa-brands fa-whatsapp me-1"></i><?= $soldeDu > 0.009 ? 'Relancer' : 'WhatsApp' ?></a><?php endif; ?>
            <?php if ($client['email']): ?><a href="mailto:<?= e($client['email']) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-envelope me-1"></i>Email</a><?php endif; ?>
            <?php if ($voitVentes): ?><a href="../ventes/caisse.php?client=<?= $id ?>" class="btn btn-sp-amber"><i class="fa-solid fa-cash-register me-1"></i>Nouvelle vente</a><?php endif; ?>
            <a href="modifier.php?id=<?= $id ?>" class="btn btn-soft-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
        </div>
    </div>
    <?php if (!empty($client['notes'])): ?>
        <div class="sp-card-footer small"><i class="fa-regular fa-note-sticky me-1 text-muted"></i><?= nl2br(e($client['notes'])) ?></div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="sp-stat tone-primary"><div class="sp-stat-top"><span class="sp-stat-label">Total dépensé</span><span class="sp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></span></div><div class="sp-stat-value" data-countup="<?= (float)$stats['total'] ?>" data-format="money"><?= fmt_money_html((float)$stats['total']) ?></div><div class="sp-stat-sub"><?= $stats['premier'] ? 'depuis le ' . e(fmt_date($stats['premier'])) : 'aucun achat' ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat"><div class="sp-stat-top"><span class="sp-stat-label">Achats</span><span class="sp-stat-icon"><i class="fa-solid fa-receipt"></i></span></div><div class="sp-stat-value" data-countup="<?= (int)$stats['nb'] ?>"><?= (int)$stats['nb'] ?></div><div class="sp-stat-sub"><?= $stats['dernier'] ? 'dernier ' . e(fmt_relative($stats['dernier'])) : '—' ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat tone-info"><div class="sp-stat-top"><span class="sp-stat-label">Panier moyen</span><span class="sp-stat-icon"><i class="fa-solid fa-basket-shopping"></i></span></div><div class="sp-stat-value" data-countup="<?= round($panierMoyen) ?>" data-format="money"><?= fmt_money_html($panierMoyen) ?></div><div class="sp-stat-sub">par achat</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat <?= $soldeDu > 0.009 ? 'tone-danger' : 'tone-success' ?>"><div class="sp-stat-top"><span class="sp-stat-label">Solde dû</span><span class="sp-stat-icon"><i class="fa-solid <?= $soldeDu > 0.009 ? 'fa-hand-holding-dollar' : 'fa-circle-check' ?>"></i></span></div><div class="sp-stat-value" data-countup="<?= $soldeDu ?>" data-format="money"><?= fmt_money_html($soldeDu) ?></div><div class="sp-stat-sub"><?= $soldeDu > 0.009 && $voitVentes ? '<a href="../ventes/credits.php">encaisser un versement</a>' : 'à jour' ?></div></div></div>
</div>

<div class="sp-tabs" role="tablist">
    <button type="button" class="sp-tab active" data-sp-tab="tabAchats"><i class="fa-solid fa-receipt"></i>Achats <span class="badge bg-secondary"><?= count($ventes) ?></span></button>
    <button type="button" class="sp-tab" data-sp-tab="tabArticles"><i class="fa-solid fa-heart"></i>Articles préférés</button>
    <?php if ($voitAgenda): ?><button type="button" class="sp-tab" data-sp-tab="tabRdv"><i class="fa-solid fa-calendar-days"></i>Rendez-vous <span class="badge bg-secondary"><?= count($rdvs) ?></span></button><?php endif; ?>
</div>

<div class="sp-tab-pane active" id="tabAchats">
    <div class="sp-card">
        <div class="sp-card-body p-0">
            <?php if (empty($ventes)): ?>
                <?= empty_state('fa-receipt', 'Aucun achat enregistré', 'Les ventes associées à ce client apparaîtront ici.', $voitVentes ? '<a href="../ventes/caisse.php?client=' . $id . '" class="btn btn-sp-amber"><i class="fa-solid fa-cash-register me-1"></i>Nouvelle vente</a>' : '') ?>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sp mb-0">
                <thead><tr><th>Facture</th><th>Date</th><th class="text-end">Montant</th><th class="text-end">Reste dû</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($ventes as $v): $reste = $v['statut'] === 'validee' ? (float)$v['montant_total'] - (float)$v['montant_paye'] : 0; ?>
                    <tr class="<?= $v['statut'] === 'annulee' ? 'is-muted' : '' ?>">
                        <td><code><?= e($v['numero_facture']) ?></code></td>
                        <td class="text-nowrap"><?= fmt_date($v['created_at']) ?><div class="sp-cell-sub"><?= fmt_date($v['created_at'], 'H:i') ?></div></td>
                        <td class="text-end fw-semibold tabular text-nowrap"><?= fmt_money((float)$v['montant_total']) ?></td>
                        <td class="text-end tabular text-nowrap <?= $reste > 0.009 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $reste > 0.009 ? fmt_money($reste) : '—' ?></td>
                        <td><?= vente_statut_badges($v) ?></td>
                        <td class="td-actions">
                            <?php if ($voitVentes): ?><button type="button" class="btn btn-sm btn-icon btn-outline-secondary" title="Détails" onclick="showVenteDetails(<?= (int)$v['id'] ?>)"><i class="fa-solid fa-eye"></i></button><?php endif; ?>
                            <a href="../ventes/facture.php?id=<?= (int)$v['id'] ?>&amp;format=a4" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-outline-secondary" title="Facture"><i class="fa-solid fa-file-invoice"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="sp-tab-pane" id="tabArticles">
    <div class="sp-card">
        <div class="sp-card-body p-0">
            <?php if (empty($articlesPreferes)): ?>
                <?= empty_state('fa-heart', 'Pas encore d\'habitudes d\'achat', 'Les articles achetés le plus souvent par ce client apparaîtront ici.') ?>
            <?php else: $maxT = max(array_map(fn($a) => (float)$a['total'], $articlesPreferes)) ?: 1; ?>
            <ul class="sp-list">
                <?php foreach ($articlesPreferes as $i => $a): ?>
                    <li class="sp-list-item">
                        <span class="sp-rank <?= $i < 3 ? 'r' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
                        <span class="li-main">
                            <span class="li-title d-block"><?= e($a['nom']) ?></span>
                            <span class="d-flex align-items-center gap-2 mt-1"><span class="sp-meter is-primary flex-grow-1"><span style="width:<?= round((float)$a['total'] / $maxT * 100) ?>%"></span></span>
                            <span class="li-sub"><?= fmt_number((float)$a['qte']) ?> <?= e($a['unite']) ?> · <?= (int)$a['fois'] ?> achat(s)</span></span>
                        </span>
                        <span class="li-end fw-bold small tabular"><?= fmt_money((float)$a['total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($voitAgenda): ?>
<div class="sp-tab-pane" id="tabRdv">
    <div class="sp-card">
        <div class="sp-card-header"><h6>Rendez-vous</h6><a href="../rendezvous/ajouter.php?client_id=<?= $id ?>" class="btn btn-sm btn-sp-amber"><i class="fa-solid fa-calendar-plus me-1"></i>Nouveau rendez-vous</a></div>
        <div class="sp-card-body p-0">
            <?php if (empty($rdvs)): ?>
                <?= empty_state('fa-calendar', 'Aucun rendez-vous', 'Planifiez un rendez-vous avec ce client depuis l\'agenda.') ?>
            <?php else: ?>
                <?php foreach ($rdvs as $r): $meta = statut_rdv_meta($r['statut']); ?>
                    <div class="sp-rdv st-<?= e($r['statut']) ?>">
                        <div class="rdv-time"><?= e(fmt_date_fr($r['date_rdv'], false)) ?><small><?= substr($r['heure_debut'], 0, 5) ?></small></div>
                        <div class="rdv-main">
                            <div class="rdv-title"><?= e($r['titre']) ?></div>
                            <div class="rdv-meta"><?php if ($r['lieu']): ?><span><i class="fa-solid fa-location-dot me-1"></i><?= e($r['lieu']) ?></span><?php endif; ?><span><?= e(date('Y', strtotime($r['date_rdv']))) ?></span></div>
                        </div>
                        <?= badge_html($meta) ?>
                        <a href="../rendezvous/modifier.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-icon btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($voitVentes) include __DIR__ . '/../includes/modal_vente_details.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
