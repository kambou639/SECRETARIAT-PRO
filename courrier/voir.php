<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.*, u.full_name AS user_nom FROM courriers c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash_set('danger', 'Courrier introuvable.');
    redirect('liste.php');
}

$entrant = $c['type'] === 'entrant';
$fichierUrl = upload_url('courriers', $c['fichier_joint']);
$ext = $c['fichier_joint'] ? strtolower(pathinfo($c['fichier_joint'], PATHINFO_EXTENSION)) : null;
$etapes = $entrant
    ? ['recu' => ['Reçu', 'fa-inbox'], 'en_traitement' => ['En traitement', 'fa-hourglass-half'], 'traite' => ['Traité', 'fa-check'], 'archive' => ['Archivé', 'fa-box-archive']]
    : ['en_traitement' => ['En préparation', 'fa-pen-nib'], 'envoye' => ['Envoyé', 'fa-paper-plane'], 'archive' => ['Archivé', 'fa-box-archive']];
$cles = array_keys($etapes);
$indexActuel = array_search($c['statut'], $cles, true);
if ($indexActuel === false) {
    $indexActuel = $c['statut'] === 'traite' ? 1 : 0;
}
$age = days_since($c['date_enregistrement']);

$pageTitle = 'Détails du courrier';
$activeMenu = 'courrier';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <h6>
                    <span class="sp-card-icon <?= $entrant ? 'tone-success' : 'tone-primary' ?>"><i class="fa-solid <?= $entrant ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></span>
                    Courrier <?= $entrant ? 'entrant' : 'sortant' ?> <code class="ms-1"><?= e($c['numero']) ?></code>
                </h6>
                <div class="d-flex gap-1 no-print">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-sp-print title="Imprimer la fiche"><i class="fa-solid fa-print"></i></button>
                    <a href="modifier.php?id=<?= $id ?>" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
                    <?php if (has_role('admin')): ?>
                        <a href="supprimer.php?id=<?= $id ?>" class="btn btn-sm btn-icon btn-outline-danger" data-method="post" data-confirm-type="danger" data-confirm="Supprimer définitivement ce courrier et sa pièce jointe ?" title="Supprimer"><i class="fa-solid fa-trash"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sp-card-body">
                <h3 class="mb-3" style="font-size:1.45rem;"><?= e($c['objet']) ?></h3>

                <div class="sp-stepper mb-4" id="stepper">
                    <?php foreach ($cles as $i => $k): ?>
                        <div class="sp-step <?= $i < $indexActuel ? 'done' : ($i === $indexActuel ? 'current' : '') ?>">
                            <button type="button" class="st-dot" data-set-statut="<?= $k ?>" data-icon="<?= e($etapes[$k][1]) ?>" title="Passer au statut « <?= e($etapes[$k][0]) ?> »"><i class="fa-solid <?= $i < $indexActuel ? 'fa-check' : e($etapes[$k][1]) ?>"></i></button>
                            <div class="st-label"><?= e($etapes[$k][0]) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <dl class="sp-dl">
                    <dt><?= $entrant ? 'Expéditeur' : 'Destinataire' ?></dt><dd><?= e(($entrant ? $c['expediteur'] : $c['destinataire']) ?: '—') ?></dd>
                    <?php if ($entrant ? $c['destinataire'] : $c['expediteur']): ?>
                        <dt><?= $entrant ? 'Destinataire interne' : 'Expéditeur interne' ?></dt><dd><?= e($entrant ? $c['destinataire'] : $c['expediteur']) ?></dd>
                    <?php endif; ?>
                    <dt>Date du courrier</dt><dd><?= e(fmt_date_fr_long($c['date_courrier'])) ?></dd>
                    <dt>Enregistré le</dt><dd><?= fmt_datetime($c['date_enregistrement']) ?> <span class="text-muted">(<?= e(fmt_relative($c['date_enregistrement'])) ?>)</span></dd>
                    <dt>Statut</dt><dd id="statutBadge"><?= badge_html(statut_courrier_meta($c['statut'])) ?>
                        <?php if (in_array($c['statut'], ['recu', 'en_traitement'], true) && $age > 3): ?><span class="sp-badge is-danger ms-1"><i class="fa-solid fa-clock"></i>en attente depuis <?= $age ?> j</span><?php endif; ?></dd>
                    <dt>Enregistré par</dt><dd><?= e($c['user_nom'] ?? '—') ?></dd>
                </dl>
                <?php if ($c['observations']): ?>
                    <div class="sp-note mt-3"><i class="fa-regular fa-note-sticky me-1"></i><?= nl2br(e($c['observations'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="sp-card">
            <div class="sp-card-header">
                <h6><span class="sp-card-icon"><i class="fa-solid fa-paperclip"></i></span>Pièce jointe</h6>
                <?php if ($fichierUrl): ?><a href="<?= e($fichierUrl) ?>" class="btn btn-sm btn-outline-secondary" download><i class="fa-solid fa-download me-1"></i>Télécharger</a><?php endif; ?>
            </div>
            <div class="sp-card-body">
                <?php if (!$fichierUrl): ?>
                    <?= empty_state('fa-file-circle-xmark', 'Aucune pièce jointe', 'Ajoutez un scan ou un PDF du courrier depuis « Modifier ».', '<a href="modifier.php?id=' . $id . '" class="btn btn-sm btn-soft-primary"><i class="fa-solid fa-upload me-1"></i>Ajouter un fichier</a>', 'py-3') ?>
                <?php elseif ($ext === 'pdf'): ?>
                    <iframe src="<?= e($fichierUrl) ?>" title="Aperçu du PDF" style="width:100%;height:560px;border:1px solid var(--sp-border);border-radius:12px;background:#fff;"></iframe>
                    <a href="<?= e($fichierUrl) ?>" target="_blank" rel="noopener" class="small d-inline-block mt-2"><i class="fa-solid fa-up-right-from-square me-1"></i>Ouvrir dans un nouvel onglet</a>
                <?php else: ?>
                    <a href="<?= e($fichierUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($fichierUrl) ?>" alt="Pièce jointe du courrier" class="img-fluid rounded border" style="max-height:600px;"></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('stepper').addEventListener('click', function (e) {
        var b = e.target.closest('[data-set-statut]');
        if (!b) return;
        SP.fetchJSON(SP.url('api/courrier_statut.php'), { body: { id: <?= $id ?>, statut: b.getAttribute('data-set-statut') } }).then(function (data) {
            if (!data.success) { SP.toast({ type: 'danger', message: data.message || 'Modification impossible.' }); return; }
            var steps = Array.prototype.slice.call(document.querySelectorAll('#stepper .sp-step'));
            var idx = steps.indexOf(b.closest('.sp-step'));
            steps.forEach(function (s, i) {
                s.classList.toggle('done', i < idx);
                s.classList.toggle('current', i === idx);
                var dot = s.querySelector('.st-dot');
                dot.querySelector('i').className = 'fa-solid ' + (i < idx ? 'fa-check' : dot.getAttribute('data-icon'));
            });
            document.getElementById('statutBadge').innerHTML = data.badge;
            SP.toast({ type: 'success', title: 'Statut mis à jour', message: data.label, duration: 2500 });
            if (SP.notif) SP.notif.invalidate();
        });
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
