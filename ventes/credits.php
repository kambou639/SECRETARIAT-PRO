<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

$tri = in_array($_GET['tri'] ?? '', ['anciennete', 'montant', 'client'], true) ? $_GET['tri'] : 'anciennete';
$filtre = in_array($_GET['statut'] ?? '', ['partielle', 'impayee'], true) ? $_GET['statut'] : '';
$orderBy = [
    'anciennete' => 'v.created_at ASC',
    'montant'    => '(v.montant_total - v.montant_paye) DESC',
    'client'     => 'c.nom ASC, v.created_at ASC',
][$tri];

$sql = "SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.montant_paye, v.statut_paiement,
               c.id AS client_id, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel,
               u.full_name AS vendeur_nom
        FROM ventes v
        LEFT JOIN clients c ON c.id = v.client_id
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.statut = 'validee' AND v.statut_paiement <> 'payee'" . ($filtre ? " AND v.statut_paiement = " . $pdo->quote($filtre) : '') . "
        ORDER BY $orderBy";
$creances = $pdo->query($sql)->fetchAll();

$totalDu = 0;
$plusAncienne = 0;
$parClient = [];
foreach ($creances as $c) {
    $reste = (float)$c['montant_total'] - (float)$c['montant_paye'];
    $totalDu += $reste;
    $plusAncienne = max($plusAncienne, days_since($c['created_at']));
    $key = $c['client_id'] ?: 0;
    if (!isset($parClient[$key])) {
        $parClient[$key] = ['nom' => trim(($c['client_nom'] ?? 'Client inconnu') . ' ' . ($c['client_prenom'] ?? '')), 'tel' => $c['client_tel'], 'id' => $c['client_id'], 'du' => 0, 'nb' => 0];
    }
    $parClient[$key]['du'] += $reste;
    $parClient[$key]['nb']++;
}
uasort($parClient, fn($a, $b) => $b['du'] <=> $a['du']);

$entreprise = get_param('nom_entreprise', APP_NAME);
$telEntreprise = get_param('telephone');
function message_relance(array $c, float $reste, string $entreprise, string $telEntreprise): string
{
    $nom = trim(($c['client_prenom'] ?? '') !== '' ? $c['client_prenom'] : ($c['client_nom'] ?? ''));
    return "Bonjour $nom, sauf erreur de notre part, votre facture {$c['numero_facture']} du " . fmt_date($c['created_at'])
        . ' présente un solde de ' . fmt_money($reste) . '. Merci de passer la régler à votre convenance. '
        . "Cordialement, $entreprise" . ($telEntreprise ? " ($telEntreprise)" : '') . '.';
}

$pageTitle = 'Ventes à crédit';
$activeMenu = 'credits';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="sp-stat tone-danger"><div class="sp-stat-top"><span class="sp-stat-label">Total à encaisser</span><span class="sp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></span></div><div class="sp-stat-value" id="kpiDu" data-countup="<?= $totalDu ?>" data-format="money"><?= fmt_money_html($totalDu) ?></div><div class="sp-stat-sub">créances en cours</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat"><div class="sp-stat-top"><span class="sp-stat-label">Factures</span><span class="sp-stat-icon"><i class="fa-solid fa-file-invoice"></i></span></div><div class="sp-stat-value" id="kpiNb"><?= count($creances) ?></div><div class="sp-stat-sub">non intégralement payées</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat tone-info"><div class="sp-stat-top"><span class="sp-stat-label">Clients débiteurs</span><span class="sp-stat-icon"><i class="fa-solid fa-users"></i></span></div><div class="sp-stat-value"><?= count($parClient) ?></div><div class="sp-stat-sub">à relancer</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat tone-warning"><div class="sp-stat-top"><span class="sp-stat-label">Plus ancienne</span><span class="sp-stat-icon"><i class="fa-solid fa-hourglass-half"></i></span></div><div class="sp-stat-value"><?= $plusAncienne ?> <small class="cur">jours</small></div><div class="sp-stat-sub"><?= $plusAncienne > 30 ? 'relance conseillée' : 'dans les délais' ?></div></div></div>
</div>

