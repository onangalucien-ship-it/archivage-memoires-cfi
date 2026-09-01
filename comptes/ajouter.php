<?php
// comptes/ajouter.php - UC4.2 : Créer un compte utilisateur
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$u = utilisateur_courant();
$roles = ['ETUDIANT', 'ENCADREUR', 'GESTIONNAIRE', 'ADMINISTRATEUR'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($nom === '' || $prenom === '' || $email === '' || !in_array($role, $roles, true)) {
        $erreurs[] = "Veuillez renseigner tous les champs obligatoires.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Adresse e-mail invalide.";
    }
    if (strlen($motDePasse) < 8) {
        $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            $erreurs[] = "Un compte existe déjà avec cette adresse e-mail.";
        }
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, actif)
             VALUES (:nom, :prenom, :email, :mot_de_passe, :role, 1)"
        );
        $stmt->execute([
            ':nom' => $nom, ':prenom' => $prenom, ':email' => $email,
            ':mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT), ':role' => $role,
        ]);
        journaliser($pdo, $u['id_utilisateur'], "Création du compte {$email} (rôle : {$role})");
        flash('succes', "Compte créé avec succès.");
        rediriger('/comptes/liste.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Ajouter un compte</h1>
    <p class="sous-titre">UC4.2</p>
</div></div>

<?php foreach ($erreurs as $err): ?><div class="alert alert-erreur"><?= e($err) ?></div><?php endforeach; ?>

<div class="carte" style="max-width:520px">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <div class="ligne-champs">
            <div class="champ">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom" required value="<?= e($_POST['prenom'] ?? '') ?>">
            </div>
            <div class="champ">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" required value="<?= e($_POST['nom'] ?? '') ?>">
            </div>
        </div>
        <div class="champ">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="champ">
            <label for="role">Rôle *</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= ($_POST['role'] ?? '') === $r ? 'selected' : '' ?>><?= e(libelle_role($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="mot_de_passe">Mot de passe temporaire *</label>
            <input type="text" id="mot_de_passe" name="mot_de_passe" required minlength="8">
            <p class="aide">8 caractères minimum. À communiquer à l'utilisateur, qui pourra le modifier ultérieurement.</p>
        </div>
        <div class="actions-ligne">
            <button type="submit" class="btn">Créer le compte</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/comptes/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
