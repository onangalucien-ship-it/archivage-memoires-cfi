<?php
// comptes/desactiver.php - UC4.2 : Désactiver / réactiver un compte
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
exiger_role(['ADMINISTRATEUR']);

$u = utilisateur_courant();
$idCible = (int) ($_GET['id'] ?? 0);

if ($idCible === (int) $u['id_utilisateur']) {
    flash('erreur', "Vous ne pouvez pas désactiver votre propre compte.");
    rediriger('/comptes/liste.php');
}

$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = :id");
$stmt->execute([':id' => $idCible]);
$compte = $stmt->fetch();

if (!$compte) {
    flash('erreur', "Compte introuvable.");
    rediriger('/comptes/liste.php');
}

$nouvelEtat = $compte['actif'] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE utilisateur SET actif = :actif WHERE id_utilisateur = :id");
$stmt->execute([':actif' => $nouvelEtat, ':id' => $idCible]);

journaliser($pdo, $u['id_utilisateur'],
    ($nouvelEtat ? "Réactivation" : "Désactivation") . " du compte {$compte['email']}");

flash('succes', $nouvelEtat ? "Compte réactivé." : "Compte désactivé.");
rediriger('/comptes/liste.php');
