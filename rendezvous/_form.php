<?php
$r = $rdv ?? [];
$rdvId = (int)($r['id'] ?? 0);
$statutActuel = in_array($r['statut'] ?? '', ['planifie', 'confirme', 'termine', 'annule'], true) ? $r['statut'] : 'planifie';
$dateVal = valid_date($r['date_rdv'] ?? null, date('Y-m-d'));
$debutVal = substr((string)($r['heure_debut'] ?? '09:00'), 0, 5);
$finVal = substr((string)($r['heure_fin'] ?? ''), 0, 5);
$clientSel = (int)($r['client_id'] ?? 0);
$retour = 'agenda.php?mois=' . substr($dateVal, 0, 7);
?>
<form method="post" novalidate data-sp-dirty-check id="rdvForm" data-rdv-id="<?= $rdvId ?>">
    <?= csrf_field() ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger sp-anim-shake"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-solid fa-calendar-day"></i>Rendez-vous</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="rTitre">Titre <span class="sp-required">*</span></label>
                        <input type="text" id="rTitre" name="titre" class="form-control" required maxlength="200" value="<?= e($r['titre'] ?? '') ?>" placeholder="Ex. : Remise des maquettes de cartes de visite" <?= $rdvId ? '' : 'autofocus' ?>>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="rDate">Date <span class="sp-required">*</span></label>
                        <input type="date" id="rDate" name="date_rdv" class="form-control" required value="<?= e($dateVal) ?>">
                        <div class="d-flex flex-wrap gap-1 mt-2" id="dateChips">
                            <button type="button" class="sp-chip sm" data-jours="0">Aujourd'hui</button>
                            <button type="button" class="sp-chip sm" data-jours="1">Demain</button>
                            <button type="button" class="sp-chip sm" data-jours="7">+ 1 semaine</button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="rDebut">Début <span class="sp-required">*</span></label>
                        <input type="time" id="rDebut" name="heure_debut" class="form-control" required step="300" value="<?= e($debutVal) ?>">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label" for="rFin">Fin <span class="text-muted fw-normal">(facultatif)</span></label>
                        <input type="time" id="rFin" name="heure_fin" class="form-control" step="300" value="<?= e($finVal) ?>">
                        <div class="invalid-feedback">L'heure de fin doit être après le début.</div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center gap-1" id="dureeChips">
                            <span class="small text-muted me-1"><i class="fa-regular fa-clock me-1"></i>Durée :</span>
                            <?php foreach ([15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h'] as $min => $lib): ?>
                                <button type="button" class="sp-chip" data-duree="<?= $min ?>"><?= $lib ?></button>
                            <?php endforeach; ?>
                            <button type="button" class="sp-chip" data-duree="0" title="Sans heure de fin (30 min par défaut dans l'agenda)">Non définie</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="rLieu">Lieu</label>
                        <div class="sp-input-icon"><i class="fa-solid fa-location-dot"></i><input type="text" id="rLieu" name="lieu" class="form-control" maxlength="150" list="lieuxRecents" value="<?= e($r['lieu'] ?? '') ?>" placeholder="Bureau, salle de réunion, adresse du client…"></div>
                        <datalist id="lieuxRecents"><?php foreach ($lieux as $l): ?><option value="<?= e($l) ?>"></option><?php endforeach; ?></datalist>
                    </div>
                </div>
            </div>

            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-solid fa-user-group"></i>Personne concernée</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="rClient">Client <span class="text-muted fw-normal">(facultatif)</span></label>
                        <select id="rClient" name="client_id" class="form-select" data-sp-combo data-placeholder="Rechercher un client par nom ou téléphone…" data-empty="Aucun client trouvé — renseignez un contact ci-dessous.">
                            <option value="">— Aucun client —</option>
                            <?php foreach ($clients as $c): $nomC = trim($c['nom'] . ' ' . $c['prenom']); ?>
                                <option value="<?= (int)$c['id'] ?>" data-sub="<?= e($c['telephone'] ?? '') ?>" data-av="<?= abs(crc32(mb_strtolower($nomC))) % 8 ?>" <?= $clientSel === (int)$c['id'] ? 'selected' : '' ?>><?= e($nomC) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="sp-client-mini mt-2" id="clientMini" hidden>
                            <span class="sp-avatar" id="clientMiniAv"></span>
                            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate" id="clientMiniNom"></div><div class="small text-muted" id="clientMiniTel"></div></div>
                            <a href="#" class="btn btn-sm btn-ghost" id="clientMiniFiche" target="_blank" rel="noopener"><i class="fa-solid fa-id-card me-1"></i>Fiche</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="rContact" id="rContactLabel">Nom du contact</label>
                        <div class="sp-input-icon"><i class="fa-solid fa-user"></i><input type="text" id="rContact" name="contact_nom" class="form-control" maxlength="150" value="<?= e($r['contact_nom'] ?? '') ?>" placeholder="Personne rencontrée"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="rTel">Téléphone</label>
                        <div class="sp-input-icon"><i class="fa-solid fa-phone"></i><input type="tel" id="rTel" name="contact_telephone" class="form-control" maxlength="30" value="<?= e($r['contact_telephone'] ?? '') ?>" placeholder="70 00 00 00"></div>
                    </div>
                </div>
            </div>

            <div class="sp-form-section">
                <div class="sp-form-section-title"><i class="fa-regular fa-note-sticky"></i>Notes</div>
                <textarea id="rDesc" name="description" class="form-control" rows="3" data-sp-autosize aria-label="Description" placeholder="Ordre du jour, documents à préparer, informations utiles…"><?= e($r['description'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sp-form-section-title"><i class="fa-solid fa-list-check"></i>Statut</div>
            <div class="row g-2 mb-4">
                <?php foreach (['planifie', 'confirme', 'termine', 'annule'] as $k): [$label, $tone, $icon] = statut_rdv_meta($k); ?>
                    <div class="col-6">
                        <label class="sp-choice <?= $tone !== 'neutral' ? 'tone-' . $tone : '' ?>">
                            <input type="radio" name="statut" value="<?= $k ?>" <?= $statutActuel === $k ? 'checked' : '' ?>>
                            <span class="sp-choice-body py-2"><span class="sp-choice-icon" style="width:30px;height:30px;"><i class="fa-solid <?= e($icon) ?>"></i></span><span class="sp-choice-title"><?= e($label) ?></span></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sp-rdv-recap" id="rdvRecap" aria-live="polite">
                <div class="d-flex align-items-center gap-3">
                    <span class="sp-mini-cal lg"><span class="m" id="recapMois"></span><span class="d" id="recapJour"></span></span>
                    <div class="min-w-0">
                        <div class="fw-semibold" id="recapDate"></div>
                        <div class="small text-muted" id="recapHeure"></div>
                        <div class="small" id="recapRel"></div>
                    </div>
                </div>
                <div class="sp-daybar-wrap mt-3" id="dayBarWrap">
                    <div class="sp-daybar" id="dayBar"></div>
                </div>
                <div class="alert alert-warning py-2 px-3 small mb-2 mt-2" id="rdvConflit" hidden><i class="fa-solid fa-triangle-exclamation"></i><div></div></div>
                <div class="small fw-semibold text-muted text-uppercase mt-2 mb-1" style="letter-spacing:.06em;font-size:.68rem;">Ce jour-là</div>
                <div id="rdvJour" class="sp-rdv-day-list"><div class="small text-muted">Chargement…</div></div>
            </div>
        </div>
    </div>
    <div class="sp-form-actions">
        <button type="submit" class="btn btn-sp-amber"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer</button>
        <a href="<?= e($retour) ?>" class="btn btn-ghost">Annuler</a>
        <span class="ms-auto small text-muted d-none d-md-inline"><kbd>Ctrl</kbd> + <kbd>Entrée</kbd> pour enregistrer</span>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('rdvForm');
    var rdvId = parseInt(form.getAttribute('data-rdv-id'), 10) || 0;
    var fDate = document.getElementById('rDate'), fDebut = document.getElementById('rDebut'), fFin = document.getElementById('rFin');
    var fClient = document.getElementById('rClient'), fContact = document.getElementById('rContact'), fTel = document.getElementById('rTel');
    var dureeChips = SP.$$('#dureeChips [data-duree]');
    var jourItems = [];
    var MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function toMin(t) { var m = /^(\d{2}):(\d{2})/.exec(t || ''); return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null; }
    function toTime(min) { min = Math.max(0, Math.min(min, 23 * 60 + 59)); return pad(Math.floor(min / 60)) + ':' + pad(min % 60); }
    function isoLocal(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function dureeTxt(min) { var h = Math.floor(min / 60), m = min % 60; return h ? h + ' h' + (m ? ' ' + pad(m) : '') : m + ' min'; }
    function parseDate(v) { var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || ''); return p ? new Date(+p[1], +p[2] - 1, +p[3]) : null; }

    // Durée mémorisée pour décaler la fin quand le début change
    var duree = (function () { var a = toMin(fDebut.value), b = toMin(fFin.value); return a !== null && b !== null && b > a ? b - a : 0; })();

    function refreshChips() {
        var a = toMin(fDebut.value), b = toMin(fFin.value);
        var d = a !== null && b !== null ? b - a : (fFin.value ? -1 : 0);
        dureeChips.forEach(function (c) { c.classList.toggle('active', parseInt(c.getAttribute('data-duree'), 10) === d); });
        fFin.classList.toggle('is-invalid', a !== null && b !== null && b <= a);
        var today = new Date(); today.setHours(0, 0, 0, 0);
        SP.$$('#dateChips [data-jours]').forEach(function (c) {
            var t = new Date(today); t.setDate(t.getDate() + parseInt(c.getAttribute('data-jours'), 10));
            c.classList.toggle('active', isoLocal(t) === fDate.value);
        });
    }

    function refreshRecap() {
        var d = parseDate(fDate.value);
        var a = toMin(fDebut.value), b = toMin(fFin.value);
        if (!d) {
            document.getElementById('recapDate').textContent = 'Date à préciser';
            document.getElementById('recapMois').textContent = '—';
            document.getElementById('recapJour').textContent = '?';
            document.getElementById('recapHeure').textContent = '';
            document.getElementById('recapRel').textContent = '';
        } else {
            var libelle = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            document.getElementById('recapDate').textContent = libelle.charAt(0).toUpperCase() + libelle.slice(1);
            document.getElementById('recapMois').textContent = MOIS[d.getMonth()];
            document.getElementById('recapJour').textContent = d.getDate();
            var h = a === null ? 'Heure à préciser' : fDebut.value + (b !== null && b > a ? ' → ' + fFin.value + ' · ' + dureeTxt(b - a) : ' · durée non définie');
            document.getElementById('recapHeure').textContent = h;
            var today = new Date(); today.setHours(0, 0, 0, 0);
            var diff = Math.round((d - today) / 86400000);
            var rel = document.getElementById('recapRel');
            rel.className = 'small ' + (diff < 0 ? 'text-danger' : diff === 0 ? 'text-success fw-semibold' : 'text-muted');
            rel.textContent = diff === 0 ? "Aujourd'hui" : diff === 1 ? 'Demain' : diff === -1 ? 'Hier' : diff > 0 ? 'Dans ' + diff + ' jours' : 'Il y a ' + (-diff) + ' jours';
        }
        renderDay();
    }

    function renderDay() {
        var a = toMin(fDebut.value), b = toMin(fFin.value);
        var selfEnd = a === null ? null : (b !== null && b > a ? b : Math.min(a + 30, 23 * 60 + 59));
        var list = document.getElementById('rdvJour');
        var conflits = [];
        jourItems.forEach(function (it) {
            it.conflit = a !== null && toMin(it.debut) < selfEnd && toMin(it.fin) > a;
            if (it.conflit) conflits.push(it);
        });
        var cancelled = (form.querySelector('input[name="statut"]:checked') || {}).value === 'annule';
        var alertBox = document.getElementById('rdvConflit');
        if (conflits.length && !cancelled) {
            alertBox.hidden = false;
            alertBox.querySelector('div').innerHTML = 'Chevauchement avec ' + conflits.map(function (c) { return '<strong>' + SP.esc(c.titre) + '</strong> (' + c.debut + ' – ' + c.fin + ')'; }).join(', ') + '. Vous pouvez tout de même enregistrer.';
        } else {
            alertBox.hidden = true;
        }
        list.innerHTML = jourItems.length ? jourItems.map(function (it) {
            return '<a class="sp-rdv-day-item' + (it.conflit ? ' is-conflict' : '') + '" href="modifier.php?id=' + it.id + '" target="_blank" rel="noopener">' +
                '<span class="t">' + it.debut + '</span><span class="min-w-0 text-truncate">' + SP.esc(it.titre) + (it.personne ? ' <small class="text-muted">· ' + SP.esc(it.personne) + '</small>' : '') + '</span></a>';
        }).join('') : '<div class="small text-muted"><i class="fa-regular fa-face-smile me-1"></i>Aucun autre rendez-vous ce jour-là.</div>';

        // Frise de la journée
        var starts = jourItems.map(function (it) { return toMin(it.debut); }), ends = jourItems.map(function (it) { return toMin(it.fin); });
        if (a !== null) { starts.push(a); ends.push(selfEnd); }
        var lo = Math.min.apply(null, [7 * 60].concat(starts)), hi = Math.max.apply(null, [19 * 60].concat(ends));
        lo = Math.floor(lo / 60) * 60; hi = Math.ceil(hi / 60) * 60;
        var span = Math.max(hi - lo, 60);
        var pos = function (m) { return ((m - lo) / span * 100).toFixed(2) + '%'; };
        var seg = function (from, to, cls, title) { return '<span class="seg' + cls + '" style="left:' + pos(from) + ';width:' + ((Math.max(to - from, 10)) / span * 100).toFixed(2) + '%" title="' + SP.esc(title) + '"></span>'; };
        var html = '';
        for (var t = lo; t <= hi; t += (span > 12 * 60 ? 180 : 120)) html += '<span class="tick" style="left:' + pos(t) + '">' + pad(t / 60) + 'h</span>';
        jourItems.forEach(function (it) { html += seg(toMin(it.debut), toMin(it.fin), it.conflit ? ' is-conflict' : '', it.debut + ' – ' + it.fin + ' · ' + it.titre); });
        if (a !== null) html += seg(a, selfEnd, ' is-self', 'Ce rendez-vous');
        document.getElementById('dayBar').innerHTML = html;
    }

    var loadDay = SP.debounce(function () {
        if (!parseDate(fDate.value)) { jourItems = []; renderDay(); return; }
        fetch(SP.url('api/rdv_jour.php?date=' + encodeURIComponent(fDate.value) + '&exclure=' + rdvId), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { jourItems = data && data.success ? data.items : []; renderDay(); })
            .catch(function () { jourItems = []; renderDay(); });
    }, 250);

    SP.$$('#dateChips [data-jours]').forEach(function (c) {
        c.addEventListener('click', function () {
            var t = new Date(); t.setDate(t.getDate() + parseInt(c.getAttribute('data-jours'), 10));
            fDate.value = isoLocal(t);
            fDate.dispatchEvent(new Event('change', { bubbles: true }));
            form.setAttribute('data-sp-dirty', '1');
        });
    });
    dureeChips.forEach(function (c) {
        c.addEventListener('click', function () {
            var min = parseInt(c.getAttribute('data-duree'), 10), a = toMin(fDebut.value);
            if (min === 0) { fFin.value = ''; duree = 0; }
            else if (a !== null) { fFin.value = toTime(a + min); duree = min; }
            else { fDebut.focus(); return; }
            form.setAttribute('data-sp-dirty', '1');
            refreshChips(); refreshRecap();
        });
    });
    fDebut.addEventListener('change', function () {
        var a = toMin(fDebut.value);
        if (a !== null && duree > 0) fFin.value = toTime(a + duree);
        refreshChips(); refreshRecap();
    });
    fFin.addEventListener('change', function () {
        var a = toMin(fDebut.value), b = toMin(fFin.value);
        duree = a !== null && b !== null && b > a ? b - a : 0;
        refreshChips(); refreshRecap();
    });
    fDate.addEventListener('change', function () { refreshChips(); refreshRecap(); loadDay(); });
    form.addEventListener('change', function (e) { if (e.target.name === 'statut') renderDay(); });

    // Client lié : carte récapitulative et contact facultatif
    function refreshClient() {
        var opt = fClient.options[fClient.selectedIndex];
        var has = !!(opt && opt.value);
        var mini = document.getElementById('clientMini');
        mini.hidden = !has;
        document.getElementById('rContactLabel').textContent = has ? 'Interlocuteur (facultatif)' : 'Nom du contact';
        fContact.placeholder = has ? 'Par défaut : ' + opt.textContent.trim() : 'Personne rencontrée';
        fTel.placeholder = has && opt.getAttribute('data-sub') ? 'Par défaut : ' + opt.getAttribute('data-sub') : '70 00 00 00';
        if (!has) return;
        var nom = opt.textContent.trim();
        var av = document.getElementById('clientMiniAv');
        av.className = 'sp-avatar av-' + (opt.getAttribute('data-av') || 0);
        av.textContent = nom.split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('');
        document.getElementById('clientMiniNom').textContent = nom;
        document.getElementById('clientMiniTel').innerHTML = opt.getAttribute('data-sub') ? '<i class="fa-solid fa-phone me-1"></i>' + SP.esc(opt.getAttribute('data-sub')) : 'Pas de téléphone enregistré';
        document.getElementById('clientMiniFiche').href = SP.url('clients/fiche.php?id=' + encodeURIComponent(opt.value));
        mini.classList.remove('sp-anim-pop'); void mini.offsetWidth; mini.classList.add('sp-anim-pop');
    }
    fClient.addEventListener('change', refreshClient);

    SP.shortcuts.register('ctrl+Enter', 'Enregistrer le rendez-vous', function () { if (form.requestSubmit) form.requestSubmit(); else form.submit(); }, { group: 'Formulaire', global: true, display: 'ctrl+Entrée' });

    refreshClient();
    refreshChips();
    refreshRecap();
    loadDay();
});
</script>
