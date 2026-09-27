<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
require_once __DIR__ . '/_traitement.php';
$pdo = Database::getConnection();
$errors = [];
$copieDe = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$d, $errors] = rdv_lire_formulaire($pdo);
    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO rendezvous (titre, description, date_rdv, heure_debut, heure_fin, lieu, contact_nom, contact_telephone, client_id, statut, user_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $d['titre'], $d['description'] ?: null, $d['date_rdv'], $d['heure_debut'], $d['heure_fin'], $d['lieu'] ?: null,
            $d['contact_nom'] ?: null, $d['contact_telephone'] ?: null, $d['client_id'], $d['statut'], $_SESSION['user_id'],
        ]);
        log_activity('rdv_creation', "RDV créé : {$d['titre']} le " . fmt_date($d['date_rdv']) . ' à ' . substr($d['heure_debut'], 0, 5));
        flash_set('success', 'Rendez-vous enregistré pour le ' . fmt_date_fr($d['date_rdv']) . ' à ' . substr($d['heure_debut'], 0, 5) . '.');
        rdv_avertir_conflits($pdo, $d);
        redirect('agenda.php?mois=' . substr($d['date_rdv'], 0, 7));
    }
    $rdv = $_POST;
} else {
    // Pré-remplissage : ?date=, ?heure=, ?client_id= ou duplication ?copie=
    $date = valid_date($_GET['date'] ?? null, date('Y-m-d'));
    $heure = valid_time($_GET['heure'] ?? null);
    if ($heure === null) {
        $heure = '09:00:00';
        if ($date === date('Y-m-d')) {
            // Prochaine demi-heure pleine pour un rendez-vous pris le jour même
            $min = (int)ceil(((int)date('G') * 60 + (int)date('i') + 1) / 30) * 30;
            if ($min >= 8 * 60 && $min <= 21 * 60) {
                $heure = sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
            }
        }
    }
    $rdv = ['date_rdv' => $date, 'heure_debut' => $heure, 'statut' => 'planifie'];

    $clientId = (int)($_GET['client_id'] ?? 0);
    if ($clientId) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $stmt->execute([$clientId]);
        if ($stmt->fetchColumn()) {
            $rdv['client_id'] = $clientId;
        }
    }

    $copieId = (int)($_GET['copie'] ?? 0);
    if ($copieId) {
        $stmt = $pdo->prepare('SELECT * FROM rendezvous WHERE id = ?');
        $stmt->execute([$copieId]);
        if ($copieDe = $stmt->fetch()) {
            $rdv = array_intersect_key($copieDe, array_flip(['titre', 'description', 'heure_debut', 'heure_fin', 'lieu', 'contact_nom', 'contact_telephone', 'client_id']));
            $rdv['statut'] = 'planifie';
            $prochaine = date('Y-m-d', strtotime($copieDe['date_rdv'] . ' +7 days'));
            $rdv['date_rdv'] = $prochaine < date('Y-m-d') ? date('Y-m-d') : $prochaine;
        }
    }
}

[$clients, $lieux] = rdv_donnees_formulaire($pdo);

$pageTitle = 'Nouveau rendez-vous';
$activeMenu = 'agenda';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header">
        <h6><span class="sp-card-icon tone-info"><i class="fa-solid fa-calendar-plus"></i></span>Planifier un rendez-vous</h6>
        <a href="agenda.php" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Agenda</a>
    </div>
    <div class="sp-card-body">
        <?php if ($copieDe): ?>
            <div class="sp-note mb-3"><i class="fa-regular fa-copy me-1"></i>Copie du rendez-vous « <?= e($copieDe['titre']) ?> » du <?= e(fmt_date_fr($copieDe['date_rdv'])) ?>. Vérifiez la date avant d'enregistrer.</div>
        <?php endif; ?>
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
