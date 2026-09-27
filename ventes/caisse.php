<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

$search = clean_input($_GET['q'] ?? '');
$sql = "SELECT a.id, a.code, a.nom, a.type, a.prix_vente, a.stock, a.unite, c.nom AS categorie_nom
        FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id
        WHERE a.actif = 1 AND (a.type = 'service' OR a.stock > 0)";
$params = [];
if ($search !== '') {
    $sql .= " AND (a.nom LIKE ? OR a.code LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY a.type ASC, a.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom LIMIT 500")->fetchAll();

// Catégories distinctes présentes parmi les articles vendables (pour les filtres rapides de la caisse)
$categories = [];
foreach ($articles as $a) {
    if (!empty($a['categorie_nom']) && !in_array($a['categorie_nom'], $categories, true)) {
        $categories[] = $a['categorie_nom'];
    }
}
sort($categories);

// Ventes du jour, pour un aperçu rapide des opérations de caisse récentes + accès aux détails
$stmtJour = $pdo->prepare("
    SELECT v.id, v.numero_facture, v.created_at, v.montant_total, v.remise_montant, v.mode_paiement, v.statut,
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

$pageTitle = 'Caisse';
$activeMenu = 'caisse';
include __DIR__ . '/../includes/header.php';
?>

<div id="venteSuccessAlert" class="alert alert-success d-none alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-circle-check me-1"></i>
    <span id="venteSuccessText"></span>
    <a href="#" id="venteSuccessFactureLink" target="_blank" class="btn btn-sm btn-outline-success ms-2"><i class="fa-solid fa-print me-1"></i>Imprimer le reçu</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="d-flex gap-2 mb-2">
            <form class="flex-grow-1" onsubmit="return false;">
                <input type="text" id="posSearch" class="form-control form-control-lg" placeholder="🔍 Rechercher un article par nom ou code...">
            </form>
            <button type="button" class="btn btn-sp-primary btn-lg flex-shrink-0" data-bs-toggle="modal" data-bs-target="#printCalcModal">
                <i class="fa-solid fa-print me-1"></i><span class="d-none d-xl-inline">Calculateur d'impression</span><span class="d-xl-none">Impression</span>
            </button>
        </div>
        <?php if (!empty($categories)): ?>
        <div class="pos-category-filters mb-3" id="posCategoryFilters">
            <button type="button" class="category-chip active" data-cat="">Toutes</button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="category-chip" data-cat="<?= e(mb_strtolower($cat)) ?>"><?= e($cat) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="row g-2" id="posGrid">
            <?php foreach ($articles as $a): ?>
                <div class="col-6 col-md-4 col-xl-3 pos-item"
                     data-nom="<?= e(mb_strtolower($a['nom'])) ?>" data-code="<?= e(mb_strtolower($a['code'])) ?>"
                     data-cat="<?= e(mb_strtolower($a['categorie_nom'] ?? '')) ?>">
                    <div class="pos-article-card" data-article="<?= e(json_encode([
                        "id" => $a["id"], "nom" => $a["nom"], "code" => $a["code"], "type" => $a["type"],
                        "prix" => (float)$a["prix_vente"], "stock" => (int)$a["stock"], "unite" => $a["unite"]
                    ], JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="pos-icon"><i class="fa-solid <?= $a['type'] === 'service' ? 'fa-print' : 'fa-box' ?>"></i></div>
                        <div class="fw-semibold small"><?= e($a['nom']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= e($a['code']) ?></div>
                        <div class="fw-bold text-sp-amber mt-1"><?= fmt_money((float)$a['prix_vente']) ?> <span class="text-muted fw-normal" style="font-size:.68rem;">/<?= e($a['unite']) ?></span></div>
                        <?php if ($a['type'] === 'service'): ?>
                            <div class="text-info" style="font-size:.7rem;"><i class="fa-solid fa-infinity"></i> Service</div>
                        <?php else: ?>
                            <div class="text-muted" style="font-size:.7rem;">Stock : <?= (int)$a['stock'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="sp-empty-state d-none" id="posNoResult"><i class="fa-solid fa-magnifying-glass"></i><p>Aucun article ne correspond à votre recherche.</p></div>
        <?php if (empty($articles)): ?>
            <div class="sp-empty-state"><i class="fa-solid fa-box-open"></i><p>Aucun article disponible en stock.</p></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="sp-card pos-cart">
            <div class="sp-card-header">
                <h6><i class="fa-solid fa-cart-shopping me-1"></i>Panier</h6>
                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" title="Vider"><i class="fa-solid fa-trash"></i></button>
            </div>
            <div class="sp-card-body">
                <div id="cartEmpty" class="text-muted small text-center py-3">Le panier est vide</div>
                <div id="cartItems" class="pos-cart-items"></div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title">Client</div>
                    <select id="clientSelect" class="form-select form-select-sm">
                        <option value="">Client de passage</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['nom'] . ' ' . ($c['prenom'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title">Remise</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <select id="remiseType" class="form-select form-select-sm">
                                <option value="aucune">Aucune remise</option>
                                <option value="pourcentage">Pourcentage (%)</option>
                                <option value="montant">Montant fixe</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" id="remiseValeur" class="form-control form-control-sm" min="0" step="1" value="0" disabled placeholder="Valeur">
                        </div>
                    </div>
                </div>

                <div class="pos-cart-section pos-cart-total">
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Sous-total</span>
                        <span id="cartSousTotal">0</span>
                    </div>
                    <div class="d-flex justify-content-between small text-danger d-none" id="cartRemiseRow">
                        <span>Remise</span>
                        <span id="cartRemiseMontant">- 0</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-5 mt-1">
                        <span>Total</span>
                        <span id="cartTotal">0 <?= e(get_param('devise','FCFA')) ?></span>
                    </div>
                </div>

                <div class="pos-cart-section">
                    <div class="pos-cart-section-title">Paiement (multi-mode possible)</div>
                    <div id="paiementsList" class="mb-2"></div>
                    <div class="row g-2 align-items-end">
                        <div class="col-5">
                            <select id="paiementMode" class="form-select form-select-sm">
                                <option value="especes">Espèces</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="carte">Carte bancaire</option>
                                <option value="virement">Virement</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <input type="number" id="paiementMontant" class="form-control form-control-sm" min="0" step="1" placeholder="Montant">
                        </div>
                        <div class="col-2">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="ajouterPaiement()" title="Ajouter ce paiement"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-link btn-sm px-0 mt-1" onclick="remplirMontantRestant()">Compléter le reste à payer</button>

                    <div class="d-flex justify-content-between small mt-2">
                        <span>Total payé</span>
                        <span id="cartTotalPaye">0</span>
                    </div>
                    <div class="d-flex justify-content-between fw-semibold">
                        <span id="cartResteLabel">Reste à payer</span>
                        <span id="cartResteMontant">0</span>
                    </div>
                    <div class="small text-danger mt-1 d-none" id="cartCreditWarning">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Vente à crédit : sélectionnez un client ci-dessus pour pouvoir valider.
                    </div>
                </div>

                <button class="btn btn-sp-amber w-100 btn-lg mt-3" id="btnValider" onclick="validerVente()" disabled>
                    <i class="fa-solid fa-check me-1"></i>Valider la vente
                </button>
            </div>
        </div>
    </div>
</div>

<div class="sp-card mt-3" id="ventesJourCard">
    <?php include __DIR__ . '/../includes/_ventes_jour_content.php'; ?>
</div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<!-- Modale de saisie de quantité (utile pour les services facturés à la page) -->
<div class="modal fade" id="qtyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="qtyModalLabel">Quantité</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2" id="qtyModalHint"></p>
        <input type="number" id="qtyModalInput" class="form-control form-control-lg text-center" min="1" value="1">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sp-amber btn-sm" id="qtyModalConfirm">Ajouter au panier</button>
      </div>
    </div>
  </div>
</div>

<!-- Modale du calculateur d'impression : type de document + pages + reliure + couverture -->
<div class="modal fade" id="printCalcModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-print me-2"></i>Calculateur d'impression</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label fw-semibold">Type de document</label>
                <select id="printDocType" class="form-select">
                    <option value="">- Sélectionner -</option>
                    <?php foreach ($articles as $a): if ($a['type'] === 'service'): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?> / <?= e($a['unite']) ?>)</option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Nombre de pages</label>
                <input type="number" id="printPages" class="form-control" min="1" value="1">
            </div>
            <div class="col-12">
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-0" id="printPricePreview">
                    <span class="text-muted small">Choisissez un type de document et un nombre de pages.</span>
                </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-6">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="printReliureCheck">
                    <label class="form-check-label fw-semibold" for="printReliureCheck"><i class="fa-solid fa-ring me-1"></i>Ajouter une reliure (spirale...)</label>
                </div>
                <select id="printReliureSelect" class="form-select form-select-sm mb-2" disabled>
                    <option value="">- Choisir un article -</option>
                    <?php foreach ($articles as $a): if ($a['type'] === 'produit'): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?>)</option>
                    <?php endif; endforeach; ?>
                </select>
                <input type="number" id="printReliureQte" class="form-control form-control-sm" min="1" value="1" disabled placeholder="Quantité">
            </div>

            <div class="col-md-6">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="printCouvertureCheck">
                    <label class="form-check-label fw-semibold" for="printCouvertureCheck"><i class="fa-solid fa-file me-1"></i>Ajouter une couverture cartonnée</label>
                </div>
                <select id="printCouvertureSelect" class="form-select form-select-sm mb-2" disabled>
                    <option value="">- Choisir un article -</option>
                    <?php foreach ($articles as $a): if ($a['type'] === 'produit'): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nom']) ?> (<?= fmt_money((float)$a['prix_vente']) ?>)</option>
                    <?php endif; endforeach; ?>
                </select>
                <input type="number" id="printCouvertureQte" class="form-control form-control-sm" min="1" value="2" disabled placeholder="Quantité">
            </div>

            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center fw-bold fs-5 border-top pt-3">
                    <span>Total du travail</span>
                    <span id="printGrandTotal">0 <?= e(get_param('devise','FCFA')) ?></span>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sp-amber" id="printCalcConfirm"><i class="fa-solid fa-cart-plus me-1"></i>Ajouter tout au panier</button>
      </div>
    </div>
  </div>
</div>

<script>
let cart = {};
const ARTICLES_DATA = <?= json_encode(array_column(array_map(fn($a) => [
    'id' => (int)$a['id'], 'nom' => $a['nom'], 'code' => $a['code'], 'type' => $a['type'],
    'prix' => (float)$a['prix_vente'], 'stock' => (int)$a['stock'], 'unite' => $a['unite'],
], $articles), null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
let pendingArticle = null;
const qtyModalEl = document.getElementById('qtyModal');
const qtyModal = new bootstrap.Modal(qtyModalEl);
const printModalEl = document.getElementById('printCalcModal');
const printModal = new bootstrap.Modal(printModalEl);

let activeCategory = '';

function applyPosFilters() {
    const q = document.getElementById('posSearch').value.trim().toLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('.pos-item').forEach(el => {
        const matchQ = el.dataset.nom.includes(q) || el.dataset.code.includes(q);
        const matchCat = !activeCategory || el.dataset.cat === activeCategory;
        const show = matchQ && matchCat;
        el.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    document.getElementById('posNoResult').classList.toggle('d-none', visibleCount > 0);
}
document.getElementById('posSearch').addEventListener('input', applyPosFilters);

document.querySelectorAll('.category-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
        document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        activeCategory = this.dataset.cat;
        applyPosFilters();
    });
});

// Délégation d'événement : lit l'article depuis l'attribut data-article (échappé côté serveur),
// plus sûr qu'un onclick inline si le nom de l'article contient une apostrophe ou un guillemet.
document.querySelectorAll('.pos-article-card').forEach(function (card) {
    card.addEventListener('click', function () {
        try {
            const article = JSON.parse(this.dataset.article);
            addToCart(article);
        } catch (e) {
            console.error('Article JSON invalide', e);
        }
    });
});

/** Interroge le serveur pour obtenir le prix unitaire réel (avec paliers dégressifs) d'un article. */
function fetchTarif(articleId, qte) {
    return fetch('tarif_calc.php?id=' + articleId + '&qte=' + qte)
        .then(r => r.json());
}

function addToCart(article) {
    // On ouvre une modale de saisie de quantité plutôt que d'incrémenter clic par clic
    // (pratique pour un service facturé à la page : ex. saisir directement "45").
    pendingArticle = article;
    const already = cart[article.id] ? cart[article.id].qte : 0;
    document.getElementById('qtyModalLabel').textContent = article.nom;
    const input = document.getElementById('qtyModalInput');
    input.value = already > 0 ? already : 1;
    input.max = article.type === 'service' ? '' : article.stock;
    refreshQtyModalPreview();
    qtyModal.show();
    setTimeout(() => { input.focus(); input.select(); }, 300);
}

function refreshQtyModalPreview() {
    if (!pendingArticle) return;
    const qte = Math.max(1, parseInt(document.getElementById('qtyModalInput').value, 10) || 1);
    const hintEl = document.getElementById('qtyModalHint');
    hintEl.textContent = 'Calcul du tarif...';
    fetchTarif(pendingArticle.id, qte).then(data => {
        if (!data.success) { hintEl.textContent = 'Erreur de calcul du tarif.'; return; }
        let txt = data.prix_unitaire.toLocaleString() + ' / ' + data.unite + ' × ' + qte + ' = ' + data.sous_total.toLocaleString() + ' <?= e(get_param('devise','FCFA')) ?>';
        if (data.degressif) txt += ' 🏷️ tarif dégressif appliqué';
        if (pendingArticle.type !== 'service') txt += ' (stock disponible : ' + pendingArticle.stock + ')';
        hintEl.textContent = txt;
    });
}
document.getElementById('qtyModalInput').addEventListener('input', debounce(refreshQtyModalPreview, 300));

document.getElementById('qtyModalConfirm').addEventListener('click', function () {
    const qte = parseInt(document.getElementById('qtyModalInput').value, 10);
    if (!pendingArticle || isNaN(qte) || qte <= 0) { qtyModal.hide(); return; }
    if (pendingArticle.type !== 'service' && qte > pendingArticle.stock) {
        alert('Stock insuffisant (disponible : ' + pendingArticle.stock + ')');
        return;
    }
    fetchTarif(pendingArticle.id, qte).then(data => {
        if (!data.success) { alert('Erreur lors du calcul du tarif.'); return; }
        cart[pendingArticle.id] = { ...pendingArticle, qte, prix: data.prix_unitaire };
        renderCart();
        qtyModal.hide();
    });
});
qtyModalEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('qtyModalConfirm').click(); }
});

