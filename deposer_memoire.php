<?php
// deposer_memoire.php - UC1.1 : Déposer un mémoire (M01)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analyse_similarite.php';
exiger_role(['ETUDIANT']);

$u = utilisateur_courant();
$erreurs = [];

$etablissements = $pdo->query("SELECT id_etablissement, nom FROM etablissement ORDER BY nom")->fetchAll();
$encadreurs = $pdo->query("SELECT id_utilisateur, nom, prenom FROM utilisateur WHERE role = 'ENCADREUR' AND actif = 1 ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $titre = trim($_POST['titre'] ?? '');
    $resume = trim($_POST['resume'] ?? '');
    $motsCles = trim($_POST['mots_cles'] ?? '');
    $filiere = trim($_POST['filiere'] ?? '');
    $annee = (int) ($_POST['annee_academique'] ?? 0);
    $idEtablissement = (int) ($_POST['id_etablissement'] ?? 0);
    $idEncadreur = !empty($_POST['id_encadreur']) ? (int) $_POST['id_encadreur'] : null;

    // SA3 : champs obligatoires manquants
    if ($titre === '' || $resume === '' || $filiere === '' || $annee < 2000 || !$idEtablissement) {
        $erreurs[] = "Veuillez renseigner tous les champs obligatoires.";
    }

    $fichier = $_FILES['fichier'] ?? null;
    if (!$fichier || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        $erreurs[] = "Veuillez sélectionner le fichier PDF du mémoire.";
    } elseif ($fichier['error'] !== UPLOAD_ERR_OK) {
        $erreurs[] = "Erreur lors du téléversement du fichier.";
    } else {
        if ($fichier['size'] > MAX_UPLOAD_SIZE) {
            $erreurs[] = "Le fichier dépasse la taille maximale autorisée (15 Mo).";
        }
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fichier['tmp_name']);
        finfo_close($finfo);
        if ($extension !== 'pdf' || $mime !== 'application/pdf') {
            $erreurs[] = "Seuls les fichiers au format PDF sont acceptés.";
        }
    }

    // Vérification de l'absence de doublon (titre + auteur + année) - UC1.1 inclut UC1.2
    if (empty($erreurs)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM memoire WHERE titre = :titre AND id_etudiant = :id_etudiant AND annee_academique = :annee"
        );
        $stmt->execute([':titre' => $titre, ':id_etudiant' => $u['id_utilisateur'], ':annee' => $annee]);
        if ($stmt->fetchColumn() > 0) {
            // SA4 : mémoire déjà existant
            $erreurs[] = "Un mémoire avec ce titre et cet auteur existe déjà.";
        }
    }

    if (empty($erreurs)) {
        try {
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            $nomFichier = uniqid('memoire_', true) . '.pdf';
            $cheminAbsolu = UPLOAD_DIR . $nomFichier;
            if (!move_uploaded_file($fichier['tmp_name'], $cheminAbsolu)) {
                throw new RuntimeException("Impossible d'enregistrer le fichier téléversé.");
            }

            $stmt = $pdo->prepare(
                "INSERT INTO memoire (titre, resume, mots_cles, annee_academique, filiere, chemin_fichier,
                    statut, id_etudiant, id_encadreur, id_etablissement)
                 VALUES (:titre, :resume, :mots_cles, :annee, :filiere, :chemin, 'EN_ATTENTE', :id_etudiant, :id_encadreur, :id_etablissement)"
            );
            $stmt->execute([
                ':titre' => $titre,
                ':resume' => $resume,
                ':mots_cles' => $motsCles,
                ':annee' => $annee,
                ':filiere' => $filiere,
                ':chemin' => 'uploads/' . $nomFichier,
                ':id_etudiant' => $u['id_utilisateur'],
                ':id_encadreur' => $idEncadreur,
                ':id_etablissement' => $idEtablissement,
            ]);
            $idMemoire = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO depot (statut, id_memoire, id_gestionnaire) VALUES ('EN_ATTENTE', :id_memoire, NULL)"
            );
            $stmt->execute([':id_memoire' => $idMemoire]);

            journaliser($pdo, $u['id_utilisateur'], "Dépôt du mémoire « {$titre} »");

            // UC3.1 : déclenchement automatique de l'analyse de similarité (extend de UC1.1)
            $resultatAnalyse = analyserMemoire($pdo, $idMemoire);

            $message = "Dépôt réussi, en attente de validation par le gestionnaire des archives.";
            if ($resultatAnalyse['alerte']) {
                $message .= " Un taux de similarité élevé (" . $resultatAnalyse['taux_max'] . " %) a été détecté avec un autre mémoire du corpus ; il sera examiné lors de la validation.";
            }
            flash('succes', $message);
            rediriger('/mes_memoires.php');
        } catch (Exception $e) {
            // E1 : problème technique lors de l'enregistrement
            $erreurs[] = "Impossible d'enregistrer le mémoire. Vérifiez la connexion à la base de données.";
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1>Déposer un mémoire</h1>
    <p class="sous-titre">UC1.1 — Le mémoire sera enregistré avec le statut « En attente » et analysé automatiquement.</p>
</div></div>

<?php foreach ($erreurs as $err): ?>
    <div class="alert alert-erreur"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="carte">
    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
        <div class="champ">
            <label for="titre">Titre du mémoire *</label>
            <input type="text" id="titre" name="titre" required value="<?= e($_POST['titre'] ?? '') ?>">
        </div>
        <div class="champ">
            <label for="resume">Résumé *</label>
            <textarea id="resume" name="resume" required><?= e($_POST['resume'] ?? '') ?></textarea>
        </div>
        <div class="champ">
            <label for="mots_cles">Mots-clés (séparés par des virgules)</label>
            <input type="text" id="mots_cles" name="mots_cles" value="<?= e($_POST['mots_cles'] ?? '') ?>">
        </div>
        <div class="ligne-champs">
            <div class="champ">
                <label for="filiere">Filière *</label>
                <input type="text" id="filiere" name="filiere" required value="<?= e($_POST['filiere'] ?? '') ?>">
            </div>
            <div class="champ">
                <label for="annee_academique">Année académique *</label>
                <input type="number" id="annee_academique" name="annee_academique" min="2000" max="2100" required
                       value="<?= e($_POST['annee_academique'] ?? date('Y')) ?>">
            </div>
            <div class="champ">
                <label for="id_etablissement">Établissement *</label>
                <select id="id_etablissement" name="id_etablissement" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($etablissements as $etab): ?>
                        <option value="<?= (int) $etab['id_etablissement'] ?>"><?= e($etab['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label for="id_encadreur">Encadreur</label>
                <select id="id_encadreur" name="id_encadreur">
                    <option value="">— Aucun / non renseigné —</option>
                    <?php foreach ($encadreurs as $enc): ?>
                        <option value="<?= (int) $enc['id_utilisateur'] ?>"><?= e($enc['prenom'] . ' ' . $enc['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="champ">
            <label for="fichier">Fichier PDF du mémoire *</label>
            <input type="file" id="fichier" name="fichier" accept="application/pdf" required>
            <p class="aide">Format PDF uniquement, 15 Mo maximum.</p>
        </div>
        <button type="submit" class="btn">Déposer le mémoire</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
