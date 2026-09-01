<?php
// export_rapport.php - UC5.3 : Exporter un rapport (CSV)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_role(['GESTIONNAIRE', 'ADMINISTRATEUR']);

$u = utilisateur_courant();
$dateDebut = trim($_GET['debut'] ?? '');
$dateFin = trim($_GET['fin'] ?? '');
$filiere = trim($_GET['filiere'] ?? '');

$conditions = [];
$params = [];
if ($dateDebut !== '') { $conditions[] = "date_depot >= :debut"; $params[':debut'] = $dateDebut . ' 00:00:00'; }
if ($dateFin !== '') { $conditions[] = "date_depot <= :fin"; $params[':fin'] = $dateFin . ' 23:59:59'; }
if ($filiere !== '') { $conditions[] = "filiere = :filiere"; $params[':filiere'] = $filiere; }
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT m.titre, u.nom, u.prenom, m.filiere, m.annee_academique, m.statut, m.date_depot,
               (SELECT MAX(taux_similarite) FROM rapport_similarite WHERE id_memoire = m.id_memoire) AS taux_max
        FROM memoire m JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant" . $where . " ORDER BY m.date_depot DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lignes = $stmt->fetchAll();

journaliser($pdo, $u['id_utilisateur'], "Export du rapport statistique (CSV)");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rapport_memoires_' . date('Ymd_His') . '.csv"');

$sortie = fopen('php://output', 'w');
fwrite($sortie, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
fputcsv($sortie, ['Titre', 'Auteur', 'Filière', 'Année académique', 'Statut', 'Date de dépôt', 'Taux de similarité max (%)'], ';');
foreach ($lignes as $l) {
    fputcsv($sortie, [
        $l['titre'],
        $l['prenom'] . ' ' . $l['nom'],
        $l['filiere'],
        $l['annee_academique'],
        libelle_statut($l['statut']),
        formater_date($l['date_depot']),
        $l['taux_max'] !== null ? $l['taux_max'] : '',
    ], ';');
}
fclose($sortie);
exit;
