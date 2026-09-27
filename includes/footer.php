<?php
/**
 * Pied de page commun. Variable optionnelle :
 *   $pageScripts (array) - scripts supplémentaires (chemins depuis la racine), chargés après app.js
 */
$pageScripts = $pageScripts ?? [];
?>
        </main><!-- /.sp-content -->
        <footer class="sp-footer no-print">
            <?= e(get_param('nom_entreprise', APP_NAME)) ?> &copy; <?= date('Y') ?> · Tous droits réservés
            · <a href="#" data-sp-shortcuts>Raccourcis clavier</a> · v<?= e(APP_VERSION) ?>
        </footer>
    </div><!-- /.sp-main -->
</div><!-- /.sp-wrapper -->

<!-- Notifications éphémères -->
<div class="sp-toasts" id="spToasts" aria-live="polite" aria-atomic="false"></div>

<!-- Boîte de dialogue de confirmation générique (remplace confirm()) -->
<div class="modal fade" id="spConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content sp-confirm-modal">
            <div class="modal-body text-center pt-4 pb-2 px-4">
                <div class="sp-confirm-icon" id="spConfirmIcon"><i class="fa-solid fa-circle-question"></i></div>
                <h5 class="mt-3 mb-2" id="spConfirmTitle">Confirmer</h5>
                <p class="text-muted mb-0" id="spConfirmMessage">Êtes-vous sûr ?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-2">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="spConfirmCancel">Annuler</button>
                <button type="button" class="btn btn-sp-primary px-4" id="spConfirmOk">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<!-- Palette de commandes (Ctrl+K) -->
<div class="modal fade sp-palette" id="spPalette" tabindex="-1" aria-hidden="true" aria-label="Recherche globale">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="sp-palette-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="spPaletteInput" placeholder="Rechercher un article, un client, une facture, une page…" autocomplete="off" spellcheck="false" aria-label="Rechercher">
                <kbd>Échap</kbd>
            </div>
            <div class="sp-palette-loading" id="spPaletteLoading"></div>
            <div class="sp-palette-results" id="spPaletteResults" role="listbox"></div>
            <div class="sp-palette-foot">
                <span><kbd>↑</kbd><kbd>↓</kbd> naviguer</span>
                <span><kbd>Entrée</kbd> ouvrir</span>
                <span><kbd>Échap</kbd> fermer</span>
            </div>
        </div>
    </div>
</div>

<!-- Aide : raccourcis clavier -->
<div class="modal fade" id="spShortcutsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="sp-card-icon"><i class="fa-solid fa-keyboard"></i></span>Raccourcis clavier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="sp-shortcuts" id="spShortcutsList"></div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= asset_url($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
