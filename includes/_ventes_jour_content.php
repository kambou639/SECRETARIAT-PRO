<?php
/**
 * Contenu de la carte "Ventes du jour" (caisse.php).
 * Attend les variables $ventesJour (tableau) et $totalJour (float) déjà définies par l'appelant.
 * Utilisé à la fois pour le rendu initial de caisse.php et pour le rafraîchissement AJAX
 * après validation d'une vente (voir ventes/ventes_jour_fragment.php).
 */
$nbValidees = count(array_filter($ventesJour, fn($v) => $v['statut'] === 'validee'));
?>
<div class="sp-card-header">
    <h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-clock-rotate-left"></i></span>Ventes du jour <span class="badge bg-secondary"><?= $nbValidees ?></span></h6>
    <div class="d-flex align-items-center gap-3">
        <span class="small text-muted">Total : <strong class="text-sp-primary tabular"><?= fmt_money($totalJour) ?></strong></span>
        <a href="historique.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-receipt me-1"></i>Historique</a>
    </div>
</div>
<div class="sp-card-body p-0">
    <?php if (empty($ventesJour)): ?>
        <?= empty_state('fa-receipt', 'Aucune vente aujourd\'hui', 'Les ventes validées en caisse s\'afficheront ici.', '', 'py-4') ?>
    <?php else: ?>
    <div class="sp-table-scroll" style="max-height:320px;">
    <table class="table table-sp mb-0">
        <thead><tr><th>N° facture</th><th>Heure</th><th>Client</th><th class="text-end">Montant</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($ventesJour as $vj):
            $court = $vj['statut'] === 'annulee' ? ['Annulée', 'danger', 'fa-ban']
                : ['payee' => ['Payée', 'success', 'fa-circle-check'], 'partielle' => ['Partiel', 'warning', 'fa-circle-half-stroke'], 'impayee' => ['Impayée', 'danger', 'fa-circle-exclamation']][$vj['statut_paiement'] ?? 'payee'] ?? ['Payée', 'success', 'fa-circle-check']; ?>
            <tr class="<?= $vj['statut'] === 'annulee' ? 'is-muted' : '' ?>">
                <td><code><?= e($vj['numero_facture']) ?></code></td>
                <td class="tabular text-nowrap"><?= fmt_date($vj['created_at'], 'H:i') ?><div class="sp-cell-sub"><?= e(explode(' ', trim((string)$vj['vendeur_nom']))[0]) ?></div></td>
                <td><?= e($vj['client_nom'] ?? 'Client de passage') ?></td>
                <td class="text-end fw-semibold tabular text-nowrap">
                    <?= fmt_money((float)$vj['montant_total']) ?>
                    <?php if ((float)$vj['remise_montant'] > 0): ?>
                        <i class="fa-solid fa-tag text-danger ms-1" title="Remise appliquée : -<?= e(fmt_money((float)$vj['remise_montant'])) ?>"></i>
                    <?php endif; ?>
                </td>
                <td><?= badge_html($court) ?></td>
                <td class="td-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir les détails" onclick="showVenteDetails(<?= (int)$vj['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                    <a href="facture.php?id=<?= (int)$vj['id'] ?>&amp;format=<?= e(get_param('format_recu', 'a4') === 'ticket' ? 'ticket' : 'a4') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-outline-secondary" title="Reçu"><i class="fa-solid fa-print"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
