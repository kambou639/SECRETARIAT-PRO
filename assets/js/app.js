/* Secrétariat Pro - JS principal */

/* ===================== Boîte de dialogue de confirmation ===================== */
/* Remplace window.confirm() par une modale stylée. Usage :
     spConfirm('Supprimer cet élément ?', { type: 'danger' }).then(function (ok) { if (ok) { ... } });
   Options : title, confirmLabel, cancelLabel, type ('danger' | 'default') */
function spConfirm(message, options) {
  options = options || {};
  return new Promise(function (resolve) {
    var modalEl = document.getElementById('spConfirmModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
      resolve(window.confirm(message || 'Confirmer cette action ?'));
      return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('spConfirmTitle');
    var msgEl = document.getElementById('spConfirmMessage');
    var okBtn = document.getElementById('spConfirmOk');
    var cancelBtn = document.getElementById('spConfirmCancel');
    var iconWrap = document.getElementById('spConfirmIcon');
    var danger = options.type === 'danger';

    titleEl.textContent = options.title || (danger ? 'Confirmer la suppression' : 'Confirmer');
    msgEl.textContent = message || 'Êtes-vous sûr de vouloir continuer ?';
    okBtn.className = 'btn px-4 ' + (danger ? 'btn-danger' : 'btn-sp-primary');
    okBtn.textContent = options.confirmLabel || (danger ? 'Supprimer' : 'Confirmer');
    cancelBtn.textContent = options.cancelLabel || 'Annuler';
    iconWrap.className = 'sp-confirm-icon' + (danger ? ' is-danger' : '');
    iconWrap.innerHTML = danger ? '<i class="fa-solid fa-trash-can"></i>' : '<i class="fa-solid fa-circle-question"></i>';

    var settled = false;
    function finish(result) {
      if (settled) return;
      settled = true;
      okBtn.removeEventListener('click', onOk);
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      resolve(result);
    }
    function onOk() { modal.hide(); finish(true); }
    function onHidden() { finish(false); }

    okBtn.addEventListener('click', onOk);
    modalEl.addEventListener('hidden.bs.modal', onHidden);
    modal.show();
  });
}

document.addEventListener('DOMContentLoaded', function () {
  // Toggle sidebar (mobile)
  var toggleBtn = document.querySelector('.toggle-sidebar');
  var sidebar = document.querySelector('.sp-sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (window.innerWidth < 992 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      }
    });
  }

  // Repli du menu (mode ordinateur), persistant via localStorage
  var collapseBtn = document.getElementById('sidebarCollapseToggle');
  if (collapseBtn) {
    if (localStorage.getItem('sp-sidebar-collapsed') === '1') {
      document.body.classList.add('sp-sidebar-collapsed');
    }
    collapseBtn.addEventListener('click', function () {
      var collapsed = document.body.classList.toggle('sp-sidebar-collapsed');
      localStorage.setItem('sp-sidebar-collapsed', collapsed ? '1' : '0');
    });
  }

  // Auto-dismiss des alertes flash après 5s
  document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
    setTimeout(function () {
      var alert = bootstrap.Alert.getOrCreateInstance(el);
      if (alert) alert.close();
    }, 5000);
  });

  // Confirmation avant suppression / validation / autre action sensible
  // (liens <a> et formulaires <form> portant l'attribut data-confirm)
  document.querySelectorAll('a[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var href = el.getAttribute('href');
      var type = el.getAttribute('data-confirm-type') || (el.classList.contains('btn-outline-danger') || el.classList.contains('btn-danger') ? 'danger' : 'default');
      spConfirm(el.getAttribute('data-confirm') || 'Confirmer cette action ?', { type: type }).then(function (ok) {
        if (ok) window.location.href = href;
      });
    });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      if (el.dataset.spConfirmed === '1') { return; }
      e.preventDefault();
      var submitBtn = el.querySelector('button[type="submit"], button:not([type])');
      var type = el.getAttribute('data-confirm-type') || (submitBtn && (submitBtn.classList.contains('btn-outline-danger') || submitBtn.classList.contains('btn-danger')) ? 'danger' : 'default');
      spConfirm(el.getAttribute('data-confirm') || 'Confirmer cette action ?', { type: type }).then(function (ok) {
        if (ok) {
          el.dataset.spConfirmed = '1';
          el.submit();
        }
      });
    });
  });

  // Mode plein écran pour tableaux (persistant via localStorage)
  document.querySelectorAll('[data-fullscreen-toggle]').forEach(function (btn) {
    var key = btn.getAttribute('data-fullscreen-toggle');
    var apply = function (on) {
      document.body.classList.toggle('sp-fullscreen-mode', on);
      var icon = btn.querySelector('i');
      if (icon) icon.className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
    };
    if (localStorage.getItem(key) === '1') apply(true);
    btn.addEventListener('click', function () {
      var on = !document.body.classList.contains('sp-fullscreen-mode');
      apply(on);
      localStorage.setItem(key, on ? '1' : '0');
    });
  });
});

/* ===================== Détails d'une vente/opération de caisse ===================== */
/* Charge et affiche le détail d'une vente dans la modale #venteDetailsModal
   (présente sur les pages Caisse et Historique des ventes). */
