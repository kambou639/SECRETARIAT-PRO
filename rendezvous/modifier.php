<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM rendezvous WHERE id = ?');
$stmt->execute([$id]);
$rdv = $stmt->fetch();
if (!$rdv) {
    flash_set('danger', 'Rendez-vous introuvable.');
    redirect('agenda.php');
}

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $titre = clean_input($_POST['titre'] ?? '');
    $dateRdv = clean_input($_POST['date_rdv'] ?? '');
    $heureDebut = clean_input($_POST['heure_debut'] ?? '');
    $heureFin = clean_input($_POST['heure_fin'] ?? '') ?: null;
    $lieu = clean_input($_POST['lieu'] ?? '');
    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $contactNom = clean_input($_POST['contact_nom'] ?? '');
    $contactTel = clean_input($_POST['contact_telephone'] ?? '');
    $statut = clean_input($_POST['statut'] ?? 'planifie');
    $description = clean_input($_POST['description'] ?? '');

    if ($titre === '' || $dateRdv === '' || $heureDebut === '') {
        $errors[] = 'Titre, date et heure de début sont obligatoires.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE rendezvous SET titre=?, description=?, date_rdv=?, heure_debut=?, heure_fin=?, lieu=?, contact_nom=?, contact_telephone=?, client_id=?, statut=? WHERE id=?');
        $stmt->execute([$titre, $description, $dateRdv, $heureDebut, $heureFin, $lieu, $contactNom, $contactTel, $clientId, $statut, $id]);
        log_activity('rdv_modification', "RDV modifié : $titre");
        flash_set('success', 'Rendez-vous mis à jour.');
        redirect('agenda.php?debut=' . $dateRdv);
    } else {
        $rdv = array_merge($rdv, $_POST);
    }
}

$pageTitle = 'Modifier le rendez-vous';
$activeMenu = 'agenda';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card"><div class="sp-card-header"><h6>Modifier le rendez-vous</h6></div>
<div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
