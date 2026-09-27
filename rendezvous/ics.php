<?php
/**
 * Export d'un rendez-vous au format iCalendar (.ics) pour l'ajouter à un agenda
 * (téléphone, Outlook, Google Agenda…).
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', 'secretaire');
$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT r.*, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_tel
    FROM rendezvous r LEFT JOIN clients c ON c.id = r.client_id WHERE r.id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    flash_set('danger', 'Rendez-vous introuvable.');
    redirect('agenda.php');
}

/** Échappement et repli des lignes selon la RFC 5545. */
function ics_text(string $s): string
{
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', ''], $s);
}
function ics_line(string $line): string
{
    $out = '';
    while (strlen($line) > 74) {
        $cut = 74;
        // Ne pas couper au milieu d'un caractère UTF-8
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line . "\r\n";
}

$tz = date_default_timezone_get();
$debut = new DateTime($r['date_rdv'] . ' ' . $r['heure_debut'], new DateTimeZone($tz));
$fin = new DateTime($r['date_rdv'] . ' ' . rdv_fin_effective($r['heure_debut'], $r['heure_fin']), new DateTimeZone($tz));
$utc = new DateTimeZone('UTC');
$personne = rdv_personne($r);
$tel = $r['contact_telephone'] ?: $r['client_tel'];
$description = trim(implode("\n", array_filter([
    $personne !== '' ? 'Avec : ' . $personne : '',
    $tel ? 'Téléphone : ' . $tel : '',
    (string)$r['description'],
])));
$host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
$status = ['planifie' => 'TENTATIVE', 'confirme' => 'CONFIRMED', 'termine' => 'CONFIRMED', 'annule' => 'CANCELLED'][$r['statut']] ?? 'TENTATIVE';

$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Secretariat Pro//Agenda//FR\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\n";
$ics .= ics_line('UID:rdv-' . (int)$r['id'] . '@' . $host);
$ics .= ics_line('DTSTAMP:' . gmdate('Ymd\THis\Z'));
$ics .= ics_line('DTSTART:' . $debut->setTimezone($utc)->format('Ymd\THis\Z'));
$ics .= ics_line('DTEND:' . $fin->setTimezone($utc)->format('Ymd\THis\Z'));
$ics .= ics_line('SUMMARY:' . ics_text($r['titre']));
if ($r['lieu']) {
    $ics .= ics_line('LOCATION:' . ics_text($r['lieu']));
}
if ($description !== '') {
    $ics .= ics_line('DESCRIPTION:' . ics_text($description));
}
$ics .= ics_line('STATUS:' . $status);
$ics .= "BEGIN:VALARM\r\nACTION:DISPLAY\r\n" . ics_line('DESCRIPTION:' . ics_text($r['titre'])) . "TRIGGER:-PT30M\r\nEND:VALARM\r\n";
$ics .= "END:VEVENT\r\nEND:VCALENDAR\r\n";

$nomFichier = 'rdv-' . $r['date_rdv'] . '-' . (int)$r['id'] . '.ics';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Content-Length: ' . strlen($ics));
echo $ics;
