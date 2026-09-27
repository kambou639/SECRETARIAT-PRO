<?php
/**
 * Recherche globale (palette Ctrl+K) : articles, clients, ventes, courriers,
 * rendez-vous et utilisateurs, selon les droits de l'utilisateur connecté.
 * Usage : api/recherche.php?q=texte
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_login();

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    json_response(['success' => true, 'groups' => []]);
}
$q = mb_substr($q, 0, 80);
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
$pdo = Database::getConnection();
$groups = [];

// --- Articles ---
$stmt = $pdo->prepare("SELECT id, code, nom, prix_vente, stock, type, actif, unite FROM articles
    WHERE nom LIKE ? OR code LIKE ? ORDER BY actif DESC, nom ASC LIMIT 6");
$stmt->execute([$like, $like]);
$items = [];
foreach ($stmt->fetchAll() as $a) {
    $sub = $a['code'] . ' · ' . fmt_money((float)$a['prix_vente']) . ' / ' . $a['unite'];
    $sub .= $a['type'] === 'service' ? ' · service' : ' · stock ' . (int)$a['stock'];
    if (!$a['actif']) $sub .= ' · inactif';
    $items[] = [
        'title' => $a['nom'],
        'sub'   => $sub,
        'url'   => has_role('admin', 'secretaire') ? 'articles/modifier.php?id=' . $a['id'] : 'articles/liste.php?q=' . rawurlencode($a['code']),
        'icon'  => $a['type'] === 'service' ? 'fa-print' : 'fa-box',
    ];
}
if ($items) $groups[] = ['label' => 'Articles', 'icon' => 'fa-box', 'items' => $items];

// --- Clients ---
$stmt = $pdo->prepare("SELECT id, nom, prenom, telephone, type, ville FROM clients
    WHERE nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR email LIKE ? OR CONCAT(nom, ' ', COALESCE(prenom, '')) LIKE ?
    ORDER BY nom ASC LIMIT 6");
$stmt->execute([$like, $like, $like, $like, $like]);
$items = [];
foreach ($stmt->fetchAll() as $c) {
    $items[] = [
        'title' => trim($c['nom'] . ' ' . ($c['prenom'] ?? '')),
        'sub'   => implode(' · ', array_filter([$c['type'] === 'entreprise' ? 'Entreprise' : 'Particulier', $c['telephone'], $c['ville']])),
        'url'   => 'clients/fiche.php?id=' . $c['id'],
        'icon'  => $c['type'] === 'entreprise' ? 'fa-building' : 'fa-user',
    ];
}
if ($items) $groups[] = ['label' => 'Clients', 'icon' => 'fa-user', 'items' => $items];

// --- Ventes (numéro de facture) ---
if (has_role('admin', 'vendeur')) {
    $stmt = $pdo->prepare("SELECT v.id, v.numero_facture, v.montant_total, v.created_at, v.statut, c.nom AS client_nom
        FROM ventes v LEFT JOIN clients c ON c.id = v.client_id
        WHERE v.numero_facture LIKE ? ORDER BY v.created_at DESC LIMIT 5");
    $stmt->execute([$like]);
    $items = [];
    foreach ($stmt->fetchAll() as $v) {
        $items[] = [
            'title' => $v['numero_facture'],
            'sub'   => fmt_money((float)$v['montant_total']) . ' · ' . fmt_datetime($v['created_at']) . ' · ' . ($v['client_nom'] ?: 'Client de passage') . ($v['statut'] === 'annulee' ? ' · annulée' : ''),
            'url'   => 'ventes/facture.php?id=' . $v['id'],
            'icon'  => 'fa-receipt',
        ];
    }
    if ($items) $groups[] = ['label' => 'Ventes', 'icon' => 'fa-receipt', 'items' => $items];
}

// --- Courriers & rendez-vous ---
if (has_role('admin', 'secretaire')) {
    $stmt = $pdo->prepare("SELECT id, type, numero, objet, expediteur, destinataire, date_courrier, statut FROM courriers
        WHERE objet LIKE ? OR numero LIKE ? OR expediteur LIKE ? OR destinataire LIKE ? ORDER BY date_courrier DESC LIMIT 5");
    $stmt->execute([$like, $like, $like, $like]);
    $items = [];
    foreach ($stmt->fetchAll() as $c) {
        $corresp = $c['type'] === 'entrant' ? $c['expediteur'] : $c['destinataire'];
        $items[] = [
            'title' => $c['objet'],
            'sub'   => implode(' · ', array_filter([$c['numero'], $corresp, fmt_date($c['date_courrier']), statut_courrier_meta($c['statut'])[0]])),
            'url'   => 'courrier/voir.php?id=' . $c['id'],
            'icon'  => $c['type'] === 'entrant' ? 'fa-envelope-open' : 'fa-paper-plane',
        ];
    }
    if ($items) $groups[] = ['label' => 'Courrier', 'icon' => 'fa-envelope', 'items' => $items];

    $stmt = $pdo->prepare("SELECT id, titre, date_rdv, heure_debut, lieu, statut FROM rendezvous
        WHERE titre LIKE ? OR contact_nom LIKE ? OR lieu LIKE ? ORDER BY date_rdv DESC LIMIT 5");
    $stmt->execute([$like, $like, $like]);
    $items = [];
    foreach ($stmt->fetchAll() as $r) {
        $items[] = [
            'title' => $r['titre'],
            'sub'   => implode(' · ', array_filter([fmt_date_fr($r['date_rdv']) . ' à ' . substr($r['heure_debut'], 0, 5), $r['lieu'], statut_rdv_meta($r['statut'])[0]])),
            'url'   => 'rendezvous/modifier.php?id=' . $r['id'],
            'icon'  => 'fa-calendar-day',
        ];
    }
    if ($items) $groups[] = ['label' => 'Rendez-vous', 'icon' => 'fa-calendar', 'items' => $items];
}

// --- Utilisateurs ---
if (has_role('admin')) {
    $stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE full_name LIKE ? OR username LIKE ? ORDER BY full_name LIMIT 4");
    $stmt->execute([$like, $like]);
    $items = [];
    foreach ($stmt->fetchAll() as $u) {
        $items[] = ['title' => $u['full_name'], 'sub' => '@' . $u['username'] . ' · ' . role_label($u['role']), 'url' => 'utilisateurs/modifier.php?id=' . $u['id'], 'icon' => 'fa-user-gear'];
    }
    if ($items) $groups[] = ['label' => 'Utilisateurs', 'icon' => 'fa-users', 'items' => $items];
}

json_response(['success' => true, 'groups' => $groups]);
