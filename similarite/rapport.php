<?php
// similarite/rapport.php - UC3.2 : Consulter le rapport de similarité
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['GESTIONNAIRE', 'ADMINISTRATEUR']);

$idMemoire = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM memoire WHERE id_memoire = :id");
$stmt->execute([':id' => $idMemoire]);
$memoire = $stmt->fetch();

if (!$memoire) {
    flash('erreur', "Mémoire introuvable.");
    rediriger('/memoires/liste.php');
}

$stmt = $pdo->prepare(
    "SELECT r.taux_similarite, r.date_analyse, m.id_memoire AS id_compare, m.titre, u.nom, u.prenom
     FROM rapport_similarite r
     JOIN memoire m ON m.id_memoire = r.id_memoire_compare
     JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant
     WHERE r.id_memoire = :id
     ORDER BY r.taux_similarite DESC"
);
$stmt->execute([':id' => $idMemoire]);
$rapports = $stmt->fetchAll();
$seuil = get_seuil_alerte($pdo);
$tauxMax = $rapports ? $rapports[0]['taux_similarite'] : 0;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Rapport de similarité</h1>
    <p class="sous-titre"><?= e($memoire['titre']) ?></p>
</div></div>

<?php if ($tauxMax >= $seuil): ?>
    <div class="alert alert-avertissement">Seuil d'alerte dépassé : taux maximal observé de <?= $tauxMax ?>% (seuil configuré : <?= $seuil ?>%).</div>
<?php endif; ?>

<div class="carte table-scroll">
    <?php if (empty($rapports)): ?>
        <p class="vide">Aucune comparaison disponible (corpus vide au moment de l'analyse).</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Mémoire comparé</th><th>Auteur</th><th>Taux de similarité</th><th>Date d'analyse</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rapports as $r): ?>
            <tr>
                <td><?= e($r['titre']) ?></td>
                <td><?= e($r['prenom'] . ' ' . $r['nom']) ?></td>
                <td class="taux-similarite <?= $r['taux_similarite'] >= $seuil ? 'taux-alerte' : 'taux-ok' ?>"><?= $r['taux_similarite'] ?>%</td>
                <td><?= e(formater_date($r['date_analyse'])) ?></td>
                <td><a href="<?= BASE_URL ?>/consulter_memoire.php?id=<?= (int) $r['id_compare'] ?>">Voir</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<p><a href="<?= BASE_URL ?>/memoires/liste.php">&larr; Retour à la gestion des mémoires</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
