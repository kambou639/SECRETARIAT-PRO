<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$pdo = Database::getConnection();
$rows = $pdo->query("
    SELECT a.code AS Code, a.nom AS Nom, c.nom AS Categorie, IF(a.type='service','service','produit') AS Type,
           a.prix_achat AS Prix_achat, a.prix_vente AS Prix_vente, a.stock AS Stock, a.seuil_alerte AS Seuil_alerte,
           a.unite AS Unite, IF(a.actif,'Oui','Non') AS Actif
    FROM articles a LEFT JOIN categories c ON c.id = a.categorie_id
    ORDER BY a.nom
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
