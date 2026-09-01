<?php
// mes_memoires.php - UC1.6 (volet étudiant) : Suivre le statut de ses mémoires déposés
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_role(['ETUDIANT']);

$u = utilisateur_courant();
$stmt = $pdo->prepare(
    "SELECT * FROM memoire WHERE id_etudiant = :id ORDER BY date_depot DESC"
);
$stmt->execute([':id' => $u['id_utilisateur']]);
$memoires = $stmt->fetchAll();

$seuil = get_seuil_alerte($pdo);

require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div>
    <h1>Mes mémoires</h1>
    <p class="sous-titre">Suivi du statut de vos dépôts</p>
</div>
<div><a class="btn" href="<?= BASE_URL ?>/deposer_memoire.php">Déposer un nouveau mémoire</a></div>
</div>

<div class="carte table-scroll">
    <?php if (empty($memoires)): ?>
        <p class="vide">Vous n'avez déposé aucun mémoire pour le moment.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Titre</th><th>Filière</th><th>Année</th><th>Statut</th><th>Date de dépôt</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($memoires as $m): ?>
            <?php
            $stmtMax = $pdo->prepare(
                "SELECT MAX(taux_similarite) FROM rapport_similarite WHERE id_memoire = :id OR id_memoire_compare = :id"
            );
            $stmtMax->execute([':id' => $m['id_memoire']]);
            $tauxMax = (float) ($stmtMax->fetchColumn() ?: 0);
            ?>
            <tr>
                <td><?= e($m['titre']) ?>
                    <?php if ($tauxMax >= $seuil): ?>
                        <br><span class="badge badge-rejete">Similarité élevée : <?= $tauxMax ?>%</span>
                    <?php endif; ?>
                </td>
                <td><?= e($m['filiere']) ?></td>
                <td><?= (int) $m['annee_academique'] ?></td>
                <td><span class="<?= classe_statut($m['statut']) ?>"><?= e(libelle_statut($m['statut'])) ?></span></td>
                <td><?= e(formater_date($m['date_depot'])) ?></td>
                <td><a href="<?= BASE_URL ?>/consulter_memoire.php?id=<?= (int) $m['id_memoire'] ?>">Détails</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