function debounce(fn, delay) {
    let t;
    return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); };
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    const newQty = cart[id].qte + delta;
    if (newQty <= 0) { delete cart[id]; renderCart(); return; }
    if (cart[id].type !== 'service' && newQty > cart[id].stock) { alert('Stock insuffisant.'); return; }
    fetchTarif(id, newQty).then(data => {
        if (!data.success) return;
        cart[id].qte = newQty;
        cart[id].prix = data.prix_unitaire;
        renderCart();
    });
}

function setQtyDirect(id, value) {
    if (!cart[id]) return;
    const qte = parseInt(value, 10);
    if (isNaN(qte) || qte <= 0) { delete cart[id]; renderCart(); return; }
    if (cart[id].type !== 'service' && qte > cart[id].stock) {
        alert('Stock insuffisant (disponible : ' + cart[id].stock + ')');
        renderCart();
        return;
    }
    fetchTarif(id, qte).then(data => {
        if (!data.success) return;
        cart[id].qte = qte;
        cart[id].prix = data.prix_unitaire;
        renderCart();
    });
}

let paiements = [];

function clearCart() {
    cart = {};
    paiements = [];
    document.getElementById('remiseType').value = 'aucune';
    document.getElementById('remiseValeur').value = 0;
    document.getElementById('remiseValeur').disabled = true;
    document.getElementById('paiementMontant').value = '';
    renderPaiements();
    renderCart();
}

