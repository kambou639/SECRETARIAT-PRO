<?php
/**
 * Lecture et validation du formulaire de rendez-vous (partagé par ajouter.php et modifier.php).
 * Renvoie [$donnees, $erreurs].
 */
function rdv_lire_formulaire(PDO $pdo): array
{
    $errors = [];
    $finSaisie = trim((string)($_POST['heure_fin'] ?? ''));
    $d = [
        'titre' => mb_substr(clean_input($_POST['titre'] ?? ''), 0, 200),
        'date_rdv' => valid_date($_POST['date_rdv'] ?? null, ''),
        'heure_debut' => valid_time($_POST['heure_debut'] ?? null),
        'heure_fin' => valid_time($finSaisie),
        'lieu' => mb_substr(clean_input($_POST['lieu'] ?? ''), 0, 150),
        'contact_nom' => mb_substr(clean_input($_POST['contact_nom'] ?? ''), 0, 150),
        'contact_telephone' => mb_substr(clean_input($_POST['contact_telephone'] ?? ''), 0, 30),
        'client_id' => (int)($_POST['client_id'] ?? 0) ?: null,
        'statut' => in_array($_POST['statut'] ?? '', ['planifie', 'confirme', 'annule', 'termine'], true) ? $_POST['statut'] : 'planifie',
        'description' => clean_input($_POST['description'] ?? ''),
    ];

    if ($d['titre'] === '') {
        $errors[] = 'Le titre est obligatoire.';
    }
    if ($d['date_rdv'] === '') {
        $errors[] = 'La date est obligatoire (format valide).';
    }
    if ($d['heure_debut'] === null) {
        $errors[] = "L'heure de début est obligatoire (format HH:MM).";
    }
    if ($finSaisie !== '' && $d['heure_fin'] === null) {
        $errors[] = "L'heure de fin n'est pas valide (format HH:MM).";
    } elseif ($d['heure_debut'] !== null && $d['heure_fin'] !== null && $d['heure_fin'] <= $d['heure_debut']) {
        $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
    }
    if ($d['client_id']) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE id = ?');
        $stmt->execute([$d['client_id']]);
        if (!$stmt->fetchColumn()) {
            $d['client_id'] = null;
            $errors[] = "Le client sélectionné n'existe plus.";
        }
    }
    return [$d, $errors];
}

/** Avertit (sans bloquer) quand le rendez-vous chevauche d'autres rendez-vous actifs. */
function rdv_avertir_conflits(PDO $pdo, array $d, int $id = 0): void
{
    if ($d['statut'] === 'annule') {
        return;
    }
    $conflits = rdv_conflits($pdo, $d['date_rdv'], $d['heure_debut'], $d['heure_fin'], $id);
    if ($conflits) {
        $noms = array_map(function ($c) {
            return '« ' . $c['titre'] . ' » (' . substr($c['heure_debut'], 0, 5) . ' – ' . substr(rdv_fin_effective($c['heure_debut'], $c['heure_fin']), 0, 5) . ')';
        }, array_slice($conflits, 0, 3));
        flash_set('warning', 'Attention, ce créneau chevauche ' . implode(', ', $noms) . (count($conflits) > 3 ? ' et ' . (count($conflits) - 3) . ' autre(s)' : '') . '.');
    }
}

/** Données communes au formulaire : clients (avec téléphone) et lieux récents. */
function rdv_donnees_formulaire(PDO $pdo): array
{
    $clients = $pdo->query('SELECT id, nom, prenom, telephone FROM clients ORDER BY nom, prenom')->fetchAll();
    $lieux = $pdo->query("SELECT lieu FROM rendezvous WHERE lieu IS NOT NULL AND lieu <> '' GROUP BY lieu ORDER BY MAX(id) DESC LIMIT 15")->fetchAll(PDO::FETCH_COLUMN);
    return [$clients, $lieux];
}
