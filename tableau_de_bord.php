<?php
// tableau_de_bord.php - UC5.1 : Consulter le tableau de bord
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$u = utilisateur_courant();
$role = $u['role'];
$idUtilisateur = $u['id_utilisateur'];

$indicateurs = [];

if (in_array($role, ['GESTIONNAIRE', 'ADMINISTRATEUR'], true)) {
    $indicateurs['total'] = (int) $pdo->query("SELECT COUNT(*) FROM memoire")->fetchColumn();
    $indicateurs['en_attente'] = (int) $pdo->query("SELECT COUNT(*) FROM memoire WHERE statut = 'EN_ATTENTE'")->fetchColumn();
    $indicateurs['publies'] = (int) $pdo->query("SELECT COUNT(*) FROM memoire WHERE statut = 'PUBLIE'")->fetchColumn();
    $indicateurs['rejetes'] = (int) $pdo->query("SELECT COUNT(*) FROM memoire WHERE statut = 'REJETE'")->fetchColumn();
    $indicateurs['consultations'] = (int) $pdo->query("SELECT COUNT(*) FROM consultation")->fetchColumn();

    $seuil = get_seuil_alerte($pdo);
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT id_memoire) FROM (
            SELECT id_memoire FROM rapport_similarite WHERE taux_similarite >= :seuil1
            UNION
            SELECT id_memoire_compare FROM rapport_similarite WHERE taux_similarite >= :seuil2
        ) AS m"
    );
    $stmt->execute([':seuil1' => $seuil, ':seuil2' => $seuil]);
    $indicateurs['alertes'] = (int) $stmt->fetchColumn();

    $recents = $pdo->query(
        "SELECT m.id_memoire, m.titre, m.statut, m.date_depot, u.nom, u.prenom
         FROM memoire m JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant
         ORDER BY m.date_depot DESC LIMIT 8"
    )->fetchAll();
} elseif ($role === 'ETUDIANT') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire WHERE id_etudiant = :id");
    $stmt->execute([':id' => $idUtilisateur]);
    $indicateurs['mes_depots'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire WHERE id_etudiant = :id AND statut = 'PUBLIE'");
    $stmt->execute([':id' => $idUtilisateur]);
    $indicateurs['mes_publies'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM memoire WHERE id_etudiant = :id AND statut = 'EN_ATTENTE'");
    $stmt->execute([':id' => $idUtilisateur]);
    $indicateurs['mes_attente'] = (int) $stmt->fetchColumn();

    $recents = $pdo->prepare(
        "SELECT id_memoire, titre, statut, date_depot FROM memoire WHERE id_etudiant = :id
         ORDER BY date_depot DESC LIMIT 5"
    );
    $recents->execute([':id' => $idUtilisateur]);
    $recents = $recents->fetchAll();
} else { // ENCADREUR
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM memoire WHERE statut = 'PUBLIE' AND id_encadreur = :id"
    );
    $stmt->execute([':id' => $idUtilisateur]);
    $indicateurs['memoires_encadres'] = (int) $stmt->fetchColumn();

    $recents = $pdo->query(
        "SELECT m.id_memoire, m.titre, m.statut, m.date_depot, u.nom, u.prenom
         FROM memoire m JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant
         WHERE m.statut = 'PUBLIE' ORDER BY m.date_depot DESC LIMIT 8"
    )->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <p class="sous-titre">Bienvenue, <?= e($u['prenom']) ?> — <?= e(libelle_role($role)) ?></p>
    </div>
</div>

<div class="grille-cartes">
    <?php if (in_array($role, ['GESTIONNAIRE', 'ADMINISTRATEUR'], true)): ?>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['total'] ?></div><div class="libelle">Mémoires archivés</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['en_attente'] ?></div><div class="libelle">Dépôts en attente de validation</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['publies'] ?></div><div class="libelle">Mémoires publiés</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['rejetes'] ?></div><div class="libelle">Dépôts rejetés</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['consultations'] ?></div><div class="libelle">Consultations enregistrées</div></div>
        <div class="carte indicateur"><div class="valeur" style="<?= $indicateurs['alertes'] ? 'color:var(--rouge)' : '' ?>"><?= $indicateurs['alertes'] ?></div><div class="libelle">Mémoires en alerte de similarité</div></div>
    <?php elseif ($role === 'ETUDIANT'): ?>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['mes_depots'] ?></div><div class="libelle">Mes dépôts</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['mes_publies'] ?></div><div class="libelle">Mémoires publiés</div></div>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['mes_attente'] ?></div><div class="libelle">En attente de validation</div></div>
    <?php else: ?>
        <div class="carte indicateur"><div class="valeur"><?= $indicateurs['memoires_encadres'] ?></div><div class="libelle">Mémoires encadrés publiés</div></div>
    <?php endif; ?>
</div>

<?php if ($role === 'ETUDIANT'): ?>
    <p><a class="btn" href="<?= BASE_URL ?>/deposer_memoire.php">Déposer un nouveau mémoire</a></p>
<?php elseif (in_array($role, ['GESTIONNAIRE', 'ADMINISTRATEUR'], true)): ?>
    <p><a class="btn btn-secondaire" href="<?= BASE_URL ?>/statistiques.php">Voir toutes les statistiques</a></p>
<?php endif; ?>

<h2><?= in_array($role, ['GESTIONNAIRE', 'ADMINISTRATEUR', 'ENCADREUR'], true) ? 'Derniers mémoires déposés' : 'Mes derniers dépôts' ?></h2>
<div class="carte table-scroll">
    <?php if (empty($recents)): ?>
        <p class="vide">Aucun mémoire à afficher.</p>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>Titre</th>
            <?php if (isset($recents[0]['nom'])): ?><th>Auteur</th><?php endif; ?>
            <th>Statut</th><th>Date de dépôt</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recents as $m): ?>
            <tr>
                <td><?= e($m['titre']) ?></td>
                <?php if (isset($m['nom'])): ?><td><?= e($m['prenom'] . ' ' . $m['nom']) ?></td><?php endif; ?>
                <td><span class="<?= classe_statut($m['statut']) ?>"><?= e(libelle_statut($m['statut'])) ?></span></td>
                <td><?= e(formater_date($m['date_depot'])) ?></td>
                <td class="actions-ligne">
                    <a href="<?= BASE_URL ?>/consulter_memoire.php?id=<?= (int) $m['id_memoire'] ?>">Voir</a>
                    <?php if ($m['statut'] === 'EN_ATTENTE' && in_array($role, ['GESTIONNAIRE', 'ADMINISTRATEUR'], true)): ?>
                        <form method="post" action="<?= BASE_URL ?>/memoires/valider.php" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $m['id_memoire'] ?>">
                            <button type="submit" name="decision" value="PUBLIE" class="btn btn-succes btn-sm" data-confirmer="Publier ce mémoire ?">Valider</button>
                        </form>
                        <form method="post" action="<?= BASE_URL ?>/memoires/valider.php" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= e(jeton_csrf()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $m['id_memoire'] ?>">
                            <button type="submit" name="decision" value="REJETE" class="btn btn-danger btn-sm" data-confirmer="Rejeter ce dépôt ?">Rejeter</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
