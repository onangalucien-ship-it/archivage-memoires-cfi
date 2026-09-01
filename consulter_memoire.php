<?php
// consulter_memoire.php - UC2.2 : Consulter un mémoire (M02)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$u = utilisateur_courant();
$idMemoire = (int) ($_GET['id'] ?? 0);
$roleConnecte = $u['role'];
$estGestion = in_array($roleConnecte, ['GESTIONNAIRE', 'ADMINISTRATEUR'], true);

$stmt = $pdo->prepare(
    "SELECT m.*, u.nom AS nom_auteur, u.prenom AS prenom_auteur, u.id_utilisateur AS id_auteur,
            e.nom AS nom_etablissement,
            enc.nom AS nom_encadreur, enc.prenom AS prenom_encadreur
     FROM memoire m
     JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant
     JOIN etablissement e ON e.id_etablissement = m.id_etablissement
     LEFT JOIN utilisateur enc ON enc.id_utilisateur = m.id_encadreur
     WHERE m.id_memoire = :id"
);
$stmt->execute([':id' => $idMemoire]);
$memoire = $stmt->fetch();

// Précondition (UC2.2) : le mémoire doit être publié, sauf pour la gestion qui doit
// pouvoir instruire les dépôts en attente.
if (!$memoire || (!$estGestion && $memoire['statut'] !== 'PUBLIE')) {
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-erreur">Mémoire introuvable ou non publié.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Règle métier (§2.3.2) : texte intégral réservé à l'auteur, son encadreur,
// le gestionnaire des archives et l'administrateur.
$estAuteur = ((int) $memoire['id_auteur'] === (int) $u['id_utilisateur']);
$estEncadreurDuMemoire = ($memoire['id_encadreur'] && (int) $memoire['id_encadreur'] === (int) $u['id_utilisateur']);
$accesTexteIntegral = $estAuteur || $estEncadreurDuMemoire || $estGestion;

// Journalisation de la consultation (à des fins statistiques - M05)
$stmt = $pdo->prepare(
    "INSERT INTO consultation (id_memoire, id_utilisateur) VALUES (:id_memoire, :id_utilisateur)"
);
$stmt->execute([':id_memoire' => $idMemoire, ':id_utilisateur' => $u['id_utilisateur']]);

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1><?= e($memoire['titre']) ?></h1>
    <p class="sous-titre">
        <span class="<?= classe_statut($memoire['statut']) ?>"><?= e(libelle_statut($memoire['statut'])) ?></span>
    </p>
</div>
<div>
    <?php if ($accesTexteIntegral): ?>
        <a class="btn" href="<?= BASE_URL ?>/telecharger_memoire.php?id=<?= $idMemoire ?>">Télécharger le PDF</a>
    <?php endif; ?>
</div>
</div>

<div class="carte fiche-memoire">
    <dl>
        <dt>Auteur</dt><dd><?= e($memoire['prenom_auteur'] . ' ' . $memoire['nom_auteur']) ?></dd>
        <dt>Établissement</dt><dd><?= e($memoire['nom_etablissement']) ?></dd>
        <dt>Filière</dt><dd><?= e($memoire['filiere']) ?></dd>
        <dt>Année académique</dt><dd><?= (int) $memoire['annee_academique'] ?></dd>
        <dt>Encadreur</dt><dd><?= $memoire['nom_encadreur'] ? e($memoire['prenom_encadreur'] . ' ' . $memoire['nom_encadreur']) : '—' ?></dd>
        <dt>Mots-clés</dt><dd><?= e($memoire['mots_cles']) ?: '—' ?></dd>
        <dt>Date de dépôt</dt><dd><?= e(formater_date($memoire['date_depot'])) ?></dd>
    </dl>

    <h3>Résumé</h3>
    <p><?= nl2br(e($memoire['resume'])) ?></p>

    <?php if (!$accesTexteIntegral): ?>
        <div class="alert alert-info">L'accès au texte intégral de ce mémoire est réservé à son auteur, à son encadreur, au gestionnaire des archives et à l'administrateur.</div>
    <?php endif; ?>
</div>

<?php if ($estGestion): ?>
<div class="carte">
    <h3 style="margin-top:0">Rapport de similarité</h3>
    <p><a href="<?= BASE_URL ?>/similarite/rapport.php?id=<?= $idMemoire ?>">Consulter le rapport de similarité de ce mémoire</a></p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
