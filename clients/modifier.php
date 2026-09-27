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

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE clients SET type=?, nom=?, prenom=?, telephone=?, email=?, adresse=?, ville=?, notes=? WHERE id=?');
        $stmt->execute([$type, $nom, $prenom ?: null, $telephone ?: null, $email ?: null, $adresse ?: null, $ville ?: null, $notes ?: null, $id]);
        log_activity('client_modification', "Client modifié : " . trim("$nom $prenom"));
        flash_set('success', 'Client mis à jour.');
        redirect('fiche.php?id=' . $id);
    } else {
        $client = array_merge($client, $_POST);
    }
}

$pageTitle = 'Modifier le client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header">
        <h6><?= avatar_html(trim($client['nom'] . ' ' . ($client['prenom'] ?? '')), 'sm') ?> <?= e(trim($client['nom'] . ' ' . ($client['prenom'] ?? ''))) ?></h6>
        <a href="fiche.php?id=<?= $id ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Fiche client</a>
    </div>
    <div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
