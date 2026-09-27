<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();
$errors = [];
$client = $_POST;

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
        $stmt = $pdo->prepare('INSERT INTO clients (type, nom, prenom, telephone, email, adresse, ville, notes) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$type, $nom, $prenom, $telephone, $email, $adresse, $ville, $notes]);
        log_activity('client_creation', "Client créé : $nom");
        flash_set('success', 'Client ajouté avec succès.');
        redirect('liste.php');
    }
}

$pageTitle = 'Nouveau client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card"><div class="sp-card-header"><h6>Nouveau client</h6></div>
<div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
