/* =========================================================
   Secrétariat Pro - JavaScript principal (v2)
   100 % local : aucune dépendance externe hormis Bootstrap (fourni).
   Expose l'espace de noms global `SP` (toasts, confirmations, thème,
   palette de commandes, notifications, tableaux, formulaires...).
   ========================================================= */
(function (window, document) {
  'use strict';

  var SP = window.SP = window.SP || {};
  var cfg = SP.cfg = window.SP_CONFIG || {};
  var ROOT = cfg.root || '';
  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  SP.reduceMotion = reduceMotion;

  /* ===================== Utilitaires ===================== */
  SP.$ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  SP.$$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
  SP.url = function (path) { return ROOT + path; };

  var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  SP.esc = function (s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return ESC[c]; }); };

  var nfCache = {};
  SP.num = function (n, decimals) {
    decimals = decimals || 0;
    var key = 'd' + decimals;
    if (!nfCache[key]) {
      try { nfCache[key] = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: decimals }); }
      catch (e) { nfCache[key] = { format: function (v) { return String(Math.round(v)); } }; }
    }
    return nfCache[key].format(Number(n) || 0).replace(/[  ]/g, ' ');
  };
  SP.money = function (n) { return SP.num(n) + ' ' + (cfg.devise || 'FCFA'); };
  SP.compact = function (n) {
    try { return new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 }).format(n).replace(/[  ]/g, ' '); }
    catch (e) { return SP.num(n); }
  };

  SP.debounce = function (fn, delay) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  };
  SP.throttle = function (fn, delay) {
    var last = 0, timer;
    return function () {
      var ctx = this, args = arguments, now = Date.now();
      if (now - last >= delay) { last = now; fn.apply(ctx, args); }
      else { clearTimeout(timer); timer = setTimeout(function () { last = Date.now(); fn.apply(ctx, args); }, delay - (now - last)); }
    };
  };

  /** Stockage local protégé (navigation privée, quotas...). */
  SP.store = {
    get: function (key, fallback) {
      try { var v = localStorage.getItem(key); return v === null ? fallback : JSON.parse(v); } catch (e) { return fallback; }
    },
    set: function (key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignoré */ } },
    remove: function (key) { try { localStorage.removeItem(key); } catch (e) { /* ignoré */ } },
    raw: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
    setRaw: function (key, value) { try { localStorage.setItem(key, value); } catch (e) { /* ignoré */ } }
  };

  /** Requête JSON avec jeton CSRF et gestion des erreurs courantes. */
  SP.fetchJSON = function (url, opts) {
    opts = opts || {};
    var headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf || '' }, opts.headers || {});
    var init = { method: opts.method || (opts.body ? 'POST' : 'GET'), headers: headers, credentials: 'same-origin' };
    if (opts.signal) init.signal = opts.signal;
    if (opts.body !== undefined) {
      if (opts.body instanceof FormData) {
        init.body = opts.body;
      } else {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(Object.assign({ csrf_token: cfg.csrf || '' }, opts.body));
      }
    }
    return fetch(url, init).then(function (r) {
      return r.text().then(function (txt) {
        var data;
        try { data = txt ? JSON.parse(txt) : {}; }
        catch (e) { var err = new Error('Réponse inattendue du serveur.'); err.status = r.status; throw err; }
        if (r.status === 401 && data && data.login) {
          SP.toast({ type: 'warning', title: 'Session expirée', message: 'Vous allez être redirigé vers la page de connexion.' });
          setTimeout(function () { window.location.href = ROOT + 'login.php'; }, 1800);
        }
        if (!r.ok && data && data.success === undefined) data.success = false;
        return data;
      });
    });
  };

  /** Charge un script local à la demande (ex. jsPDF). */
  var loadedScripts = {};
  SP.loadScript = function (path) {
    if (!loadedScripts[path]) {
      loadedScripts[path] = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = ROOT + path + '?v=' + encodeURIComponent(cfg.version || '1');
        s.onload = resolve;
        s.onerror = function () { delete loadedScripts[path]; reject(new Error('Chargement impossible : ' + path)); };
        document.head.appendChild(s);
      });
    }
    return loadedScripts[path];
  };

  /** Crée et soumet un formulaire POST (avec jeton CSRF). */
  SP.postTo = function (url, fields) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = url;
    form.style.display = 'none';
    var all = Object.assign({ csrf_token: cfg.csrf || '' }, fields || {});
    Object.keys(all).forEach(function (k) {
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = k; input.value = all[k];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    SP.progress.start();
    form.submit();
  };

  /* ===================== Barre de progression de navigation ===================== */
  SP.progress = (function () {
    var el, timer, value = 0;
    function bar() { return el || (el = document.getElementById('spProgress')); }
    function set(v) { value = v; if (bar()) bar().style.transform = 'scaleX(' + v + ')'; }
    return {
      start: function () {
        if (!bar()) return;
        clearInterval(timer);
        bar().classList.remove('is-done');
        bar().classList.add('is-active');
        set(0.08);
        timer = setInterval(function () { if (value < 0.9) set(value + (0.9 - value) * 0.08); }, 180);
      },
      done: function () {
        if (!bar()) return;
        clearInterval(timer);
        bar().classList.add('is-done');
        setTimeout(function () { bar().classList.remove('is-active', 'is-done'); set(0); }, 650);
      }
    };
  })();

  /* ===================== Notifications éphémères (toasts) ===================== */
  var TOAST_META = {
    success: ['fa-circle-check', 'Succès'],
    danger: ['fa-circle-exclamation', 'Erreur'],
    warning: ['fa-triangle-exclamation', 'Attention'],
    info: ['fa-circle-info', 'Information']
  };
  SP.toast = function (opts) {
    if (typeof opts === 'string') opts = { message: opts };
    var type = TOAST_META[opts.type] ? opts.type : (opts.type === 'error' ? 'danger' : 'info');
    var meta = TOAST_META[type];
    var duration = opts.duration !== undefined ? opts.duration : (type === 'danger' ? 8000 : (type === 'success' ? 4000 : 5000));
    var box = document.getElementById('spToasts');
    if (!box) {
      box = document.createElement('div');
      box.id = 'spToasts'; box.className = 'sp-toasts';
      document.body.appendChild(box);
    }
    while (box.children.length >= 5) box.removeChild(box.firstChild);

    var el = document.createElement('div');
    el.className = 'sp-toast is-' + type;
    el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    var msg = opts.html ? opts.html : SP.esc(opts.message || '');
    el.innerHTML =
      '<div class="t-icon"><i class="fa-solid ' + (opts.icon || meta[0]) + '"></i></div>' +
      '<div><div class="t-title">' + SP.esc(opts.title || meta[1]) + '</div>' + (msg ? '<div class="t-msg">' + msg + '</div>' : '') + '</div>' +
      '<button type="button" class="t-close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>' +
      (duration > 0 ? '<div class="t-progress"></div>' : '');
    box.appendChild(el);

    var closed = false, anim = null, fallback = null;
    function close() {
      if (closed) return;
      closed = true;
      clearTimeout(fallback);
      el.classList.add('is-leaving');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 360);
    }
    el.querySelector('.t-close').addEventListener('click', close);
    if (duration > 0) {
      var prog = el.querySelector('.t-progress');
      if (prog && prog.animate) {
        anim = prog.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], { duration: duration, easing: 'linear', fill: 'forwards' });
        anim.onfinish = close;
        el.addEventListener('mouseenter', function () { anim.pause(); });
        el.addEventListener('mouseleave', function () { anim.play(); });
      } else {
        fallback = setTimeout(close, duration);
      }
    }
    return { close: close, el: el };
  };

  /* ===================== Boîte de dialogue de confirmation ===================== */
  /* Remplace window.confirm() par une modale stylée. Usage :
       SP.confirm('Supprimer cet élément ?', { type: 'danger' }).then(function (ok) { ... });
     Options : title, confirmLabel, cancelLabel, type ('danger' | 'success' | 'default'), icon */
  SP.confirm = function (message, options) {
    options = options || {};
    return new Promise(function (resolve) {
      var modalEl = document.getElementById('spConfirmModal');
      if (!modalEl || typeof bootstrap === 'undefined') {
        resolve(window.confirm(message || 'Confirmer cette action ?'));
        return;
      }
      var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      var okBtn = document.getElementById('spConfirmOk');
      var cancelBtn = document.getElementById('spConfirmCancel');
      var iconWrap = document.getElementById('spConfirmIcon');
      var type = options.type || 'default';
      var danger = type === 'danger';

      document.getElementById('spConfirmTitle').textContent = options.title || (danger ? 'Confirmer la suppression' : 'Confirmer');
      document.getElementById('spConfirmMessage').textContent = message || 'Êtes-vous sûr de vouloir continuer ?';
      okBtn.className = 'btn px-4 ' + (danger ? 'btn-danger' : (type === 'success' ? 'btn-success' : 'btn-sp-primary'));
      okBtn.textContent = options.confirmLabel || (danger ? 'Supprimer' : 'Confirmer');
      cancelBtn.textContent = options.cancelLabel || 'Annuler';
      iconWrap.className = 'sp-confirm-icon' + (danger ? ' is-danger' : (type === 'success' ? ' is-success' : ''));
      iconWrap.innerHTML = '<i class="fa-solid ' + (options.icon || (danger ? 'fa-trash-can' : (type === 'success' ? 'fa-circle-check' : 'fa-circle-question'))) + '"></i>';

      var settled = false;
      function finish(result) {
        if (settled) return;
        settled = true;
        okBtn.removeEventListener('click', onOk);
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
        modalEl.removeEventListener('shown.bs.modal', onShown);
        resolve(result);
      }
      function onOk() { finish(true); modal.hide(); }
      function onHidden() { finish(false); }
      function onShown() { okBtn.focus(); }

      okBtn.addEventListener('click', onOk);
      modalEl.addEventListener('hidden.bs.modal', onHidden);
      modalEl.addEventListener('shown.bs.modal', onShown);
      modal.show();
    });
  };
  window.spConfirm = SP.confirm; // compatibilité

  /* ===================== Thème clair / sombre ===================== */
  SP.theme = {
    pref: function () { return SP.store.raw('sp-theme') || 'auto'; },
    isDark: function () { return document.documentElement.getAttribute('data-theme') === 'dark'; },
    apply: function (pref) {
      var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
      var r = document.documentElement;
      r.setAttribute('data-theme', dark ? 'dark' : 'light');
      r.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', dark ? '#1A070B' : '#6E1423');
      document.dispatchEvent(new CustomEvent('sp:themechange', { detail: { dark: dark } }));
    },
    set: function (pref, originEvent) {
      SP.store.setRaw('sp-theme', pref);
      var self = this;
      var run = function () { self.apply(pref); };
      if (document.startViewTransition && !reduceMotion && originEvent && originEvent.clientX !== undefined) {
        var x = originEvent.clientX || window.innerWidth - 60, y = originEvent.clientY || 30;
        var radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
        document.documentElement.classList.add('sp-theme-anim');
        var t = document.startViewTransition(run);
        t.ready.then(function () {
          document.documentElement.animate(
            { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)'] },
            { duration: 550, easing: 'cubic-bezier(.16,1,.3,1)', pseudoElement: '::view-transition-new(root)' }
          );
        }).catch(function () {});
        t.finished.then(function () { document.documentElement.classList.remove('sp-theme-anim'); }, function () { document.documentElement.classList.remove('sp-theme-anim'); });
      } else {
        run();
      }
    },
    toggle: function (e) { this.set(this.isDark() ? 'light' : 'dark', e); }
  };
  if (window.matchMedia) {
    var mq = matchMedia('(prefers-color-scheme: dark)');
    var onSystemChange = function () { if (SP.theme.pref() === 'auto') SP.theme.apply('auto'); };
    if (mq.addEventListener) mq.addEventListener('change', onSystemChange); else if (mq.addListener) mq.addListener(onSystemChange);
  }

  /* ===================== Graphiques (Chart.js, thème dynamique) ===================== */
  SP.charts = {
    instances: [],
    css: function (name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); },
    colors: function () {
      var c = this.css.bind(this);
      return {
        text: c('--sp-text'), muted: c('--sp-text-muted'), subtle: c('--sp-text-subtle'), border: c('--sp-border'),
        surface: c('--sp-surface'), primary: c('--sp-primary-text'), primaryFill: c('--sp-primary'), accent: c('--sp-accent'),
        success: c('--sp-success'), info: c('--sp-info'), danger: c('--sp-danger'), warning: c('--sp-warning'),
        primaryRgb: c('--sp-primary-rgb'), accentRgb: c('--sp-accent-rgb'), successRgb: c('--sp-success-rgb'), infoRgb: c('--sp-info-rgb'),
        dark: SP.theme.isDark()
      };
    },
    palette: function () {
      var k = this.colors();
      return [k.accent, k.primary, k.info, k.success, '#8C5A8A', '#C06A45', '#3E8C87', '#7D7A3E', k.warning, k.danger];
    },
    applyDefaults: function () {
      if (!window.Chart) return;
      var k = this.colors();
      var d = Chart.defaults;
      d.font.family = "'Inter', -apple-system, 'Segoe UI', sans-serif";
      d.font.size = 12;
      d.color = k.muted;
      d.borderColor = k.border;
      d.animation.duration = reduceMotion ? 0 : 900;
      d.animation.easing = 'easeOutQuart';
      d.plugins.legend.labels.usePointStyle = true;
      d.plugins.legend.labels.boxWidth = 8;
      d.plugins.legend.labels.boxHeight = 8;
      d.plugins.legend.labels.padding = 14;
      var tt = d.plugins.tooltip;
      tt.backgroundColor = k.dark ? '#F2EADF' : '#241A16';
      tt.titleColor = k.dark ? '#1C1714' : '#F6EFE3';
      tt.bodyColor = k.dark ? '#1C1714' : '#F6EFE3';
      tt.padding = 11;
      tt.cornerRadius = 10;
      tt.titleFont = { weight: '700', family: "'Inter', sans-serif" };
      tt.boxPadding = 5;
      tt.usePointStyle = true;
    },
    /** Dégradé vertical pour les aires sous courbe. */
    gradient: function (ctx, rgb, from, to) {
      var chart = ctx.chart, area = chart.chartArea;
      if (!area) return 'rgba(' + rgb + ',' + (from || 0.25) + ')';
      var g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
      g.addColorStop(0, 'rgba(' + rgb + ',' + (from === undefined ? 0.28 : from) + ')');
      g.addColorStop(1, 'rgba(' + rgb + ',' + (to === undefined ? 0.02 : to) + ')');
      return g;
    },
    /** Crée un graphique reconstruit automatiquement au changement de thème. */
    create: function (canvas, factory) {
      if (!window.Chart || !canvas) return null;
      this.applyDefaults();
      var entry = { canvas: canvas, factory: factory, chart: new Chart(canvas, factory(this.colors())) };
      this.instances.push(entry);
      return entry.chart;
    },
    refreshAll: function () {
      var self = this;
      this.applyDefaults();
      this.instances.forEach(function (entry) {
        entry.chart.destroy();
        entry.chart = new Chart(entry.canvas, entry.factory(self.colors()));
      });
    },
    moneyTick: function (v) { return SP.compact(v); }
  };
  document.addEventListener('sp:themechange', function () { SP.charts.refreshAll(); });

  /* ===================== Compteurs animés ===================== */
  SP.countUp = function (el) {
    var target = parseFloat(el.getAttribute('data-countup'));
    if (isNaN(target)) return;
    var format = el.getAttribute('data-format') || 'int';
    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var suffix = el.getAttribute('data-suffix') || '';
    var render = function (v) {
      if (format === 'money') { el.innerHTML = SP.esc(SP.num(v)) + '<small class="cur">' + SP.esc(cfg.devise || 'FCFA') + '</small>'; return; }
      el.textContent = SP.num(v, decimals) + suffix;
    };
    if (reduceMotion || target === 0) { render(target); return; }
    var start = null, dur = Math.min(1600, 700 + Math.log10(Math.abs(target) + 1) * 180);
    var step = function (ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      var eased = 1 - Math.pow(2, -10 * p);
      render(p >= 1 ? target : target * eased);
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  /* ===================== Apparition au défilement ===================== */
  SP.reveal = function (scope) {
    var els = SP.$$('.sp-reveal:not(.is-visible), [data-countup]:not([data-counted])', scope);
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('is-visible'); if (el.hasAttribute('data-countup')) { el.setAttribute('data-counted', '1'); SP.countUp(el); } });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        el.classList.add('is-visible');
        if (el.hasAttribute('data-countup') && !el.hasAttribute('data-counted')) { el.setAttribute('data-counted', '1'); SP.countUp(el); }
        io.unobserve(el);
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });
    els.forEach(function (el, i) {
      if (el.classList.contains('sp-reveal') && !el.style.transitionDelay) el.style.transitionDelay = Math.min(i, 6) * 60 + 'ms';
      io.observe(el);
    });
  };

  /* ===================== Contrôles segmentés (curseur animé) ===================== */
  SP.segments = function (scope) {
    SP.$$('.sp-seg[data-sp-seg]', scope).forEach(function (seg) {
      if (seg.__glider) { seg.__glider.update(false); return; }
      var glider = document.createElement('span');
      glider.className = 'sp-seg-glider';
      seg.insertBefore(glider, seg.firstChild);
      seg.classList.add('has-glider');
      var update = function (animate) {
        var active = seg.querySelector('.active') || (function () {
          var c = seg.querySelector('.btn-check:checked');
          return c ? seg.querySelector('label[for="' + c.id + '"]') : null;
        })();
        if (!active || !active.offsetWidth) { glider.style.width = '0'; return; }
        if (!animate) glider.style.transition = 'none';
        glider.style.width = active.offsetWidth + 'px';
        glider.style.transform = 'translateX(' + active.offsetLeft + 'px)';
        if (!animate) { void glider.offsetWidth; glider.style.transition = ''; }
      };
      seg.__glider = { update: update };
      seg.addEventListener('click', function (e) {
        var target = e.target.closest('button, a, .sp-seg-btn');
        if (target && seg.contains(target) && target.tagName !== 'LABEL' && !target.hasAttribute('data-no-active')) {
          SP.$$('.active', seg).forEach(function (x) { x.classList.remove('active'); });
          target.classList.add('active');
        }
        setTimeout(function () { update(true); }, 0);
      });
      seg.addEventListener('change', function () { update(true); });
      update(false);
      window.addEventListener('resize', SP.debounce(function () { update(false); }, 150));
    });
  };

  /* ===================== Onglets (paramètres, fiches) ===================== */
  SP.tabs = function (scope) {
    SP.$$('.sp-tabs', scope).forEach(function (tabs) {
      var buttons = SP.$$('[data-sp-tab]', tabs);
      var show = function (id, push) {
        buttons.forEach(function (b) {
          var on = b.getAttribute('data-sp-tab') === id;
          b.classList.toggle('active', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
          var pane = document.getElementById(b.getAttribute('data-sp-tab'));
          if (pane) pane.classList.toggle('active', on);
        });
        if (push && history.replaceState) history.replaceState(null, '', '#' + id);
      };
      buttons.forEach(function (b) {
        b.setAttribute('role', 'tab');
        b.addEventListener('click', function () { show(b.getAttribute('data-sp-tab'), true); });
      });
      var fromHash = window.location.hash.replace('#', '');
      if (fromHash && buttons.some(function (b) { return b.getAttribute('data-sp-tab') === fromHash; })) show(fromHash, false);
    });
  };

  /* ===================== Tableaux : tri et filtre instantané ===================== */
  function cellValue(row, index, type) {
    var cell = row.children[index];
    if (!cell) return '';
    var raw = cell.hasAttribute('data-value') ? cell.getAttribute('data-value') : cell.textContent.trim();
    if (type === 'num') {
      var n = parseFloat(String(raw).replace(/[^\d,.\-]/g, '').replace(/\s/g, '').replace(',', '.'));
      return isNaN(n) ? -Infinity : n;
    }
    return String(raw).toLowerCase();
  }
  SP.sortable = function (scope) {
    SP.$$('table[data-sp-sort]', scope).forEach(function (table) {
      if (table.__sortable) return;
      table.__sortable = true;
      SP.$$('thead th[data-sort]', table).forEach(function (th) {
        th.setAttribute('tabindex', '0');
        th.setAttribute('role', 'button');
        var sort = function () {
          var tbody = table.tBodies[0];
          if (!tbody) return;
          var index = Array.prototype.indexOf.call(th.parentNode.children, th);
          var type = th.getAttribute('data-sort') || 'text';
          var asc = !th.classList.contains('is-asc');
          SP.$$('thead th', table).forEach(function (h) { h.classList.remove('is-asc', 'is-desc'); });
          th.classList.add(asc ? 'is-asc' : 'is-desc');
          var rows = SP.$$('tr', tbody).filter(function (r) { return !r.hasAttribute('data-sp-static') && !r.hasAttribute('data-sp-empty'); });
          // Les lignes « compagnons » (ex. formulaire d'édition repliable) suivent leur ligne
          var companions = new Map();
          rows.forEach(function (r) {
            var list = [], n = r.nextElementSibling;
            while (n && n.hasAttribute('data-sp-static')) { list.push(n); n = n.nextElementSibling; }
            companions.set(r, list);
          });
          rows.sort(function (a, b) {
            var va = cellValue(a, index, type), vb = cellValue(b, index, type);
            if (type === 'num') return asc ? va - vb : vb - va;
            return asc ? va.localeCompare(vb, 'fr') : vb.localeCompare(va, 'fr');
          });
          rows.forEach(function (r) { tbody.appendChild(r); companions.get(r).forEach(function (c) { tbody.appendChild(c); }); });
          if (!reduceMotion && rows.length <= 150) {
            rows.forEach(function (r, i) {
              if (r.animate) r.animate([{ opacity: 0.35, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, delay: Math.min(i, 20) * 12, easing: 'ease-out' });
            });
          }
        };
        th.addEventListener('click', sort);
        th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); } });
      });
    });
  };

  /** Filtre instantané des lignes d'un tableau (ou de cartes) selon un champ de saisie. */
  SP.filter = function (input, targetSelector, itemSelector) {
    var target = typeof targetSelector === 'string' ? document.querySelector(targetSelector) : targetSelector;
    if (!input || !target) return;
    var isTable = target.tagName === 'TABLE';
    var getItems = function () { return isTable ? SP.$$('tbody tr:not([data-sp-empty]):not([data-sp-static])', target) : SP.$$(itemSelector || '[data-filter-item]', target); };
    var counter = input.getAttribute('data-sp-filter-count') ? document.querySelector(input.getAttribute('data-sp-filter-count')) : null;
    var emptyRow = null;
    var run = function () {
      var q = input.value.trim().toLowerCase();
      var terms = q.split(/\s+/).filter(Boolean);
      var visible = 0;
      getItems().forEach(function (item) {
        var text = (item.getAttribute('data-search') || item.textContent).toLowerCase();
        var show = terms.every(function (t) { return text.indexOf(t) > -1; });
        item.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (counter) counter.textContent = visible;
      if (isTable) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.setAttribute('data-sp-empty', '1');
          var cols = (target.tHead && target.tHead.rows[0]) ? target.tHead.rows[0].cells.length : 1;
          emptyRow.innerHTML = '<td colspan="' + cols + '" class="text-center text-muted py-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Aucun résultat pour cette recherche.</td>';
        }
        if (visible === 0 && q) { if (!emptyRow.parentNode && target.tBodies[0]) target.tBodies[0].appendChild(emptyRow); }
        else if (emptyRow.parentNode) emptyRow.parentNode.removeChild(emptyRow);
      }
      input.dispatchEvent(new CustomEvent('sp:filtered', { detail: { visible: visible } }));
    };
    input.addEventListener('input', SP.debounce(run, 90));
    if (input.value) run();
  };
  /* Compatibilité avec l'ancienne API */
  window.spTableFilter = function (inputEl, tableSelector) { SP.filter(inputEl, tableSelector); };

  /* ===================== Formulaires ===================== */
  function passwordScore(pw) {
    if (!pw) return 0;
    var s = 0;
    if (pw.length >= 8) s++;
    if (pw.length >= 12) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    if (pw.length < 8) s = Math.min(s, 1);
    return Math.max(1, Math.min(4, s));
  }
  var STRENGTH_LABELS = ['', 'Faible', 'Moyen', 'Bon', 'Excellent'];

  SP.forms = function (scope) {
    // Afficher / masquer les mots de passe + alerte verr. majuscules + robustesse
    SP.$$('input[type="password"]:not([data-sp-enhanced])', scope).forEach(function (input) {
      input.setAttribute('data-sp-enhanced', '1');
      var holder = input;
      if (!input.closest('.input-group') && !input.hasAttribute('data-no-toggle')) {
        var wrap = document.createElement('div');
        wrap.className = 'sp-password';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sp-password-toggle';
        btn.setAttribute('aria-label', 'Afficher le mot de passe');
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
        btn.addEventListener('click', function () {
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
          btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
          input.focus();
        });
        wrap.appendChild(btn);
        holder = wrap;
      }
      // Champ avec icône : les indications se placent sous le conteneur (l'icône reste centrée)
      if (holder.parentNode.classList && holder.parentNode.classList.contains('sp-input-icon')) holder = holder.parentNode;
      var caps = document.createElement('div');
      caps.className = 'sp-capslock';
      caps.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Verrouillage des majuscules activé';
      holder.parentNode.insertBefore(caps, holder.nextSibling);
      var checkCaps = function (e) { if (e.getModifierState) caps.classList.toggle('is-visible', e.getModifierState('CapsLock')); };
      input.addEventListener('keydown', checkCaps);
      input.addEventListener('keyup', checkCaps);
      input.addEventListener('blur', function () { caps.classList.remove('is-visible'); });

      if (input.hasAttribute('data-sp-strength')) {
        var meter = document.createElement('div');
        meter.className = 'sp-strength';
        meter.innerHTML = '<span></span><span></span><span></span><span></span>';
        var label = document.createElement('div');
        label.className = 'sp-strength-label';
        caps.parentNode.insertBefore(meter, caps.nextSibling);
        meter.parentNode.insertBefore(label, meter.nextSibling);
        input.addEventListener('input', function () {
          var sc = input.value ? passwordScore(input.value) : 0;
          meter.setAttribute('data-level', sc);
          label.textContent = sc ? 'Robustesse : ' + STRENGTH_LABELS[sc] : '';
        });
      }
    });

    // Zones de dépôt de fichiers
    SP.$$('.sp-dropzone:not([data-sp-enhanced])', scope).forEach(function (dz) {
      dz.setAttribute('data-sp-enhanced', '1');
      var input = dz.querySelector('input[type="file"]');
      var text = dz.querySelector('.dz-text');
      var preview = dz.querySelector('.dz-preview');
      var original = text ? text.innerHTML : '';
      if (!input) return;
      ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.add('is-dragover'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.remove('is-dragover'); }); });
      input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) { dz.classList.remove('has-file'); if (text) text.innerHTML = original; if (preview) preview.removeAttribute('src'); return; }
        dz.classList.add('has-file');
        var size = f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' Mo' : Math.max(1, Math.round(f.size / 1024)) + ' Ko';
        if (text) text.innerHTML = '<strong>' + SP.esc(f.name) + '</strong>' + size + ' · cliquez pour changer';
        if (preview) {
          if (/^image\//.test(f.type) && window.URL) preview.src = URL.createObjectURL(f);
          else preview.removeAttribute('src');
        }
      });
    });

    // Zones de texte auto-extensibles
    SP.$$('textarea[data-sp-autosize]:not([data-sp-enhanced])', scope).forEach(function (ta) {
      ta.setAttribute('data-sp-enhanced', '1');
      var fit = function () { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight + 2, 420) + 'px'; };
      ta.addEventListener('input', fit);
      fit();
    });
  };

  /* ===================== Liste déroulante avec recherche ===================== */
  // <select data-sp-combo data-placeholder="…"> ; <option data-sub="…"> ajoute une ligne secondaire
  SP.norm = function (s) {
    s = String(s === null || s === undefined ? '' : s).toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  };
  SP.combo = function (scope) {
    SP.$$('select[data-sp-combo]:not([data-sp-enhanced])', scope).forEach(function (select) {
      select.setAttribute('data-sp-enhanced', '1');
      var items = SP.$$('option', select).filter(function (o) { return o.value !== ''; }).map(function (o) {
        var sub = o.getAttribute('data-sub') || '';
        return { value: o.value, label: o.textContent.trim(), sub: sub, key: SP.norm(o.textContent + ' ' + sub) };
      });
      var wrap = document.createElement('div');
      wrap.className = 'sp-combo';
      wrap.innerHTML = '<div class="sp-input-icon"><i class="fa-solid fa-magnifying-glass"></i>' +
        '<input type="text" class="form-control" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"></div>' +
        '<button type="button" class="sp-combo-clear" aria-label="Effacer"><i class="fa-solid fa-xmark"></i></button>' +
        '<div class="sp-combo-list" role="listbox"></div>';
      select.parentNode.insertBefore(wrap, select);
      select.hidden = true;
      var input = wrap.querySelector('input'), list = wrap.querySelector('.sp-combo-list');
      input.placeholder = select.getAttribute('data-placeholder') || 'Rechercher…';
      if (select.id) {
        input.id = select.id + 'Combo';
        SP.$$('label[for="' + select.id + '"]').forEach(function (l) { l.setAttribute('for', input.id); });
      }
      var matches = [], idx = 0;
      function current() {
        for (var i = 0; i < items.length; i++) if (items[i].value === select.value) return items[i];
        return null;
      }
      function sync() {
        var c = current();
        input.value = c ? c.label : '';
        wrap.classList.toggle('has-value', !!c);
      }
      function close() { wrap.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); }
      function render() {
        var q = SP.norm(input.value.trim());
        var c = current();
        if (c && input.value === c.label) q = '';
        matches = items.filter(function (it) { return !q || it.key.indexOf(q) > -1; }).slice(0, 50);
        idx = 0;
        list.innerHTML = matches.length ? matches.map(function (it, i) {
          return '<div class="sp-combo-option' + (i === 0 ? ' active' : '') + '" role="option" data-idx="' + i + '">' +
            '<span>' + SP.esc(it.label) + (it.sub ? '<small class="d-block">' + SP.esc(it.sub) + '</small>' : '') + '</span></div>';
        }).join('') : '<div class="sp-combo-empty">' + SP.esc(select.getAttribute('data-empty') || 'Aucun résultat.') + '</div>';
        wrap.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
      }
      function choose(it) {
        select.value = it ? it.value : '';
        sync();
        close();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        var form = select.form;
        if (form && form.hasAttribute('data-sp-dirty-check')) form.setAttribute('data-sp-dirty', '1');
      }
      input.addEventListener('focus', function () { input.select(); render(); });
      input.addEventListener('input', render);
      input.addEventListener('keydown', function (e) {
        var opts = SP.$$('.sp-combo-option', list);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (!wrap.classList.contains('is-open')) { render(); return; }
          if (!opts.length) return;
          idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
          opts.forEach(function (o, i) { o.classList.toggle('active', i === idx); });
          opts[idx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
          if (wrap.classList.contains('is-open')) { e.preventDefault(); if (matches[idx]) choose(matches[idx]); }
        } else if (e.key === 'Escape') {
          if (wrap.classList.contains('is-open')) { e.stopPropagation(); close(); sync(); }
        }
      });
      list.addEventListener('mousedown', function (e) {
        var o = e.target.closest('.sp-combo-option');
        if (!o) return;
        e.preventDefault();
        choose(matches[parseInt(o.getAttribute('data-idx'), 10)]);
      });
      input.addEventListener('blur', function () { setTimeout(function () { close(); sync(); }, 120); });
      wrap.querySelector('.sp-combo-clear').addEventListener('click', function () { choose(null); input.focus(); });
      select.addEventListener('change', sync);
      sync();
    });
  };

  // Soumission : état de chargement + protection contre le double envoi
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.getAttribute('data-sp-submitting') === '1') { e.preventDefault(); return; }
    setTimeout(function () {
      if (e.defaultPrevented || form.hasAttribute('data-no-loading')) return;
      form.setAttribute('data-sp-submitting', '1');
      form.removeAttribute('data-sp-dirty');
      var btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
      if (btn && btn.classList.contains('btn')) btn.classList.add('is-loading');
      if (form.getAttribute('target') !== '_blank' && (form.method || 'get').toLowerCase() === 'post') SP.progress.start();
      // Sécurité : réactive le formulaire si la page ne change pas (téléchargement, nouvel onglet...)
      setTimeout(function () { form.removeAttribute('data-sp-submitting'); if (btn) btn.classList.remove('is-loading'); SP.progress.done(); }, form.getAttribute('target') === '_blank' ? 1200 : 12000);
    }, 0);
  });

  // Avertissement en cas de modifications non enregistrées
  document.addEventListener('input', function (e) {
    var form = e.target.form || (e.target.closest && e.target.closest('form'));
    if (form && form.hasAttribute('data-sp-dirty-check')) form.setAttribute('data-sp-dirty', '1');
  });
  window.addEventListener('beforeunload', function (e) {
    if (document.querySelector('form[data-sp-dirty="1"]:not([data-sp-submitting="1"])')) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // Empêche la molette de modifier par erreur un champ numérique
  document.addEventListener('wheel', function (e) {
    var el = document.activeElement;
    if (el && el.type === 'number' && el === e.target) el.blur();
  }, { passive: true });

  /* ===================== Confirmations & actions sensibles ===================== */
  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('a[data-confirm], button[data-confirm-href]') : null;
    if (!link) return;
    e.preventDefault();
    var href = link.getAttribute('href') || link.getAttribute('data-confirm-href');
    var isDanger = link.classList.contains('btn-outline-danger') || link.classList.contains('btn-danger') || link.classList.contains('text-danger');
    var type = link.getAttribute('data-confirm-type') || (isDanger ? 'danger' : 'default');
    SP.confirm(link.getAttribute('data-confirm') || 'Confirmer cette action ?', {
      type: type,
      title: link.getAttribute('data-confirm-title') || undefined,
      confirmLabel: link.getAttribute('data-confirm-label') || undefined
    }).then(function (ok) {
      if (!ok) return;
      if ((link.getAttribute('data-method') || '').toLowerCase() === 'post') SP.postTo(href);
      else { SP.progress.start(); window.location.href = href; }
    });
  });
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
    if (form.getAttribute('data-sp-confirmed') === '1') { form.removeAttribute('data-sp-confirmed'); return; }
    e.preventDefault();
    e.stopImmediatePropagation();
    var submitBtn = form.querySelector('button[type="submit"], button:not([type])');
    var isDanger = submitBtn && (submitBtn.classList.contains('btn-outline-danger') || submitBtn.classList.contains('btn-danger'));
    var type = form.getAttribute('data-confirm-type') || (isDanger ? 'danger' : 'default');
    SP.confirm(form.getAttribute('data-confirm') || 'Confirmer cette action ?', {
      type: type,
      title: form.getAttribute('data-confirm-title') || undefined,
      confirmLabel: form.getAttribute('data-confirm-label') || undefined
    }).then(function (ok) {
      if (!ok) return;
      form.setAttribute('data-sp-confirmed', '1');
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });
  }, true);

  /* ===================== Effet d'onde sur les boutons ===================== */
  document.addEventListener('pointerdown', function (e) {
    if (reduceMotion || e.button !== 0) return;
    var btn = e.target.closest ? e.target.closest('.btn') : null;
    if (!btn || btn.disabled || btn.classList.contains('btn-link') || btn.hasAttribute('data-no-ripple')) return;
    var rect = btn.getBoundingClientRect();
    var ink = document.createElement('span');
    ink.className = 'sp-ripple';
    var size = Math.max(rect.width, rect.height);
    ink.style.width = ink.style.height = size / 2 + 'px';
    ink.style.marginLeft = ink.style.marginTop = -size / 4 + 'px';
    ink.style.left = (e.clientX - rect.left) + 'px';
    ink.style.top = (e.clientY - rect.top) + 'px';
    btn.appendChild(ink);
    setTimeout(function () { if (ink.parentNode) ink.parentNode.removeChild(ink); }, 650);
  });

  /* ===================== Palette de commandes (Ctrl+K) ===================== */
  SP.palette = (function () {
    var modal, modalEl, input, results, loading, items = [], active = 0, controller = null, lastQuery = null;

    function norm(s) { return String(s || '').toLowerCase().normalize ? String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') : String(s || '').toLowerCase(); }
    function score(text, q) {
      var t = norm(text), qq = norm(q);
      if (!qq) return 1;
      var idx = t.indexOf(qq);
      if (idx === 0) return 100;
      if (idx > 0) return 80 - Math.min(idx, 40);
      var ti = 0, gaps = 0;
      for (var i = 0; i < qq.length; i++) {
        var found = t.indexOf(qq[i], ti);
        if (found < 0) return 0;
        gaps += found - ti;
        ti = found + 1;
      }
      return Math.max(1, 40 - gaps);
    }
    function highlight(text, q) {
      var safe = SP.esc(text);
      if (!q) return safe;
      var t = norm(text), qq = norm(q), idx = t.indexOf(qq);
      if (idx < 0) return safe;
      return SP.esc(text.slice(0, idx)) + '<mark>' + SP.esc(text.slice(idx, idx + q.length)) + '</mark>' + SP.esc(text.slice(idx + q.length));
    }
    function localItems() {
      var list = [];
      (cfg.actions || []).forEach(function (a) { list.push({ group: 'Actions rapides', title: a.label, url: a.url, icon: a.icon }); });
      (cfg.nav || []).forEach(function (n) { list.push({ group: 'Navigation', title: n.label, sub: n.group, url: n.url, icon: n.icon }); });
      list.push({ group: 'Préférences', title: 'Basculer le thème clair / sombre', icon: 'fa-circle-half-stroke', run: function () { SP.theme.toggle(); } });
      list.push({ group: 'Préférences', title: 'Afficher les raccourcis clavier', icon: 'fa-keyboard', run: function () { SP.shortcuts.help(); } });
      list.push({ group: 'Préférences', title: 'Se déconnecter', icon: 'fa-right-from-bracket', url: ROOT + 'logout.php' });
      return list;
    }
    function render(list, q) {
      items = list;
      active = 0;
      if (!list.length) {
        results.innerHTML = '<div class="sp-palette-empty"><i class="fa-solid fa-magnifying-glass"></i>Aucun résultat pour « ' + SP.esc(q) + ' »</div>';
        return;
      }
      var html = '', group = null;
      list.forEach(function (it, i) {
        if (it.group !== group) { group = it.group; html += '<div class="sp-palette-group">' + SP.esc(group) + '</div>'; }
        html += '<div class="sp-palette-item' + (i === 0 ? ' active' : '') + '" role="option" data-idx="' + i + '" style="animation-delay:' + Math.min(i, 12) * 18 + 'ms">' +
          '<div class="pi-icon"><i class="fa-solid ' + SP.esc(it.icon || 'fa-circle') + '"></i></div>' +
          '<div class="pi-text"><div class="pi-title">' + highlight(it.title, q) + '</div>' + (it.sub ? '<div class="pi-sub">' + SP.esc(it.sub) + '</div>' : '') + '</div>' +
          '<span class="pi-hint"><kbd>Entrée</kbd></span></div>';
      });
      results.innerHTML = html;
    }
    function setActive(i) {
      var nodes = SP.$$('.sp-palette-item', results);
      if (!nodes.length) return;
      active = (i + nodes.length) % nodes.length;
      nodes.forEach(function (n, k) { n.classList.toggle('active', k === active); });
      nodes[active].scrollIntoView({ block: 'nearest' });
    }
    function choose(i, newTab) {
      var it = items[i];
      if (!it) return;
      if (it.run) { close(); setTimeout(it.run, 200); return; }
      if (it.url) {
        close();
        if (newTab) window.open(it.url, '_blank');
        else { SP.progress.start(); window.location.href = it.url; }
      }
    }
    function search(q) {
      lastQuery = q;
      var local = localItems()
        .map(function (it) { return { it: it, s: Math.max(score(it.title, q), it.sub ? score(it.sub, q) * 0.5 : 0) }; })
        .filter(function (x) { return x.s > 0; })
        .sort(function (a, b) { return b.s - a.s; })
        .map(function (x) { return x.it; });
      if (!q) { render(local, q); return; }
      render(local.slice(0, 6), q);
      if (q.length < 2) return;
      if (controller && controller.abort) controller.abort();
      controller = window.AbortController ? new AbortController() : null;
      loading.classList.add('is-active');
      SP.fetchJSON(ROOT + 'api/recherche.php?q=' + encodeURIComponent(q), { signal: controller ? controller.signal : undefined })
        .then(function (data) {
          if (q !== lastQuery) return;
          loading.classList.remove('is-active');
          var remote = [];
          (data.groups || []).forEach(function (g) {
            (g.items || []).forEach(function (r) { remote.push({ group: g.label, title: r.title, sub: r.sub, url: r.url, icon: r.icon || g.icon }); });
          });
          render(remote.concat(local.slice(0, 5)), q);
        })
        .catch(function (err) { if (!err || err.name !== 'AbortError') loading.classList.remove('is-active'); });
    }
    var searchDebounced = SP.debounce(search, 160);
    function open() {
      if (!modal) init();
      if (!modal) return;
      modal.show();
    }
    function close() { if (modal) modal.hide(); }
    function init() {
      modalEl = document.getElementById('spPalette');
      if (!modalEl || typeof bootstrap === 'undefined') return;
      modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      input = document.getElementById('spPaletteInput');
      results = document.getElementById('spPaletteResults');
      loading = document.getElementById('spPaletteLoading');
      modalEl.addEventListener('shown.bs.modal', function () { input.focus(); input.select(); });
      modalEl.addEventListener('show.bs.modal', function () { input.value = ''; search(''); });
      input.addEventListener('input', function () {
        var q = input.value.trim();
        if (!q) search(''); else searchDebounced(q);
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); choose(active, e.ctrlKey || e.metaKey); }
      });
      results.addEventListener('click', function (e) {
        var item = e.target.closest('.sp-palette-item');
        if (item) choose(parseInt(item.getAttribute('data-idx'), 10), e.ctrlKey || e.metaKey);
      });
      results.addEventListener('mousemove', function (e) {
        var item = e.target.closest('.sp-palette-item');
        if (item) { var i = parseInt(item.getAttribute('data-idx'), 10); if (i !== active) setActive(i); }
      });
    }
    return { open: open, close: close };
  })();

  /* ===================== Centre de notifications ===================== */
  SP.notif = (function () {
    var KEY = 'sp-notif-cache';
    var busy = false;
    function readCache() {
      try { var c = JSON.parse(sessionStorage.getItem(KEY) || 'null'); return c && c.user === cfg.user ? c : null; } catch (e) { return null; }
    }
    function writeCache(data) {
      try { sessionStorage.setItem(KEY, JSON.stringify({ t: Date.now(), user: cfg.user, data: data })); } catch (e) { /* ignoré */ }
    }
    function render(data, fresh) {
      var list = document.getElementById('spNotifList');
      var badge = document.getElementById('spNotifCount');
      var btn = document.getElementById('spNotifBtn');
      var itemsList = data.items || [];
      var total = data.total || 0;
      if (badge) {
        badge.textContent = total > 99 ? '99+' : total;
        badge.classList.toggle('is-visible', total > 0);
      }
      var seen = parseInt(SP.store.raw('sp-notif-seen') || '0', 10);
      if (fresh && btn && total > seen) { btn.classList.remove('is-ringing'); void btn.offsetWidth; btn.classList.add('is-ringing'); }
      SP.store.setRaw('sp-notif-seen', String(total));
      var counts = data.counts || {};
      SP.$$('[data-nav-badge]').forEach(function (b) {
        var key = b.getAttribute('data-nav-badge');
        var n = counts[key] || 0;
        b.textContent = n > 99 ? '99+' : n;
        b.classList.toggle('is-visible', n > 0);
        b.classList.toggle('is-alert', key === 'stock' || key === 'credits_retard');
      });
      if (!list) return;
      if (!itemsList.length) {
        list.innerHTML = '<div class="sp-notif-empty"><i class="fa-solid fa-circle-check"></i>Tout est en ordre.<br><small>Aucune alerte pour le moment.</small></div>';
        return;
      }
      list.innerHTML = itemsList.map(function (n, i) {
        return '<a class="sp-notif-item lvl-' + SP.esc(n.level || 'info') + '" href="' + SP.esc(ROOT + (n.url || '#')) + '" style="animation:spFadeUp .3s ' + (i * 40) + 'ms both">' +
          '<span class="ni-icon"><i class="fa-solid ' + SP.esc(n.icon || 'fa-bell') + '"></i></span>' +
          '<span><span class="ni-title d-block">' + SP.esc(n.title) + '</span><span class="ni-text d-block">' + SP.esc(n.text || '') + '</span></span></a>';
      }).join('');
    }
    function load(force) {
      if (!document.getElementById('spNotifBtn')) return;
      var cache = readCache();
      if (cache) render(cache.data, false);
      if (!force && cache && Date.now() - cache.t < 60000) return;
      if (busy) return;
      busy = true;
      var icon = SP.$('#spNotifRefresh i');
      if (icon) icon.classList.add('sp-spin');
      SP.fetchJSON(ROOT + 'api/notifications.php').then(function (data) {
        if (data && data.success) { writeCache(data); render(data, true); }
      }).catch(function () {}).then(function () { busy = false; if (icon) icon.classList.remove('sp-spin'); });
    }
    return { load: load, invalidate: function () { try { sessionStorage.removeItem(KEY); } catch (e) { /* ignoré */ } } };
  })();

  /* ===================== Raccourcis clavier ===================== */
  SP.shortcuts = (function () {
    var list = [];
    var pending = null, pendingTimer = null;
    function isTyping(el) {
      if (!el) return false;
      var tag = el.tagName;
      return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }
    function register(keys, label, fn, opts) {
      opts = opts || {};
      list.push({ keys: keys, label: label, fn: fn, group: opts.group || 'Général', global: !!opts.global, display: opts.display || keys });
    }
    function comboOf(e) {
      var k = e.key;
      if (k === ' ') k = 'Space';
      if (k.length === 1) k = k.toLowerCase();
      var parts = [];
      if (e.ctrlKey || e.metaKey) parts.push('ctrl');
      if (e.altKey) parts.push('alt');
      if (e.shiftKey && k.length > 1) parts.push('shift');
      parts.push(k);
      return parts.join('+');
    }
    function help() {
      var box = document.getElementById('spShortcutsList');
      var modalEl = document.getElementById('spShortcutsModal');
      if (!box || !modalEl) return;
      var groups = {};
      list.forEach(function (s) { (groups[s.group] = groups[s.group] || []).push(s); });
      box.innerHTML = Object.keys(groups).map(function (g) {
        return '<div><div class="small-caps mb-2 mt-2">' + SP.esc(g) + '</div>' + groups[g].map(function (s) {
          var keys = String(s.display).split(' ').map(function (part) {
            return part.split('+').map(function (k) { return '<kbd>' + SP.esc(k === 'ctrl' ? 'Ctrl' : (k.length === 1 ? k.toUpperCase() : k)) + '</kbd>'; }).join('+');
          }).join(' puis ');
          return '<div class="sp-shortcut"><span>' + SP.esc(s.label) + '</span><span>' + keys + '</span></div>';
        }).join('') + '</div>';
      }).join('');
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    document.addEventListener('keydown', function (e) {
      if (e.defaultPrevented || e.isComposing) return;
      var combo = comboOf(e);
      var typing = isTyping(e.target);
      if (pending) {
        var seq = pending + ' ' + combo;
        pending = null;
        clearTimeout(pendingTimer);
        for (var i = 0; i < list.length; i++) {
          if (list[i].keys === seq && !typing) { e.preventDefault(); list[i].fn(e); return; }
        }
      }
      for (var j = 0; j < list.length; j++) {
        var s = list[j];
        if (s.keys === combo && (!typing || s.global)) { e.preventDefault(); s.fn(e); return; }
        if (!typing && s.keys.indexOf(combo + ' ') === 0) {
          pending = combo;
          pendingTimer = setTimeout(function () { pending = null; }, 1200);
          return;
        }
      }
    });
    return { register: register, help: help, list: list };
  })();

  /* ===================== Détails d'une vente (modale) ===================== */
  window.showVenteDetails = function (id) {
    var modalEl = document.getElementById('venteDetailsModal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var loading = document.getElementById('vdLoading');
    var errorEl = document.getElementById('vdError');
    var content = document.getElementById('vdContent');
    loading.classList.remove('d-none');
    errorEl.classList.add('d-none');
    content.classList.add('d-none');
    document.getElementById('vdNumero').textContent = '';
    modal.show();

    SP.fetchJSON(ROOT + 'ventes/vente_details.php?id=' + encodeURIComponent(id))
      .then(function (data) {
        loading.classList.add('d-none');
        if (!data.success) {
          errorEl.textContent = data.message || 'Impossible de charger les détails de cette vente.';
          errorEl.classList.remove('d-none');
          return;
        }
        var v = data.vente, dev = ' ' + (data.devise || cfg.devise || '');
        var m = function (n) { return SP.num(n) + dev; };
        var set = function (idSel, txt) { var el = document.getElementById(idSel); if (el) el.textContent = txt; };
        set('vdNumero', v.numero_facture);
        set('vdClient', v.client);
        set('vdClientTel', v.client_tel || '');
        set('vdVendeur', v.vendeur || '-');
        set('vdDate', v.date);
        set('vdPaiement', v.mode_paiement);
        var statutEl = document.getElementById('vdStatut');
        if (v.statut === 'validee') {
          var sp = { payee: ['Payée', 'is-success', 'fa-circle-check'], partielle: ['Paiement partiel', 'is-warning', 'fa-circle-half-stroke'], impayee: ['Impayée', 'is-danger', 'fa-circle-exclamation'] }[v.statut_paiement] || ['Validée', 'is-success', 'fa-check'];
          statutEl.className = 'sp-badge ' + sp[1];
          statutEl.innerHTML = '<i class="fa-solid ' + sp[2] + '"></i>' + SP.esc(sp[0]);
        } else {
          statutEl.className = 'sp-badge is-danger';
          statutEl.innerHTML = '<i class="fa-solid fa-ban"></i>Annulée';
        }
        var toggleRow = function (rowId, show) { var r = document.getElementById(rowId); if (r) r.classList.toggle('d-none', !show); };
        toggleRow('vdSousTotalRow', v.remise_montant > 0);
        toggleRow('vdRemiseRow', v.remise_montant > 0);
        if (v.remise_montant > 0) {
          set('vdSousTotal', m(v.montant_brut));
          set('vdRemise', v.remise_type === 'pourcentage' ? '- ' + SP.num(v.remise_valeur, 2) + ' % (' + m(v.remise_montant) + ')' : '- ' + m(v.remise_montant));
        }
        set('vdTotal', m(v.montant_total));
        set('vdPaye', m(v.montant_paye));
        set('vdMonnaie', m(v.monnaie_rendue));
        toggleRow('vdResteRow', v.reste_a_payer > 0.009);
        if (v.reste_a_payer > 0.009) set('vdReste', m(v.reste_a_payer));

        var paiementsWrap = document.getElementById('vdPaiementsWrap');
        var paiementsList = document.getElementById('vdPaiementsList');
        paiementsList.innerHTML = '';
        if (data.paiements && data.paiements.length) {
          paiementsWrap.classList.remove('d-none');
          paiementsList.innerHTML = data.paiements.map(function (p) {
            return '<li><span class="tl-dot tone-success"><i class="fa-solid fa-coins"></i></span>' +
              '<div class="tl-title">' + SP.esc(m(p.montant)) + ' · ' + SP.esc(p.mode_paiement) + '</div>' +
              '<div class="tl-meta">' + SP.esc(p.date) + (p.note ? ' — ' + SP.esc(p.note) : '') + '</div></li>';
          }).join('');
        } else {
          paiementsWrap.classList.add('d-none');
        }
        var obsWrap = document.getElementById('vdObsWrap');
        if (v.observations) { set('vdObs', v.observations); obsWrap.classList.remove('d-none'); }
        else obsWrap.classList.add('d-none');

        document.getElementById('vdLignes').innerHTML = data.lignes.map(function (l) {
          return '<tr><td><div class="sp-cell-title">' + SP.esc(l.article_nom || 'Article supprimé') + '</div><div class="sp-cell-sub">' + SP.esc(l.article_code || '') + '</div></td>' +
            '<td class="text-center">' + SP.esc(SP.num(l.quantite)) + ' <span class="text-muted small">' + SP.esc(l.unite || '') + '</span></td>' +
            '<td class="text-end">' + SP.esc(SP.num(l.prix_unitaire)) + '</td>' +
            '<td class="text-end fw-semibold">' + SP.esc(SP.num(l.sous_total)) + '</td></tr>';
        }).join('');

        var base = ROOT + 'ventes/facture.php?id=' + encodeURIComponent(v.id);
        var fa4 = document.getElementById('vdFactureLink');
        var ftk = document.getElementById('vdTicketLink');
        if (fa4) fa4.href = base + '&format=a4';
        if (ftk) ftk.href = base + '&format=ticket';
        content.classList.remove('d-none');
      })
      .catch(function () {
        loading.classList.add('d-none');
        errorEl.textContent = 'Erreur réseau. Merci de réessayer.';
        errorEl.classList.remove('d-none');
      });
  };

  /* ===================== Barre latérale ===================== */
  function initSidebar() {
    var sidebar = document.getElementById('spSidebar');
    var toggleBtn = SP.$('.toggle-sidebar');
    var backdrop = document.getElementById('spSidebarBackdrop');
    if (!sidebar) return;
    var close = function () { sidebar.classList.remove('open'); document.body.style.overflow = ''; };
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        var open = sidebar.classList.toggle('open');
        document.body.style.overflow = open ? 'hidden' : '';
      });
    }
    if (backdrop) backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sidebar.classList.contains('open')) close(); });
    window.addEventListener('resize', SP.debounce(function () { if (window.innerWidth >= 992) close(); }, 150));

    var collapseBtn = document.getElementById('sidebarCollapseToggle');
    var root = document.documentElement;
    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        var collapsed = root.classList.toggle('sp-sidebar-collapsed');
        SP.store.setRaw('sp-sidebar-collapsed', collapsed ? '1' : '0');
        setTimeout(function () { SP.segments(); window.dispatchEvent(new Event('resize')); }, 320);
      });
    }
    // Infobulles des liens quand le menu est replié
    if (typeof bootstrap !== 'undefined') {
      SP.$$('.sp-nav .nav-link').forEach(function (link) {
        var tip = new bootstrap.Tooltip(link, { title: link.getAttribute('data-label'), placement: 'right', trigger: 'hover', container: 'body', offset: [0, 10] });
        link.addEventListener('show.bs.tooltip', function (e) {
          if (!root.classList.contains('sp-sidebar-collapsed') || window.innerWidth < 992) e.preventDefault();
        });
        void tip;
      });
    }
    // Garde le lien actif visible dans un menu long
    var active = SP.$('.sp-nav .nav-link.active', sidebar);
    if (active && active.scrollIntoView && active.offsetTop > sidebar.clientHeight * 0.6) active.scrollIntoView({ block: 'center' });
  }

  /* ===================== Initialisation ===================== */
  function initTopbar() {
    var bar = document.getElementById('spTopbar');
    if (!bar) return;
    var onScroll = function () { bar.classList.toggle('is-scrolled', window.scrollY > 4); };
    window.addEventListener('scroll', SP.throttle(onScroll, 80), { passive: true });
    onScroll();
  }

  function initNavigationProgress() {
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-confirm') || a.hasAttribute('data-no-progress')) return;
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel|sms):/i.test(href)) return;
      if (a.host && a.host !== window.location.host) return;
      SP.progress.start();
    });
    window.addEventListener('pageshow', function (e) {
      SP.progress.done();
      if (e.persisted) {
        SP.$$('form[data-sp-submitting]').forEach(function (f) { f.removeAttribute('data-sp-submitting'); });
        SP.$$('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
      }
    });
  }

  function initDefaultShortcuts() {
    var go = function (path) { return function () { SP.progress.start(); window.location.href = ROOT + path; }; };
    var role = cfg.role;
    SP.shortcuts.register('ctrl+k', 'Recherche globale / palette de commandes', function () { SP.palette.open(); }, { global: true, display: 'ctrl+K' });
    SP.shortcuts.register('/', 'Recherche globale', function () { SP.palette.open(); });
    SP.shortcuts.register('?', 'Afficher cette aide', function () { SP.shortcuts.help(); });
    SP.shortcuts.register('t', 'Basculer le thème clair / sombre', function () { SP.theme.toggle(); });
    SP.shortcuts.register('g d', 'Aller au tableau de bord', go('dashboard.php'), { group: 'Navigation' });
    if (role === 'admin' || role === 'vendeur') {
      SP.shortcuts.register('g c', 'Aller à la caisse', go('ventes/caisse.php'), { group: 'Navigation' });
      SP.shortcuts.register('g v', 'Historique des ventes', go('ventes/historique.php'), { group: 'Navigation' });
    }
    SP.shortcuts.register('g a', 'Catalogue articles', go('articles/liste.php'), { group: 'Navigation' });
    SP.shortcuts.register('g l', 'Clients', go('clients/liste.php'), { group: 'Navigation' });
    if (role === 'admin' || role === 'secretaire') {
      SP.shortcuts.register('g m', 'Courrier', go('courrier/liste.php'), { group: 'Navigation' });
      SP.shortcuts.register('g r', 'Agenda des rendez-vous', go('rendezvous/agenda.php'), { group: 'Navigation' });
    }
    if (role === 'admin') SP.shortcuts.register('g s', 'Statistiques', go('rapports/statistiques.php'), { group: 'Navigation' });
  }

  function initFlashes() {
    var node = document.getElementById('spFlashData');
    if (!node) return;
    var flashes = [];
    try { flashes = JSON.parse(node.textContent || '[]'); } catch (e) { flashes = []; }
    flashes.forEach(function (f, i) {
      setTimeout(function () { SP.toast({ type: f.type, message: f.message }); }, 250 + i * 140);
    });
  }

  function initMisc() {
    // Thème
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('[data-sp-theme-toggle]') : null;
      if (t) { e.preventDefault(); SP.theme.toggle(e); }
      var p = e.target.closest ? e.target.closest('[data-sp-palette]') : null;
      if (p) { e.preventDefault(); SP.palette.open(); }
      var s = e.target.closest ? e.target.closest('[data-sp-shortcuts]') : null;
      if (s) { e.preventDefault(); SP.shortcuts.help(); }
      var pr = e.target.closest ? e.target.closest('[data-sp-print]') : null;
      if (pr) { e.preventDefault(); window.print(); }
      var fs = e.target.closest ? e.target.closest('[data-sp-fullscreen]') : null;
      if (fs) {
        e.preventDefault();
        if (!document.fullscreenElement && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(function () {});
        else if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
      }
    });
    document.addEventListener('fullscreenchange', function () {
      SP.$$('[data-sp-fullscreen] i').forEach(function (i) { i.className = 'fa-solid ' + (document.fullscreenElement ? 'fa-compress' : 'fa-expand'); });
    });

    // Filtres instantanés déclarés en HTML : <input data-sp-filter="#maTable">
    SP.$$('input[data-sp-filter]').forEach(function (input) { SP.filter(input, input.getAttribute('data-sp-filter'), input.getAttribute('data-sp-filter-item')); });

    // Mode plein écran des tableaux (persistant)
    SP.$$('[data-fullscreen-toggle]').forEach(function (btn) {
      var key = btn.getAttribute('data-fullscreen-toggle');
      var apply = function (on) {
        document.body.classList.toggle('sp-fullscreen-mode', on);
        var icon = btn.querySelector('i');
        if (icon) icon.className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
      };
      if (SP.store.raw(key) === '1') apply(true);
      btn.addEventListener('click', function () {
        var on = !document.body.classList.contains('sp-fullscreen-mode');
        apply(on);
        SP.store.setRaw(key, on ? '1' : '0');
      });
    });

    // Anciennes alertes à fermeture automatique
    SP.$$('.alert-auto-dismiss').forEach(function (el) {
      setTimeout(function () { var a = bootstrap.Alert.getOrCreateInstance(el); if (a) a.close(); }, 5000);
    });

    // Infobulles Bootstrap déclarées en HTML
    if (typeof bootstrap !== 'undefined') {
      SP.$$('[data-bs-toggle="tooltip"]').forEach(function (el) { bootstrap.Tooltip.getOrCreateInstance(el); });
      // Menus déroulants dans un tableau défilant : positionnement fixe pour ne pas être rognés
      SP.$$('.table-responsive [data-bs-toggle="dropdown"], .sp-table-scroll [data-bs-toggle="dropdown"]').forEach(function (el) {
        bootstrap.Dropdown.getOrCreateInstance(el, { popperConfig: function (conf) { return Object.assign({}, conf, { strategy: 'fixed' }); } });
      });
    }

    // Connexion réseau perdue / retrouvée
    window.addEventListener('offline', function () { SP.toast({ type: 'warning', title: 'Hors ligne', message: 'La connexion au serveur est interrompue.', duration: 0 }); });
    window.addEventListener('online', function () { SP.toast({ type: 'success', title: 'Connexion rétablie', message: 'Vous êtes de nouveau connecté.' }); });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initTopbar();
    initNavigationProgress();
    if (cfg.role) initDefaultShortcuts();
    initMisc();
    SP.forms();
    SP.combo();
    SP.segments();
    SP.tabs();
    SP.sortable();
    SP.reveal();
    initFlashes();
    SP.notif.load(false);
    setInterval(function () { if (!document.hidden) SP.notif.load(true); }, 180000);
    var notifBtn = document.getElementById('spNotifBtn');
    if (notifBtn) notifBtn.addEventListener('show.bs.dropdown', function () { SP.notif.load(false); });
    var refresh = document.getElementById('spNotifRefresh');
    if (refresh) refresh.addEventListener('click', function () { SP.notif.load(true); });
    requestAnimationFrame(function () { document.documentElement.classList.remove('sp-preload'); });
  });
})(window, document);