function showVenteDetails(id) {
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

  fetch('vente_details.php?id=' + encodeURIComponent(id))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      loading.classList.add('d-none');
      if (!data.success) {
        errorEl.textContent = data.message || 'Impossible de charger les détails de cette vente.';
        errorEl.classList.remove('d-none');
        return;
      }
      var v = data.vente;
      document.getElementById('vdNumero').textContent = v.numero_facture;
      document.getElementById('vdClient').textContent = v.client;
      document.getElementById('vdClientTel').textContent = v.client_tel || '';
      document.getElementById('vdVendeur').textContent = v.vendeur;
      document.getElementById('vdDate').textContent = v.date;
      document.getElementById('vdPaiement').textContent = v.mode_paiement;
      var statutEl = document.getElementById('vdStatut');
      if (v.statut === 'validee') {
        statutEl.textContent = 'Validée';
        statutEl.className = 'badge bg-success';
      } else {
        statutEl.textContent = 'Annulée';
        statutEl.className = 'badge bg-danger';
      }
      var statutPaiementEl = document.getElementById('vdStatutPaiement');
      if (v.statut === 'validee' && v.statut_paiement !== 'payee') {
        statutPaiementEl.classList.remove('d-none');
        if (v.statut_paiement === 'partielle') {
          statutPaiementEl.textContent = 'Paiement partiel';
          statutPaiementEl.className = 'badge bg-warning text-dark';
        } else {
          statutPaiementEl.textContent = 'Impayée';
          statutPaiementEl.className = 'badge bg-secondary';
        }
      } else {
        statutPaiementEl.classList.add('d-none');
      }
      var devise = ' ' + data.devise;
      if (v.remise_montant > 0) {
        document.getElementById('vdSousTotalRow').classList.remove('d-none');
        document.getElementById('vdRemiseRow').classList.remove('d-none');
        document.getElementById('vdSousTotal').textContent = v.montant_brut.toLocaleString() + devise;
        var remiseLabel = v.remise_type === 'pourcentage' ? ('- ' + v.remise_valeur.toLocaleString() + '% (' + v.remise_montant.toLocaleString() + devise + ')') : ('- ' + v.remise_montant.toLocaleString() + devise);
        document.getElementById('vdRemise').textContent = remiseLabel;
      } else {
        document.getElementById('vdSousTotalRow').classList.add('d-none');
        document.getElementById('vdRemiseRow').classList.add('d-none');
      }
      document.getElementById('vdTotal').textContent = v.montant_total.toLocaleString() + devise;
      document.getElementById('vdPaye').textContent = v.montant_paye.toLocaleString() + devise;
      document.getElementById('vdMonnaie').textContent = v.monnaie_rendue.toLocaleString() + devise;

      var resteRow = document.getElementById('vdResteRow');
      if (v.reste_a_payer > 0.009) {
        resteRow.classList.remove('d-none');
        document.getElementById('vdReste').textContent = v.reste_a_payer.toLocaleString() + devise;
      } else {
        resteRow.classList.add('d-none');
      }

      var paiementsWrap = document.getElementById('vdPaiementsWrap');
      var paiementsList = document.getElementById('vdPaiementsList');
      paiementsList.innerHTML = '';
      if (data.paiements && data.paiements.length) {
        paiementsWrap.classList.remove('d-none');
        data.paiements.forEach(function (p) {
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="text-muted" style="font-size:.8rem;">' + p.date + '</td>' +
            '<td style="font-size:.8rem;">' + p.mode_paiement + (p.note ? ' <span class="text-muted">(' + p.note + ')</span>' : '') + '</td>' +
            '<td class="text-end fw-semibold" style="font-size:.8rem;">' + p.montant.toLocaleString() + devise + '</td>';
          paiementsList.appendChild(tr);
        });
      } else {
        paiementsWrap.classList.add('d-none');
      }

      var obsWrap = document.getElementById('vdObsWrap');
      if (v.observations) {
        document.getElementById('vdObs').textContent = v.observations;
        obsWrap.classList.remove('d-none');
      } else {
        obsWrap.classList.add('d-none');
      }

      var tbody = document.getElementById('vdLignes');
      tbody.innerHTML = '';
      data.lignes.forEach(function (l) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>' + l.article_nom + '<div class="text-muted" style="font-size:.72rem;">' + l.article_code + '</div></td>' +
          '<td class="text-center">' + l.quantite + ' ' + l.unite + '</td>' +
          '<td class="text-end">' + l.prix_unitaire.toLocaleString() + '</td>' +
          '<td class="text-end">' + l.sous_total.toLocaleString() + '</td>';
        tbody.appendChild(tr);
      });

      document.getElementById('vdFactureLink').href = 'facture.php?id=' + v.id;
      content.classList.remove('d-none');
    })
    .catch(function () {
      loading.classList.add('d-none');
      errorEl.textContent = 'Erreur réseau. Merci de réessayer.';
      errorEl.classList.remove('d-none');
    });
}

/* Recherche instantanée côté client dans un tableau */
function spTableFilter(inputEl, tableSelector) {
  var table = document.querySelector(tableSelector);
  if (!table) return;
  var rows = table.querySelectorAll('tbody tr');
  inputEl.addEventListener('input', function () {
    var q = inputEl.value.trim().toLowerCase();
    rows.forEach(function (row) {
      row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    });
  });
}
