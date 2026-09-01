<?php
// memoires/supprimer.php - UC1.5 : Supprimer (physiquement) un mémoire
// Règle métier : la suppression physique d'un mémoire déjà publié est interdite (seul le retrait logique l'est).
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
if (!in_array($memoire['statut'], ['EN_ATTENTE', 'REJETE'], true)) {
    flash('erreur', "La suppression physique d'un mémoire publié ou déjà retiré est interdite. Utilisez le retrait.");
    rediriger('/memoires/liste.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM rapport_similarite WHERE id_memoire = :id OR id_memoire_compare = :id")
            ->execute([':id' => $idMemoire]);
        $pdo->prepare("DELETE FROM consultation WHERE id_memoire = :id")->execute([':id' => $idMemoire]);
        $pdo->prepare("DELETE FROM depot WHERE id_memoire = :id")->execute([':id' => $idMemoire]);
        $pdo->prepare("DELETE FROM memoire WHERE id_memoire = :id")->execute([':id' => $idMemoire]);

        $cheminAbsolu = __DIR__ . '/../' . $memoire['chemin_fichier'];
        if (is_file($cheminAbsolu)) {
            unlink($cheminAbsolu);
        }

        journaliser($pdo, $u['id_utilisateur'], "Suppression définitive du mémoire « {$memoire['titre']} »");

        $pdo->commit();
        flash('succes', "Le mémoire a été supprimé définitivement.");
        rediriger('/memoires/liste.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('erreur', "Impossible de supprimer le mémoire. Vérifiez la connexion à la base de données.");
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Supprimer définitivement</h1>
    <p class="sous-titre"><?= e($memoire['titre']) ?></p>
</div></div>

<div class="alert alert-avertissement">Cette opération est irréversible et réservée à la correction d'une erreur de saisie avant publication.</div>

<div class="carte">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $idMemoire ?>">
        <div class="actions-ligne">
            <button type="submit" class="btn btn-danger" data-confirmer="Confirmez-vous la suppression DÉFINITIVE de ce mémoire ? Cette action est irréversible.">Confirmer la suppression</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/memoires/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
