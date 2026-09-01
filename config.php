<?php
// config.php - Connexion à la base de données et constantes de l'application

date_default_timezone_set('Africa/Brazzaville');

$host = "localhost";
$dbname = "archivage_memoires";
$user = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Détermine le chemin web racine de l'application, quel que soit le nom du dossier
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
$appRoot = realpath(__DIR__);
$base = '';
if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
    $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
}
define('BASE_URL', rtrim($base, '/'));
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024); // 15 Mo

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