function computeCartTotals() {
    const sousTotal = Object.values(cart).reduce((s, it) => s + it.qte * it.prix, 0);
    const remiseType = document.getElementById('remiseType').value;
    let remiseValeur = parseFloat(document.getElementById('remiseValeur').value) || 0;
    let remiseMontant = 0;
    if (remiseType === 'pourcentage') {
        remiseValeur = Math.min(Math.max(remiseValeur, 0), 100);
        remiseMontant = sousTotal * remiseValeur / 100;
    } else if (remiseType === 'montant') {
        remiseValeur = Math.max(remiseValeur, 0);
        remiseMontant = Math.min(remiseValeur, sousTotal);
    }
    const total = Math.max(0, sousTotal - remiseMontant);
    const totalPaye = paiements.reduce((s, p) => s + p.montant, 0);
    const reste = Math.round((total - totalPaye) * 100) / 100;
    return { sousTotal, remiseType, remiseValeur, remiseMontant, total, totalPaye, reste };
}

function renderCart() {
    const itemsEl = document.getElementById('cartItems');
    const keys = Object.keys(cart);
    document.getElementById('cartEmpty').style.display = keys.length ? 'none' : 'block';
    itemsEl.innerHTML = '';
    keys.forEach(id => {
        const it = cart[id];
        const sousTotal = it.qte * it.prix;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between py-2 border-bottom';
        row.innerHTML = `
            <div class="flex-grow-1 me-2">
                <div class="small fw-semibold">${it.type === 'service' ? '<i class=\"fa-solid fa-print text-info me-1\"></i>' : ''}${it.nom}</div>
                <div class="text-muted" style="font-size:.72rem;">${it.prix.toLocaleString()} / ${it.unite} &times; ${it.qte} = ${sousTotal.toLocaleString()}</div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-outline-secondary" onclick="changeQty(${id},-1)">-</button>
                <input type="number" class="form-control form-control-sm text-center" style="width:62px;" value="${it.qte}" min="1"
                       onchange="setQtyDirect(${id}, this.value)">
                <button class="btn btn-sm btn-outline-secondary" onclick="changeQty(${id},1)">+</button>
            </div>`;
        itemsEl.appendChild(row);
    });
    updateMonnaie();
}

