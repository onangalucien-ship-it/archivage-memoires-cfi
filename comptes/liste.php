<?php
// comptes/liste.php - UC4.2 : Gérer les comptes utilisateurs
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$comptes = $pdo->query("SELECT * FROM utilisateur ORDER BY actif DESC, role, nom")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Comptes utilisateurs</h1>
    <p class="sous-titre">UC4.2 — Création, modification et désactivation des comptes</p>
</div>
<div><a class="btn" href="<?= BASE_URL ?>/comptes/ajouter.php">Ajouter un compte</a></div>
</div>

<div class="carte table-scroll">
    <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Créé le</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($comptes as $c): ?>
            <tr>
                <td><?= e($c['prenom'] . ' ' . $c['nom']) ?></td>
                <td><?= e($c['email']) ?></td>
                <td><span class="badge-role"><?= e(libelle_role($c['role'])) ?></span></td>
                <td><?= $c['actif'] ? '<span class="badge badge-publie">Actif</span>' : '<span class="badge badge-retire">Désactivé</span>' ?></td>
                <td><?= e(formater_date($c['date_creation'])) ?></td>
                <td class="actions-ligne">
                    <a href="<?= BASE_URL ?>/comptes/modifier.php?id=<?= (int) $c['id_utilisateur'] ?>">Modifier</a>
                    <a href="<?= BASE_URL ?>/comptes/desactiver.php?id=<?= (int) $c['id_utilisateur'] ?>" data-confirmer="Confirmez-vous ce changement de statut ?"><?= $c['actif'] ? 'Désactiver' : 'Réactiver' ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
