<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pdo = Database::getConnection();

$users = $pdo->query("SELECT * FROM users ORDER BY full_name")->fetchAll();

$roleLabels = ['admin' => ['Administrateur','admin'], 'secretaire' => ['Secrétaire','secretaire'], 'vendeur' => ['Vendeur','vendeur']];

$pageTitle = 'Utilisateurs';
$activeMenu = 'utilisateurs';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-end mb-3 no-print">
    <a href="ajouter.php" class="btn btn-sp-amber"><i class="fa-solid fa-plus me-1"></i>Nouvel utilisateur</a>
</div>

<div class="sp-card">
    <div class="sp-card-header"><h6>Utilisateurs (<?= count($users) ?>)</h6></div>
    <div class="sp-card-body p-0">
        <table class="table table-sp mb-0">
            <thead><tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th class="text-end no-print">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $rl = $roleLabels[$u['role']] ?? ['-','secondary']; ?>
                <tr>
                    <td class="fw-semibold"><?= e($u['full_name']) ?></td>
                    <td><code><?= e($u['username']) ?></code></td>
                    <td><span class="sp-role-badge <?= $rl[1] ?>"><?= e($rl[0]) ?></span></td>
                    <td>
                        <?php if ($u['actif']): ?><span class="badge bg-success">Actif</span>
                        <?php else: ?><span class="badge bg-secondary">Désactivé</span><?php endif; ?>
                        <?php if (!empty($u['bloque_jusqu']) && strtotime($u['bloque_jusqu']) > time()): ?>
                            <span class="badge bg-danger">Bloqué</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['derniere_connexion'] ? fmt_datetime($u['derniere_connexion']) : 'Jamais' ?></td>
                    <td class="text-end no-print">
                        <a href="modifier.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="supprimer.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" data-confirm="Supprimer cet utilisateur ?"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
