<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

$sql = "SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.montant_paye, v.statut_paiement,
               c.id AS client_id, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel,
               u.full_name AS vendeur_nom
        FROM ventes v
        LEFT JOIN clients c ON c.id = v.client_id
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.statut = 'validee' AND v.statut_paiement != 'payee'
        ORDER BY v.created_at ASC";
$creances = $pdo->query($sql)->fetchAll();

$totalDu = 0;
foreach ($creances as $c) {
    $totalDu += (float)$c['montant_total'] - (float)$c['montant_paye'];
}

$pageTitle = 'Ventes à crédit';
$activeMenu = 'credits';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-card mb-3">
    <div class="sp-card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="text-muted small text-uppercase fw-semibold">Total des créances en cours</div>
            <div class="fs-4 fw-bold" style="color:var(--sp-navy);"><?= fmt_money($totalDu) ?></div>
        </div>
        <div class="text-muted small"><?= count($creances) ?> vente(s) non intégralement payée(s)</div>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-header"><h6><i class="fa-solid fa-hand-holding-dollar me-1"></i>Ventes à crédit en cours</h6></div>
    <div class="sp-card-body p-0">
        <?php if (empty($creances)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-circle-check"></i><p class="mb-0">Aucune créance en cours. Toutes les ventes sont réglées.</p></div>
        <?php else: ?>
        <table class="table table-sp mb-0">
            <thead><tr><th>N° Facture</th><th>Date</th><th>Client</th><th class="text-end">Total</th><th class="text-end">Payé</th><th class="text-end">Reste à payer</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($creances as $c): ?>
                <?php $reste = (float)$c['montant_total'] - (float)$c['montant_paye']; ?>
                <tr>
                    <td><code><?= e($c['numero_facture']) ?></code></td>
                    <td><?= fmt_datetime($c['created_at']) ?></td>
                    <td>
                        <?= e(trim(($c['client_nom'] ?? 'Client inconnu') . ' ' . ($c['client_prenom'] ?? ''))) ?>
                        <?php if (!empty($c['client_tel'])): ?><div class="text-muted" style="font-size:.72rem;"><?= e($c['client_tel']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end"><?= fmt_money((float)$c['montant_total']) ?></td>
                    <td class="text-end"><?= fmt_money((float)$c['montant_paye']) ?></td>
                    <td class="text-end fw-bold text-danger"><?= fmt_money($reste) ?></td>
                    <td>
                        <?php if ($c['statut_paiement'] === 'partielle'): ?>
                            <span class="badge bg-warning text-dark">Partiel</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Impayée</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" title="Voir les détails" onclick="showVenteDetails(<?= $c['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                        <button type="button" class="btn btn-sm btn-sp-amber" onclick="ouvrirVersement(<?= $c['id'] ?>, '<?= e(addslashes($c['numero_facture'])) ?>', <?= $reste ?>)">
                            <i class="fa-solid fa-money-bill me-1"></i>Versement
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modale d'ajout de versement sur une vente à crédit -->
<div class="modal fade" id="versementModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter un versement - <span id="vsFacture"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info d-flex justify-content-between">
            <span>Reste à payer</span>
            <strong id="vsReste"></strong>
        </div>
        <div id="vsError" class="alert alert-danger d-none"></div>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Montant du versement</label>
            <input type="number" id="vsMontant" class="form-control" min="1" step="1">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Mode de paiement</label>
            <select id="vsMode" class="form-select">
                <option value="especes">Espèces</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="carte">Carte bancaire</option>
                <option value="virement">Virement</option>
                <option value="autre">Autre</option>
            </select>
        </div>
        <div class="mb-0">
            <label class="form-label small fw-semibold">Note (optionnel)</label>
            <input type="text" id="vsNote" class="form-control" maxlength="150" placeholder="Ex: 2e versement">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sp-amber" id="vsConfirm" onclick="confirmerVersement()"><i class="fa-solid fa-check me-1"></i>Enregistrer le versement</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<script>
let vsVenteId = null, vsResteMax = 0;
const versementModal = new bootstrap.Modal(document.getElementById('versementModal'));

function ouvrirVersement(venteId, numero, reste) {
    vsVenteId = venteId;
    vsResteMax = reste;
    document.getElementById('vsFacture').textContent = numero;
    document.getElementById('vsReste').textContent = reste.toLocaleString() + ' <?= e(get_param('devise','FCFA')) ?>';
    document.getElementById('vsMontant').value = reste;
    document.getElementById('vsMontant').max = reste;
    document.getElementById('vsNote').value = '';
    document.getElementById('vsError').classList.add('d-none');
    versementModal.show();
}

function confirmerVersement() {
    const montant = parseFloat(document.getElementById('vsMontant').value) || 0;
    const errorEl = document.getElementById('vsError');
    errorEl.classList.add('d-none');

    if (montant <= 0) {
        errorEl.textContent = 'Le montant doit être supérieur à 0.';
        errorEl.classList.remove('d-none');
        return;
    }
    if (montant > vsResteMax + 0.01) {
        errorEl.textContent = 'Le montant dépasse le reste à payer.';
        errorEl.classList.remove('d-none');
        return;
    }

    document.getElementById('vsConfirm').disabled = true;
    fetch('versement_ajouter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: '<?= csrf_token() ?>',
            vente_id: vsVenteId,
            montant: montant,
            mode_paiement: document.getElementById('vsMode').value,
            note: document.getElementById('vsNote').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            errorEl.textContent = data.message || 'Erreur lors de l\'enregistrement.';
            errorEl.classList.remove('d-none');
            document.getElementById('vsConfirm').disabled = false;
        }
    })
    .catch(() => {
        errorEl.textContent = 'Erreur réseau. Merci de réessayer.';
        errorEl.classList.remove('d-none');
        document.getElementById('vsConfirm').disabled = false;
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
