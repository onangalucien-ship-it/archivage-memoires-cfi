<?php
// statistiques.php - UC5.1 / UC5.2 : Tableau de bord et rapport statistique (M05)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_role(['GESTIONNAIRE', 'ADMINISTRATEUR']);

$dateDebut = trim($_GET['debut'] ?? '');
$dateFin = trim($_GET['fin'] ?? '');
$filiere = trim($_GET['filiere'] ?? '');

$conditionsDate = [];
$params = [];
if ($dateDebut !== '') {
    $conditionsDate[] = "date_depot >= :debut";
    $params[':debut'] = $dateDebut . ' 00:00:00';
}
if ($dateFin !== '') {
    $conditionsDate[] = "date_depot <= :fin";
    $params[':fin'] = $dateFin . ' 23:59:59';
}
if ($filiere !== '') {
    $conditionsDate[] = "filiere = :filiere";
    $params[':filiere'] = $filiere;
}
$whereMemoire = $conditionsDate ? ' WHERE ' . implode(' AND ', $conditionsDate) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire" . $whereMemoire);
$stmt->execute($params);
$totalDepots = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire" . $whereMemoire . ($whereMemoire ? " AND" : " WHERE") . " statut = 'PUBLIE'");
$stmt->execute($params);
$totalPublies = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire" . $whereMemoire . ($whereMemoire ? " AND" : " WHERE") . " statut = 'REJETE'");
$stmt->execute($params);
$totalRejetes = (int) $stmt->fetchColumn();

$tauxValidation = ($totalPublies + $totalRejetes) > 0 ? round($totalPublies / ($totalPublies + $totalRejetes) * 100, 1) : 0;

$seuil = get_seuil_alerte($pdo);
$condSeuil1 = ($whereMemoire ? " AND" : " WHERE") . " r.taux_similarite >= :seuil1";
$condSeuil2 = ($whereMemoire ? " AND" : " WHERE") . " r.taux_similarite >= :seuil2";
$sqlAlertes = "SELECT COUNT(DISTINCT id_m) FROM (
        SELECT r.id_memoire AS id_m FROM rapport_similarite r
        JOIN memoire m ON m.id_memoire = r.id_memoire" . $whereMemoire . $condSeuil1 . "
        UNION
        SELECT r.id_memoire_compare AS id_m FROM rapport_similarite r
        JOIN memoire m ON m.id_memoire = r.id_memoire_compare" . $whereMemoire . $condSeuil2 . "
    ) AS combines";
$stmt = $pdo->prepare($sqlAlertes);
$stmt->execute(array_merge($params, [':seuil1' => $seuil, ':seuil2' => $seuil]));
$totalAlertes = (int) $stmt->fetchColumn();

$sqlConsult = "SELECT COUNT(*) FROM consultation c JOIN memoire m ON m.id_memoire = c.id_memoire" . $whereMemoire;
$stmt = $pdo->prepare($sqlConsult);
$stmt->execute($params);
$totalConsultations = (int) $stmt->fetchColumn();

$sqlFiliere = "SELECT filiere, COUNT(*) AS nb_depots,
                      SUM(statut = 'PUBLIE') AS nb_publies,
                      SUM(statut = 'REJETE') AS nb_rejetes,
                      SUM(statut = 'EN_ATTENTE') AS nb_attente
               FROM memoire" . $whereMemoire . " GROUP BY filiere ORDER BY nb_depots DESC";
$stmt = $pdo->prepare($sqlFiliere);
$stmt->execute($params);
$parFiliere = $stmt->fetchAll();

$filieres = $pdo->query("SELECT DISTINCT filiere FROM memoire ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

$queryExport = http_build_query(['debut' => $dateDebut, 'fin' => $dateFin, 'filiere' => $filiere]);

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1>Statistiques et reporting</h1>
    <p class="sous-titre">UC5.1 / UC5.2 — Indicateurs d'activité, filtrables par période et par filière</p>
</div>
<div><a class="btn btn-secondaire" href="<?= BASE_URL ?>/export_rapport.php?<?= $queryExport ?>">Exporter (CSV)</a></div>
</div>

<form method="get" class="carte filtres">
    <div class="champ"><label for="debut">Depuis le</label><input type="date" id="debut" name="debut" value="<?= e($dateDebut) ?>"></div>
    <div class="champ"><label for="fin">Jusqu'au</label><input type="date" id="fin" name="fin" value="<?= e($dateFin) ?>"></div>
    <div class="champ">
        <label for="filiere">Filière</label>
        <select id="filiere" name="filiere">
            <option value="">Toutes</option>
            <?php foreach ($filieres as $f): ?>
                <option value="<?= e($f) ?>" <?= $filiere === $f ? 'selected' : '' ?>><?= e($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ"><button type="submit" class="btn">Générer le rapport</button></div>
</form>

<div class="grille-cartes">
    <div class="carte indicateur"><div class="valeur"><?= $totalDepots ?></div><div class="libelle">Dépôts</div></div>
    <div class="carte indicateur"><div class="valeur"><?= $totalPublies ?></div><div class="libelle">Mémoires publiés</div></div>
    <div class="carte indicateur"><div class="valeur"><?= $totalRejetes ?></div><div class="libelle">Dépôts rejetés</div></div>
    <div class="carte indicateur"><div class="valeur"><?= $tauxValidation ?>%</div><div class="libelle">Taux de validation</div></div>
    <div class="carte indicateur"><div class="valeur"><?= $totalConsultations ?></div><div class="libelle">Consultations</div></div>
    <div class="carte indicateur"><div class="valeur" style="<?= $totalAlertes ? 'color:var(--rouge)' : '' ?>"><?= $totalAlertes ?></div><div class="libelle">Alertes de similarité</div></div>
</div>

<h2>Répartition par filière</h2>
<div class="carte table-scroll">
    <?php if (empty($parFiliere)): ?>
        <p class="vide">Aucune donnée pour ces critères.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Filière</th><th>Dépôts</th><th>Publiés</th><th>Rejetés</th><th>En attente</th></tr></thead>
        <tbody>
        <?php foreach ($parFiliere as $ligne): ?>
            <tr>
                <td><?= e($ligne['filiere']) ?></td>
                <td><?= (int) $ligne['nb_depots'] ?></td>
                <td><?= (int) $ligne['nb_publies'] ?></td>
                <td><?= (int) $ligne['nb_rejetes'] ?></td>
                <td><?= (int) $ligne['nb_attente'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
