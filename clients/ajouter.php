<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = Database::getConnection();
$errors = [];
$client = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $type = ($_POST['type'] ?? '') === 'entreprise' ? 'entreprise' : 'particulier';
    $nom = mb_substr(clean_input($_POST['nom'] ?? ''), 0, 100);
    $prenom = $type === 'entreprise' ? '' : mb_substr(clean_input($_POST['prenom'] ?? ''), 0, 100);
    $telephone = mb_substr(clean_input($_POST['telephone'] ?? ''), 0, 30);
    $email = mb_substr(clean_input($_POST['email'] ?? ''), 0, 150);
    $ville = mb_substr(clean_input($_POST['ville'] ?? ''), 0, 100);
    $adresse = mb_substr(clean_input($_POST['adresse'] ?? ''), 0, 255);
    $notes = clean_input($_POST['notes'] ?? '');

    if ($nom === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if (empty($errors) && $telephone !== '') {
        $dup = $pdo->prepare('SELECT id FROM clients WHERE nom = ? AND COALESCE(prenom, \'\') = ? AND telephone = ? LIMIT 1');
        $dup->execute([$nom, $prenom, $telephone]);
        if ($existant = $dup->fetch()) {
            $errors[] = 'Ce client existe déjà (même nom et même téléphone).';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO clients (type, nom, prenom, telephone, email, adresse, ville, notes) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$type, $nom, $prenom ?: null, $telephone ?: null, $email ?: null, $adresse ?: null, $ville ?: null, $notes ?: null]);
        $id = (int)$pdo->lastInsertId();
        log_activity('client_creation', "Client créé : " . trim("$nom $prenom"));
        flash_set('success', 'Client « ' . trim("$nom $prenom") . ' » ajouté.');
        redirect('fiche.php?id=' . $id);
    }
}

$pageTitle = 'Nouveau client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6><span class="sp-card-icon tone-success"><i class="fa-solid fa-user-plus"></i></span>Nouveau client</h6><a href="liste.php" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Clients</a></div>
    <div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
