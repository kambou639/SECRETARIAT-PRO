<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();
$errors = [];
$courrier = $_POST;

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

    $fichier = null;
    if (empty($errors) && !empty($_FILES['fichier_joint']['name'])) {
        try {
            $fichier = handle_upload($_FILES['fichier_joint'], UPLOAD_COURRIERS, ['pdf','jpg','jpeg','png']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO courriers (type, numero, objet, expediteur, destinataire, date_courrier, statut, fichier_joint, observations, user_id)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$type, $numero, $objet, $expediteur, $destinataire, $dateCourrier, $statut, $fichier, $observations, $_SESSION['user_id']]);
        log_activity('courrier_creation', "Courrier créé : $numero - $objet");
        flash_set('success', 'Courrier enregistré avec succès.');
        redirect('liste.php');
    }
}

$pageTitle = 'Nouveau courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card"><div class="sp-card-header"><h6>Enregistrer un courrier</h6></div>
<div class="sp-card-body"><?php include __DIR__ . '/_form.php'; ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
