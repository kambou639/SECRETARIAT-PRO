<?php
/**
 * Export Excel des ventes (généré dans le navigateur avec SheetJS, fourni en local).
 * Reprend les filtres de l'historique ; detail=1 : une ligne par article vendu.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');

$debut = valid_date($_GET['debut'] ?? null, date('Y-m-01'));
$fin = valid_date($_GET['fin'] ?? null, date('Y-m-d'));
$statut = in_array($_GET['statut'] ?? '', ['validee', 'annulee'], true) ? $_GET['statut'] : '';
$paiement = in_array($_GET['paiement'] ?? '', ['payee', 'partielle', 'impayee'], true) ? $_GET['paiement'] : '';
$mode = array_key_exists($_GET['mode'] ?? '', modes_paiement()) ? $_GET['mode'] : '';
$vendeurId = has_role('admin') ? (int)($_GET['vendeur'] ?? 0) : 0;
$q = clean_input($_GET['q'] ?? '');
$detail = !empty($_GET['detail']);

$where = ['DATE(v.created_at) BETWEEN ? AND ?'];
$params = [$debut, $fin];
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

$pdo = Database::getConnection();
$statutLabel = fn($v) => $v['statut'] === 'annulee' ? 'Annulée' : statut_paiement_meta($v['statut_paiement'])[0];
$rows = [];
if ($detail) {
    $stmt = $pdo->prepare("SELECT v.numero_facture, v.created_at, v.statut, v.statut_paiement, u.full_name AS vendeur, c.nom AS client_nom, c.prenom AS client_prenom,
            a.nom AS article, a.code, a.unite, d.quantite, d.prix_unitaire, d.sous_total
        FROM vente_details d JOIN ventes v ON v.id = d.vente_id LEFT JOIN articles a ON a.id = d.article_id
        LEFT JOIN users u ON u.id = v.user_id LEFT JOIN clients c ON c.id = v.client_id
        WHERE $whereSql ORDER BY v.created_at, d.id");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'Facture' => $r['numero_facture'], 'Date' => fmt_datetime($r['created_at']),
            'Client' => $r['client_nom'] ? trim($r['client_nom'] . ' ' . ($r['client_prenom'] ?? '')) : 'Client de passage',
            'Vendeur' => $r['vendeur'], 'Article' => $r['article'], 'Code' => $r['code'],
            'Quantité' => (int)$r['quantite'], 'Unité' => $r['unite'], 'Prix unitaire' => (float)$r['prix_unitaire'],
            'Sous-total' => (float)$r['sous_total'], 'Statut' => $statutLabel($r),
        ];
    }
} else {
    $stmt = $pdo->prepare("SELECT v.*, u.full_name AS vendeur, c.nom AS client_nom, c.prenom AS client_prenom
        FROM ventes v LEFT JOIN users u ON u.id = v.user_id LEFT JOIN clients c ON c.id = v.client_id
        WHERE $whereSql ORDER BY v.created_at");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $v) {
        $rows[] = [
            'Facture' => $v['numero_facture'], 'Date' => fmt_datetime($v['created_at']), 'Vendeur' => $v['vendeur'],
            'Client' => $v['client_nom'] ? trim($v['client_nom'] . ' ' . ($v['client_prenom'] ?? '')) : 'Client de passage',
            'Sous-total' => (float)$v['montant_brut'], 'Remise' => (float)$v['remise_montant'], 'Montant' => (float)$v['montant_total'],
            'Montant payé' => (float)$v['montant_paye'], 'Reste dû' => $v['statut'] === 'validee' ? round((float)$v['montant_total'] - (float)$v['montant_paye'], 2) : 0,
            'Paiement' => mode_paiement_label($v['mode_paiement']), 'Statut' => $statutLabel($v), 'Observations' => (string)$v['observations'],
        ];
    }
}
$fichier = ($detail ? 'ventes_detail_' : 'ventes_') . $debut . '_au_' . $fin . '.xlsx';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export des ventes</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/inter.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center sp-anim-fade-up">
    <div class="sp-empty-state"><i class="fa-solid fa-file-excel"></i>
        <h6 id="msg">Génération du fichier Excel…</h6>
        <p><?= count($rows) ?> ligne(s) · du <?= e(fmt_date($debut)) ?> au <?= e(fmt_date($fin)) ?></p>
        <a href="../ventes/historique.php" class="btn btn-sp-primary" id="retour"><i class="fa-solid fa-arrow-left me-1"></i>Retour à l'historique</a>
    </div>
</div>
<script src="<?= asset_url('assets/js/xlsx.full.min.js') ?>"></script>
<script>
(function () {
    var data = <?= js_json($rows) ?>;
    var ws = XLSX.utils.json_to_sheet(data.length ? data : [{ Information: 'Aucune vente sur cette période.' }]);
    var cols = data.length ? Object.keys(data[0]) : ['Information'];
    ws['!cols'] = cols.map(function (k) {
        var max = k.length;
        data.forEach(function (r) { max = Math.max(max, String(r[k] === null ? '' : r[k]).length); });
        return { wch: Math.min(48, max + 2) };
    });
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Ventes');
    XLSX.writeFile(wb, <?= js_json($fichier) ?>);
    document.getElementById('msg').textContent = 'Fichier téléchargé : ' + <?= js_json($fichier) ?>;
    setTimeout(function () { if (history.length > 1) history.back(); else window.location.href = '../ventes/historique.php'; }, 1200);
})();
</script>
</body>
</html>
