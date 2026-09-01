<?php
// memoires/retirer.php - UC1.4 : Retirer (logiquement) un mémoire
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $motif = trim($_POST['motif'] ?? '');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO depot (statut, commentaire_validation, id_memoire, id_gestionnaire)
             VALUES ('RETIRE', :motif, :id_memoire, :id_gestionnaire)"
        );
        $stmt->execute([':motif' => $motif ?: null, ':id_memoire' => $idMemoire, ':id_gestionnaire' => $u['id_utilisateur']]);

        $stmt = $pdo->prepare("UPDATE memoire SET statut = 'RETIRE' WHERE id_memoire = :id");
        $stmt->execute([':id' => $idMemoire]);

        journaliser($pdo, $u['id_utilisateur'], "Retrait du mémoire « {$memoire['titre']} » (motif : " . ($motif ?: 'non précisé') . ")");

        $pdo->commit();
        flash('succes', "Le mémoire a été retiré du référentiel public.");
        rediriger('/memoires/liste.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('erreur', "Impossible de retirer le mémoire. Vérifiez la connexion à la base de données.");
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Retirer un mémoire</h1>
    <p class="sous-titre"><?= e($memoire['titre']) ?> — le mémoire restera archivé mais ne sera plus consultable publiquement.</p>
</div></div>

<div class="carte">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $idMemoire ?>">
        <div class="champ">
            <label for="motif">Motif du retrait</label>
            <textarea id="motif" name="motif" placeholder="Facultatif"></textarea>
        </div>
        <div class="actions-ligne">
            <button type="submit" class="btn btn-danger" data-confirmer="Confirmez-vous le retrait de ce mémoire ?">Confirmer le retrait</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/memoires/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
