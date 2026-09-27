<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

// Articles vendables : services + produits (y compris en rupture, affichés grisés)
$articles = $pdo->query("SELECT a.id, a.code, a.nom, a.type, a.prix_vente, a.stock, a.seuil_alerte, a.unite, a.image, c.nom AS categorie_nom
        FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id
        WHERE a.actif = 1
        ORDER BY a.type ASC, a.nom ASC")->fetchAll();

// Paliers de prix dégressifs : le prix est calculé instantanément dans le navigateur
// (et toujours recalculé côté serveur à la validation).
$paliers = [];
foreach ($pdo->query("SELECT article_id, quantite_min, quantite_max, prix_unitaire FROM paliers_prix ORDER BY article_id, quantite_min")->fetchAll() as $p) {
    $paliers[(int)$p['article_id']][] = [
        'min' => (int)$p['quantite_min'],
        'max' => $p['quantite_max'] !== null ? (int)$p['quantite_max'] : null,
        'prix' => (float)$p['prix_unitaire'],
    ];
}

$clients = $pdo->query("SELECT id, nom, prenom, telephone FROM clients ORDER BY nom, prenom LIMIT 2000")->fetchAll();

// Catégories présentes parmi les articles (filtres rapides)
$categories = [];
foreach ($articles as $a) {
    $cat = $a['categorie_nom'] ?: 'Sans catégorie';
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
}
ksort($categories);

// Ventes du jour (aperçu rapide des opérations de caisse récentes)
$stmtJour = $pdo->prepare("
    SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.remise_montant, v.mode_paiement, v.statut, v.statut_paiement,
           u.full_name AS vendeur_nom, c.nom AS client_nom
    FROM ventes v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN clients c ON c.id = v.client_id
    WHERE DATE(v.created_at) = CURDATE()
    ORDER BY v.created_at DESC
    LIMIT 30
");
$stmtJour->execute();
$ventesJour = $stmtJour->fetchAll();
$totalJour = 0;
foreach ($ventesJour as $vj) {
    if ($vj['statut'] === 'validee') $totalJour += (float)$vj['montant_total'];
}

$devise = get_param('devise', 'FCFA');
$articlesJs = [];
foreach ($articles as $a) {
    $articlesJs[(int)$a['id']] = [
        'id' => (int)$a['id'], 'nom' => $a['nom'], 'code' => $a['code'], 'type' => $a['type'],
        'prix' => (float)$a['prix_vente'], 'stock' => (int)$a['stock'], 'seuil' => (int)$a['seuil_alerte'],
        'unite' => $a['unite'], 'cat' => $a['categorie_nom'] ?: 'Sans catégorie',
    ];
}

$pageTitle = 'Caisse';
$activeMenu = 'caisse';
$bodyClass = 'pos-page';
$pageScripts = ['assets/js/caisse.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7 col-xl-8">
        <div class="pos-toolbar">
            <div class="pos-search sp-input-icon" id="posSearchWrap">
                <i class="fa-solid fa-barcode lg"></i>
                <input type="search" id="posSearch" class="form-control form-control-lg" placeholder="Scanner un code-barres ou rechercher un article…" autocomplete="off" spellcheck="false" aria-label="Rechercher un article">
                <div class="sp-input-end d-none d-md-flex">
                    <span class="scan-pill" title="Un lecteur de codes-barres peut être utilisé directement"><i class="fa-solid fa-bolt"></i>Scan</span>
                    <kbd>F2</kbd>
                </div>
            </div>
            <button type="button" class="btn btn-sp-primary btn-lg pos-calc-btn" data-bs-toggle="modal" data-bs-target="#printCalcModal" title="Calculateur d'impression (F4)">
                <i class="fa-solid fa-calculator"></i><span class="d-none d-sm-inline ms-2">Impression</span>
            </button>
        </div>

        <div class="pos-subbar">
            <div class="pos-category-filters" id="posCategoryFilters" role="tablist" aria-label="Catégories">
                <button type="button" class="category-chip active" data-cat="">Tout <span class="count"><?= count($articles) ?></span></button>
                <?php foreach ($categories as $cat => $nb): ?>
                    <button type="button" class="category-chip" data-cat="<?= e(mb_strtolower($cat)) ?>"><?= e($cat) ?> <span class="count"><?= $nb ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <div class="sp-seg sp-seg-sm" data-sp-seg id="posViewToggle" aria-label="Affichage">
                    <button type="button" data-view="grid" class="active" title="Vue grille" aria-label="Vue grille"><i class="fa-solid fa-grip"></i></button>
                    <button type="button" data-view="list" title="Vue liste" aria-label="Vue liste"><i class="fa-solid fa-list"></i></button>
                </div>
                <button type="button" class="sp-icon-btn d-none d-md-inline-grid" data-sp-fullscreen title="Plein écran" aria-label="Plein écran"><i class="fa-solid fa-expand"></i></button>
                <button type="button" class="sp-icon-btn d-none d-md-inline-grid" id="posSoundToggle" title="Son du lecteur" aria-label="Son du lecteur"><i class="fa-solid fa-volume-high"></i></button>
            </div>
        </div>

        <div class="pos-grid" id="posGrid">
            <?php foreach ($articles as $a):
                $isService = $a['type'] === 'service';
                $stock = (int)$a['stock'];
                $out = !$isService && $stock <= 0;
                $low = !$isService && !$out && $stock <= (int)$a['seuil_alerte'];
                $img = article_image_url($a['image']);
                $cat = $a['categorie_nom'] ?: 'Sans catégorie'; ?>
                <div class="pos-item" data-id="<?= (int)$a['id'] ?>" data-cat="<?= e(mb_strtolower($cat)) ?>" data-search="<?= e(mb_strtolower($a['nom'] . ' ' . $a['code'] . ' ' . $cat)) ?>">
                    <div class="pos-article-card<?= $isService ? ' is-service' : '' ?><?= $out ? ' is-out' : '' ?>" tabindex="0" role="button" aria-label="<?= e($a['nom']) ?>">
                        <span class="pos-incart">0</span>
                        <div class="pos-icon">
                            <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-solid <?= $isService ? 'fa-print' : 'fa-box' ?>"></i><?php endif; ?>
                        </div>
                        <div class="pos-meta">
                            <div class="pos-name"><?= e($a['nom']) ?></div>
                            <div class="pos-code"><?= e($a['code']) ?></div>
                        </div>
                        <div class="pos-bottom">
                            <div class="pos-price"><?= fmt_number((float)$a['prix_vente']) ?> <small><?= e($devise) ?>/<?= e($a['unite']) ?></small></div>
                            <?php if ($isService): ?>
                                <span class="pos-stock svc"><i class="fa-solid fa-infinity"></i> Service</span>
                            <?php else: ?>
                                <span class="pos-stock<?= ($low || $out) ? ' low' : '' ?>" data-stock-label><?= $out ? 'Rupture' : 'Stock ' . $stock ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$out): ?><button type="button" class="pos-add" title="Ajouter 1 <?= e($a['unite']) ?>" aria-label="Ajouter 1 <?= e($a['unite']) ?>"><i class="fa-solid fa-plus"></i></button><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="d-none" id="posNoResult">
            <?= empty_state('fa-magnifying-glass', 'Aucun article trouvé', 'Vérifiez l\'orthographe ou scannez à nouveau le code-barres.') ?>
        </div>
        <?php if (empty($articles)): ?>
            <?= empty_state('fa-box-open', 'Catalogue vide', 'Ajoutez des articles pour commencer à vendre.', has_role('admin', 'secretaire') ? '<a href="../articles/ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouvel article</a>' : '') ?>
        <?php endif; ?>

        <div class="sp-card mt-4" id="ventesJourCard">
            <?php include __DIR__ . '/../includes/_ventes_jour_content.php'; ?>
        </div>
    </div>

    <div class="col-lg-5 col-xl-4 pos-cart-col">
        <div class="sp-card pos-cart" id="posCart" aria-label="Panier">
            <div class="sp-card-header">
                <h6><i class="fa-solid fa-cart-shopping"></i> Panier <span class="pos-cart-count" id="cartCount">0</span></h6>
                <div class="d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" aria-expanded="false" title="Ventes mises en attente" id="heldBtn">
                            <i class="fa-solid fa-layer-group"></i> <span id="heldCount">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" style="min-width:290px;" id="heldMenu"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" id="btnHold" title="Mettre la vente en attente"><i class="fa-solid fa-circle-pause"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost" id="btnClear" title="Vider le panier"><i class="fa-solid fa-trash-can"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost pos-cart-close" id="btnCartClose" title="Fermer le panier" aria-label="Fermer le panier"><i class="fa-solid fa-chevron-down"></i></button>
                </div>
            </div>
            <div class="sp-card-body pos-cart-body">
                <div id="cartEmpty" class="pos-cart-empty">
                    <i class="fa-solid fa-basket-shopping"></i>
                    Le panier est vide.<br><small>Cliquez sur un article ou scannez un code-barres.</small>
                </div>
                <div id="cartItems" class="pos-cart-items" aria-live="polite"></div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title">
                        <span><i class="fa-solid fa-user me-1"></i>Client</span>
                        <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#clientQuickModal"><i class="fa-solid fa-user-plus me-1"></i>Nouveau</button>
                    </div>
                    <div class="sp-combo" id="clientCombo">
                        <div class="sp-input-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" class="form-control form-control-sm" id="clientSearch" placeholder="Client de passage — rechercher…" autocomplete="off" aria-label="Rechercher un client">
                        </div>
                        <button type="button" class="sp-combo-clear" id="clientClear" title="Retirer le client" aria-label="Retirer le client"><i class="fa-solid fa-xmark"></i></button>
                        <div class="sp-combo-list" id="clientList" role="listbox"></div>
                    </div>
                    <input type="hidden" id="clientSelect" value="">
                </div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title"><span><i class="fa-solid fa-tag me-1"></i>Remise</span></div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="sp-seg sp-seg-sm flex-shrink-0" data-sp-seg id="remiseSeg">
                            <input type="radio" class="btn-check" name="remiseType" id="remiseAucune" value="aucune" checked>
                            <label class="sp-seg-btn" for="remiseAucune">Aucune</label>
                            <input type="radio" class="btn-check" name="remiseType" id="remisePct" value="pourcentage">
                            <label class="sp-seg-btn" for="remisePct">%</label>
                            <input type="radio" class="btn-check" name="remiseType" id="remiseMontant" value="montant">
                            <label class="sp-seg-btn" for="remiseMontant"><?= e($devise) ?></label>
                        </div>
                        <input type="number" id="remiseValeur" class="form-control form-control-sm" min="0" step="1" value="" placeholder="Valeur" disabled aria-label="Valeur de la remise">
                    </div>
                </div>

                <div class="pos-cart-total">
                    <div class="row-line"><span>Sous-total</span><span id="cartSousTotal" class="tabular">0</span></div>
                    <div class="row-line text-danger d-none" id="cartRemiseRow"><span>Remise</span><span id="cartRemiseMontant" class="tabular"></span></div>
                    <div class="grand"><span>Total</span><span class="grand-value" id="cartTotal">0</span></div>
                </div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title"><span><i class="fa-solid fa-wallet me-1"></i>Paiement</span><span class="text-muted fw-normal text-lowercase" style="letter-spacing:0;">plusieurs modes possibles</span></div>
                    <div class="pos-pay-modes" id="payModes">
                        <?php foreach (['especes', 'mobile_money', 'carte', 'virement', 'autre'] as $i => $mode): ?>
                            <input type="radio" class="btn-check" name="payMode" id="pm_<?= $mode ?>" value="<?= $mode ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <label for="pm_<?= $mode ?>"><i class="fa-solid <?= e(mode_paiement_icon($mode)) ?>"></i><?= e(str_replace(' bancaire', '', mode_paiement_label($mode))) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group">
                        <input type="number" id="paiementMontant" class="form-control" min="0" step="1" placeholder="Montant reçu" aria-label="Montant reçu">
                        <button type="button" class="btn btn-sp-primary" id="btnAddPay" title="Ajouter ce paiement (multi-mode)"><i class="fa-solid fa-plus"></i></button>
                    </div>
                    <div class="pos-cash-btns" id="cashBtns">
                        <button type="button" class="exact" data-cash="exact"><i class="fa-solid fa-equals me-1"></i>Exact</button>
                        <?php foreach ([500, 1000, 2000, 5000, 10000] as $billet): ?>
                            <button type="button" data-cash="<?= $billet ?>">+<?= fmt_number($billet) ?></button>
                        <?php endforeach; ?>
                        <button type="button" data-cash="clear" title="Effacer le montant"><i class="fa-solid fa-delete-left"></i></button>
                    </div>
                    <div class="pos-payments mt-2" id="paiementsList"></div>
                    <div class="pos-balance is-neutral" id="balance"><span id="balanceLabel">Reste à payer</span><span class="val" id="balanceValue">0</span></div>
                    <div class="small text-danger mt-2 d-none" id="cartCreditWarning">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Vente à crédit : choisissez un client (ou <a href="#" data-bs-toggle="modal" data-bs-target="#clientQuickModal">créez-le</a>) pour valider.
                    </div>
                </div>

                <div class="pos-cart-section">
                    <a class="small fw-semibold" data-bs-toggle="collapse" href="#obsWrap" role="button" aria-expanded="false" aria-controls="obsWrap"><i class="fa-regular fa-note-sticky me-1"></i>Ajouter une note à la vente</a>
                    <div class="collapse" id="obsWrap">
                        <textarea id="observations" class="form-control form-control-sm mt-2" maxlength="255" rows="2" placeholder="Ex. : à livrer demain, reliure bleue…"></textarea>
                    </div>
                </div>

                <button type="button" class="btn btn-sp-amber w-100 pos-validate" id="btnValider" disabled>
                    <i class="fa-solid fa-check me-1"></i>Valider la vente <span class="kbd-hint">F9</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Barre panier (mobile) -->
<button type="button" class="pos-fab" id="posFab" aria-controls="posCart">
    <span class="fab-count" id="fabCount">0</span>
    <span>Voir le panier</span>
    <span class="fab-total" id="fabTotal">0</span>
</button>
<div class="sp-sidebar-backdrop" id="posBackdrop"></div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<!-- Modale de saisie de quantité -->
<div class="modal fade" id="qtyModal" tabindex="-1" aria-labelledby="qtyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content">
      <div class="modal-header">
        <div>
            <h5 class="modal-title" id="qtyModalLabel">Quantité</h5>
            <div class="small text-muted" id="qtyModalSub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="sp-big-stepper mb-3">
            <button type="button" data-step="-1" aria-label="Diminuer"><i class="fa-solid fa-minus"></i></button>
            <input type="number" id="qtyModalInput" class="form-control" min="1" value="1" aria-label="Quantité">
            <button type="button" data-step="1" aria-label="Augmenter"><i class="fa-solid fa-plus"></i></button>
        </div>
        <div class="sp-qty-quick mb-3" id="qtyQuick"></div>
        <div class="sp-tiers mb-3" id="qtyTiers"></div>
        <div class="sp-price-preview">
            <div class="pp-total" id="qtyTotal">0</div>
            <div class="pp-detail" id="qtyDetail"></div>
        </div>
        <div class="small text-danger mt-2 d-none" id="qtyError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sp-amber" id="qtyModalConfirm"><i class="fa-solid fa-cart-plus me-1"></i><span>Ajouter au panier</span></button>
      </div>
    </div>
  </div>
</div>

<!-- Calculateur d'impression : type de document + pages + reliure + couverture -->
<div class="modal fade" id="printCalcModal" tabindex="-1" aria-labelledby="printCalcTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="printCalcTitle"><span class="sp-card-icon tone-primary"><i class="fa-solid fa-print"></i></span>Calculateur d'impression</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="printDocType">1. Type d'impression</label>
                <select id="printDocType" class="form-select">
                    <option value="">- Sélectionner -</option>
                    <?php foreach ($articles as $a): if ($a['type'] === 'service'): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?> / <?= e($a['unite']) ?>)</option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="printPages">2. Nombre de pages</label>
                <input type="number" id="printPages" class="form-control" min="1" value="1">
            </div>
            <div class="col-12">
                <div class="sp-tiers" id="printTiers"></div>
                <div class="alert alert-light d-flex justify-content-between align-items-center mb-0 mt-2" id="printPricePreview">
                    <span class="text-muted small">Choisissez un type d'impression et un nombre de pages.</span>
                </div>
            </div>

            <div class="col-12"><div class="sp-divider-text">3. Finitions (optionnel)</div></div>

            <div class="col-md-6">
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="printReliureCheck">
                    <label class="form-check-label fw-semibold" for="printReliureCheck"><i class="fa-solid fa-ring me-1"></i>Reliure (spirale…)</label>
                </div>
                <div class="row g-2">
                    <div class="col-8">
                        <select id="printReliureSelect" class="form-select form-select-sm" disabled aria-label="Article de reliure">
                            <option value="">- Choisir -</option>
                            <?php foreach ($articles as $a): if ($a['type'] === 'produit' && (int)$a['stock'] > 0): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?>)</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4"><input type="number" id="printReliureQte" class="form-control form-control-sm" min="1" value="1" disabled aria-label="Quantité de reliure"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="printCouvertureCheck">
                    <label class="form-check-label fw-semibold" for="printCouvertureCheck"><i class="fa-solid fa-file me-1"></i>Couverture cartonnée</label>
                </div>
                <div class="row g-2">
                    <div class="col-8">
                        <select id="printCouvertureSelect" class="form-select form-select-sm" disabled aria-label="Article de couverture">
                            <option value="">- Choisir -</option>
                            <?php foreach ($articles as $a): if ($a['type'] === 'produit' && (int)$a['stock'] > 0): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?>)</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4"><input type="number" id="printCouvertureQte" class="form-control form-control-sm" min="1" value="2" disabled aria-label="Quantité de couvertures"></div>
                </div>
            </div>

            <div class="col-12">
                <div class="pos-cart-total d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-uppercase small" style="letter-spacing:.06em;">Total du travail</span>
                    <span class="grand-value" id="printGrandTotal">0</span>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sp-amber" id="printCalcConfirm"><i class="fa-solid fa-cart-plus me-1"></i>Ajouter tout au panier</button>
      </div>
    </div>
  </div>
