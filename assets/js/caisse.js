/* =========================================================
   Secrétariat Pro - Caisse (point de vente)
   - Prix dégressifs calculés instantanément (toujours revérifiés côté serveur)
   - Lecteur de codes-barres : code exact + Entrée = ajout direct
   - Panier animé, paiement multi-mode, boutons de billets, monnaie à rendre
   - Recherche / création rapide de client, ventes mises en attente
   - Panier conservé en cas de rechargement de la page
   ========================================================= */
(function () {
  'use strict';

  var D = window.SP_CAISSE;
  var SP = window.SP;
  if (!D || !SP) return;
  var $ = SP.$, $$ = SP.$$;

  var ARTICLES = D.articles || {};
  var PALIERS = D.paliers || {};
  var DEVISE = SP.cfg.devise || 'FCFA';
  var STATE_KEY = 'sp-caisse-etat-' + D.user;
  var HELD_KEY = 'sp-caisse-attente-' + D.user;
  var SOUND_KEY = 'sp-caisse-son';

  var state = freshState();
  var activeCat = '';
  var pendingId = null;

  function freshState() {
    return { items: [], paiements: [], remiseType: 'aucune', remiseValeur: 0, clientId: '', clientLabel: '', observations: '' };
  }
  function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
  function money(n) { return SP.money(n); }
  function norm(s) {
    s = String(s || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  }
  function art(id) { return ARTICLES[id]; }
  function isService(id) { return art(id) && art(id).type === 'service'; }

  /* ---------------- Tarifs ---------------- */
  function bestTier(id, qte) {
    var best = null;
    (PALIERS[id] || []).forEach(function (t) {
      if (t.min <= qte && (t.max === null || t.max >= qte) && (!best || t.min > best.min)) best = t;
    });
    return best;
  }
  function prixEffectif(id, qte) {
    var t = bestTier(id, qte);
    return t ? t.prix : (art(id) ? art(id).prix : 0);
  }
  function tiersHtml(id, qte) {
    var a = art(id), tiers = PALIERS[id] || [];
    if (!a || !tiers.length) return '';
    var used = bestTier(id, qte);
    var parts = [];
    if (tiers[0].min > 1) parts.push({ label: '1 – ' + (tiers[0].min - 1), prix: a.prix, active: !used });
    tiers.forEach(function (t) {
      parts.push({ label: t.min + (t.max !== null ? ' – ' + t.max : ' et +'), prix: t.prix, active: used === t });
    });
    return parts.map(function (p) {
      return '<div class="sp-tier' + (p.active ? ' is-active' : '') + '"><strong>' + SP.esc(SP.num(p.prix)) + ' ' + SP.esc(DEVISE) + '</strong>' + SP.esc(p.label) + ' ' + SP.esc(a.unite) + '</div>';
    }).join('');
  }

  /* ---------------- Calculs ---------------- */
  function findItem(id) {
    for (var i = 0; i < state.items.length; i++) if (state.items[i].id === id) return state.items[i];
    return null;
  }
  function qtyInCart(id) { var it = findItem(id); return it ? it.qte : 0; }
  function pendingAmount() {
    var v = parseFloat($('#paiementMontant').value);
    return v > 0 ? round2(v) : 0;
  }
  function totals() {
    var sousTotal = 0;
    state.items.forEach(function (it) { sousTotal += prixEffectif(it.id, it.qte) * it.qte; });
    sousTotal = round2(sousTotal);
    var remiseValeur = Math.max(0, parseFloat(state.remiseValeur) || 0);
    var remiseMontant = 0;
    if (state.remiseType === 'pourcentage') {
      remiseValeur = Math.min(remiseValeur, 100);
      remiseMontant = round2(sousTotal * remiseValeur / 100);
    } else if (state.remiseType === 'montant') {
      remiseMontant = Math.min(remiseValeur, sousTotal);
    } else {
      remiseValeur = 0;
    }
    var total = Math.max(0, round2(sousTotal - remiseMontant));
    var paye = round2(state.paiements.reduce(function (s, p) { return s + p.montant; }, 0));
    var pending = pendingAmount();
    return { sousTotal: sousTotal, remiseValeur: remiseValeur, remiseMontant: remiseMontant, total: total, paye: paye, pending: pending, reste: round2(total - paye - pending), resteSansPending: round2(total - paye) };
  }

  /* ---------------- Son (bip du lecteur) ---------------- */
  var audioCtx = null;
  var soundOn = SP.store.raw(SOUND_KEY) !== '0';
  function beep(ok) {
    if (!soundOn) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var t = audioCtx.currentTime, o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.type = ok ? 'sine' : 'square';
      o.frequency.value = ok ? 1480 : 180;
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(ok ? 0.16 : 0.08, t + 0.01);
      g.gain.exponentialRampToValueAtTime(0.0001, t + (ok ? 0.11 : 0.3));
      o.connect(g); g.connect(audioCtx.destination);
      o.start(t); o.stop(t + 0.32);
    } catch (e) { /* audio indisponible */ }
  }
  function renderSoundBtn() {
    var b = $('#posSoundToggle');
    if (b) { b.innerHTML = '<i class="fa-solid ' + (soundOn ? 'fa-volume-high' : 'fa-volume-xmark') + '"></i>'; b.title = soundOn ? 'Couper le son du lecteur' : 'Activer le son du lecteur'; }
  }

  /* ---------------- Persistance ---------------- */
  var save = SP.debounce(function () { SP.store.set(STATE_KEY, state); }, 200);
  function restore() {
    var saved = SP.store.get(STATE_KEY, null);
    if (!saved || !saved.items || !saved.items.length) return false;
    var dropped = 0;
    state = Object.assign(freshState(), saved);
    state.items = state.items.filter(function (it) {
      var a = art(it.id);
      if (!a || (a.type !== 'service' && a.stock <= 0)) { dropped++; return false; }
      if (a.type !== 'service' && it.qte > a.stock) it.qte = a.stock;
      return it.qte > 0;
    });
    if (state.clientId && !D.clients.some(function (c) { return String(c.id) === String(state.clientId); })) { state.clientId = ''; state.clientLabel = ''; }
    return { dropped: dropped };
  }

  /* ---------------- Grille d'articles : filtres ---------------- */
  var items = $$('.pos-item').map(function (el) {
    var id = parseInt(el.getAttribute('data-id'), 10);
    return { el: el, id: id, card: el.querySelector('.pos-article-card'), search: norm(el.getAttribute('data-search')), cat: el.getAttribute('data-cat') };
  });
  var codeMap = {};
  Object.keys(ARTICLES).forEach(function (id) { codeMap[norm(ARTICLES[id].code)] = parseInt(id, 10); });

  function applyFilters() {
    var terms = norm($('#posSearch').value.trim()).split(/\s+/).filter(Boolean);
    var visible = 0;
    items.forEach(function (it) {
      var show = (!activeCat || it.cat === activeCat) && terms.every(function (t) { return it.search.indexOf(t) > -1; });
      it.el.classList.toggle('is-hidden', !show);
      if (show) visible++;
    });
    $('#posNoResult').classList.toggle('d-none', visible > 0 || !items.length);
    return visible;
  }
  function visibleItems() { return items.filter(function (it) { return !it.el.classList.contains('is-hidden'); }); }

  function updateCardStock(id) {
    var a = art(id), it = items.filter(function (x) { return x.id === id; })[0];
    if (!a || !it || a.type === 'service') return;
    var label = it.card.querySelector('[data-stock-label]');
    var out = a.stock <= 0;
    it.card.classList.toggle('is-out', out);
    if (label) {
      label.textContent = out ? 'Rupture' : 'Stock ' + a.stock;
      label.classList.toggle('low', out || a.stock <= a.seuil);
    }
    var add = it.card.querySelector('.pos-add');
    if (add && out) add.remove();
  }

  function syncCardBadges() {
    items.forEach(function (it) {
      var q = qtyInCart(it.id);
      var badge = it.card.querySelector('.pos-incart');
      it.card.classList.toggle('in-cart', q > 0);
      if (badge) badge.textContent = q > 999 ? '999+' : q;
    });
  }

  /* ---------------- Animation « vol vers le panier » ---------------- */
  function flyToCart(fromEl) {
    if (SP.reduceMotion || !fromEl) return;
    var target = window.innerWidth < 992 ? $('#posFab') : $('#cartCount');
    if (!target) return;
    var a = fromEl.getBoundingClientRect(), b = target.getBoundingClientRect();
    if (!b.width) return;
    var dot = document.createElement('div');
    dot.className = 'pos-fly';
    dot.innerHTML = '<i class="fa-solid fa-plus"></i>';
    dot.style.left = (a.left + a.width / 2 - 17) + 'px';
    dot.style.top = (a.top + a.height / 2 - 17) + 'px';
    document.body.appendChild(dot);
    requestAnimationFrame(function () {
      dot.style.transform = 'translate(' + (b.left + b.width / 2 - (a.left + a.width / 2)) + 'px,' + (b.top + b.height / 2 - (a.top + a.height / 2)) + 'px) scale(.45)';
      dot.style.opacity = '0.3';
    });
    setTimeout(function () { dot.remove(); }, 750);
  }
  function bump(el) {
    if (!el) return;
    el.classList.remove('is-bump');
    void el.offsetWidth;
    el.classList.add('is-bump');
  }

  /* ---------------- Panier : opérations ---------------- */
  function canSet(id, qte, silent) {
    var a = art(id);
    if (!a) return false;
    if (a.type !== 'service' && qte > a.stock) {
      if (!silent) {
        SP.toast({ type: 'warning', title: 'Stock insuffisant', message: a.nom + ' : ' + a.stock + ' ' + a.unite + '(s) disponible(s).' });
        beep(false);
      }
      return false;
    }
    return true;
  }
  function setQty(id, qte, opts) {
    opts = opts || {};
    qte = Math.floor(qte);
    var it = findItem(id);
    if (!qte || qte <= 0) { removeItem(id); return true; }
    if (!canSet(id, qte)) {
      var line = $('.pos-line[data-id="' + id + '"]');
      if (line) { line.classList.remove('sp-anim-shake'); void line.offsetWidth; line.classList.add('sp-anim-shake'); }
      return false;
    }
    if (it) it.qte = qte;
    else state.items.push({ id: id, qte: qte });
    if (opts.from) flyToCart(opts.from);
    refresh({ changed: id, isNew: !it });
    return true;
  }
  function addOne(id, fromEl) {
    var ok = setQty(id, qtyInCart(id) + 1, { from: fromEl });
    if (ok) beep(true);
    return ok;
  }
  function removeItem(id) {
    state.items = state.items.filter(function (it) { return it.id !== id; });
    refresh({ removed: id });
  }
  function resetSale() {
    state = freshState();
    $('#paiementMontant').value = '';
    $('#observations').value = '';
    $('#remiseAucune').checked = true;
    $('#remiseValeur').value = '';
    $('#remiseValeur').disabled = true;
    selectClient('', '');
    var seg = $('#remiseSeg');
    if (seg && seg.__glider) seg.__glider.update(true);
    refresh({});
  }

  /* ---------------- Panier : rendu ---------------- */
  function lineHtml(it) {
    var a = art(it.id);
    var pu = prixEffectif(it.id, it.qte);
    var tier = bestTier(it.id, it.qte);
    var badge = tier && pu < a.prix ? '<span class="pos-tier" title="Tarif dégressif">-' + Math.round((1 - pu / a.prix) * 100) + ' %</span>' : '';
    return '<div class="pl-name">' + (a.type === 'service' ? '<i class="fa-solid fa-print text-info me-1"></i>' : '') + SP.esc(a.nom) + ' ' + badge + '</div>' +
      '<div class="pl-total">' + SP.esc(SP.num(pu * it.qte)) + '</div>' +
      '<div class="pl-controls">' +
        '<div class="pl-sub">' + SP.esc(SP.num(pu)) + ' ' + SP.esc(DEVISE) + ' / ' + SP.esc(a.unite) + '</div>' +
        '<div class="d-flex align-items-center gap-1">' +
          '<div class="pos-qty"><button type="button" data-act="dec" aria-label="Diminuer"><i class="fa-solid fa-minus"></i></button>' +
          '<input type="number" min="1" value="' + it.qte + '" data-act="set" aria-label="Quantité">' +
          '<button type="button" data-act="inc" aria-label="Augmenter"><i class="fa-solid fa-plus"></i></button></div>' +
          '<button type="button" class="pl-remove" data-act="remove" title="Retirer du panier" aria-label="Retirer"><i class="fa-solid fa-xmark"></i></button>' +
        '</div>' +
      '</div>';
  }
  function renderCart(info) {
    var box = $('#cartItems');
    var present = {};
    state.items.forEach(function (it) {
      present[it.id] = true;
      var node = box.querySelector('.pos-line[data-id="' + it.id + '"]');
      var focused = node && document.activeElement && node.contains(document.activeElement) && document.activeElement.tagName === 'INPUT';
      if (!node) {
        node = document.createElement('div');
        node.className = 'pos-line' + (info && info.isNew && info.changed === it.id ? ' is-new' : '');
        node.setAttribute('data-id', it.id);
        node.innerHTML = lineHtml(it);
        box.appendChild(node);
        if (info && info.isNew && info.changed === it.id) node.scrollIntoView({ block: 'nearest', behavior: SP.reduceMotion ? 'auto' : 'smooth' });
      } else if (!focused) {
        node.innerHTML = lineHtml(it);
        if (info && info.changed === it.id) { node.classList.remove('sp-anim-highlight'); void node.offsetWidth; node.classList.add('sp-anim-highlight'); }
      } else {
        // Ne réécrit pas le champ en cours de saisie : met seulement les montants à jour
        var pu = prixEffectif(it.id, it.qte);
        node.querySelector('.pl-total').textContent = SP.num(pu * it.qte);
        node.querySelector('.pl-sub').textContent = SP.num(pu) + ' ' + DEVISE + ' / ' + art(it.id).unite;
      }
    });
    $$('.pos-line', box).forEach(function (node) {
      var id = parseInt(node.getAttribute('data-id'), 10);
      if (!present[id] && !node.classList.contains('is-removing')) {
        node.classList.add('is-removing');
        node.style.height = node.offsetHeight + 'px';
        setTimeout(function () { node.remove(); }, SP.reduceMotion ? 0 : 300);
      }
    });
    $('#cartEmpty').style.display = state.items.length ? 'none' : '';
  }

  function renderPaiements() {
    var box = $('#paiementsList');
    box.innerHTML = state.paiements.map(function (p, idx) {
      return '<div class="pos-payment"><i class="fa-solid ' + modeIcon(p.mode) + ' mode"></i>' + SP.esc(modeLabel(p.mode)) +
        '<span class="amt">' + SP.esc(money(p.montant)) + '</span>' +
        '<button type="button" data-remove-pay="' + idx + '" title="Retirer ce paiement" aria-label="Retirer"><i class="fa-solid fa-xmark"></i></button></div>';
    }).join('');
  }
  function modeLabel(m) { var l = $('label[for="pm_' + m + '"]'); return l ? l.textContent.trim() : m; }
  function modeIcon(m) { var i = $('label[for="pm_' + m + '"] i'); return i ? i.className.replace('fa-solid', '').trim() : 'fa-coins'; }

  var lastTotal = null, lastCount = null;
  function renderTotals() {
    var t = totals();
    var count = state.items.length;
    $('#cartSousTotal').textContent = money(t.sousTotal);
    var totalEl = $('#cartTotal');
    totalEl.innerHTML = SP.esc(SP.num(t.total)) + '<small class="cur">' + SP.esc(DEVISE) + '</small>';
    if (lastTotal !== null && lastTotal !== t.total) bump(totalEl);
    lastTotal = t.total;
    $('#cartRemiseRow').classList.toggle('d-none', !(t.remiseMontant > 0));
    $('#cartRemiseMontant').textContent = '- ' + money(t.remiseMontant);
    var countEl = $('#cartCount');
    countEl.textContent = count;
    if (lastCount !== null && lastCount !== count) bump(countEl);
    lastCount = count;
    $('#fabCount').textContent = count;
    $('#fabTotal').textContent = money(t.total);

    var balance = $('#balance'), label = $('#balanceLabel'), val = $('#balanceValue');
    var warn = $('#cartCreditWarning');
    var peutValider = state.items.length > 0;
    balance.classList.remove('is-due', 'is-change', 'is-neutral');
    if (!state.items.length) {
      balance.classList.add('is-neutral'); label.textContent = 'Reste à payer'; val.textContent = money(0);
      warn.classList.add('d-none');
    } else if (t.reste > 0.009) {
      balance.classList.add('is-due');
      label.textContent = (t.paye + t.pending) > 0 ? 'Reste à payer (crédit)' : 'Reste à payer';
      val.textContent = money(t.reste);
      var needClient = !state.clientId;
      warn.classList.toggle('d-none', !needClient);
      if (needClient) peutValider = false;
    } else {
      balance.classList.add('is-change');
      label.textContent = t.reste < -0.009 ? 'Monnaie à rendre' : 'Compte exact';
      val.textContent = money(Math.abs(t.reste));
      warn.classList.add('d-none');
    }
    $('#btnValider').disabled = !peutValider;
  }

  function refresh(info) {
    renderCart(info || {});
    renderPaiements();
    renderTotals();
    syncCardBadges();
    save();
  }

  /* ---------------- Modale de quantité ---------------- */
  var qtyModalEl = $('#qtyModal');
  var qtyModal = bootstrap.Modal.getOrCreateInstance(qtyModalEl);
  function openQty(id) {
    var a = art(id);
    if (!a) return;
    if (a.type !== 'service' && a.stock <= 0) { SP.toast({ type: 'warning', title: 'Rupture de stock', message: a.nom }); beep(false); return; }
    pendingId = id;
    var already = qtyInCart(id);
    $('#qtyModalLabel').textContent = a.nom;
    $('#qtyModalSub').textContent = a.code + ' · ' + SP.num(a.prix) + ' ' + DEVISE + ' / ' + a.unite + (a.type === 'service' ? ' · service' : ' · ' + a.stock + ' en stock');
    var input = $('#qtyModalInput');
    input.value = already > 0 ? already : 1;
    if (a.type !== 'service') input.max = a.stock; else input.removeAttribute('max');
    var quick = a.type === 'service' ? [10, 20, 50, 100, 200, 500] : [1, 2, 3, 5, 10, 20].filter(function (q) { return q <= a.stock; });
    $('#qtyQuick').innerHTML = quick.map(function (q) { return '<button type="button" data-q="' + q + '">' + q + '</button>'; }).join('');
    $('#qtyModalConfirm span').textContent = already > 0 ? 'Mettre à jour le panier' : 'Ajouter au panier';
    refreshQtyPreview();
    qtyModal.show();
  }
  function refreshQtyPreview() {
    if (pendingId === null) return;
    var a = art(pendingId);
    var qte = Math.max(1, parseInt($('#qtyModalInput').value, 10) || 1);
    var pu = prixEffectif(pendingId, qte);
    $('#qtyTotal').innerHTML = SP.esc(SP.num(pu * qte)) + '<small class="cur">' + SP.esc(DEVISE) + '</small>';
    $('#qtyDetail').innerHTML = SP.esc(SP.num(pu) + ' ' + DEVISE + ' / ' + a.unite + ' × ' + SP.num(qte)) + (pu < a.prix ? ' <span class="pos-tier ms-1">Tarif dégressif</span>' : '');
    $('#qtyTiers').innerHTML = tiersHtml(pendingId, qte);
    var err = $('#qtyError');
    if (a.type !== 'service' && qte > a.stock) { err.textContent = 'Stock insuffisant : ' + a.stock + ' disponible(s).'; err.classList.remove('d-none'); }
    else err.classList.add('d-none');
  }
  function confirmQty() {
    if (pendingId === null) return;
    var qte = parseInt($('#qtyModalInput').value, 10);
    if (isNaN(qte) || qte <= 0) { qtyModal.hide(); return; }
    if (setQty(pendingId, qte)) { beep(true); qtyModal.hide(); }
    else { var dlg = qtyModalEl.querySelector('.modal-content'); dlg.classList.remove('sp-anim-shake'); void dlg.offsetWidth; dlg.classList.add('sp-anim-shake'); }
  }
  qtyModalEl.addEventListener('shown.bs.modal', function () { var i = $('#qtyModalInput'); i.focus(); i.select(); });
  qtyModalEl.addEventListener('hidden.bs.modal', function () { pendingId = null; $('#posSearch').focus(); });
  $('#qtyModalInput').addEventListener('input', refreshQtyPreview);
  $('#qtyModalInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); confirmQty(); } });
  $('#qtyModalConfirm').addEventListener('click', confirmQty);
  qtyModalEl.addEventListener('click', function (e) {
    var step = e.target.closest('[data-step]');
    var quick = e.target.closest('[data-q]');
    var input = $('#qtyModalInput');
    if (step) { input.value = Math.max(1, (parseInt(input.value, 10) || 0) + parseInt(step.getAttribute('data-step'), 10)); refreshQtyPreview(); }
    if (quick) { input.value = quick.getAttribute('data-q'); refreshQtyPreview(); input.focus(); }
  });

  /* ---------------- Recherche & lecteur de codes-barres ---------------- */
  var search = $('#posSearch');
  search.addEventListener('input', SP.debounce(applyFilters, 80));
  search.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { search.value = ''; applyFilters(); return; }
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var q = search.value.trim();
    if (!q) return;
    var id = codeMap[norm(q)];
    var wrap = $('#posSearchWrap');
    if (id) {
      if (isService(id)) openQty(id);
      else if (addOne(id, wrap)) SP.toast({ type: 'success', title: 'Ajouté au panier', message: art(id).nom + ' × ' + qtyInCart(id), duration: 1800 });
      search.value = '';
      applyFilters();
      wrap.classList.add('is-scanned');
      setTimeout(function () { wrap.classList.remove('is-scanned'); }, 600);
      return;
    }
    applyFilters(); // applique tout de suite le filtre (la saisie est temporisée)
    var vis = visibleItems();
    if (vis.length === 1) { openQty(vis[0].id); return; }
    if (!vis.length) {
      beep(false);
      wrap.classList.remove('sp-anim-shake'); void wrap.offsetWidth; wrap.classList.add('sp-anim-shake');
      SP.toast({ type: 'warning', title: 'Article introuvable', message: 'Aucun article ne correspond à « ' + q + ' ».', duration: 2500 });
      search.select(); // le prochain scan remplace le code erroné au lieu de s'y ajouter
    }
  });

  $('#posCategoryFilters').addEventListener('click', function (e) {
    var chip = e.target.closest('.category-chip');
    if (!chip) return;
    $$('.category-chip', this).forEach(function (c) { c.classList.remove('active'); });
    chip.classList.add('active');
    activeCat = chip.getAttribute('data-cat');
    applyFilters();
  });

  var grid = $('#posGrid');
  grid.addEventListener('click', function (e) {
    var item = e.target.closest('.pos-item');
    if (!item) return;
    var id = parseInt(item.getAttribute('data-id'), 10);
    var card = item.querySelector('.pos-article-card');
    if (card.classList.contains('is-out')) { SP.toast({ type: 'warning', title: 'Rupture de stock', message: art(id).nom }); beep(false); return; }
    if (e.target.closest('.pos-add')) {
      e.stopPropagation();
      if (addOne(id, card.querySelector('.pos-icon'))) { card.classList.remove('is-flash'); void card.offsetWidth; card.classList.add('is-flash'); }
      return;
    }
    openQty(id);
  });
  grid.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('pos-article-card')) {
      e.preventDefault();
      openQty(parseInt(e.target.closest('.pos-item').getAttribute('data-id'), 10));
    }
  });

  // Affichage grille / liste
  var view = SP.store.raw('sp-pos-vue') || 'grid';
  function applyView(v) {
    grid.classList.toggle('is-list', v === 'list');
    $$('#posViewToggle [data-view]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-view') === v); });
    var seg = $('#posViewToggle');
    if (seg && seg.__glider) seg.__glider.update(false);
  }
  $('#posViewToggle').addEventListener('click', function (e) {
    var b = e.target.closest('[data-view]');
    if (!b) return;
    view = b.getAttribute('data-view');
    SP.store.setRaw('sp-pos-vue', view);
    applyView(view);
  });

  /* ---------------- Panier : interactions ---------------- */
  var cartItems = $('#cartItems');
  cartItems.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn || btn.tagName === 'INPUT') return;
    var id = parseInt(btn.closest('.pos-line').getAttribute('data-id'), 10);
    var act = btn.getAttribute('data-act');
    if (act === 'inc') setQty(id, qtyInCart(id) + 1);
    else if (act === 'dec') setQty(id, qtyInCart(id) - 1);
    else if (act === 'remove') removeItem(id);
  });
  cartItems.addEventListener('change', function (e) {
    if (e.target.getAttribute('data-act') !== 'set') return;
    var id = parseInt(e.target.closest('.pos-line').getAttribute('data-id'), 10);
    var v = parseInt(e.target.value, 10);
    if (!setQty(id, isNaN(v) ? 0 : v)) e.target.value = qtyInCart(id);
    else refresh({ changed: id });
  });
  cartItems.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.getAttribute('data-act') === 'set') { e.preventDefault(); e.target.blur(); }
  });

  $('#btnClear').addEventListener('click', function () {
    if (!state.items.length && !state.paiements.length) return;
    SP.confirm('Vider le panier et annuler les paiements saisis ?', { type: 'danger', title: 'Vider le panier', confirmLabel: 'Vider' }).then(function (ok) { if (ok) resetSale(); });
  });

  /* ---------------- Remise ---------------- */
  $$('input[name="remiseType"]').forEach(function (r) {
    r.addEventListener('change', function () {
      state.remiseType = r.value;
      var input = $('#remiseValeur');
      input.disabled = r.value === 'aucune';
      input.placeholder = r.value === 'pourcentage' ? '% de remise' : (r.value === 'montant' ? 'Montant' : 'Valeur');
      if (r.value === 'aucune') { input.value = ''; state.remiseValeur = 0; } else input.focus();
      refresh({});
    });
  });
  $('#remiseValeur').addEventListener('input', function () {
    var v = parseFloat(this.value) || 0;
    if (state.remiseType === 'pourcentage' && v > 100) { v = 100; this.value = 100; }
    state.remiseValeur = Math.max(0, v);
    refresh({});
  });

  /* ---------------- Paiements ---------------- */
  function selectedMode() { var r = $('input[name="payMode"]:checked'); return r ? r.value : 'especes'; }
  function commitPending() {
    var input = $('#paiementMontant');
    var montant = pendingAmount();
    if (montant <= 0) return false;
    state.paiements.push({ mode: selectedMode(), montant: montant });
    input.value = '';
    refresh({});
    return true;
  }
  $('#btnAddPay').addEventListener('click', function () {
    if (!commitPending()) {
      var t = totals();
      if (t.resteSansPending > 0) { $('#paiementMontant').value = t.resteSansPending; commitPending(); }
    }
  });
  $('#paiementMontant').addEventListener('input', function () { renderTotals(); });
  $('#paiementMontant').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) { e.preventDefault(); commitPending(); }
  });
  $('#cashBtns').addEventListener('click', function (e) {
    var b = e.target.closest('[data-cash]');
    if (!b) return;
    var input = $('#paiementMontant');
    var v = b.getAttribute('data-cash');
    if (v === 'exact') input.value = Math.max(0, totals().resteSansPending) || '';
    else if (v === 'clear') input.value = '';
    else input.value = (parseFloat(input.value) || 0) + parseFloat(v);
    renderTotals();
    bump($('#balanceValue'));
  });
  $('#paiementsList').addEventListener('click', function (e) {
    var b = e.target.closest('[data-remove-pay]');
    if (!b) return;
    state.paiements.splice(parseInt(b.getAttribute('data-remove-pay'), 10), 1);
    refresh({});
  });
  $('#observations').addEventListener('input', function () { state.observations = this.value; save(); });

  /* ---------------- Client : recherche avec suggestions ---------------- */
  var combo = $('#clientCombo'), clientInput = $('#clientSearch'), clientList = $('#clientList');
  var comboIdx = 0, comboMatches = [];
  function selectClient(id, label) {
    state.clientId = id ? String(id) : '';
    state.clientLabel = id ? label : '';
    $('#clientSelect').value = state.clientId;
    clientInput.value = state.clientLabel;
    combo.classList.toggle('has-value', !!state.clientId);
    combo.classList.remove('is-open');
    renderTotals();
    save();
  }
  function renderCombo() {
    var q = norm(clientInput.value.trim());
    comboMatches = D.clients.filter(function (c) { return !q || norm(c.label + ' ' + (c.tel || '')).indexOf(q) > -1; }).slice(0, 40);
    comboIdx = 0;
    if (!comboMatches.length) {
      clientList.innerHTML = '<div class="sp-combo-empty">Aucun client trouvé.<br><a href="#" data-bs-toggle="modal" data-bs-target="#clientQuickModal">Créer « ' + SP.esc(clientInput.value.trim()) + ' »</a></div>';
    } else {
      clientList.innerHTML = comboMatches.map(function (c, i) {
        return '<div class="sp-combo-option' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '" role="option">' +
          '<i class="fa-solid fa-user text-muted"></i><span class="flex-grow-1">' + SP.esc(c.label) + '</span>' + (c.tel ? '<small>' + SP.esc(c.tel) + '</small>' : '') + '</div>';
      }).join('');
    }
    combo.classList.add('is-open');
  }
  clientInput.addEventListener('focus', function () { if (state.clientId) clientInput.select(); renderCombo(); });
  clientInput.addEventListener('input', renderCombo);
  clientInput.addEventListener('keydown', function (e) {
    var opts = $$('.sp-combo-option', clientList);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!opts.length) return;
      comboIdx = (comboIdx + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
      opts.forEach(function (o, i) { o.classList.toggle('active', i === comboIdx); });
      opts[comboIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (comboMatches[comboIdx]) selectClient(comboMatches[comboIdx].id, comboMatches[comboIdx].label);
    } else if (e.key === 'Escape') {
      combo.classList.remove('is-open');
      clientInput.value = state.clientLabel;
    }
  });
  clientList.addEventListener('mousedown', function (e) {
    var o = e.target.closest('.sp-combo-option');
    if (!o) return;
    e.preventDefault();
    var c = comboMatches[parseInt(o.getAttribute('data-idx'), 10)];
    if (c) selectClient(c.id, c.label);
  });
  clientInput.addEventListener('blur', function () {
    setTimeout(function () { combo.classList.remove('is-open'); clientInput.value = state.clientLabel; }, 150);
  });
  $('#clientClear').addEventListener('click', function () { selectClient('', ''); clientInput.focus(); });

  // Création rapide d'un client
  var cqModalEl = $('#clientQuickModal');
  cqModalEl.addEventListener('show.bs.modal', function () {
    $('#cqError').classList.add('d-none');
    var typed = clientInput.value.trim();
    if (typed && typed !== state.clientLabel) {
      var parts = typed.split(/\s+/);
      $('#cqNom').value = parts.shift();
      $('#cqPrenom').value = parts.join(' ');
    }
  });
  cqModalEl.addEventListener('shown.bs.modal', function () { $('#cqNom').focus(); });
  $('#clientQuickForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var nom = $('#cqNom').value.trim();
    var err = $('#cqError');
    if (!nom) { err.textContent = 'Le nom est obligatoire.'; err.classList.remove('d-none'); $('#cqNom').focus(); return; }
    var btn = $('#cqSubmit');
    btn.classList.add('is-loading');
    SP.fetchJSON(SP.url('api/client_creer.php'), { body: {
      nom: nom, prenom: $('#cqPrenom').value.trim(), telephone: $('#cqTel').value.trim(),
      type: ($('input[name="cq_type"]:checked') || {}).value || 'particulier'
    } }).then(function (data) {
      btn.classList.remove('is-loading');
      if (!data.success) { err.textContent = data.message || 'Création impossible.'; err.classList.remove('d-none'); return; }
      D.clients.push(data.client);
      D.clients.sort(function (a, b) { return a.label.localeCompare(b.label, 'fr'); });
      selectClient(data.client.id, data.client.label);
      bootstrap.Modal.getOrCreateInstance(cqModalEl).hide();
      $('#clientQuickForm').reset();
      SP.toast({ type: 'success', title: 'Client créé', message: data.client.label + ' est sélectionné pour cette vente.' });
    }).catch(function () {
      btn.classList.remove('is-loading');
      err.textContent = 'Erreur réseau. Merci de réessayer.';
      err.classList.remove('d-none');
    });
  });

  /* ---------------- Ventes en attente ---------------- */
  function heldList() { return SP.store.get(HELD_KEY, []) || []; }
  function renderHeld() {
    var list = heldList();
    $('#heldCount').textContent = list.length;
    $('#heldBtn').classList.toggle('text-warning', list.length > 0);
    var menu = $('#heldMenu');
    if (!list.length) {
      menu.innerHTML = '<div class="px-3 py-3 small text-muted text-center"><i class="fa-regular fa-clock d-block fs-4 mb-2 opacity-50"></i>Aucune vente en attente.<br>Utilisez <i class="fa-solid fa-circle-pause"></i> pour mettre une vente de côté.</div>';
      return;
    }
    menu.innerHTML = '<h6 class="dropdown-header">Ventes en attente</h6>' + list.map(function (h) {
      return '<div class="dropdown-item d-flex align-items-center gap-2" style="cursor:default;">' +
        '<div class="flex-grow-1" style="min-width:0;"><div class="fw-semibold text-truncate">' + SP.esc(h.label) + '</div>' +
        '<div class="small text-muted">' + SP.esc(h.count + ' article(s) · ' + money(h.total) + ' · ' + h.time) + '</div></div>' +
        '<button type="button" class="btn btn-sm btn-soft-primary" data-held-restore="' + h.id + '" title="Reprendre"><i class="fa-solid fa-play"></i></button>' +
        '<button type="button" class="btn btn-sm btn-ghost" data-held-delete="' + h.id + '" title="Supprimer"><i class="fa-solid fa-trash-can"></i></button></div>';
    }).join('');
  }
  function holdCurrent(silent) {
    if (!state.items.length) { if (!silent) SP.toast({ type: 'info', message: 'Le panier est vide : rien à mettre en attente.' }); return false; }
    var t = totals();
    var list = heldList();
    var now = new Date();
    list.unshift({
      id: Date.now(),
      label: state.clientLabel || ('Vente en attente n° ' + (list.length + 1)),
      time: ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2),
      count: state.items.length, total: t.total,
      state: JSON.parse(JSON.stringify(state))
    });
    SP.store.set(HELD_KEY, list.slice(0, 20));
    resetSale();
    renderHeld();
    bump($('#heldCount'));
    if (!silent) SP.toast({ type: 'success', title: 'Vente mise en attente', message: 'Reprenez-la à tout moment depuis le bouton « ventes en attente » du panier.' });
    return true;
  }
  $('#btnHold').addEventListener('click', function () { holdCurrent(false); });
  $('#heldMenu').addEventListener('click', function (e) {
    var r = e.target.closest('[data-held-restore]'), d = e.target.closest('[data-held-delete]');
    if (!r && !d) return;
    e.stopPropagation();
    var id = parseInt((r || d).getAttribute(r ? 'data-held-restore' : 'data-held-delete'), 10);
    var list = heldList();
    var entry = list.filter(function (h) { return h.id === id; })[0];
    list = list.filter(function (h) { return h.id !== id; });
    SP.store.set(HELD_KEY, list);
    if (r && entry) {
      if (state.items.length) holdCurrent(true);
      state = Object.assign(freshState(), entry.state);
      state.items = state.items.filter(function (it) { return art(it.id) && canSet(it.id, it.qte, true); });
      applyStateToForm();
      refresh({});
      SP.toast({ type: 'success', title: 'Vente reprise', message: entry.label });
      var dd = bootstrap.Dropdown.getInstance($('#heldBtn'));
      if (dd) dd.hide();
    }
    renderHeld();
  });

  function applyStateToForm() {
    var r = $('input[name="remiseType"][value="' + state.remiseType + '"]') || $('#remiseAucune');
    r.checked = true;
    $('#remiseValeur').disabled = state.remiseType === 'aucune';
    $('#remiseValeur').value = state.remiseType === 'aucune' ? '' : state.remiseValeur;
    $('#observations').value = state.observations || '';
    if (state.observations) { var c = $('#obsWrap'); if (c) c.classList.add('show'); }
    selectClient(state.clientId, state.clientLabel);
    var seg = $('#remiseSeg');
    if (seg && seg.__glider) seg.__glider.update(false);
  }

  /* ---------------- Validation de la vente ---------------- */
  function valider() {
    if (!state.items.length || $('#btnValider').disabled) return;
    commitPending();
    var t = totals();
    if (t.reste > 0.009 && !state.clientId) {
      SP.toast({ type: 'warning', title: 'Client requis', message: 'Sélectionnez un client pour une vente à crédit (paiement incomplet).' });
      clientInput.focus();
      return;
    }
    var detail = 'Total : ' + money(t.total) + '. ';
    if (t.reste > 0.009) detail += 'Payé : ' + money(t.paye) + ' — reste dû ' + money(t.reste) + ' (vente à crédit pour ' + state.clientLabel + ').';
    else if (t.reste < -0.009) detail += 'Reçu : ' + money(t.paye) + ' — monnaie à rendre : ' + money(-t.reste) + '.';
    else detail += 'Paiement exact.';
    SP.confirm(detail, { title: 'Valider la vente ?', confirmLabel: 'Valider la vente', type: 'success', icon: 'fa-cash-register' })
      .then(function (ok) { if (ok) envoyer(t); });
  }

  function envoyer(t) {
    var btn = $('#btnValider');
    btn.disabled = true;
    btn.classList.add('is-loading');
    SP.fetchJSON('valider.php', { body: {
      client_id: state.clientId,
      remise_type: state.remiseType,
      remise_valeur: t.remiseValeur,
      paiements: state.paiements.map(function (p) { return { mode: p.mode, montant: p.montant }; }),
      items: state.items.map(function (it) { return { id: it.id, qte: it.qte, prix: prixEffectif(it.id, it.qte) }; }),
      observations: state.observations || ''
    } }).then(function (data) {
      btn.classList.remove('is-loading');
      if (!data.success) {
        SP.toast({ type: 'danger', title: 'Vente non enregistrée', message: data.message || 'Erreur lors de la validation de la vente.' });
        renderTotals();
        return;
      }
      state.items.forEach(function (it) {
        var a = art(it.id);
        if (a && a.type !== 'service') { a.stock = Math.max(0, a.stock - it.qte); updateCardStock(it.id); }
      });
      showSuccess(data);
      resetSale();
      refreshVentesJour();
      if (SP.notif) { SP.notif.invalidate(); SP.notif.load(true); }
    }).catch(function () {
      btn.classList.remove('is-loading');
      renderTotals();
      SP.toast({ type: 'danger', title: 'Erreur réseau', message: 'La vente n\'a pas pu être envoyée. Vérifiez la connexion et réessayez : le panier est conservé.' });
    });
  }

  var successModalEl = $('#saleSuccessModal');
  function showSuccess(data) {
    var base = 'facture.php?id=' + encodeURIComponent(data.vente_id);
    var primary = D.formatRecu === 'ticket' ? 'ticket' : 'a4';
    var secondary = primary === 'ticket' ? 'a4' : 'ticket';
    var labels = { ticket: '<i class="fa-solid fa-receipt me-1"></i>Ticket de caisse', a4: '<i class="fa-solid fa-file-invoice me-1"></i>Facture A4' };
    $('#ssPrimary').href = base + '&format=' + primary + '&print=1';
    $('#ssPrimary').innerHTML = '<i class="fa-solid fa-print me-1"></i>Imprimer : ' + (primary === 'ticket' ? 'ticket' : 'facture A4');
    $('#ssSecondary').href = base + '&format=' + secondary + '&print=1';
    $('#ssSecondary').innerHTML = labels[secondary];
    $('#ssNumero').textContent = 'N° ' + data.numero;
    var reste = data.reste_a_payer || 0, rendu = data.monnaie_rendue || 0;
    var changeEl = $('#ssChange');
    if (reste > 0.009) {
      $('#ssChangeLabel').textContent = 'Reste dû (vente à crédit)';
      changeEl.style.color = 'var(--sp-danger)';
      changeEl.innerHTML = SP.esc(SP.num(reste)) + '<small class="cur">' + SP.esc(DEVISE) + '</small>';
    } else {
      $('#ssChangeLabel').textContent = rendu > 0.009 ? 'Monnaie à rendre' : 'Paiement exact';
      changeEl.style.color = '';
      changeEl.innerHTML = SP.esc(SP.num(rendu)) + '<small class="cur">' + SP.esc(DEVISE) + '</small>';
    }
    $('#ssTotalLine').textContent = 'Total de la vente : ' + money(data.montant_total || 0) + ' · encaissé : ' + money(data.montant_paye || 0);
    // Relance les animations SVG
    var svg = successModalEl.querySelector('svg');
    var clone = svg.cloneNode(true);
    svg.parentNode.replaceChild(clone, svg);
    bootstrap.Modal.getOrCreateInstance(successModalEl).show();
    confetti();
  }
  successModalEl.addEventListener('shown.bs.modal', function () { $('#ssNew').focus(); });
  successModalEl.addEventListener('hidden.bs.modal', function () { search.focus(); });

  function confetti() {
    if (SP.reduceMotion) return;
    var colors = ['#B8863B', '#E9D8AE', '#6E1423', '#3F7A52', '#4A6E82', '#D96C5B'];
    var cx = window.innerWidth / 2, cy = window.innerHeight * 0.32;
    for (var i = 0; i < 46; i++) {
      var p = document.createElement('i');
      p.className = 'sp-confetti';
      var angle = Math.random() * Math.PI * 2, dist = 120 + Math.random() * 260;
      p.style.left = cx + 'px';
      p.style.top = cy + 'px';
      p.style.background = colors[i % colors.length];
      p.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
      p.style.setProperty('--dy', (Math.sin(angle) * dist + 180) + 'px');
      p.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
      p.style.animationDelay = (Math.random() * 0.12) + 's';
      document.body.appendChild(p);
      (function (el) { setTimeout(function () { el.remove(); }, 1500); })(p);
    }
  }

  function refreshVentesJour() {
    fetch('ventes_jour_fragment.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
      .then(function (html) {
        var card = $('#ventesJourCard');
        card.innerHTML = html;
        var first = card.querySelector('tbody tr');
        if (first) first.classList.add('is-new');
      })
      .catch(function () { /* la liste se mettra à jour au prochain chargement */ });
  }

  $('#btnValider').addEventListener('click', valider);

  /* ---------------- Calculateur d'impression ---------------- */
  var printModalEl = $('#printCalcModal');
  var pc = {
    doc: $('#printDocType'), pages: $('#printPages'),
    relCheck: $('#printReliureCheck'), relSel: $('#printReliureSelect'), relQte: $('#printReliureQte'),
    couvCheck: $('#printCouvertureCheck'), couvSel: $('#printCouvertureSelect'), couvQte: $('#printCouvertureQte')
  };
  function printLines() {
    var lines = [];
    var docId = parseInt(pc.doc.value, 10);
    if (docId) lines.push({ id: docId, qte: Math.max(1, parseInt(pc.pages.value, 10) || 1), role: 'doc' });
    if (pc.relCheck.checked && pc.relSel.value) lines.push({ id: parseInt(pc.relSel.value, 10), qte: Math.max(1, parseInt(pc.relQte.value, 10) || 1), role: 'reliure' });
    if (pc.couvCheck.checked && pc.couvSel.value) lines.push({ id: parseInt(pc.couvSel.value, 10), qte: Math.max(1, parseInt(pc.couvQte.value, 10) || 1), role: 'couverture' });
    return lines;
  }
  function refreshPrint() {
    var lines = printLines();
    var preview = $('#printPricePreview');
    var docLine = lines.filter(function (l) { return l.role === 'doc'; })[0];
    if (!docLine) {
      preview.innerHTML = '<span class="text-muted small">Choisissez un type d\'impression et un nombre de pages.</span>';
      $('#printTiers').innerHTML = '';
    } else {
      var a = art(docLine.id), pu = prixEffectif(docLine.id, docLine.qte);
      $('#printTiers').innerHTML = tiersHtml(docLine.id, docLine.qte);
      preview.innerHTML = '<span>' + (pu < a.prix ? '<span class="pos-tier me-2">Tarif dégressif</span>' : '') + SP.esc(a.nom) + ' — ' + SP.esc(SP.num(pu)) + ' ' + SP.esc(DEVISE) + ' / ' + SP.esc(a.unite) + ' × ' + SP.esc(SP.num(docLine.qte)) + '</span><strong>' + SP.esc(money(pu * docLine.qte)) + '</strong>';
    }
    var total = lines.reduce(function (s, l) { return s + prixEffectif(l.id, l.qte) * l.qte; }, 0);
    var el = $('#printGrandTotal');
    el.innerHTML = SP.esc(SP.num(total)) + '<small class="cur">' + SP.esc(DEVISE) + '</small>';
    bump(el);
  }
  // Pré-sélectionne l'article le plus probable (spirale / couverture) sans jamais imposer un choix hasardeux
  function suggest(select, pattern) {
    if (select.value) return;
    for (var i = 1; i < select.options.length; i++) {
      if (pattern.test(norm(select.options[i].textContent))) { select.selectedIndex = i; return; }
    }
  }
  [[pc.relCheck, pc.relSel, pc.relQte, /spirale|reliure|thermocoll/], [pc.couvCheck, pc.couvSel, pc.couvQte, /couverture|cartonn/]].forEach(function (g) {
    g[0].addEventListener('change', function () {
      g[1].disabled = !g[0].checked; g[2].disabled = !g[0].checked;
      if (g[0].checked) { suggest(g[1], g[3]); g[1].focus(); }
      refreshPrint();
    });
  });
  [pc.doc, pc.pages, pc.relSel, pc.relQte, pc.couvSel, pc.couvQte].forEach(function (el) {
    el.addEventListener('input', refreshPrint);
    el.addEventListener('change', refreshPrint);
  });
  printModalEl.addEventListener('shown.bs.modal', function () { (pc.doc.value ? pc.pages : pc.doc).focus(); });
  $('#printCalcConfirm').addEventListener('click', function () {
    var lines = printLines();
    if (!lines.some(function (l) { return l.role === 'doc'; })) {
      SP.toast({ type: 'warning', message: 'Veuillez choisir un type d\'impression.' });
      pc.doc.focus();
      return;
    }
    // Regroupe les quantités par article et vérifie le stock des produits
    var merged = {};
    lines.forEach(function (l) { merged[l.id] = (merged[l.id] || 0) + l.qte; });
    var ids = Object.keys(merged);
    for (var i = 0; i < ids.length; i++) {
      var id = parseInt(ids[i], 10);
      if (!canSet(id, qtyInCart(id) + merged[id])) return;
    }
    ids.forEach(function (idStr) {
      var id = parseInt(idStr, 10);
      var it = findItem(id);
      if (it) it.qte += merged[id]; else state.items.push({ id: id, qte: merged[id] });
    });
    refresh({ changed: parseInt(ids[0], 10), isNew: true });
    beep(true);
    bootstrap.Modal.getOrCreateInstance(printModalEl).hide();
    SP.toast({ type: 'success', title: 'Travail d\'impression ajouté', message: ids.length + ' ligne(s) ajoutée(s) au panier.' });
    pc.doc.value = ''; pc.pages.value = 1;
    pc.relCheck.checked = false; pc.relSel.disabled = true; pc.relSel.value = ''; pc.relQte.disabled = true; pc.relQte.value = 1;
    pc.couvCheck.checked = false; pc.couvSel.disabled = true; pc.couvSel.value = ''; pc.couvQte.disabled = true; pc.couvQte.value = 2;
    refreshPrint();
  });

  /* ---------------- Panier mobile (tiroir) ---------------- */
  var cartEl = $('#posCart'), backdrop = $('#posBackdrop');
  function openCart() { cartEl.classList.add('open'); backdrop.style.opacity = '1'; backdrop.style.visibility = 'visible'; document.body.style.overflow = 'hidden'; }
  function closeCart() { cartEl.classList.remove('open'); backdrop.style.opacity = ''; backdrop.style.visibility = ''; document.body.style.overflow = ''; }
  $('#posFab').addEventListener('click', openCart);
  $('#btnCartClose').addEventListener('click', closeCart);
  backdrop.addEventListener('click', closeCart);
  window.addEventListener('resize', SP.debounce(function () { if (window.innerWidth >= 992) closeCart(); }, 150));

  /* ---------------- Raccourcis clavier ---------------- */
  var focusSearch = function () { if (window.innerWidth < 992) closeCart(); search.focus(); search.select(); };
  SP.shortcuts.register('F2', 'Rechercher / scanner un article', focusSearch, { group: 'Caisse', global: true });
  SP.shortcuts.register('F4', 'Calculateur d\'impression', function () { bootstrap.Modal.getOrCreateInstance(printModalEl).show(); }, { group: 'Caisse', global: true });
  SP.shortcuts.register('F7', 'Rechercher un client', function () { if (window.innerWidth < 992) openCart(); clientInput.focus(); }, { group: 'Caisse', global: true });
  SP.shortcuts.register('F8', 'Saisir le montant reçu', function () { if (window.innerWidth < 992) openCart(); $('#paiementMontant').focus(); }, { group: 'Caisse', global: true });
  SP.shortcuts.register('F9', 'Valider la vente', valider, { group: 'Caisse', global: true });
  SP.shortcuts.register('ctrl+Enter', 'Valider la vente', valider, { group: 'Caisse', global: true, display: 'ctrl+Entrée' });

  var soundBtn = $('#posSoundToggle');
  if (soundBtn) soundBtn.addEventListener('click', function () { soundOn = !soundOn; SP.store.setRaw(SOUND_KEY, soundOn ? '1' : '0'); renderSoundBtn(); if (soundOn) beep(true); });

  /* ---------------- Démarrage ---------------- */
  applyView(view);
  renderSoundBtn();
  renderHeld();
  var restored = restore();
  applyStateToForm();
  refresh({});
  if (restored) {
    SP.toast({ type: 'info', title: 'Panier restauré', message: 'La vente en cours avant le rechargement a été récupérée.' + (restored.dropped ? ' ' + restored.dropped + ' article(s) indisponible(s) retiré(s).' : ''), duration: 4000 });
  }
  if (window.innerWidth >= 992) setTimeout(function () { search.focus(); }, 350);
})();
