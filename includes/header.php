<?php
$u = utilisateur_courant();
$page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archivage des mémoires — CFI-CIRAS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= BASE_URL ?>/tableau_de_bord.php">
            <span class="brand-mark">CFI</span> Archivage &amp; consultation des mémoires
        </a>
        <?php if ($u): ?>
        <nav class="nav-main">
            <a href="<?= BASE_URL ?>/tableau_de_bord.php">Tableau de bord</a>
            <a href="<?= BASE_URL ?>/rechercher_memoire.php">Rechercher</a>
            <?php if ($u['role'] === 'ETUDIANT'): ?>
                <a href="<?= BASE_URL ?>/deposer_memoire.php">Déposer un mémoire</a>
                <a href="<?= BASE_URL ?>/mes_memoires.php">Mes mémoires</a>
            <?php endif; ?>
            <?php if (in_array($u['role'], ['GESTIONNAIRE', 'ADMINISTRATEUR'], true)): ?>
                <a href="<?= BASE_URL ?>/memoires/liste.php">Gestion des mémoires</a>
                <a href="<?= BASE_URL ?>/statistiques.php">Statistiques</a>
            <?php endif; ?>
            <?php if ($u['role'] === 'ADMINISTRATEUR'): ?>
                <a href="<?= BASE_URL ?>/comptes/liste.php">Comptes</a>
                <a href="<?= BASE_URL ?>/similarite/seuil.php">Seuil de similarité</a>
                <a href="<?= BASE_URL ?>/journal.php">Journal d'activité</a>
            <?php endif; ?>
        </nav>
        <div class="nav-user">
            <span><?= e($u['prenom'] . ' ' . $u['nom']) ?> <small>(<?= e(libelle_role($u['role'])) ?>)</small></span>
            <a class="btn-deconnexion" href="<?= BASE_URL ?>/deconnexion.php">Déconnexion</a>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="container">
    <?php foreach (recuperer_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