</div>

<!-- Création rapide d'un client -->
<div class="modal fade" id="clientQuickModal" tabindex="-1" aria-labelledby="clientQuickTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="clientQuickForm" data-no-loading novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="clientQuickTitle"><span class="sp-card-icon tone-success"><i class="fa-solid fa-user-plus"></i></span>Nouveau client</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="cqError"></div>
        <div class="row g-3">
            <div class="col-12">
                <div class="sp-seg sp-seg-sm" data-sp-seg>
                    <input type="radio" class="btn-check" name="cq_type" id="cqPart" value="particulier" checked><label class="sp-seg-btn" for="cqPart"><i class="fa-solid fa-user"></i>Particulier</label>
                    <input type="radio" class="btn-check" name="cq_type" id="cqEnt" value="entreprise"><label class="sp-seg-btn" for="cqEnt"><i class="fa-solid fa-building"></i>Entreprise</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cqNom">Nom <span class="sp-required">*</span></label>
                <input type="text" class="form-control" id="cqNom" maxlength="100" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cqPrenom">Prénom</label>
                <input type="text" class="form-control" id="cqPrenom" maxlength="100">
            </div>
            <div class="col-12">
                <label class="form-label" for="cqTel">Téléphone</label>
                <div class="sp-input-icon"><i class="fa-solid fa-phone"></i><input type="tel" class="form-control" id="cqTel" maxlength="30" placeholder="Ex. 70 12 34 56"></div>
                <div class="form-text">Utile pour relancer un paiement par WhatsApp.</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-sp-primary" id="cqSubmit"><i class="fa-solid fa-check me-1"></i>Créer et sélectionner</button>
      </div>
    </form>
  </div>
