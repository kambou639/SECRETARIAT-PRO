<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();
$errors = [];
$courrier = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $type = ($_POST['type'] ?? '') === 'sortant' ? 'sortant' : 'entrant';
    $numero = mb_substr(clean_input($_POST['numero'] ?? ''), 0, 50);
    $objet = mb_substr(clean_input($_POST['objet'] ?? ''), 0, 255);
    $expediteur = mb_substr(clean_input($_POST['expediteur'] ?? ''), 0, 150);
    $destinataire = mb_substr(clean_input($_POST['destinataire'] ?? ''), 0, 150);
    $dateCourrier = valid_date($_POST['date_courrier'] ?? null, '');
    $statut = in_array($_POST['statut'] ?? '', ['recu', 'en_traitement', 'traite', 'archive', 'envoye'], true) ? $_POST['statut'] : ($type === 'sortant' ? 'envoye' : 'recu');
    $observations = clean_input($_POST['observations'] ?? '');

    if ($numero === '' || $objet === '' || $dateCourrier === '') {
        $errors[] = 'Numéro, objet et date (valide) sont obligatoires.';
    }

    $fichier = null;
    if (empty($errors) && !empty($_FILES['fichier_joint']['name'])) {
        try {
            $fichier = handle_upload($_FILES['fichier_joint'], UPLOAD_COURRIERS, ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO courriers (type, numero, objet, expediteur, destinataire, date_courrier, statut, fichier_joint, observations, user_id)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$type, $numero, $objet, $expediteur ?: null, $destinataire ?: null, $dateCourrier, $statut, $fichier, $observations ?: null, $_SESSION['user_id']]);
        $id = (int)$pdo->lastInsertId();
        log_activity('courrier_creation', "Courrier créé : $numero - $objet");
        flash_set('success', 'Courrier enregistré avec succès.');
        redirect('voir.php?id=' . $id);
    }
}

$pageTitle = 'Nouveau courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-envelope-circle-check"></i></span>Enregistrer un courrier</h6><a href="liste.php" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Courrier</a></div>
    <div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
