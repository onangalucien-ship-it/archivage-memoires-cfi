<?php
// memoires/modifier.php - UC1.3 : Mettre à jour les métadonnées d'un mémoire
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

$etablissements = $pdo->query("SELECT id_etablissement, nom FROM etablissement ORDER BY nom")->fetchAll();
$encadreurs = $pdo->query("SELECT id_utilisateur, nom, prenom FROM utilisateur WHERE role = 'ENCADREUR' ORDER BY nom")->fetchAll();
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $titre = trim($_POST['titre'] ?? '');
    $resume = trim($_POST['resume'] ?? '');
    $motsCles = trim($_POST['mots_cles'] ?? '');
    $filiere = trim($_POST['filiere'] ?? '');
    $annee = (int) ($_POST['annee_academique'] ?? 0);
    $idEtablissement = (int) ($_POST['id_etablissement'] ?? 0);
    $idEncadreur = !empty($_POST['id_encadreur']) ? (int) $_POST['id_encadreur'] : null;

    if ($titre === '' || $resume === '' || $filiere === '' || $annee < 2000 || !$idEtablissement) {
        $erreurs[] = "Veuillez renseigner tous les champs obligatoires.";
    }

    if (empty($erreurs)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE memoire SET titre = :titre, resume = :resume, mots_cles = :mots_cles,
                    filiere = :filiere, annee_academique = :annee, id_etablissement = :id_etablissement,
                    id_encadreur = :id_encadreur
                 WHERE id_memoire = :id"
            );
            $stmt->execute([
                ':titre' => $titre, ':resume' => $resume, ':mots_cles' => $motsCles,
                ':filiere' => $filiere, ':annee' => $annee, ':id_etablissement' => $idEtablissement,
                ':id_encadreur' => $idEncadreur, ':id' => $idMemoire,
            ]);
            journaliser($pdo, $u['id_utilisateur'], "Mise à jour des métadonnées du mémoire « {$titre} »");
            flash('succes', "Métadonnées mises à jour.");
            rediriger('/memoires/liste.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erreurs[] = "Un mémoire avec ce titre, cet auteur et cette année existe déjà.";
            } else {
                $erreurs[] = "Erreur de validation des données. Veuillez vérifier les champs saisis.";
            }
        }
    }
    $memoire = array_merge($memoire, [
        'titre' => $titre, 'resume' => $resume, 'mots_cles' => $motsCles, 'filiere' => $filiere,
        'annee_academique' => $annee, 'id_etablissement' => $idEtablissement, 'id_encadreur' => $idEncadreur,
    ]);
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Modifier les métadonnées</h1>
    <p class="sous-titre">UC1.3 — <?= e($memoire['titre']) ?></p>
</div></div>

<?php foreach ($erreurs as $err): ?><div class="alert alert-erreur"><?= e($err) ?></div><?php endforeach; ?>

<div class="carte">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $idMemoire ?>">
        <div class="champ">
            <label for="titre">Titre *</label>
            <input type="text" id="titre" name="titre" required value="<?= e($memoire['titre']) ?>">
        </div>
        <div class="champ">
            <label for="resume">Résumé *</label>
            <textarea id="resume" name="resume" required><?= e($memoire['resume']) ?></textarea>
        </div>
        <div class="champ">
            <label for="mots_cles">Mots-clés</label>
            <input type="text" id="mots_cles" name="mots_cles" value="<?= e($memoire['mots_cles']) ?>">
        </div>
        <div class="ligne-champs">
            <div class="champ">
                <label for="filiere">Filière *</label>
                <input type="text" id="filiere" name="filiere" required value="<?= e($memoire['filiere']) ?>">
            </div>
            <div class="champ">
                <label for="annee_academique">Année académique *</label>
                <input type="number" id="annee_academique" name="annee_academique" required value="<?= (int) $memoire['annee_academique'] ?>">
            </div>
            <div class="champ">
                <label for="id_etablissement">Établissement *</label>
                <select id="id_etablissement" name="id_etablissement" required>
                    <?php foreach ($etablissements as $etab): ?>
                        <option value="<?= (int) $etab['id_etablissement'] ?>" <?= (int) $memoire['id_etablissement'] === (int) $etab['id_etablissement'] ? 'selected' : '' ?>><?= e($etab['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label for="id_encadreur">Encadreur</label>
                <select id="id_encadreur" name="id_encadreur">
                    <option value="">— Aucun —</option>
                    <?php foreach ($encadreurs as $enc): ?>
                        <option value="<?= (int) $enc['id_utilisateur'] ?>" <?= (int) $memoire['id_encadreur'] === (int) $enc['id_utilisateur'] ? 'selected' : '' ?>><?= e($enc['prenom'] . ' ' . $enc['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="actions-ligne">
            <button type="submit" class="btn">Enregistrer</button>
            <a class="btn btn-secondaire" href="<?= BASE_URL ?>/memoires/liste.php">Annuler</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