const modeLabels = { especes: 'Espèces', mobile_money: 'Mobile Money', carte: 'Carte bancaire', virement: 'Virement', autre: 'Autre' };

function renderPaiements() {
    const listEl = document.getElementById('paiementsList');
    listEl.innerHTML = '';
    paiements.forEach((p, idx) => {
        const row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center small py-1';
        row.innerHTML = `
            <span>${modeLabels[p.mode] || p.mode} : ${p.montant.toLocaleString()}</span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="supprimerPaiement(${idx})" title="Retirer"><i class="fa-solid fa-xmark"></i></button>`;
        listEl.appendChild(row);
    });
}

function ajouterPaiement() {
    const mode = document.getElementById('paiementMode').value;
    const montant = parseFloat(document.getElementById('paiementMontant').value) || 0;
    if (montant <= 0) return;
    paiements.push({ mode, montant });
    document.getElementById('paiementMontant').value = '';
    renderPaiements();
    updateMonnaie();
}

function supprimerPaiement(idx) {
    paiements.splice(idx, 1);
    renderPaiements();
    updateMonnaie();
}

function remplirMontantRestant() {
    const t = computeCartTotals();
    if (t.reste > 0) {
        document.getElementById('paiementMontant').value = t.reste;
    }
}

function updateMonnaie() {
    const t = computeCartTotals();
    const devise = ' <?= e(get_param('devise','FCFA')) ?>';
    document.getElementById('cartSousTotal').textContent = t.sousTotal.toLocaleString() + devise;
    document.getElementById('cartTotal').textContent = t.total.toLocaleString() + devise;
    const remiseRow = document.getElementById('cartRemiseRow');
    if (t.remiseMontant > 0) {
        remiseRow.classList.remove('d-none');
        document.getElementById('cartRemiseMontant').textContent = '- ' + t.remiseMontant.toLocaleString() + devise;
    } else {
        remiseRow.classList.add('d-none');
    }
    document.getElementById('cartTotalPaye').textContent = t.totalPaye.toLocaleString() + devise;

    const resteLabel = document.getElementById('cartResteLabel');
    const resteMontant = document.getElementById('cartResteMontant');
    const creditWarning = document.getElementById('cartCreditWarning');
    const clientChoisi = document.getElementById('clientSelect').value !== '';
    let peutValider = Object.keys(cart).length > 0;

    if (t.reste > 0.009) {
        resteLabel.textContent = 'Reste à payer';
        resteMontant.textContent = t.reste.toLocaleString() + devise;
        resteMontant.className = 'text-danger';
        if (!clientChoisi) {
            creditWarning.classList.remove('d-none');
            peutValider = false;
        } else {
            creditWarning.classList.add('d-none');
        }
    } else {
        creditWarning.classList.add('d-none');
        resteLabel.textContent = 'Monnaie à rendre';
        resteMontant.textContent = Math.abs(t.reste).toLocaleString() + devise;
        resteMontant.className = 'text-success';
    }
    document.getElementById('btnValider').disabled = !peutValider;
}

