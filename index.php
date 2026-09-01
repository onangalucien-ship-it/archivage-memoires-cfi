<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

rediriger(est_connecte() ? '/tableau_de_bord.php' : '/connexion.php');
