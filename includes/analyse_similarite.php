<?php
// includes/analyse_similarite.php - M03 : Analyse automatique des contenus (UC3.1)
// Se déclenche automatiquement à chaque dépôt (extend de UC1.1)

require_once __DIR__ . '/functions.php';

/**
 * Compare un mémoire nouvellement déposé au corpus déjà archivé et enregistre
 * un rapport de similarité pour chaque comparaison. Retourne un résumé de
 * l'analyse (taux maximum observé et déclenchement ou non de l'alerte).
 */
function analyserMemoire(PDO $pdo, int $idMemoire): array
{
    $stmt = $pdo->prepare(
        "SELECT titre, resume, mots_cles FROM memoire WHERE id_memoire = :id"
    );
    $stmt->execute([':id' => $idMemoire]);
    $memoire = $stmt->fetch();

    if (!$memoire) {
        return ['nb_compares' => 0, 'taux_max' => 0.0, 'alerte' => false];
    }

    $texteMemoire = $memoire['titre'] . ' ' . $memoire['resume'] . ' ' . $memoire['mots_cles'];

    // Corpus de référence : tous les autres mémoires non retirés (§2.4.2)
    $stmt = $pdo->prepare(
        "SELECT id_memoire, titre, resume, mots_cles FROM memoire
         WHERE id_memoire != :id AND statut != 'RETIRE'"
    );
    $stmt->execute([':id' => $idMemoire]);
    $corpus = $stmt->fetchAll();

    $tauxMax = 0.0;
    $insert = $pdo->prepare(
        "INSERT INTO rapport_similarite (taux_similarite, id_memoire, id_memoire_compare)
         VALUES (:taux, :id_memoire, :id_memoire_compare)"
    );

    foreach ($corpus as $reference) {
        $texteReference = $reference['titre'] . ' ' . $reference['resume'] . ' ' . $reference['mots_cles'];
        $taux = calculer_taux_similarite($texteMemoire, $texteReference);

        $insert->execute([
            ':taux' => $taux,
            ':id_memoire' => $idMemoire,
            ':id_memoire_compare' => $reference['id_memoire'],
        ]);

        if ($taux > $tauxMax) {
            $tauxMax = $taux;
        }
    }

    $seuil = get_seuil_alerte($pdo);
    $alerte = $tauxMax >= $seuil;

    journaliser($pdo, null, "Analyse automatique de similarité — mémoire #{$idMemoire} (taux max : {$tauxMax}%)"
        . ($alerte ? " — SEUIL D'ALERTE DÉPASSÉ" : ""));

    return [
        'nb_compares' => count($corpus),
        'taux_max' => $tauxMax,
        'alerte' => $alerte,
        'seuil' => $seuil,
    ];
}
