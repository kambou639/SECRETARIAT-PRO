<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courriers WHERE id = ?');
$stmt->execute([$id]);
$courrier = $stmt->fetch();
if (!$courrier) {
    flash_set('danger', 'Courrier introuvable.');
    redirect('liste.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $type = ($_POST['type'] ?? '') === 'sortant' ? 'sortant' : 'entrant';
    $numero = mb_substr(clean_input($_POST['numero'] ?? ''), 0, 50);
    $objet = mb_substr(clean_input($_POST['objet'] ?? ''), 0, 255);
    $expediteur = mb_substr(clean_input($_POST['expediteur'] ?? ''), 0, 150);
    $destinataire = mb_substr(clean_input($_POST['destinataire'] ?? ''), 0, 150);
    $dateCourrier = valid_date($_POST['date_courrier'] ?? null, '');
    $statut = in_array($_POST['statut'] ?? '', ['recu', 'en_traitement', 'traite', 'archive', 'envoye'], true) ? $_POST['statut'] : $courrier['statut'];
    $observations = clean_input($_POST['observations'] ?? '');

    if ($numero === '' || $objet === '' || $dateCourrier === '') {
        $errors[] = 'Numéro, objet et date (valide) sont obligatoires.';
    }

    $fichier = $courrier['fichier_joint'];
    $ancienFichier = null;
    if (empty($errors) && !empty($_FILES['fichier_joint']['name'])) {
        try {
            $nf = handle_upload($_FILES['fichier_joint'], UPLOAD_COURRIERS, ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
            if ($nf) {
                $ancienFichier = $fichier;
                $fichier = $nf;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif (empty($errors) && !empty($_POST['supprimer_fichier']) && $fichier) {
        $ancienFichier = $fichier;
        $fichier = null;
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE courriers SET type=?, numero=?, objet=?, expediteur=?, destinataire=?, date_courrier=?, statut=?, fichier_joint=?, observations=? WHERE id=?');
        $stmt->execute([$type, $numero, $objet, $expediteur ?: null, $destinataire ?: null, $dateCourrier, $statut, $fichier, $observations ?: null, $id]);
        delete_upload(UPLOAD_COURRIERS, $ancienFichier);
        log_activity('courrier_modification', "Courrier modifié : $numero");
        flash_set('success', 'Courrier mis à jour.');
        redirect('voir.php?id=' . $id);
    } else {
        $courrier = array_merge($courrier, $_POST);
    }
}

$pageTitle = 'Modifier le courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header"><h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-pen"></i></span><?= e($courrier['objet']) ?></h6><a href="voir.php?id=<?= $id ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a></div>
    <div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
