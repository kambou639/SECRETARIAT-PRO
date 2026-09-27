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

$stmt = $pdo->prepare("SELECT * FROM ventes WHERE client_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$id]);
$ventes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant_total - montant_paye),0) AS solde FROM ventes WHERE client_id = ? AND statut = 'validee' AND statut_paiement != 'payee'");
$stmt->execute([$id]);
$soldeDu = (float)$stmt->fetch()['solde'];

$stmt = $pdo->prepare("SELECT * FROM rendezvous WHERE client_id = ? ORDER BY date_rdv DESC LIMIT 10");
$stmt->execute([$id]);
$rdvs = $stmt->fetchAll();

$pageTitle = 'Fiche client';
$activeMenu = 'clients';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="sp-card">
            <div class="sp-card-header"><h6><?= e($client['nom'] . ' ' . $client['prenom']) ?></h6>
                <a href="modifier.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
            </div>
            <div class="sp-card-body">
                <p class="mb-1"><i class="fa-solid fa-tag me-2 text-muted"></i><?= e(ucfirst($client['type'])) ?></p>
                <p class="mb-1"><i class="fa-solid fa-phone me-2 text-muted"></i><?= e($client['telephone'] ?: '-') ?></p>
                <p class="mb-1"><i class="fa-solid fa-envelope me-2 text-muted"></i><?= e($client['email'] ?: '-') ?></p>
                <p class="mb-1"><i class="fa-solid fa-location-dot me-2 text-muted"></i><?= e(trim(($client['adresse'] ?? '') . ' ' . ($client['ville'] ?? '')) ?: '-') ?></p>
                <?php if ($soldeDu > 0.009): ?>
                    <p class="mb-1"><i class="fa-solid fa-hand-holding-dollar me-2 text-danger"></i><span class="text-danger fw-semibold">Solde dû : <?= fmt_money($soldeDu) ?></span> <a href="../ventes/credits.php" class="small">voir</a></p>
                <?php endif; ?>
                <?php if ($client['notes']): ?><hr><p class="text-muted small mb-0"><?= nl2br(e($client['notes'])) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="sp-card mb-3">
            <div class="sp-card-header"><h6>Historique des achats</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($ventes)): ?>
                    <div class="sp-empty-state py-4"><i class="fa-solid fa-receipt"></i><p class="mb-0">Aucun achat enregistré.</p></div>
                <?php else: ?>
                <table class="table table-sp mb-0">
                    <thead><tr><th>Facture</th><th>Date</th><th class="text-end">Montant</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($ventes as $v): ?>
                        <tr>
                            <td><a href="../ventes/facture.php?id=<?= $v['id'] ?>" target="_blank"><?= e($v['numero_facture']) ?></a></td>
                            <td><?= fmt_datetime($v['created_at']) ?></td>
                            <td class="text-end"><?= fmt_money((float)$v['montant_total']) ?></td>
                            <td><span class="badge <?= $v['statut']==='validee'?'bg-success':'bg-danger' ?>"><?= e(ucfirst($v['statut'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="sp-card">
            <div class="sp-card-header"><h6>Rendez-vous liés</h6></div>
            <div class="sp-card-body p-0">
                <?php if (empty($rdvs)): ?>
                    <div class="sp-empty-state py-4"><i class="fa-solid fa-calendar"></i><p class="mb-0">Aucun rendez-vous.</p></div>
                <?php else: ?>
                <table class="table table-sp mb-0">
                    <thead><tr><th>Titre</th><th>Date</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($rdvs as $r): ?>
                        <tr><td><?= e($r['titre']) ?></td><td><?= fmt_date($r['date_rdv']) ?> <?= substr($r['heure_debut'],0,5) ?></td>
                        <td><span class="badge bg-secondary"><?= e(ucfirst($r['statut'])) ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
