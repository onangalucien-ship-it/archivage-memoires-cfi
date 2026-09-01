<?php
// rechercher_memoire.php - UC1.2 / UC2.1 : Rechercher un mémoire (M01/M02)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_connexion(); // Aucun accès anonyme : réservé aux membres authentifiés de l'établissement

$u = utilisateur_courant();
$peutVoirTousStatuts = in_array($u['role'], ['GESTIONNAIRE', 'ADMINISTRATEUR'], true);

$motCle = trim($_GET['mot_cle'] ?? '');
$filiere = trim($_GET['filiere'] ?? '');
$annee = trim($_GET['annee'] ?? '');
$auteur = trim($_GET['auteur'] ?? '');
$statut = $peutVoirTousStatuts ? trim($_GET['statut'] ?? '') : 'PUBLIE';
$rechercheLancee = isset($_GET['rechercher']);

$filieres = $pdo->query("SELECT DISTINCT filiere FROM memoire ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

$resultats = [];
if ($rechercheLancee) {
    $conditions = [];
    $params = [];

    // Seuls les mémoires publiés apparaissent dans les résultats de recherche (sauf pour la gestion)
    if ($peutVoirTousStatuts && $statut !== '') {
        $conditions[] = "m.statut = :statut";
        $params[':statut'] = $statut;
    } elseif (!$peutVoirTousStatuts) {
        $conditions[] = "m.statut = 'PUBLIE'";
    }

    if ($motCle !== '') {
        $conditions[] = "(m.titre LIKE :motcle1 OR m.resume LIKE :motcle2 OR m.mots_cles LIKE :motcle3)";
        $params[':motcle1'] = "%$motCle%";
        $params[':motcle2'] = "%$motCle%";
        $params[':motcle3'] = "%$motCle%";
    }
    if ($filiere !== '') {
        $conditions[] = "m.filiere = :filiere";
        $params[':filiere'] = $filiere;
    }
    if ($annee !== '') {
        $conditions[] = "m.annee_academique = :annee";
        $params[':annee'] = (int) $annee;
    }
    if ($auteur !== '') {
        $conditions[] = "(u.nom LIKE :auteur1 OR u.prenom LIKE :auteur2)";
        $params[':auteur1'] = "%$auteur%";
        $params[':auteur2'] = "%$auteur%";
    }

    $sql = "SELECT m.*, u.nom AS nom_auteur, u.prenom AS prenom_auteur
            FROM memoire m JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY m.date_depot DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1>Rechercher un mémoire</h1>
    <p class="sous-titre">Recherche multicritères dans le référentiel des mémoires publiés</p>
</div></div>

<form method="get" class="carte filtres">
    <div class="champ">
        <label for="mot_cle">Mot-clé</label>
        <input type="text" id="mot_cle" name="mot_cle" value="<?= e($motCle) ?>" placeholder="Titre, résumé, mots-clés">
    </div>
    <div class="champ">
        <label for="filiere">Filière</label>
        <select id="filiere" name="filiere">
            <option value="">Toutes</option>
            <?php foreach ($filieres as $f): ?>
                <option value="<?= e($f) ?>" <?= $filiere === $f ? 'selected' : '' ?>><?= e($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ">
        <label for="annee">Année académique</label>
        <input type="number" id="annee" name="annee" value="<?= e($annee) ?>" min="2000" max="2100">
    </div>
    <div class="champ">
        <label for="auteur">Auteur</label>
        <input type="text" id="auteur" name="auteur" value="<?= e($auteur) ?>">
    </div>
    <?php if ($peutVoirTousStatuts): ?>
    <div class="champ">
        <label for="statut">Statut</label>
        <select id="statut" name="statut">
            <option value="">Tous</option>
            <?php foreach (['EN_ATTENTE', 'PUBLIE', 'REJETE', 'RETIRE'] as $s): ?>
                <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>><?= e(libelle_statut($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="champ">
        <button type="submit" name="rechercher" value="1" class="btn">Rechercher</button>
    </div>
</form>

<?php if ($rechercheLancee): ?>
<div class="carte table-scroll">
    <?php if (empty($resultats)): ?>
        <p class="vide">Aucun mémoire ne correspond à ces critères.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Titre</th><th>Auteur</th><th>Filière</th><th>Année</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($resultats as $m): ?>
            <tr>
                <td><?= e($m['titre']) ?></td>
                <td><?= e($m['prenom_auteur'] . ' ' . $m['nom_auteur']) ?></td>
                <td><?= e($m['filiere']) ?></td>
                <td><?= (int) $m['annee_academique'] ?></td>
                <td><span class="<?= classe_statut($m['statut']) ?>"><?= e(libelle_statut($m['statut'])) ?></span></td>
                <td><a href="<?= BASE_URL ?>/consulter_memoire.php?id=<?= (int) $m['id_memoire'] ?>">Consulter</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
