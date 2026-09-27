        </div><!-- /.sp-content -->
        <footer class="text-center text-muted py-3 no-print" style="font-size:.78rem;">
            <?= e(get_param('nom_entreprise', APP_NAME)) ?> &copy; <?= date('Y') ?> - Tous droits réservés
        </footer>
    </div><!-- /.sp-main -->
</div><!-- /.sp-wrapper -->

<!-- Boîte de dialogue de confirmation générique (remplace confirm()) -->
<div class="modal fade" id="spConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sp-confirm-modal">
            <div class="modal-body text-center pt-4 pb-2">
                <div class="sp-confirm-icon" id="spConfirmIcon"><i class="fa-solid fa-circle-question"></i></div>
                <h5 class="mt-3 mb-2" id="spConfirmTitle">Confirmer</h5>
                <p class="text-muted mb-0" id="spConfirmMessage">Êtes-vous sûr ?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-1">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="spConfirmCancel">Annuler</button>
                <button type="button" class="btn btn-sp-primary px-4" id="spConfirmOk">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $ROOT ?>assets/js/app.js"></script>
</body>
</html>
