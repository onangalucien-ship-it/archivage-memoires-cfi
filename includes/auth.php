<?php
// includes/auth.php - Authentification et contrôle d'accès par rôle (M04)

function est_connecte(): bool
{
    return isset($_SESSION['id_utilisateur']);
}

function utilisateur_courant(): ?array
{
    if (!est_connecte()) {
        return null;
    }
    return [
        'id_utilisateur' => $_SESSION['id_utilisateur'],
        'nom' => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'],
        'role' => $_SESSION['role'],
    ];
}

function exiger_connexion(): void
{
    if (!est_connecte()) {
        header("Location: " . BASE_URL . "/connexion.php");
        exit;
    }
}

function exiger_role(array $roles): void
{
    exiger_connexion();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-erreur">Vous n\'avez pas l\'autorisation d\'effectuer cette action.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
