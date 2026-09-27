<?php
/**
 * Contenu de la carte "Ventes du jour" (caisse.php).
 * Attend les variables $ventesJour (tableau) et $totalJour (float) déjà définies par l'appelant.
 * Utilisé à la fois pour le rendu initial de caisse.php et pour le rafraîchissement AJAX
 * après validation d'une vente (voir ventes/ventes_jour_fragment.php).
 */
?>
<div class="sp-card-header">
    <h6><i class="fa-solid fa-clock-rotate-left me-1"></i>Ventes du jour (<?= count($ventesJour) ?>)</h6>
    <div class="d-flex align-items-center gap-3">
        <span class="small text-muted">Total : <strong style="color:var(--sp-navy);"><?= fmt_money($totalJour) ?></strong></span>
        <a href="historique.php" class="btn btn-sm btn-outline-secondary">Historique complet</a>
    </div>
</div>
<div class="sp-card-body p-0">
    <?php if (empty($ventesJour)): ?>
        <div class="sp-empty-state py-3"><i class="fa-solid fa-receipt"></i><p class="mb-0">Aucune vente enregistrée aujourd'hui.</p></div>
    <?php else: ?>
    <div style="max-height:280px; overflow-y:auto;">
    <table class="table table-sp mb-0">
        <thead><tr><th>N° Facture</th><th>Heure</th><th>Client</th><th>Vendeur</th><th class="text-end">Montant</th><th>Statut</th><th class="text-end">Détails</th></tr></thead>
        <tbody>
        <?php foreach ($ventesJour as $vj): ?>
            <tr>
                <td><code><?= e($vj['numero_facture']) ?></code></td>
                <td><?= fmt_date($vj['created_at'], 'H:i') ?></td>
                <td><?= e($vj['client_nom'] ?? 'Client de passage') ?></td>
                <td><?= e($vj['vendeur_nom']) ?></td>
                <td class="text-end fw-semibold">
                    <?= fmt_money((float)$vj['montant_total']) ?>
                    <?php if ((float)$vj['remise_montant'] > 0): ?>
                        <span class="badge bg-danger-subtle text-danger-emphasis ms-1" title="Remise appliquée : -<?= fmt_money((float)$vj['remise_montant']) ?>"><i class="fa-solid fa-tag"></i></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($vj['statut'] === 'validee'): ?>
                        <span class="badge bg-success">Validée</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Annulée</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="Voir les détails" onclick="showVenteDetails(<?= $vj['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
