<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
require_once __DIR__ . '/_traitement.php';
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT r.*, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel, u.full_name AS user_nom
    FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?');
$stmt->execute([$id]);
$rdv = $stmt->fetch();
if (!$rdv) {
    flash_set('danger', 'Rendez-vous introuvable.');
    redirect('agenda.php');
}
$original = $rdv;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$d, $errors] = rdv_lire_formulaire($pdo);
    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE rendezvous SET titre=?, description=?, date_rdv=?, heure_debut=?, heure_fin=?, lieu=?, contact_nom=?, contact_telephone=?, client_id=?, statut=? WHERE id=?');
        $stmt->execute([
            $d['titre'], $d['description'] ?: null, $d['date_rdv'], $d['heure_debut'], $d['heure_fin'], $d['lieu'] ?: null,
            $d['contact_nom'] ?: null, $d['contact_telephone'] ?: null, $d['client_id'], $d['statut'], $id,
        ]);
        log_activity('rdv_modification', "RDV modifié : {$d['titre']} (" . fmt_date($d['date_rdv']) . ')');
        flash_set('success', 'Rendez-vous mis à jour.');
        rdv_avertir_conflits($pdo, $d, $id);
        redirect('agenda.php?mois=' . substr($d['date_rdv'], 0, 7));
    }
    $rdv = array_merge($rdv, $_POST);
}

$personne = rdv_personne($original);
$telephone = $original['contact_telephone'] ?: $original['client_tel'];
$waUrl = in_array($original['statut'], ['planifie', 'confirme'], true) ? whatsapp_url($telephone, rdv_message_rappel($original)) : null;
$meta = statut_rdv_meta($original['statut']);

$pageTitle = 'Modifier le rendez-vous';
$activeMenu = 'agenda';
include __DIR__ . '/../includes/header.php';
?>
<div class="sp-card">
    <div class="sp-card-header flex-wrap gap-2">
        <h6 class="min-w-0"><span class="sp-card-icon tone-info"><i class="fa-solid fa-calendar-day"></i></span><span class="text-truncate"><?= e($original['titre']) ?></span> <?= badge_html($meta) ?></h6>
        <div class="d-flex flex-wrap gap-1 no-print">
            <?php if ($waUrl): ?>
                <a href="<?= e($waUrl) ?>" class="btn btn-sm btn-whatsapp" target="_blank" rel="noopener" title="Envoyer un rappel par WhatsApp"><i class="fa-brands fa-whatsapp me-1"></i>Rappel</a>
            <?php endif; ?>
            <a href="ics.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Ajouter à un calendrier (téléphone, Outlook, Google…)" data-no-progress><i class="fa-regular fa-calendar-plus me-1"></i>.ics</a>
            <a href="ajouter.php?copie=<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Créer un rendez-vous similaire"><i class="fa-regular fa-copy me-1"></i>Dupliquer</a>
            <?php if ($original['statut'] !== 'annule'): ?>
                <a href="supprimer.php?id=<?= $id ?>" class="btn btn-sm btn-outline-danger" data-method="post" data-confirm="Annuler ce rendez-vous ? Il restera visible dans l'agenda avec le statut « Annulé »." data-confirm-title="Annuler le rendez-vous" data-confirm-label="Oui, annuler"><i class="fa-solid fa-calendar-xmark me-1"></i>Annuler le RDV</a>
            <?php endif; ?>
            <a href="agenda.php?mois=<?= e(substr($original['date_rdv'], 0, 7)) ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-left me-1"></i>Agenda</a>
        </div>
    </div>
    <div class="sp-card-body">
        <p class="small text-muted mb-3">
            <i class="fa-regular fa-clock me-1"></i>Créé <?= e(fmt_relative($original['created_at'])) ?><?= $original['user_nom'] ? ' par ' . e($original['user_nom']) : '' ?>
            <?php if ($personne !== ''): ?> · <i class="fa-solid fa-user ms-1 me-1"></i><?= e($personne) ?><?php endif; ?>
            <?php if ($telephone): ?> · <a href="<?= e(tel_href($telephone)) ?>"><i class="fa-solid fa-phone me-1"></i><?= e($telephone) ?></a><?php endif; ?>
        </p>
        <?php include __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
