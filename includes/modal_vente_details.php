<!-- Modale de détails d'une opération de vente/caisse (contenu chargé en AJAX via vente_details.php) -->
<div class="modal fade" id="venteDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-receipt me-2"></i>Détails de la vente <span id="vdNumero" style="color:var(--sp-amber);"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="vdLoading" class="text-center text-muted py-4">
            <i class="fa-solid fa-spinner fa-spin me-1"></i>Chargement...
        </div>
        <div id="vdError" class="alert alert-danger d-none"></div>
        <div id="vdContent" class="d-none">
            <div class="row mb-3">
                <div class="col-6">
                    <div class="text-muted small text-uppercase fw-semibold">Client</div>
                    <div id="vdClient" class="fw-semibold"></div>
                    <div id="vdClientTel" class="text-muted small"></div>
                </div>
                <div class="col-6 text-end">
                    <div class="text-muted small text-uppercase fw-semibold">Vendu par</div>
                    <div id="vdVendeur" class="fw-semibold"></div>
                    <div class="text-muted small"><span id="vdDate"></span></div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-secondary-subtle text-dark-emphasis" id="vdPaiement"></span>
                <span class="badge" id="vdStatut"></span>
                <span class="badge d-none" id="vdStatutPaiement"></span>
            </div>
            <table class="table table-sp mb-3">
                <thead><tr><th>Article</th><th class="text-center">Qté</th><th class="text-end">P.U.</th><th class="text-end">Total</th></tr></thead>
                <tbody id="vdLignes"></tbody>
            </table>
            <div class="d-flex justify-content-end">
                <table class="table table-borderless w-auto mb-0">
                    <tr id="vdSousTotalRow" class="d-none"><td class="text-muted">Sous-total</td><td class="text-end" id="vdSousTotal"></td></tr>
                    <tr id="vdRemiseRow" class="d-none"><td class="text-muted">Remise</td><td class="text-end text-danger" id="vdRemise"></td></tr>
                    <tr><td class="text-muted">Total</td><td class="text-end fw-bold fs-5" id="vdTotal"></td></tr>
                    <tr><td class="text-muted">Montant payé</td><td class="text-end" id="vdPaye"></td></tr>
                    <tr id="vdResteRow" class="d-none"><td class="text-muted">Reste à payer</td><td class="text-end text-danger fw-semibold" id="vdReste"></td></tr>
                    <tr><td class="text-muted">Monnaie rendue</td><td class="text-end" id="vdMonnaie"></td></tr>
                </table>
            </div>
            <div id="vdPaiementsWrap" class="mt-2 d-none">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Paiements enregistrés</div>
                <table class="table table-sm mb-0">
                    <tbody id="vdPaiementsList"></tbody>
                </table>
            </div>
            <div id="vdObsWrap" class="mt-2 small text-muted d-none">
                <i class="fa-solid fa-note-sticky me-1"></i><span id="vdObs"></span>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" id="vdFactureLink" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-print me-1"></i>Imprimer la facture</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