</div>

<!-- Vente enregistrée -->
<div class="modal fade" id="saleSuccessModal" tabindex="-1" aria-labelledby="ssTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content text-center">
      <div class="modal-body p-4">
        <div class="sp-success-check" aria-hidden="true">
            <svg viewBox="0 0 100 100"><circle class="c-circle" cx="50" cy="50" r="45"/><path class="c-check" d="M29 52 l14 14 l28 -31"/></svg>
        </div>
        <h4 class="mt-3 mb-1 font-display" id="ssTitle">Vente enregistrée</h4>
        <div class="text-muted small" id="ssNumero"></div>
        <div class="my-4" id="ssChangeWrap">
            <div class="small-caps mb-1" id="ssChangeLabel">Monnaie à rendre</div>
            <div class="sp-change-due" id="ssChange">0</div>
            <div class="small text-muted mt-1" id="ssTotalLine"></div>
        </div>
        <div class="d-grid gap-2">
            <a href="#" class="btn btn-sp-amber btn-lg" id="ssPrimary" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i>Imprimer le reçu</a>
            <a href="#" class="btn btn-outline-secondary" id="ssSecondary" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice me-1"></i>Facture A4</a>
            <button type="button" class="btn btn-sp-primary" data-bs-dismiss="modal" id="ssNew"><i class="fa-solid fa-plus me-1"></i>Nouvelle vente <kbd class="ms-1">Entrée</kbd></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.SP_CAISSE = <?= js_json([
    'articles'   => $articlesJs,
    'paliers'    => $paliers,
    'clients'    => array_map(fn($c) => ['id' => (int)$c['id'], 'label' => trim($c['nom'] . ' ' . ($c['prenom'] ?? '')), 'tel' => $c['telephone'] ?? ''], $clients),
    'formatRecu' => get_param('format_recu', 'a4') === 'ticket' ? 'ticket' : 'a4',
    'user'       => (int)$_SESSION['user_id'],
]) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