document.getElementById('clientSelect').addEventListener('change', updateMonnaie);

const remiseTypeEl = document.getElementById('remiseType');
const remiseValeurEl = document.getElementById('remiseValeur');
remiseTypeEl.addEventListener('change', function () {
    const active = this.value !== 'aucune';
    remiseValeurEl.disabled = !active;
    remiseValeurEl.placeholder = this.value === 'pourcentage' ? '% remise' : (this.value === 'montant' ? 'Montant remise' : 'Valeur');
    if (!active) remiseValeurEl.value = 0;
    updateMonnaie();
});
remiseValeurEl.addEventListener('input', updateMonnaie);

function refreshVentesJour() {
    fetch('ventes_jour_fragment.php')
        .then(r => r.ok ? r.text() : Promise.reject())
        .then(html => {
            document.getElementById('ventesJourCard').innerHTML = html;
        })
        .catch(() => { /* échec silencieux : la liste se mettra à jour au prochain chargement de page */ });
}

function validerVente() {
    if (Object.keys(cart).length === 0) return;
    const t = computeCartTotals();

    if (t.reste > 0.009 && !document.getElementById('clientSelect').value) {
        alert('Sélectionnez un client pour valider une vente à crédit (paiement incomplet).');
        return;
    }

    const devise = ' <?= e(get_param('devise','FCFA')) ?>';
    const nbArticles = Object.keys(cart).length;
    const message = 'Valider cette vente de ' + t.total.toLocaleString() + devise
        + ' (' + nbArticles + ' article' + (nbArticles > 1 ? 's' : '') + ') ?';

    spConfirm(message, { title: 'Confirmer la vente', confirmLabel: 'Valider la vente' }).then(function (ok) {
        if (ok) envoyerVente(t);
    });
}

