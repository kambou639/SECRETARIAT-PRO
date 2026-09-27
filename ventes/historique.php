<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');
$pdo = Database::getConnection();

$dateDebut = valid_date($_GET['debut'] ?? null, date('Y-m-01'));
$dateFin = valid_date($_GET['fin'] ?? null, date('Y-m-d'));
if ($dateFin < $dateDebut) {
    [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
}
$statut = in_array($_GET['statut'] ?? '', ['validee', 'annulee'], true) ? $_GET['statut'] : '';
$paiement = in_array($_GET['paiement'] ?? '', ['payee', 'partielle', 'impayee'], true) ? $_GET['paiement'] : '';
$mode = array_key_exists($_GET['mode'] ?? '', modes_paiement()) ? $_GET['mode'] : '';
$vendeurId = has_role('admin') ? (int)($_GET['vendeur'] ?? 0) : 0;
$q = clean_input($_GET['q'] ?? '');

$where = ['DATE(v.created_at) BETWEEN ? AND ?'];
$params = [$dateDebut, $dateFin];
if ($statut !== '') { $where[] = 'v.statut = ?'; $params[] = $statut; }
if ($paiement !== '') { $where[] = "v.statut = 'validee' AND v.statut_paiement = ?"; $params[] = $paiement; }
if ($mode !== '') { $where[] = 'v.mode_paiement = ?'; $params[] = $mode; }
if ($vendeurId > 0) { $where[] = 'v.user_id = ?'; $params[] = $vendeurId; }
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = "(v.numero_facture LIKE ? OR c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);
$from = "FROM ventes v LEFT JOIN users u ON u.id = v.user_id LEFT JOIN clients c ON c.id = v.client_id WHERE $whereSql";

// Synthèse de la période filtrée
$stmt = $pdo->prepare("SELECT
        SUM(CASE WHEN v.statut='validee' THEN 1 ELSE 0 END) AS nb_validees,
        SUM(CASE WHEN v.statut='annulee' THEN 1 ELSE 0 END) AS nb_annulees,
        COALESCE(SUM(CASE WHEN v.statut='validee' THEN v.montant_total END), 0) AS ca,
        COALESCE(SUM(CASE WHEN v.statut='validee' THEN v.remise_montant END), 0) AS remises,
        COALESCE(SUM(CASE WHEN v.statut='validee' THEN v.montant_total - v.montant_paye END), 0) AS du,
        COUNT(*) AS total_lignes
    $from");
$stmt->execute($params);
$synth = $stmt->fetch();
$nbValidees = (int)$synth['nb_validees'];
$panierMoyen = $nbValidees > 0 ? (float)$synth['ca'] / $nbValidees : 0;

$pg = paginate((int)$synth['total_lignes'], (int)($_GET['page'] ?? 1), 25);
$stmt = $pdo->prepare("SELECT v.*, u.full_name AS vendeur_nom, c.nom AS client_nom, c.prenom AS client_prenom
    $from ORDER BY v.created_at DESC, v.id DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$ventes = $stmt->fetchAll();

$vendeurs = has_role('admin') ? $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin','vendeur') ORDER BY full_name")->fetchAll() : [];
$formatDefaut = get_param('format_recu', 'a4') === 'ticket' ? 'ticket' : 'a4';
$exportQuery = http_build_query(array_filter(['debut' => $dateDebut, 'fin' => $dateFin, 'statut' => $statut, 'paiement' => $paiement, 'mode' => $mode, 'vendeur' => $vendeurId ?: '', 'q' => $q], fn($v) => $v !== ''));
$filtresActifs = ($statut !== '') + ($paiement !== '') + ($mode !== '') + ($vendeurId > 0) + ($q !== '');

$pageTitle = 'Historique des ventes';
$activeMenu = 'ventes';
include __DIR__ . '/../includes/header.php';
?>

<div class="sp-page-head no-print">
    <div>
        <h2>Ventes du <?= e(fmt_date_fr($dateDebut)) ?><?= $dateDebut !== $dateFin ? ' au ' . e(fmt_date_fr($dateFin)) : '' ?></h2>
        <p><?= fmt_number((float)$synth['total_lignes']) ?> opération(s)<?= $filtresActifs ? ' · ' . $filtresActifs . ' filtre(s) actif(s)' : '' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?= date_presets_html($dateDebut, $dateFin) ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="sp-stat tone-primary"><div class="sp-stat-top"><span class="sp-stat-label">Chiffre d'affaires</span><span class="sp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></span></div><div class="sp-stat-value" data-countup="<?= (float)$synth['ca'] ?>" data-format="money"><?= fmt_money_html((float)$synth['ca']) ?></div><div class="sp-stat-sub">ventes validées</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat"><div class="sp-stat-top"><span class="sp-stat-label">Ventes</span><span class="sp-stat-icon"><i class="fa-solid fa-receipt"></i></span></div><div class="sp-stat-value" data-countup="<?= $nbValidees ?>"><?= $nbValidees ?></div><div class="sp-stat-sub"><?= (int)$synth['nb_annulees'] ?> annulée(s)</div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat tone-info"><div class="sp-stat-top"><span class="sp-stat-label">Panier moyen</span><span class="sp-stat-icon"><i class="fa-solid fa-basket-shopping"></i></span></div><div class="sp-stat-value" data-countup="<?= round($panierMoyen) ?>" data-format="money"><?= fmt_money_html($panierMoyen) ?></div><div class="sp-stat-sub">remises : <?= fmt_money((float)$synth['remises']) ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="sp-stat tone-danger"><div class="sp-stat-top"><span class="sp-stat-label">Reste à encaisser</span><span class="sp-stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span></div><div class="sp-stat-value" data-countup="<?= (float)$synth['du'] ?>" data-format="money"><?= fmt_money_html((float)$synth['du']) ?></div><div class="sp-stat-sub"><a href="credits.php">voir les crédits</a></div></div></div>
</div>

<div class="sp-card mb-3 no-print">
    <div class="sp-card-body">
        <form method="get" data-no-loading>
            <div class="row g-2 align-items-end">
                <div class="col-md-12 col-xl-5">
                    <label class="form-label small" for="fq">Recherche</label>
                    <div class="sp-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="fq" name="q" class="form-control" placeholder="N° facture, client, téléphone…" value="<?= e($q) ?>"></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small" for="fdebut">Du</label>
                    <input type="date" id="fdebut" name="debut" class="form-control" value="<?= e($dateDebut) ?>">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small" for="ffin">Au</label>
                    <input type="date" id="ffin" name="fin" class="form-control" value="<?= e($dateFin) ?>">
                </div>
                <div class="col-md-4 col-xl-3 d-flex gap-2">
                    <button class="btn btn-sp-primary flex-grow-1 text-nowrap"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
                    <button type="button" class="btn btn-outline-secondary text-nowrap" data-bs-toggle="collapse" data-bs-target="#filtresAvances" aria-expanded="<?= ($statut . $paiement . $mode) !== '' || $vendeurId ? 'true' : 'false' ?>" aria-controls="filtresAvances" title="Plus de filtres">
                        <i class="fa-solid fa-sliders"></i><?php $nbAv = ($statut !== '') + ($paiement !== '') + ($mode !== '') + ($vendeurId > 0); if ($nbAv): ?> <span class="badge bg-primary"><?= $nbAv ?></span><?php endif; ?>
                    </button>
                    <?php if ($filtresActifs): ?><a href="historique.php?debut=<?= e($dateDebut) ?>&amp;fin=<?= e($dateFin) ?>" class="btn btn-outline-secondary" title="Effacer les filtres"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
                </div>
            </div>
            <div class="collapse<?= ($statut . $paiement . $mode) !== '' || $vendeurId ? ' show' : '' ?>" id="filtresAvances">
                <div class="row g-2 pt-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small" for="fstatut">Statut</label>
                        <select id="fstatut" name="statut" class="form-select">
                            <option value="">Tous</option>
                            <option value="validee" <?= $statut === 'validee' ? 'selected' : '' ?>>Validée</option>
                            <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small" for="fpaiement">Règlement</label>
                        <select id="fpaiement" name="paiement" class="form-select">
                            <option value="">Tous</option>
                            <option value="payee" <?= $paiement === 'payee' ? 'selected' : '' ?>>Payée</option>
                            <option value="partielle" <?= $paiement === 'partielle' ? 'selected' : '' ?>>Paiement partiel</option>
                            <option value="impayee" <?= $paiement === 'impayee' ? 'selected' : '' ?>>Impayée</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small" for="fmode">Mode de paiement</label>
                        <select id="fmode" name="mode" class="form-select">
                            <option value="">Tous</option>
                            <?php foreach (modes_paiement() as $k => [$label]): ?>
                                <option value="<?= e($k) ?>" <?= $mode === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (has_role('admin')): ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small" for="fvendeur">Vendeur</label>
                        <select id="fvendeur" name="vendeur" class="form-select">
                            <option value="0">Tous</option>
                            <?php foreach ($vendeurs as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= $vendeurId === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon"><i class="fa-solid fa-receipt"></i></span>Opérations</h6>
        <div class="d-flex gap-2">
            <a href="../import_export/export_ventes.php?<?= e($exportQuery) ?>" class="btn btn-sm btn-outline-secondary" data-no-progress><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
            <a href="../import_export/export_ventes.php?<?= e($exportQuery) ?>&amp;detail=1" class="btn btn-sm btn-outline-secondary" data-no-progress title="Une ligne par article vendu"><i class="fa-solid fa-list-ul me-1"></i>Excel détaillé</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-sp-print><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
    <div class="sp-card-body p-0">
        <?php if (empty($ventes)): ?>
            <?= empty_state('fa-receipt', 'Aucune vente', 'Aucune opération ne correspond à ces critères sur la période.', '<a href="caisse.php" class="btn btn-sp-amber"><i class="fa-solid fa-cash-register me-1"></i>Ouvrir la caisse</a>') ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sp mb-0" data-sp-sort>
            <thead><tr>
                <th data-sort="text">N° facture</th><th data-sort="text">Date</th><th data-sort="text">Client</th><th class="d-none d-lg-table-cell">Vendeur</th>
                <th class="text-end" data-sort="num">Montant</th><th class="d-none d-md-table-cell">Paiement</th><th>Statut</th><th class="text-end no-print">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($ventes as $v): $annulee = $v['statut'] === 'annulee'; ?>
                <tr class="<?= $annulee ? 'is-muted' : '' ?>">
                    <td><code><?= e($v['numero_facture']) ?></code></td>
                    <td data-value="<?= e($v['created_at']) ?>" class="text-nowrap"><?= fmt_date($v['created_at']) ?><div class="sp-cell-sub"><?= fmt_date($v['created_at'], 'H:i') ?></div></td>
                    <td>
                        <?php if ($v['client_nom']): ?>
                            <a href="../clients/fiche.php?id=<?= (int)$v['client_id'] ?>" class="sp-cell-title"><?= e(trim($v['client_nom'] . ' ' . ($v['client_prenom'] ?? ''))) ?></a>
                        <?php else: ?><span class="text-muted">Client de passage</span><?php endif; ?>
                        <?php if ($v['observations']): ?><div class="sp-cell-sub text-truncate" style="max-width:220px;" title="<?= e($v['observations']) ?>"><i class="fa-regular fa-note-sticky me-1"></i><?= e($v['observations']) ?></div><?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted text-nowrap"><?= e($v['vendeur_nom']) ?></td>
                    <td class="text-end fw-semibold text-nowrap" data-value="<?= (float)$v['montant_total'] ?>">
                        <span class="<?= $annulee ? 'text-decoration-line-through' : '' ?>"><?= fmt_money((float)$v['montant_total']) ?></span>
                        <?php if ((float)($v['remise_montant'] ?? 0) > 0): ?>
                            <i class="fa-solid fa-tag text-danger ms-1" title="Remise : -<?= e(fmt_money((float)$v['remise_montant'])) ?>"></i>
                        <?php endif; ?>
                        <?php if (!$annulee && $v['statut_paiement'] !== 'payee'): ?><div class="sp-cell-sub text-danger">reste <?= fmt_money((float)$v['montant_total'] - (float)$v['montant_paye']) ?></div><?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap"><span class="text-muted small"><i class="fa-solid <?= e(mode_paiement_icon($v['mode_paiement'])) ?> me-1"></i><?= e(mode_paiement_label($v['mode_paiement'])) ?></span></td>
                    <td><?= vente_statut_badges($v) ?></td>
                    <td class="td-actions no-print">
                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir les détails" onclick="showVenteDetails(<?= (int)$v['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                        <div class="btn-group">
                            <a href="facture.php?id=<?= (int)$v['id'] ?>&amp;format=<?= $formatDefaut ?>" class="btn btn-sm btn-icon btn-outline-secondary" title="Imprimer" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Autres formats"></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="facture.php?id=<?= (int)$v['id'] ?>&amp;format=a4" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i>Facture A4</a></li>
                                <li><a class="dropdown-item" href="facture.php?id=<?= (int)$v['id'] ?>&amp;format=ticket" target="_blank" rel="noopener"><i class="fa-solid fa-receipt"></i>Ticket de caisse</a></li>
                            </ul>
                        </div>
                        <?php if (!$annulee && has_role('admin')): ?>
                        <a href="annuler.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-icon btn-outline-danger" title="Annuler la vente" data-method="post"
                           data-confirm="Annuler la vente <?= e($v['numero_facture']) ?> ? Le stock des produits sera remis à jour." data-confirm-title="Annuler la vente" data-confirm-label="Annuler la vente" data-confirm-type="danger"><i class="fa-solid fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= pagination_html($pg, 'opérations') ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/modal_vente_details.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
