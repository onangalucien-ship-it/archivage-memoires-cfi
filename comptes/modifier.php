<?php
// comptes/modifier.php - UC4.2 : Modifier un compte utilisateur
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$u = utilisateur_courant();
$idCible = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$roles = ['ETUDIANT', 'ENCADREUR', 'GESTIONNAIRE', 'ADMINISTRATEUR'];

$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = :id");
$stmt->execute([':id' => $idCible]);
$compte = $stmt->fetch();

if (!$compte) {
    flash('erreur', "Compte introuvable.");
    rediriger('/comptes/liste.php');
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $nouveauMotDePasse = $_POST['mot_de_passe'] ?? '';

    if ($nom === '' || $prenom === '' || $email === '' || !in_array($role, $roles, true)) {
        $erreurs[] = "Veuillez renseigner tous les champs obligatoires.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Adresse e-mail invalide.";
    }
    if ($nouveauMotDePasse !== '' && strlen($nouveauMotDePasse) < 8) {
        $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email AND id_utilisateur != :id");
        $stmt->execute([':email' => $email, ':id' => $idCible]);
        if ($stmt->fetchColumn() > 0) {
            $erreurs[] = "Un autre compte utilise déjà cette adresse e-mail.";
        }
    }

    if (empty($erreurs)) {
        if ($nouveauMotDePasse !== '') {
            $stmt = $pdo->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, role=:role, mot_de_passe=:mdp WHERE id_utilisateur=:id"
            );
            $stmt->execute([
                ':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':role' => $role,
                ':mdp' => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT), ':id' => $idCible,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE utilisateur SET nom=:nom, prenom=:prenom, email=:email, role=:role WHERE id_utilisateur=:id"
            );
            $stmt->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':role' => $role, ':id' => $idCible]);
        }
        journaliser($pdo, $u['id_utilisateur'], "Modification du compte {$email}");
        flash('succes', "Compte mis à jour.");
        rediriger('/comptes/liste.php');
    }
    $compte = array_merge($compte, ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'role' => $role]);
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Modifier le compte</h1>
    <p class="sous-titre">UC4.2</p>
</div></div>

<?php foreach ($erreurs as $err): ?><div class="alert alert-erreur"><?= e($err) ?></div><?php endforeach; ?>

<div class="carte" style="max-width:520px">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $idCible ?>">
        <div class="ligne-champs">
            <div class="champ">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom" required value="<?= e($compte['prenom']) ?>">
            </div>
            <div class="champ">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" required value="<?= e($compte['nom']) ?>">
            </div>
        </div>
        <div class="champ">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" required value="<?= e($compte['email']) ?>">
        </div>
        <div class="champ">
            <label for="role">Rôle *</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $compte['role'] === $r ? 'selected' : '' ?>><?= e(libelle_role($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="mot_de_passe">Nouveau mot de passe</label>
            <input type="text" id="mot_de_passe" name="mot_de_passe" minlength="8">
            <p class="aide">Laisser vide pour conserver le mot de passe actuel.</p>
        </div>
        <div class="actions-ligne">
            <button type="submit" class="btn">Enregistrer</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/comptes/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
