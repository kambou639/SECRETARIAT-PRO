<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');

$pageTitle = 'Import / Export Excel';
$activeMenu = 'import_export';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="sp-card">
            <div class="sp-card-header"><h6><i class="fa-solid fa-file-arrow-up me-1"></i>Importer des articles</h6></div>
            <div class="sp-card-body">
                <p class="text-muted small">Importez un fichier Excel (.xlsx) contenant vos articles. Colonnes attendues : <code>code</code>, <code>nom</code>, <code>categorie</code>, <code>prix_achat</code>, <code>prix_vente</code>, <code>stock</code>, <code>seuil_alerte</code>, <code>unite</code>.</p>
                <button class="btn btn-sm btn-outline-secondary mb-3" onclick="downloadTemplate()"><i class="fa-solid fa-download me-1"></i>Télécharger le modèle</button>
                <div class="mb-3">
                    <input type="file" id="importFile" class="form-control" accept=".xlsx,.xls">
                </div>
                <button class="btn btn-sp-amber" onclick="importArticles()"><i class="fa-solid fa-upload me-1"></i>Analyser et importer</button>
                <div id="importResult" class="mt-3"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="sp-card">
            <div class="sp-card-header"><h6><i class="fa-solid fa-file-arrow-down me-1"></i>Exporter des données</h6></div>
            <div class="sp-card-body">
                <p class="text-muted small">Génération côté navigateur (SheetJS), aucune donnée envoyée à un serveur externe.</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary text-start" onclick="exportArticles()"><i class="fa-solid fa-boxes-stacked me-2"></i>Exporter le catalogue d'articles</button>
                    <a href="export_ventes.php" class="btn btn-outline-secondary text-start"><i class="fa-solid fa-cash-register me-2"></i>Exporter les ventes du mois</a>
                    <button class="btn btn-outline-secondary text-start" onclick="exportClients()"><i class="fa-solid fa-address-book me-2"></i>Exporter la liste des clients</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $ROOT ?>assets/js/xlsx.full.min.js"></script>
<script>
function downloadJsonAsExcel(url, filename, sheetName) {
    fetch(url).then(r => r.json()).then(data => {
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, sheetName);
        XLSX.writeFile(wb, filename);
    }).catch(() => alert('Erreur lors de la génération du fichier.'));
}
function exportArticles() {
    downloadJsonAsExcel('data_articles.php', 'articles_export.xlsx', 'Articles');
}
function exportClients() {
    downloadJsonAsExcel('data_clients.php', 'clients_export.xlsx', 'Clients');
}

function downloadTemplate() {
    const ws = XLSX.utils.json_to_sheet([
        { code: 'ART-0001', nom: 'Exemple produit', categorie: 'Fournitures de bureau', type: 'produit', prix_achat: 500, prix_vente: 1000, stock: 20, seuil_alerte: 5, unite: 'pièce' },
        { code: 'IMP-0001', nom: 'Exemple service (impression)', categorie: 'Impressions & Reprographie', type: 'service', prix_achat: 0, prix_vente: 25, stock: 0, seuil_alerte: 0, unite: 'page' }
    ]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Articles');
    XLSX.writeFile(wb, 'modele_import_articles.xlsx');
}

function importArticles() {
    const input = document.getElementById('importFile');
    const resultEl = document.getElementById('importResult');
    if (!input.files.length) { alert('Choisissez un fichier Excel.'); return; }

    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
            if (!rows.length) {
                resultEl.innerHTML = '<div class="alert alert-warning py-2">Fichier vide ou format non reconnu.</div>';
                return;
            }
            resultEl.innerHTML = '<div class="alert alert-info py-2">Envoi de ' + rows.length + ' ligne(s) au serveur...</div>';
            fetch('import_articles_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: '<?= csrf_token() ?>', rows })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    resultEl.innerHTML = `<div class="alert alert-success py-2">${data.created} article(s) créé(s), ${data.updated} mis à jour, ${data.skipped} ignoré(s).</div>`;
                } else {
                    resultEl.innerHTML = '<div class="alert alert-danger py-2">' + (data.message || 'Erreur lors de l\'import.') + '</div>';
                }
            })
            .catch(() => { resultEl.innerHTML = '<div class="alert alert-danger py-2">Erreur réseau lors de l\'import.</div>'; });
        } catch (err) {
            resultEl.innerHTML = '<div class="alert alert-danger py-2">Impossible de lire ce fichier Excel.</div>';
        }
    };
    reader.readAsArrayBuffer(input.files[0]);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
