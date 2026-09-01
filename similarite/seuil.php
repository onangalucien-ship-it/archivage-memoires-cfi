<?php
// similarite/seuil.php - UC3.3 : Configurer le seuil d'alerte
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$u = utilisateur_courant();
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $valeur = $_POST['seuil'] ?? '';

    if (!is_numeric($valeur) || $valeur < 0 || $valeur > 100) {
        $erreur = "Le seuil doit être une valeur numérique comprise entre 0 et 100.";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO parametre (cle, valeur) VALUES ('seuil_similarite', :valeur)
             ON DUPLICATE KEY UPDATE valeur = :valeur"
        );
        $stmt->execute([':valeur' => (string) (float) $valeur]);
        journaliser($pdo, $u['id_utilisateur'], "Modification du seuil d'alerte de similarité (nouvelle valeur : {$valeur}%)");
        flash('succes', "Le seuil d'alerte a été mis à jour.");
        rediriger('/similarite/seuil.php');
    }
}

$seuilActuel = get_seuil_alerte($pdo);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Seuil d'alerte de similarité</h1>
    <p class="sous-titre">UC3.3 — Valeur de taux de similarité à partir de laquelle le gestionnaire est notifié</p>
</div></div>

<?php if ($erreur): ?><div class="alert alert-erreur"><?= e($erreur) ?></div><?php endif; ?>

<div class="carte" style="max-width:420px">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <div class="champ">
            <label for="seuil">Seuil actuel (%)</label>
            <input type="number" id="seuil" name="seuil" min="0" max="100" step="1" value="<?= e((string) $seuilActuel) ?>" required>
            <p class="aide">Valeur par défaut : 30 %. Une similarité supérieure ou égale à ce seuil déclenche une alerte visible par le gestionnaire lors de la validation.</p>
        </div>
        <button type="submit" class="btn">Enregistrer</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
