<?php
// memoires/valider.php - UC1.6 : Suivre / statuer sur le statut du mémoire (validation du dépôt)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['GESTIONNAIRE', 'ADMINISTRATEUR']);

$u = utilisateur_courant();
$idMemoire = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM memoire WHERE id_memoire = :id");
$stmt->execute([':id' => $idMemoire]);
$memoire = $stmt->fetch();

if (!$memoire) {
    flash('erreur', "Mémoire introuvable.");
    rediriger('/memoires/liste.php');
}
if ($memoire['statut'] !== 'EN_ATTENTE') {
    flash('erreur', "Ce mémoire n'est plus en attente de validation.");
    rediriger('/memoires/liste.php');
}

$stmtRapport = $pdo->prepare(
    "SELECT r.taux_similarite, m.titre, m.id_memoire AS id_compare
     FROM rapport_similarite r JOIN memoire m ON m.id_memoire = r.id_memoire_compare
     WHERE r.id_memoire = :id ORDER BY r.taux_similarite DESC LIMIT 5"
);
$stmtRapport->execute([':id' => $idMemoire]);
$rapports = $stmtRapport->fetchAll();
$seuil = get_seuil_alerte($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $decision = $_POST['decision'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (!in_array($decision, ['PUBLIE', 'REJETE'], true)) {
        flash('erreur', "Décision invalide.");
        rediriger('/memoires/valider.php?id=' . $idMemoire);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO depot (statut, commentaire_validation, id_memoire, id_gestionnaire)
             VALUES (:statut, :commentaire, :id_memoire, :id_gestionnaire)"
        );
        $stmt->execute([
            ':statut' => $decision,
            ':commentaire' => $commentaire ?: null,
            ':id_memoire' => $idMemoire,
            ':id_gestionnaire' => $u['id_utilisateur'],
        ]);

        // Un mémoire ne peut être publié sans validation préalable du gestionnaire des archives.
        // Memoire.statut fait foi et est systématiquement synchronisé sur le dernier Depot.statut.
        $stmt = $pdo->prepare("UPDATE memoire SET statut = :statut WHERE id_memoire = :id");
        $stmt->execute([':statut' => $decision, ':id' => $idMemoire]);

        journaliser($pdo, $u['id_utilisateur'],
            ($decision === 'PUBLIE' ? "Validation (publication) " : "Rejet ") . "du mémoire « {$memoire['titre']} »");

        $pdo->commit();
        flash('succes', $decision === 'PUBLIE' ? "Le mémoire a été publié." : "Le dépôt a été rejeté.");
        rediriger('/memoires/liste.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('erreur', "Impossible d'enregistrer la décision. Vérifiez la connexion à la base de données.");
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Valider le dépôt</h1>
    <p class="sous-titre"><?= e($memoire['titre']) ?></p>
</div></div>

<?php if (!empty($rapports)): ?>
<div class="carte">
    <h3 style="margin-top:0">Rapport de similarité (aperçu)</h3>
    <table>
        <thead><tr><th>Mémoire comparé</th><th>Taux</th></tr></thead>
        <tbody>
        <?php foreach ($rapports as $r): ?>
            <tr>
                <td><?= e($r['titre']) ?></td>
                <td class="taux-similarite <?= $r['taux_similarite'] >= $seuil ? 'taux-alerte' : 'taux-ok' ?>"><?= $r['taux_similarite'] ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($rapports[0]['taux_similarite'] >= $seuil): ?>
        <div class="alert alert-avertissement">Le taux de similarité maximal (<?= $rapports[0]['taux_similarite'] ?>%) dépasse le seuil d'alerte configuré (<?= $seuil ?>%). Examinez le rapport complet avant de statuer.</div>
    <?php endif; ?>
    <p><a href="<?= BASE_URL ?>/similarite/rapport.php?id=<?= $idMemoire ?>">Voir le rapport complet</a></p>
</div>
<?php endif; ?>

<div class="carte">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $idMemoire ?>">
        <div class="champ">
            <label for="commentaire">Commentaire de validation</label>
            <textarea id="commentaire" name="commentaire" placeholder="Facultatif"></textarea>
        </div>
        <div class="actions-ligne">
            <button type="submit" name="decision" value="PUBLIE" class="btn btn-succes">Publier</button>
            <button type="submit" name="decision" value="REJETE" class="btn btn-danger">Rejeter</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/memoires/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
