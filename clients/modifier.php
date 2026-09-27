<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    flash_set('danger', 'Client introuvable.');
    redirect('liste.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $type = ($_POST['type'] ?? '') === 'entreprise' ? 'entreprise' : 'particulier';
    $nom = clean_input($_POST['nom'] ?? '');
    $prenom = clean_input($_POST['prenom'] ?? '');
    $telephone = clean_input($_POST['telephone'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $ville = clean_input($_POST['ville'] ?? '');
    $adresse = clean_input($_POST['adresse'] ?? '');
    $notes = clean_input($_POST['notes'] ?? '');

    if ($nom === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE clients SET type=?, nom=?, prenom=?, telephone=?, email=?, adresse=?, ville=?, notes=? WHERE id=?');
        $stmt->execute([$type, $nom, $prenom, $telephone, $email, $adresse, $ville, $notes, $id]);
        log_activity('client_modification', "Client modifié : $nom");
        flash_set('success', 'Client mis à jour.');
        redirect('liste.php');
    } else {
        $client = array_merge($client, $_POST);
    }
}

$pageTitle = 'Modifier le client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card"><div class="sp-card-header"><h6>Modifier : <?= e($client['nom']) ?></h6></div>
<div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
