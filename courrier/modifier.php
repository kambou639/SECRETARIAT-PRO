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
    $numero = clean_input($_POST['numero'] ?? '');
    $objet = clean_input($_POST['objet'] ?? '');
    $expediteur = clean_input($_POST['expediteur'] ?? '');
    $destinataire = clean_input($_POST['destinataire'] ?? '');
    $dateCourrier = clean_input($_POST['date_courrier'] ?? '');
    $statut = clean_input($_POST['statut'] ?? 'recu');
    $observations = clean_input($_POST['observations'] ?? '');

    if ($numero === '' || $objet === '' || $dateCourrier === '') {
        $errors[] = 'Numéro, objet et date sont obligatoires.';
    }

    $fichier = $courrier['fichier_joint'];
    if (empty($errors) && !empty($_FILES['fichier_joint']['name'])) {
        try {
            $nf = handle_upload($_FILES['fichier_joint'], UPLOAD_COURRIERS, ['pdf','jpg','jpeg','png']);
            if ($nf) {
                if ($fichier && file_exists(UPLOAD_COURRIERS . '/' . $fichier)) @unlink(UPLOAD_COURRIERS . '/' . $fichier);
                $fichier = $nf;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE courriers SET type=?, numero=?, objet=?, expediteur=?, destinataire=?, date_courrier=?, statut=?, fichier_joint=?, observations=? WHERE id=?');
        $stmt->execute([$type, $numero, $objet, $expediteur, $destinataire, $dateCourrier, $statut, $fichier, $observations, $id]);
        log_activity('courrier_modification', "Courrier modifié : $numero");
        flash_set('success', 'Courrier mis à jour.');
        redirect('liste.php');
    } else {
        $courrier = array_merge($courrier, $_POST);
    }
}

$pageTitle = 'Modifier le courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card"><div class="sp-card-header"><h6>Modifier le courrier</h6></div>
<div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
