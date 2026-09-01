<?php
// telecharger_memoire.php - UC2.3 : Télécharger un mémoire (M02)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$u = utilisateur_courant();
$idMemoire = (int) ($_GET['id'] ?? 0);
$estGestion = in_array($u['role'], ['GESTIONNAIRE', 'ADMINISTRATEUR'], true);

$stmt = $pdo->prepare("SELECT * FROM memoire WHERE id_memoire = :id");
$stmt->execute([':id' => $idMemoire]);
$memoire = $stmt->fetch();

if (!$memoire || (!$estGestion && $memoire['statut'] !== 'PUBLIE')) {
    http_response_code(404);
    die("Mémoire introuvable ou non publié.");
}

// Le téléchargement du texte intégral suit les mêmes règles de droits d'accès que la consultation
$estAuteur = ((int) $memoire['id_etudiant'] === (int) $u['id_utilisateur']);
$estEncadreurDuMemoire = ($memoire['id_encadreur'] && (int) $memoire['id_encadreur'] === (int) $u['id_utilisateur']);
$accesAutorise = $estAuteur || $estEncadreurDuMemoire || $estGestion;

if (!$accesAutorise) {
    http_response_code(403);
    die("Vous n'avez pas l'autorisation de télécharger le texte intégral de ce mémoire.");
}

$cheminAbsolu = __DIR__ . '/' . $memoire['chemin_fichier'];
if (!is_file($cheminAbsolu)) {
    http_response_code(404);
    die("Le fichier associé à ce mémoire est introuvable.");
}

journaliser($pdo, $u['id_utilisateur'], "Téléchargement du mémoire « {$memoire['titre']} »");

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w\-. ]/', '_', $memoire['titre']) . '.pdf"');
header('Content-Length: ' . filesize($cheminAbsolu));
readfile($cheminAbsolu);
exit;