<div class="row g-3">
    <div class="<?= count($parClient) > 1 ? 'col-xl-8' : 'col-12' ?>">
        <div class="sp-card">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon tone-danger"><i class="fa-solid fa-hand-holding-dollar"></i></span>Créances en cours</h6>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <div class="sp-input-icon" style="width:220px;"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" placeholder="Filtrer…" data-sp-filter="#tableCredits" aria-label="Filtrer les créances"></div>
                    <div class="sp-seg sp-seg-sm" data-sp-seg>
                        <?php foreach (['anciennete' => 'Plus anciennes', 'montant' => 'Montant', 'client' => 'Client'] as $k => $label): ?>
                            <a href="?<?= e(http_build_query(array_merge($_GET, ['tri' => $k]))) ?>" class="<?= $tri === $k ? 'active' : '' ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="sp-card-body p-0">
                <?php if (empty($creances)): ?>
                    <?= empty_state('fa-circle-check', 'Aucune créance en cours', 'Toutes les ventes sont réglées. Bravo !') ?>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-sp mb-0" id="tableCredits">
                    <thead><tr><th>Facture</th><th>Client</th><th class="text-end">Total</th><th style="min-width:130px;">Réglé</th><th class="text-end">Reste</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($creances as $c):
                        $reste = (float)$c['montant_total'] - (float)$c['montant_paye'];
                        $jours = days_since($c['created_at']);
                        $pct = (float)$c['montant_total'] > 0 ? (float)$c['montant_paye'] / (float)$c['montant_total'] * 100 : 0;
                        $nomClient = trim(($c['client_nom'] ?? 'Client inconnu') . ' ' . ($c['client_prenom'] ?? ''));
                        $wa = whatsapp_url($c['client_tel'], message_relance($c, $reste, $entreprise, $telEntreprise));
                        $tel = tel_href($c['client_tel']); ?>
                        <tr data-vente="<?= (int)$c['id'] ?>" data-total="<?= (float)$c['montant_total'] ?>" data-paye="<?= (float)$c['montant_paye'] ?>">
                            <td>
                                <code><?= e($c['numero_facture']) ?></code>
                                <div class="sp-cell-sub mt-1"><?= fmt_date($c['created_at']) ?> ·
                                    <span class="sp-badge <?= $jours > 30 ? 'is-danger' : ($jours > 14 ? 'is-warning' : '') ?>" style="padding:.2rem .45rem;"><?= $jours ?> j</span></div>
                            </td>
                            <td>
                                <div class="sp-cell-flex">
                                    <?= avatar_html($nomClient, 'sm') ?>
                                    <div style="min-width:0;">
                                        <?php if ($c['client_id']): ?><a class="sp-cell-title" href="../clients/fiche.php?id=<?= (int)$c['client_id'] ?>"><?= e($nomClient) ?></a><?php else: ?><span class="sp-cell-title"><?= e($nomClient) ?></span><?php endif; ?>
                                        <div class="sp-cell-sub"><?= e($c['client_tel'] ?: 'Pas de téléphone') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap tabular"><?= fmt_money((float)$c['montant_total']) ?></td>
                            <td>
                                <div class="sp-meter" title="<?= round($pct) ?> % réglé"><span data-meter style="width:<?= round($pct, 1) ?>%"></span></div>
                                <div class="sp-cell-sub mt-1 text-nowrap" data-paye-label><?= fmt_money((float)$c['montant_paye']) ?> · <?= round($pct) ?> %</div>
                            </td>
                            <td class="text-end fw-bold text-danger text-nowrap tabular" data-reste-label><?= fmt_money($reste) ?></td>
                            <td class="td-actions">
                                <button type="button" class="btn btn-sm btn-sp-amber" onclick="ouvrirVersement(<?= (int)$c['id'] ?>, <?= e(js_json($c['numero_facture'])) ?>, <?= e(js_json($nomClient)) ?>)">
                                    <i class="fa-solid fa-money-bill-wave me-1"></i>Versement
                                </button>
                                <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-whatsapp" title="Relancer par WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
                                <?php if ($tel): ?><a href="<?= e($tel) ?>" class="btn btn-sm btn-icon btn-outline-secondary d-none d-md-inline-flex" title="Appeler"><i class="fa-solid fa-phone"></i></a><?php endif; ?>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" title="Détails" onclick="showVenteDetails(<?= (int)$c['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
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

    <?php if (count($parClient) > 1): ?>
    <div class="col-xl-4">
        <div class="sp-card">
            <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-ranking-star"></i></span>Principaux débiteurs</h6></div>
            <div class="sp-card-body p-0">
                <ul class="sp-list">
                    <?php $maxDu = max(array_column($parClient, 'du')) ?: 1; foreach (array_slice($parClient, 0, 8, true) as $pc): ?>
                        <li class="sp-list-item">
                            <?= avatar_html($pc['nom'], 'sm') ?>
                            <span class="li-main">
                                <?php if ($pc['id']): ?><a class="li-title d-block text-reset" href="../clients/fiche.php?id=<?= (int)$pc['id'] ?>"><?= e($pc['nom']) ?></a><?php else: ?><span class="li-title d-block"><?= e($pc['nom']) ?></span><?php endif; ?>
                                <span class="d-flex align-items-center gap-2 mt-1">
                                    <span class="sp-meter is-out flex-grow-1"><span style="width:<?= round($pc['du'] / $maxDu * 100) ?>%"></span></span>
                                    <span class="li-sub"><?= $pc['nb'] ?> facture(s)</span>
                                </span>
                            </span>
                            <span class="li-end fw-bold small text-danger tabular"><?= fmt_money($pc['du']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="sp-note mt-3 small"><i class="fa-brands fa-whatsapp me-1"></i>Le bouton WhatsApp prépare un message de relance poli avec le montant restant dû. Le numéro est complété par l'indicatif <strong>+<?= e(get_param('indicatif_pays', '226')) ?></strong> (modifiable dans les paramètres).</div>
    </div>
    <?php endif; ?>
</div>

<!-- Modale d'ajout de versement sur une vente à crédit -->
<div class="modal fade" id="versementModal" tabindex="-1" aria-labelledby="vsTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="versementForm" data-no-loading novalidate>
      <div class="modal-header">
        <div>
            <h5 class="modal-title" id="vsTitle"><span class="sp-card-icon tone-success"><i class="fa-solid fa-money-bill-wave"></i></span>Enregistrer un versement</h5>
            <div class="small text-muted"><span id="vsFacture"></span> · <span id="vsClient"></span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="pos-balance is-due mt-0 mb-3"><span>Reste à payer</span><span class="val" id="vsReste"></span></div>
        <div id="vsError" class="alert alert-danger d-none"></div>
        <label class="form-label" for="vsMontant">Montant du versement</label>
        <div class="input-group mb-2">
            <input type="number" id="vsMontant" class="form-control form-control-lg" min="1" step="1" required>
            <span class="input-group-text"><?= e(get_param('devise', 'FCFA')) ?></span>
        </div>
        <div class="sp-qty-quick justify-content-start mb-3" id="vsQuick">
            <button type="button" data-part="1">Tout le reste</button>
            <button type="button" data-part="0.5">La moitié</button>
            <button type="button" data-part="0.25">Un quart</button>
        </div>
        <label class="form-label">Mode de paiement</label>
        <div class="pos-pay-modes mb-3">
            <?php foreach (['especes', 'mobile_money', 'carte', 'virement', 'autre'] as $i => $m): ?>
                <input type="radio" class="btn-check" name="vsMode" id="vs_<?= $m ?>" value="<?= $m ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <label for="vs_<?= $m ?>"><i class="fa-solid <?= e(mode_paiement_icon($m)) ?>"></i><?= e(str_replace(' bancaire', '', mode_paiement_label($m))) ?></label>
            <?php endforeach; ?>
        </div>
        <label class="form-label" for="vsNote">Note (optionnel)</label>
        <input type="text" id="vsNote" class="form-control" maxlength="150" placeholder="Ex. : 2e versement, reçu par Awa…">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-sp-amber" id="vsConfirm"><i class="fa-solid fa-check me-1"></i>Enregistrer le versement</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<script>
var vsVenteId = null, vsReste = 0;
function ligneVente(id) { return document.querySelector('tr[data-vente="' + id + '"]'); }
function ouvrirVersement(venteId, numero, client) {
    var row = ligneVente(venteId);
    vsVenteId = venteId;
    vsReste = Math.round((parseFloat(row.dataset.total) - parseFloat(row.dataset.paye)) * 100) / 100;
    document.getElementById('vsFacture').textContent = numero;
    document.getElementById('vsClient').textContent = client;
    document.getElementById('vsReste').textContent = SP.money(vsReste);
    document.getElementById('vsMontant').value = vsReste;
    document.getElementById('vsMontant').max = vsReste;
    document.getElementById('vsNote').value = '';
    document.getElementById('vsError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('versementModal')).show();
}
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('versementModal');
    modalEl.addEventListener('shown.bs.modal', function () { var i = document.getElementById('vsMontant'); i.focus(); i.select(); });
    document.getElementById('vsQuick').addEventListener('click', function (e) {
        var b = e.target.closest('[data-part]');
        if (!b) return;
        document.getElementById('vsMontant').value = Math.max(1, Math.round(vsReste * parseFloat(b.dataset.part)));
    });
    document.getElementById('versementForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var montant = parseFloat(document.getElementById('vsMontant').value) || 0;
        var errorEl = document.getElementById('vsError');
        var btn = document.getElementById('vsConfirm');
        errorEl.classList.add('d-none');
        if (montant <= 0) { errorEl.textContent = 'Le montant doit être supérieur à 0.'; errorEl.classList.remove('d-none'); return; }
        if (montant > vsReste + 0.01) { errorEl.textContent = 'Le montant dépasse le reste à payer (' + SP.money(vsReste) + ').'; errorEl.classList.remove('d-none'); return; }
        btn.classList.add('is-loading');
        SP.fetchJSON('versement_ajouter.php', { body: {
            vente_id: vsVenteId, montant: montant,
            mode_paiement: (document.querySelector('input[name="vsMode"]:checked') || {}).value || 'especes',
            note: document.getElementById('vsNote').value
        } }).then(function (data) {
            btn.classList.remove('is-loading');
            if (!data.success) { errorEl.textContent = data.message || 'Erreur lors de l\'enregistrement.'; errorEl.classList.remove('d-none'); return; }
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            var row = ligneVente(vsVenteId);
            var total = parseFloat(row.dataset.total);
            row.dataset.paye = data.montant_paye;
            var pct = total > 0 ? data.montant_paye / total * 100 : 100;
            row.querySelector('[data-meter]').style.width = pct + '%';
            row.querySelector('[data-paye-label]').textContent = SP.money(data.montant_paye) + ' · ' + Math.round(pct) + ' %';
            row.querySelector('[data-reste-label]').textContent = SP.money(data.reste_a_payer);
            row.classList.remove('is-new'); void row.offsetWidth; row.classList.add('is-new');
            var kpi = document.getElementById('kpiDu');
            var du = 0;
            document.querySelectorAll('#tableCredits tbody tr[data-vente]').forEach(function (r) { du += parseFloat(r.dataset.total) - parseFloat(r.dataset.paye); });
            kpi.innerHTML = SP.esc(SP.num(du)) + '<small class="cur">' + SP.esc(SP.cfg.devise) + '</small>';
            if (data.statut_paiement === 'payee') {
                SP.toast({ type: 'success', title: 'Facture soldée', message: 'La vente est désormais entièrement payée.' });
                setTimeout(function () {
                    row.style.transition = 'opacity .4s, transform .4s';
                    row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
                    setTimeout(function () {
                        row.remove();
                        document.getElementById('kpiNb').textContent = document.querySelectorAll('#tableCredits tbody tr[data-vente]').length;
                    }, 420);
                }, 900);
            } else {
                SP.toast({ type: 'success', title: 'Versement enregistré', message: 'Reste à payer : ' + SP.money(data.reste_a_payer) + '.' });
            }
            if (SP.notif) SP.notif.invalidate();
        }).catch(function () {
            btn.classList.remove('is-loading');
            errorEl.textContent = 'Erreur réseau. Merci de réessayer.';
            errorEl.classList.remove('d-none');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
