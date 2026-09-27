<?php
/**
 * Centre de notifications : alertes de stock, créances, rendez-vous du jour,
 * courriers en attente. Renvoie aussi les compteurs affichés dans le menu.
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login();

$pdo = Database::getConnection();
$items = [];
$counts = ['stock' => 0, 'credits' => 0, 'courrier' => 0, 'rdv' => 0];
$gereCatalogue = has_role('admin', 'secretaire');

// --- Stock : ruptures et seuils d'alerte ---
$counts['stock'] = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE actif = 1 AND type = 'produit' AND stock <= seuil_alerte")->fetchColumn();
if ($counts['stock'] > 0) {
    $rows = $pdo->query("SELECT id, nom, stock, seuil_alerte, unite FROM articles
        WHERE actif = 1 AND type = 'produit' AND stock <= seuil_alerte ORDER BY stock ASC, nom ASC LIMIT 4")->fetchAll();
    foreach ($rows as $a) {
        $rupture = (int)$a['stock'] <= 0;
        $items[] = [
            'level' => $rupture ? 'danger' : 'warning',
            'icon'  => $rupture ? 'fa-box-open' : 'fa-triangle-exclamation',
            'title' => ($rupture ? 'Rupture : ' : 'Stock faible : ') . $a['nom'],
            'text'  => $rupture ? 'Plus aucun ' . $a['unite'] . ' en stock.' : (int)$a['stock'] . ' restant(s) — seuil d\'alerte ' . (int)$a['seuil_alerte'] . '.',
            'url'   => $gereCatalogue ? 'articles/stock_ajuster.php?id=' . $a['id'] : 'articles/liste.php?stock=alerte',
        ];
    }
    if ($counts['stock'] > 4) {
        $items[] = ['level' => 'warning', 'icon' => 'fa-boxes-stacked', 'title' => ($counts['stock'] - 4) . ' autre(s) article(s) à réapprovisionner', 'text' => 'Voir le catalogue filtré sur les alertes.', 'url' => 'articles/liste.php?stock=alerte'];
    }
}

// --- Créances (ventes à crédit) ---
if (has_role('admin', 'vendeur')) {
    $row = $pdo->query("SELECT COUNT(*) AS nb, COALESCE(SUM(montant_total - montant_paye), 0) AS du,
            SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS retard
        FROM ventes WHERE statut = 'validee' AND statut_paiement <> 'payee'")->fetch();
    $counts['credits'] = (int)$row['nb'];
    if ((int)$row['retard'] > 0) {
        $items[] = [
            'level' => 'danger', 'icon' => 'fa-hourglass-end',
            'title' => (int)$row['retard'] . ' créance(s) de plus de 30 jours',
            'text'  => 'Pensez à relancer les clients concernés.',
            'url'   => 'ventes/credits.php?tri=anciennete',
        ];
    } elseif ($counts['credits'] > 0) {
        $items[] = [
            'level' => 'info', 'icon' => 'fa-hand-holding-dollar',
            'title' => $counts['credits'] . ' vente(s) à crédit en cours',
            'text'  => 'Total restant dû : ' . fmt_money((float)$row['du']) . '.',
            'url'   => 'ventes/credits.php',
        ];
    }
}

// --- Rendez-vous du jour & courrier en attente ---
if (has_role('admin', 'secretaire')) {
    $rdvs = $pdo->query("SELECT id, titre, heure_debut, lieu FROM rendezvous
        WHERE date_rdv = CURDATE() AND statut IN ('planifie', 'confirme') ORDER BY heure_debut ASC")->fetchAll();
    $counts['rdv'] = count($rdvs);
    $now = date('H:i:s');
    foreach (array_slice(array_values(array_filter($rdvs, fn($r) => $r['heure_debut'] >= date('H:i:s', strtotime('-30 minutes')))), 0, 3) as $r) {
        $bientot = $r['heure_debut'] >= $now && $r['heure_debut'] <= date('H:i:s', strtotime('+1 hour'));
        $items[] = [
            'level' => $bientot ? 'warning' : 'info',
            'icon'  => 'fa-calendar-day',
            'title' => 'RDV à ' . substr($r['heure_debut'], 0, 5) . ' : ' . $r['titre'],
            'text'  => ($bientot ? 'Dans moins d\'une heure' : 'Aujourd\'hui') . ($r['lieu'] ? ' · ' . $r['lieu'] : ''),
            'url'   => 'rendezvous/agenda.php',
        ];
    }

    $row = $pdo->query("SELECT COUNT(*) AS nb, SUM(CASE WHEN date_enregistrement < DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS vieux
        FROM courriers WHERE statut IN ('recu', 'en_traitement')")->fetch();
    $counts['courrier'] = (int)$row['nb'];
    if ($counts['courrier'] > 0) {
        $items[] = [
            'level' => (int)$row['vieux'] > 0 ? 'warning' : 'info',
            'icon'  => 'fa-envelope-open-text',
            'title' => $counts['courrier'] . ' courrier(s) en attente de traitement',
            'text'  => (int)$row['vieux'] > 0 ? (int)$row['vieux'] . ' reçu(s) il y a plus de 3 jours.' : 'Reçus récemment.',
            'url'   => 'courrier/liste.php?vue=attente',
        ];
    }
}

json_response(['success' => true, 'total' => count($items), 'counts' => $counts, 'items' => $items]);
