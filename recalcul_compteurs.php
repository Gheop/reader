<?php
/**
 * Recalcul des compteurs non lus
 *
 * Ce script recalcule les compteurs unread_count_user_1 et unread_count_user_2
 * dans la table reader_flux en comptant les articles réellement présents dans reader_unread_cache
 *
 * Usage: php recalcul_compteurs.php
 */

echo "=== Recalcul des compteurs d'articles non lus ===\n\n";

include(__DIR__ . '/conf.php');

if (!isset($_SESSION['mysqli'])) {
    die("Erreur: Connexion MySQL non disponible\n");
}

$mysqli = $_SESSION['mysqli'];

// Désactiver l'autocommit pour faire les opérations en transaction
$mysqli->autocommit(false);

try {
    echo "1. Lecture des compteurs actuels...\n";

    // Sauvegarder l'état actuel
    $result = $mysqli->query("
        SELECT
            id,
            title,
            unread_count_user_1,
            unread_count_user_2
        FROM reader_flux
        ORDER BY id
    ");

    $feeds = [];
    while ($row = $result->fetch_assoc()) {
        $feeds[$row['id']] = $row;
    }

    echo "   Trouvé " . count($feeds) . " flux\n\n";

    echo "2. Recalcul des compteurs depuis reader_unread_cache...\n";

    // Recalculer pour chaque flux
    $updates = 0;
    $corrections = 0;

    foreach ($feeds as $feedId => $feed) {
        // Compter les articles réels dans le cache pour user 1
        $result1 = $mysqli->query("
            SELECT COUNT(*) as count
            FROM reader_unread_cache
            WHERE id_flux = $feedId AND id_user = 1
        ");
        $count1 = $result1->fetch_assoc()['count'];

        // Compter les articles réels dans le cache pour user 2
        $result2 = $mysqli->query("
            SELECT COUNT(*) as count
            FROM reader_unread_cache
            WHERE id_flux = $feedId AND id_user = 2
        ");
        $count2 = $result2->fetch_assoc()['count'];

        // Vérifier si correction nécessaire
        $needsUpdate = false;
        if ($count1 != $feed['unread_count_user_1'] || $count2 != $feed['unread_count_user_2']) {
            $needsUpdate = true;
            $corrections++;

            echo "   [CORRECTION] Flux #{$feedId} - {$feed['title']}\n";
            echo "     User 1: {$feed['unread_count_user_1']} -> $count1\n";
            echo "     User 2: {$feed['unread_count_user_2']} -> $count2\n";
        }

        // Mettre à jour
        if ($needsUpdate) {
            $stmt = $mysqli->prepare("
                UPDATE reader_flux
                SET unread_count_user_1 = ?,
                    unread_count_user_2 = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iii", $count1, $count2, $feedId);
            $stmt->execute();
            $updates++;
        }
    }

    echo "\n3. Vérification des flux sans articles...\n";

    // Réinitialiser les compteurs des flux qui n'ont plus d'articles
    $result = $mysqli->query("
        SELECT id, title, unread_count_user_1, unread_count_user_2
        FROM reader_flux
        WHERE (unread_count_user_1 > 0 OR unread_count_user_2 > 0)
        AND id NOT IN (SELECT DISTINCT id_flux FROM reader_unread_cache)
    ");

    $emptyFeeds = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['unread_count_user_1'] > 0 || $row['unread_count_user_2'] > 0) {
            echo "   [RESET] Flux #{$row['id']} - {$row['title']}\n";
            echo "     Compteurs: {$row['unread_count_user_1']}/{$row['unread_count_user_2']} -> 0/0\n";

            $mysqli->query("
                UPDATE reader_flux
                SET unread_count_user_1 = 0, unread_count_user_2 = 0
                WHERE id = {$row['id']}
            ");
            $emptyFeeds++;
            $corrections++;
        }
    }

    echo "\n4. Validation et commit...\n";

    // Vérifier l'intégrité
    $check = $mysqli->query("
        SELECT
            (SELECT SUM(unread_count_user_1) FROM reader_flux) as total_user1,
            (SELECT SUM(unread_count_user_2) FROM reader_flux) as total_user2,
            (SELECT COUNT(*) FROM reader_unread_cache WHERE id_user = 1) as cache_user1,
            (SELECT COUNT(*) FROM reader_unread_cache WHERE id_user = 2) as cache_user2
    ");

    $integrity = $check->fetch_assoc();

    echo "   Totaux user 1: {$integrity['total_user1']} (compteurs) = {$integrity['cache_user1']} (cache)\n";
    echo "   Totaux user 2: {$integrity['total_user2']} (compteurs) = {$integrity['cache_user2']} (cache)\n";

    if ($integrity['total_user1'] == $integrity['cache_user1'] &&
        $integrity['total_user2'] == $integrity['cache_user2']) {
        echo "   ✓ Intégrité vérifiée!\n\n";
        $mysqli->commit();
        echo "=== Recalcul terminé avec succès ===\n";
        echo "Flux mis à jour: $updates\n";
        echo "Corrections appliquées: $corrections\n";
        echo "Flux réinitialisés: $emptyFeeds\n";
    } else {
        echo "   ✗ ERREUR: Les totaux ne correspondent pas!\n";
        echo "   Annulation des modifications...\n";
        $mysqli->rollback();
        exit(1);
    }

} catch (Exception $e) {
    echo "\nERREUR: " . $e->getMessage() . "\n";
    echo "Annulation des modifications...\n";
    $mysqli->rollback();
    exit(1);
}

// Réactiver l'autocommit
$mysqli->autocommit(true);

echo "\nRafraîchissez votre navigateur pour voir les compteurs mis à jour.\n";
