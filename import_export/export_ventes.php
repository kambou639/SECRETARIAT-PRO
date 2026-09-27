<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'vendeur');

$debut = clean_input($_GET['debut'] ?? date('Y-m-01'));
$fin = clean_input($_GET['fin'] ?? date('Y-m-d'));

$pdo = Database::getConnection();
$stmt = $pdo->prepare("
    SELECT v.numero_facture AS Facture, v.created_at AS Date, u.full_name AS Vendeur,
           COALESCE(c.nom,'Client de passage') AS Client, v.montant_brut AS `Sous-total`,
           v.remise_montant AS Remise, v.montant_total AS Montant, v.montant_paye AS `Montant payé`,
           v.mode_paiement AS Paiement, v.statut_paiement AS `Statut paiement`, v.statut AS Statut
    FROM ventes v LEFT JOIN users u ON u.id=v.user_id LEFT JOIN clients c ON c.id=v.client_id
    WHERE DATE(v.created_at) BETWEEN ? AND ? ORDER BY v.created_at
");
$stmt->execute([$debut, $fin]);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Export ventes</title></head>
<body>
<script src="../assets/js/xlsx.full.min.js"></script>
<script>
const data = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
const ws = XLSX.utils.json_to_sheet(data);
const wb = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(wb, ws, 'Ventes');
XLSX.writeFile(wb, 'ventes_<?= e($debut) ?>_au_<?= e($fin) ?>.xlsx');
window.location.href = '../ventes/historique.php';
</script>
<p>Génération du fichier en cours, téléchargement automatique...</p>
</body></html>
