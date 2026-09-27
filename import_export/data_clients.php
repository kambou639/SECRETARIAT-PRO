<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$pdo = Database::getConnection();
$rows = $pdo->query("
    SELECT nom AS Nom, prenom AS Prenom, type AS Type, telephone AS Telephone, email AS Email,
           ville AS Ville, adresse AS Adresse
    FROM clients ORDER BY nom
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
