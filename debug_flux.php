<?php
/**
 * Debug flux discrepancy
 * Check what's different between counter and actual articles
 */

include(__DIR__ . '/conf.php');
include(__DIR__ . '/auth.php');

$fluxId = isset($argv[1]) ? (int)$argv[1] : 976;
$userId = isset($argv[2]) ? (int)$argv[2] : 1;

echo "=== Debug Flux #$fluxId pour User #$userId ===\n\n";

$mysqli = $_SESSION['mysqli'];

// 1. Check flux counter
$result = $mysqli->query("
    SELECT id, title, unread_count_user_1, unread_count_user_2
    FROM reader_flux
    WHERE id = $fluxId
");

$flux = $result->fetch_assoc();
$counterColumn = $userId == 1 ? 'unread_count_user_1' : 'unread_count_user_2';

echo "1. Compteur dans reader_flux:\n";
echo "   Flux: {$flux['title']}\n";
echo "   User 1: {$flux['unread_count_user_1']} articles\n";
echo "   User 2: {$flux['unread_count_user_2']} articles\n";
echo "   Current user ($userId): {$flux[$counterColumn]} articles\n\n";

// 2. Check reader_unread_cache
$result = $mysqli->query("
    SELECT COUNT(*) as count
    FROM reader_unread_cache
    WHERE id_flux = $fluxId AND id_user = $userId
");

$cacheCount = $result->fetch_assoc()['count'];

echo "2. Articles dans reader_unread_cache:\n";
echo "   Total: $cacheCount articles\n\n";

// 3. List actual articles in cache
$result = $mysqli->query("
    SELECT C.id_item, I.title, I.link
    FROM reader_unread_cache C
    JOIN reader_item I ON I.id = C.id_item
    WHERE C.id_flux = $fluxId AND C.id_user = $userId
    ORDER BY C.id_item DESC
    LIMIT 20
");

echo "3. Articles dans le cache (max 20):\n";
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "   [$count] Item #{$row['id_item']}: {$row['title']}\n";
}
echo "\n";

// 4. Check what API would return
$limit = 100;
$sql = "
    SELECT
        I.id,
        I.title,
        I.link,
        I.description,
        I.pubdate,
        F.id as feed_id,
        F.title as feed_title
    FROM reader_unread_cache C
    INNER JOIN reader_item I ON I.id = C.id_item
    INNER JOIN reader_flux F ON F.id = C.id_flux
    WHERE C.id_user = $userId
    AND C.id_flux = $fluxId
    ORDER BY I.pubdate DESC
    LIMIT $limit
";

$result = $mysqli->query($sql);
$apiCount = $result->num_rows;

echo "4. Ce que l'API retournerait:\n";
echo "   Articles retournés: $apiCount\n\n";

// 5. Check for orphan entries
$result = $mysqli->query("
    SELECT COUNT(*) as count
    FROM reader_unread_cache C
    LEFT JOIN reader_item I ON I.id = C.id_item
    WHERE C.id_flux = $fluxId
    AND C.id_user = $userId
    AND I.id IS NULL
");

$orphans = $result->fetch_assoc()['count'];

echo "5. Entrées orphelines (cache sans item):\n";
echo "   Orphelins: $orphans\n\n";

// 6. Diagnosis
echo "=== DIAGNOSTIC ===\n";
if ($flux[$counterColumn] == $cacheCount && $cacheCount == $apiCount) {
    echo "✓ Tout est cohérent!\n";
} else {
    echo "✗ INCOHÉRENCE DÉTECTÉE:\n";
    echo "  - Compteur flux: {$flux[$counterColumn]}\n";
    echo "  - Cache count:   $cacheCount\n";
    echo "  - API return:    $apiCount\n";
    echo "  - Orphelins:     $orphans\n\n";

    if ($orphans > 0) {
        echo "  PROBLÈME: $orphans entrées dans le cache pointent vers des items supprimés!\n";
        echo "  Solution: Nettoyer les entrées orphelines.\n";
    }

    if ($flux[$counterColumn] != $cacheCount) {
        echo "  PROBLÈME: Le compteur ne correspond pas au cache.\n";
        echo "  Solution: Relancer recalcul_compteurs.php\n";
    }
}
