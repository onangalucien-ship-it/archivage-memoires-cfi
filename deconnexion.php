<?php
// deconnexion.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (est_connecte()) {
    journaliser($pdo, $_SESSION['id_utilisateur'], "Déconnexion du système");
}

$_SESSION = [];
session_destroy();

session_start();
flash('info', 'Vous avez été déconnecté.');
rediriger('/connexion.php');
