<?php
// connexion.php - UC4.1 : S'authentifier
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (est_connecte()) {
    rediriger('/tableau_de_bord.php');
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $motDePasse === '') {
        $erreur = "Veuillez renseigner votre identifiant et votre mot de passe.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            // SA1 : Identifiant ou mot de passe incorrect
            $erreur = "Identifiant ou mot de passe incorrect.";
        } elseif (!$utilisateur['actif']) {
            // E1 : Compte désactivé
            $erreur = "Ce compte a été désactivé, contactez l'administrateur.";
        } else {
            session_regenerate_id(true);
            $_SESSION['id_utilisateur'] = (int) $utilisateur['id_utilisateur'];
            $_SESSION['role'] = $utilisateur['role'];
            $_SESSION['nom'] = $utilisateur['nom'];
            $_SESSION['prenom'] = $utilisateur['prenom'];

            journaliser($pdo, $utilisateur['id_utilisateur'], "Connexion au système");
            rediriger('/tableau_de_bord.php');
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap">
    <div class="carte">
        <h1>Connexion</h1>
        <p class="sous-titre">Système d'archivage intelligent des mémoires</p>

        <?php if ($erreur): ?>
            <div class="alert alert-erreur"><?= e($erreur) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
            <div class="champ">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            </div>
            <button type="submit" class="btn" style="width:100%">Se connecter</button>
        </form>

        <div class="comptes-demo">
            <strong>Comptes de démonstration</strong> (mot de passe : <code>Passer123</code>)<br>
            admin@archivage.cg · gestionnaire@archivage.cg · encadreur@archivage.cg · etudiant@archivage.cg<br>
            L'accès au système est réservé aux membres de l'établissement : la création de compte se fait exclusivement par l'administrateur.
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
