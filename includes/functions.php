<?php
// includes/functions.php - Fonctions utilitaires partagées

function e(?string $valeur): string
{
    return htmlspecialchars($valeur ?? '', ENT_QUOTES, 'UTF-8');
}

function journaliser(PDO $pdo, ?int $idUtilisateur, string $action): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO journal_activite (action, id_utilisateur) VALUES (:action, :id_utilisateur)"
    );
    $stmt->execute([':action' => $action, ':id_utilisateur' => $idUtilisateur]);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function recuperer_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function jeton_csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifier_csrf(): void
{
    $envoye = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $envoye)) {
        http_response_code(400);
        die("Jeton de sécurité invalide. Merci de recharger la page et réessayer.");
    }
}

function libelle_role(string $role): string
{
    $libelles = [
        'ETUDIANT' => 'Étudiant',
        'ENCADREUR' => 'Encadreur',
        'GESTIONNAIRE' => 'Gestionnaire des archives',
        'ADMINISTRATEUR' => 'Administrateur',
    ];
    return $libelles[$role] ?? $role;
}

function libelle_statut(string $statut): string
{
    $libelles = [
        'EN_ATTENTE' => 'En attente',
        'PUBLIE' => 'Publié',
        'REJETE' => 'Rejeté',
        'RETIRE' => 'Retiré',
    ];
    return $libelles[$statut] ?? $statut;
}

function classe_statut(string $statut): string
{
    $classes = [
        'EN_ATTENTE' => 'badge badge-attente',
        'PUBLIE' => 'badge badge-publie',
        'REJETE' => 'badge badge-rejete',
        'RETIRE' => 'badge badge-retire',
    ];
    return $classes[$statut] ?? 'badge';
}

function get_seuil_alerte(PDO $pdo): float
{
    $stmt = $pdo->prepare("SELECT valeur FROM parametre WHERE cle = 'seuil_similarite'");
    $stmt->execute();
    $valeur = $stmt->fetchColumn();
    return $valeur !== false ? (float) $valeur : 30.0;
}

/**
 * Calcule un taux de similarité lexicale (indice de Jaccard sur les mots) entre deux textes.
 * Limite assumée (cf. Conclusion du mémoire) : comparaison lexicale, non sémantique.
 */
function calculer_taux_similarite(string $texteA, string $texteB): float
{
    $motsA = extraire_mots($texteA);
    $motsB = extraire_mots($texteB);

    if (empty($motsA) || empty($motsB)) {
        return 0.0;
    }

    $intersection = count(array_intersect($motsA, $motsB));
    $union = count(array_unique(array_merge($motsA, $motsB)));

    if ($union === 0) {
        return 0.0;
    }

    return round(($intersection / $union) * 100, 2);
}

function extraire_mots(string $texte): array
{
    $motsVides = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'en', 'à', 'au', 'aux',
        'dans', 'pour', 'par', 'sur', 'ce', 'ces', 'cet', 'cette', 'est', 'sont', 'qui', 'que',
        'se', 'sa', 'son', 'ses', 'avec', 'ou', 'il', 'elle', 'ils', 'elles', 'nous', 'vous',
        'plus', 'sans', 'ne', 'pas', 'the', 'and', 'of', 'to', 'a', 'in', 'for'];

    $texte = mb_strtolower($texte, 'UTF-8');
    $texte = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texte);
    $mots = preg_split('/\s+/u', trim($texte));
    $mots = array_filter($mots, function ($mot) use ($motsVides) {
        return mb_strlen($mot) > 2 && !in_array($mot, $motsVides, true);
    });

    return array_values(array_unique($mots));
}

function formater_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y à H:i', $ts) : '-';
}

function rediriger(string $chemin): void
{
    header("Location: " . BASE_URL . $chemin);
    exit;
}
