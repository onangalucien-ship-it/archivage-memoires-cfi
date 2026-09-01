<?php
// memoires/liste.php - Vue de gestion des mémoires (M01) pour le gestionnaire des archives / l'administrateur
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['GESTIONNAIRE', 'ADMINISTRATEUR']);

$statut = trim($_GET['statut'] ?? '');
$conditions = [];
$params = [];
if ($statut !== '') {
    $conditions[] = "m.statut = :statut";
    $params[':statut'] = $statut;
}
$sql = "SELECT m.*, u.nom AS nom_auteur, u.prenom AS prenom_auteur,
               (SELECT MAX(taux_similarite) FROM rapport_similarite
                WHERE id_memoire = m.id_memoire OR id_memoire_compare = m.id_memoire) AS taux_max
        FROM memoire m JOIN utilisateur u ON u.id_utilisateur = m.id_etudiant";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY (m.statut = 'EN_ATTENTE') DESC, m.date_depot DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$memoires = $stmt->fetchAll();

$seuil = get_seuil_alerte($pdo);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div>
    <h1>Gestion des mémoires</h1>
    <p class="sous-titre">M01 — Dépôts, validation, mise à jour des métadonnées, retrait</p>
</div></div>

<form method="get" class="carte filtres">
    <div class="champ">
        <label for="statut">Statut</label>
        <select id="statut" name="statut" onchange="this.form.submit()">
            <option value="">Tous</option>
            <?php foreach (['EN_ATTENTE', 'PUBLIE', 'REJETE', 'RETIRE'] as $s): ?>
                <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>><?= e(libelle_statut($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="carte table-scroll">
    <?php if (empty($memoires)): ?>
        <p class="vide">Aucun mémoire à afficher.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Titre</th><th>Auteur</th><th>Filière / Année</th><th>Statut</th><th>Similarité</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($memoires as $m): ?>
            <tr>
                <td><?= e($m['titre']) ?></td>
                <td><?= e($m['prenom_auteur'] . ' ' . $m['nom_auteur']) ?></td>
                <td><?= e($m['filiere']) ?> / <?= (int) $m['annee_academique'] ?></td>
                <td><span class="<?= classe_statut($m['statut']) ?>"><?= e(libelle_statut($m['statut'])) ?></span></td>
                <td>
                    <?php if ($m['taux_max'] !== null): ?>
                        <span class="taux-similarite <?= $m['taux_max'] >= $seuil ? 'taux-alerte' : 'taux-ok' ?>"><?= $m['taux_max'] ?>%</span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="actions-ligne">
                    <a href="<?= BASE_URL ?>/consulter_memoire.php?id=<?= (int) $m['id_memoire'] ?>">Voir</a>
                    <?php if ($m['statut'] === 'EN_ATTENTE'): ?>
                        <a href="<?= BASE_URL ?>/memoires/valider.php?id=<?= (int) $m['id_memoire'] ?>">Valider</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/memoires/modifier.php?id=<?= (int) $m['id_memoire'] ?>">Modifier</a>
                    <?php if ($m['statut'] === 'PUBLIE'): ?>
                        <a href="<?= BASE_URL ?>/memoires/retirer.php?id=<?= (int) $m['id_memoire'] ?>">Retirer</a>
                    <?php endif; ?>
                    <?php if (in_array($m['statut'], ['EN_ATTENTE', 'REJETE'], true)): ?>
                        <a href="<?= BASE_URL ?>/memoires/supprimer.php?id=<?= (int) $m['id_memoire'] ?>" class="btn-danger-link" data-confirmer="Confirmez-vous la suppression définitive de ce mémoire ?">Supprimer</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/similarite/rapport.php?id=<?= (int) $m['id_memoire'] ?>">Similarité</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