function envoyerVente(t) {
    const payload = {
        csrf_token: '<?= csrf_token() ?>',
        client_id: document.getElementById('clientSelect').value,
        remise_type: t.remiseType,
        remise_valeur: t.remiseValeur,
        paiements: paiements.map(p => ({ mode: p.mode, montant: p.montant })),
        items: Object.values(cart).map(it => ({ id: it.id, qte: it.qte, prix: it.prix }))
    };

    document.getElementById('btnValider').disabled = true;
    fetch('valider.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Reste sur la page Caisse : on affiche une confirmation avec un lien
            // vers le reçu (ouvert dans un nouvel onglet), sans rediriger l'utilisateur.
            clearCart();
            document.getElementById('venteSuccessFactureLink').href = 'facture.php?id=' + data.vente_id;
            document.getElementById('venteSuccessText').textContent = 'Vente ' + data.numero + ' enregistrée avec succès.';
            const alertEl = document.getElementById('venteSuccessAlert');
            alertEl.classList.remove('d-none');
            alertEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            refreshVentesJour();
        } else {
            alert(data.message || 'Erreur lors de la validation de la vente.');
            document.getElementById('btnValider').disabled = false;
        }
    })
    .catch(() => {
        alert('Erreur réseau. Merci de réessayer.');
        document.getElementById('btnValider').disabled = false;
    });
}

