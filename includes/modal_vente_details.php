<!-- Modale de détails d'une opération de vente/caisse (contenu chargé en AJAX via ventes/vente_details.php) -->
<div class="modal fade" id="venteDetailsModal" tabindex="-1" aria-labelledby="vdTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vdTitle"><span class="sp-card-icon"><i class="fa-solid fa-receipt"></i></span>Vente <span id="vdNumero" class="text-sp-amber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div id="vdLoading" class="py-2">
            <span class="sp-skeleton mb-3" style="height:54px"></span>
            <span class="sp-skeleton mb-2" style="height:32px"></span>
            <span class="sp-skeleton mb-2" style="height:32px"></span>
            <span class="sp-skeleton" style="height:32px;width:60%"></span>
        </div>
        <div id="vdError" class="alert alert-danger d-none"></div>
        <div id="vdContent" class="d-none">
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <div class="sp-note h-100" style="background:var(--sp-surface-2);border-color:var(--sp-border);">
                        <div class="small-caps mb-1">Client</div>
                        <div id="vdClient" class="fw-semibold"></div>
                        <div id="vdClientTel" class="text-muted small"></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="sp-note h-100" style="background:var(--sp-surface-2);border-color:var(--sp-border);">
                        <div class="small-caps mb-1">Vendu par</div>
                        <div id="vdVendeur" class="fw-semibold"></div>
                        <div class="text-muted small"><i class="fa-regular fa-clock me-1"></i><span id="vdDate"></span></div>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="sp-badge is-outline"><i class="fa-solid fa-wallet"></i><span id="vdPaiement"></span></span>
                <span class="sp-badge" id="vdStatut"></span>
            </div>
            <div class="table-responsive">
            <table class="table table-sp mb-3">
                <thead><tr><th>Article</th><th class="text-center">Qté</th><th class="text-end">P.U.</th><th class="text-end">Total</th></tr></thead>
                <tbody id="vdLignes"></tbody>
            </table>
            </div>
            <div class="row g-3 align-items-start">
                <div class="col-md-6 order-2 order-md-1">
                    <div id="vdPaiementsWrap" class="d-none">
                        <div class="small-caps mb-2">Paiements enregistrés</div>
                        <ul class="sp-timeline" id="vdPaiementsList"></ul>
                    </div>
                    <div id="vdObsWrap" class="sp-note mt-3 d-none"><i class="fa-solid fa-note-sticky me-1"></i><span id="vdObs"></span></div>
                </div>
                <div class="col-md-6 order-1 order-md-2">
                    <table class="table table-borderless table-sm mb-0">
                        <tr id="vdSousTotalRow" class="d-none"><td class="text-muted">Sous-total</td><td class="text-end tabular" id="vdSousTotal"></td></tr>
                        <tr id="vdRemiseRow" class="d-none"><td class="text-muted">Remise</td><td class="text-end text-danger tabular" id="vdRemise"></td></tr>
                        <tr><td class="fw-bold">Total</td><td class="text-end fw-bold fs-5 font-display tabular" id="vdTotal"></td></tr>
                        <tr><td class="text-muted">Montant payé</td><td class="text-end tabular" id="vdPaye"></td></tr>
                        <tr id="vdResteRow" class="d-none"><td class="text-muted">Reste à payer</td><td class="text-end text-danger fw-semibold tabular" id="vdReste"></td></tr>
                        <tr><td class="text-muted">Monnaie rendue</td><td class="text-end tabular" id="vdMonnaie"></td></tr>
                    </table>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" id="vdTicketLink" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-receipt me-1"></i>Ticket</a>
        <a href="#" id="vdFactureLink" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-file-invoice me-1"></i>Facture A4</a>
        <button type="button" class="btn btn-sp-primary btn-sm" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
