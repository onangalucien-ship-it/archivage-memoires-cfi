<?php
// journal.php - UC4.4 : Consulter le journal d'activité
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$idUtilisateur = trim($_GET['utilisateur'] ?? '');
$dateDebut = trim($_GET['debut'] ?? '');
$dateFin = trim($_GET['fin'] ?? '');

$conditions = [];
$params = [];
if ($idUtilisateur !== '') {
    $conditions[] = "j.id_utilisateur = :id_utilisateur";
    $params[':id_utilisateur'] = (int) $idUtilisateur;
}
if ($dateDebut !== '') {
    $conditions[] = "j.date_action >= :debut";
    $params[':debut'] = $dateDebut . ' 00:00:00';
}
if ($dateFin !== '') {
    $conditions[] = "j.date_action <= :fin";
    $params[':fin'] = $dateFin . ' 23:59:59';
}

$sql = "SELECT j.*, u.nom, u.prenom FROM journal_activite j
        LEFT JOIN utilisateur u ON u.id_utilisateur = j.id_utilisateur";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY j.date_action DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entrees = $stmt->fetchAll();

$utilisateurs = $pdo->query("SELECT id_utilisateur, nom, prenom FROM utilisateur ORDER BY nom")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1>Journal d'activité</h1>
    <p class="sous-titre">UC4.4 — Historique des opérations sensibles (300 dernières entrées)</p>
</div></div>

<form method="get" class="carte filtres">
    <div class="champ">
        <label for="utilisateur">Utilisateur</label>
        <select id="utilisateur" name="utilisateur">
            <option value="">Tous</option>
            <?php foreach ($utilisateurs as $ut): ?>
                <option value="<?= (int) $ut['id_utilisateur'] ?>" <?= $idUtilisateur == $ut['id_utilisateur'] ? 'selected' : '' ?>><?= e($ut['prenom'] . ' ' . $ut['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ">
        <label for="debut">Depuis le</label>
        <input type="date" id="debut" name="debut" value="<?= e($dateDebut) ?>">
    </div>
    <div class="champ">
        <label for="fin">Jusqu'au</label>
        <input type="date" id="fin" name="fin" value="<?= e($dateFin) ?>">
    </div>
    <div class="champ"><button type="submit" class="btn">Filtrer</button></div>
</form>

<div class="carte table-scroll">
    <?php if (empty($entrees)): ?>
        <p class="vide">Aucune activité enregistrée.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($entrees as $entree): ?>
            <tr>
                <td><?= e(formater_date($entree['date_action'])) ?></td>
                <td><?= $entree['nom'] ? e($entree['prenom'] . ' ' . $entree['nom']) : '<em>Système</em>' ?></td>
                <td><?= e($entree['action']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