/* ===================== Calculateur d'impression ===================== */

const printDocType = document.getElementById('printDocType');
const printPages = document.getElementById('printPages');
const printReliureCheck = document.getElementById('printReliureCheck');
const printReliureSelect = document.getElementById('printReliureSelect');
const printReliureQte = document.getElementById('printReliureQte');
const printCouvertureCheck = document.getElementById('printCouvertureCheck');
const printCouvertureSelect = document.getElementById('printCouvertureSelect');
const printCouvertureQte = document.getElementById('printCouvertureQte');

printReliureCheck.addEventListener('change', function () {
    printReliureSelect.disabled = !this.checked;
    printReliureQte.disabled = !this.checked;
    refreshPrintPreview();
});
printCouvertureCheck.addEventListener('change', function () {
    printCouvertureSelect.disabled = !this.checked;
    printCouvertureQte.disabled = !this.checked;
    refreshPrintPreview();
});
[printDocType, printPages, printReliureSelect, printReliureQte, printCouvertureSelect, printCouvertureQte].forEach(el => {
    el.addEventListener('input', debounce(refreshPrintPreview, 300));
    el.addEventListener('change', refreshPrintPreview);
});

let printPreviewData = { doc: null, reliure: null, couverture: null };

function refreshPrintPreview() {
    const previewEl = document.getElementById('printPricePreview');
    const docId = printDocType.value;
    const pages = Math.max(1, parseInt(printPages.value, 10) || 1);

    if (!docId) {
        previewEl.innerHTML = '<span class="text-muted small">Choisissez un type de document et un nombre de pages.</span>';
        printPreviewData.doc = null;
        updatePrintGrandTotal();
        return;
    }

    const promises = [fetchTarif(docId, pages)];
    const reliureOn = printReliureCheck.checked && printReliureSelect.value;
    const couvertureOn = printCouvertureCheck.checked && printCouvertureSelect.value;
    promises.push(reliureOn ? fetchTarif(printReliureSelect.value, Math.max(1, parseInt(printReliureQte.value,10)||1)) : Promise.resolve(null));
    promises.push(couvertureOn ? fetchTarif(printCouvertureSelect.value, Math.max(1, parseInt(printCouvertureQte.value,10)||1)) : Promise.resolve(null));

    Promise.all(promises).then(([doc, reliure, couverture]) => {
        printPreviewData.doc = (doc && doc.success) ? doc : null;
        printPreviewData.reliure = (reliure && reliure.success) ? reliure : null;
        printPreviewData.couverture = (couverture && couverture.success) ? couverture : null;

        if (!printPreviewData.doc) {
            previewEl.innerHTML = '<span class="text-danger small">Erreur de calcul du tarif.</span>';
            updatePrintGrandTotal();
            return;
        }
        let html = '<span>' + printPreviewData.doc.nom + ' - ' + printPreviewData.doc.prix_unitaire.toLocaleString()
            + ' / ' + printPreviewData.doc.unite + ' × ' + pages + '</span><strong>' + printPreviewData.doc.sous_total.toLocaleString() + ' <?= e(get_param('devise','FCFA')) ?></strong>';
        if (printPreviewData.doc.degressif) html = '<span class="badge bg-success-subtle text-success-emphasis me-2">Tarif dégressif</span>' + html;
        previewEl.innerHTML = html;
        updatePrintGrandTotal();
    });
}

function updatePrintGrandTotal() {
    let total = 0;
    if (printPreviewData.doc) total += printPreviewData.doc.sous_total;
    if (printPreviewData.reliure) total += printPreviewData.reliure.sous_total;
    if (printPreviewData.couverture) total += printPreviewData.couverture.sous_total;
    document.getElementById('printGrandTotal').textContent = total.toLocaleString() + ' <?= e(get_param('devise','FCFA')) ?>';
}

document.getElementById('printCalcConfirm').addEventListener('click', function () {
    if (!printDocType.value) { alert('Veuillez choisir un type de document.'); return; }
    const pages = Math.max(1, parseInt(printPages.value, 10) || 1);

    const lines = [{ id: parseInt(printDocType.value, 10), qte: pages }];
    if (printReliureCheck.checked && printReliureSelect.value) {
        lines.push({ id: parseInt(printReliureSelect.value, 10), qte: Math.max(1, parseInt(printReliureQte.value,10)||1) });
    }
    if (printCouvertureCheck.checked && printCouvertureSelect.value) {
        lines.push({ id: parseInt(printCouvertureSelect.value, 10), qte: Math.max(1, parseInt(printCouvertureQte.value,10)||1) });
    }

    // Vérifie le stock pour les articles physiques avant d'ajouter
    for (const l of lines) {
        const ref = ARTICLES_DATA[l.id];
        if (!ref) continue;
        const already = cart[l.id] ? cart[l.id].qte : 0;
        if (ref.type !== 'service' && (already + l.qte) > ref.stock) {
            alert('Stock insuffisant pour "' + ref.nom + '" (disponible : ' + ref.stock + ').');
            return;
        }
    }

    Promise.all(lines.map(l => {
        const already = cart[l.id] ? cart[l.id].qte : 0;
        const totalQte = already + l.qte;
        return fetchTarif(l.id, totalQte).then(data => ({ id: l.id, totalQte, data }));
    })).then(results => {
        results.forEach(r => {
            if (!r.data.success) return;
            const ref = ARTICLES_DATA[r.id];
            cart[r.id] = { id: r.id, nom: ref.nom, code: ref.code, type: ref.type, unite: ref.unite, stock: ref.stock, qte: r.totalQte, prix: r.data.prix_unitaire };
        });
        renderCart();
        printModal.hide();
        // Réinitialise le formulaire du calculateur pour la prochaine impression
        printDocType.value = '';
        printPages.value = 1;
        printReliureCheck.checked = false; printReliureSelect.disabled = true; printReliureSelect.value = '';
        printCouvertureCheck.checked = false; printCouvertureSelect.disabled = true; printCouvertureSelect.value = '';
        printPreviewData = { doc: null, reliure: null, couverture: null };
        document.getElementById('printPricePreview').innerHTML = '<span class="text-muted small">Choisissez un type de document et un nombre de pages.</span>';
        document.getElementById('printGrandTotal').textContent = '0 <?= e(get_param('devise','FCFA')) ?>';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
