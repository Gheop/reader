<?php
/**
 * Fix counter inconsistencies after manual data deletion
 * - Remove orphan entries from reader_unread_cache
 * - Recalculate denormalized counters in reader_flux
 */

include('/www/conf.php');

echo "=== Fixing Reader Counters ===\n\n";

// Step 1: Remove orphan entries from cache
echo "Step 1: Removing orphan entries from reader_unread_cache...\n";

$result = $_SESSION['mysqli']->query("
    DELETE C FROM reader_unread_cache C
    LEFT JOIN reader_item I ON C.id_item = I.id
    WHERE I.id IS NULL
");

$orphansRemoved = $_SESSION['mysqli']->affected_rows;
echo "Removed $orphansRemoved orphan entries.\n\n";

// Step 2: Recalculate counters for all feeds
echo "Step 2: Recalculating denormalized counters...\n";

// Get all feeds
$feeds = $_SESSION['mysqli']->query("SELECT id FROM reader_flux ORDER BY id");

$updated = 0;
while ($feed = $feeds->fetch_assoc()) {
    $feedId = $feed['id'];

    // Count unread for user 1
    $r1 = $_SESSION['mysqli']->query("
        SELECT COUNT(*) as cnt
        FROM reader_unread_cache
        WHERE id_flux = $feedId AND id_user = 1
    ");
    $count1 = $r1->fetch_assoc()['cnt'];

    // Count unread for user 2
    $r2 = $_SESSION['mysqli']->query("
        SELECT COUNT(*) as cnt
        FROM reader_unread_cache
        WHERE id_flux = $feedId AND id_user = 2
    ");
    $count2 = $r2->fetch_assoc()['cnt'];

    // Update flux
    $_SESSION['mysqli']->query("
        UPDATE reader_flux
        SET unread_count_user_1 = $count1,
            unread_count_user_2 = $count2
        WHERE id = $feedId
    ");

    if ($_SESSION['mysqli']->affected_rows > 0) {
        $updated++;
        echo "Feed $feedId: user_1=$count1, user_2=$count2\n";
    }
}

echo "\nUpdated $updated feeds.\n\n";

// Step 3: Verify results
echo "Step 3: Verification...\n";

$verify = $_SESSION['mysqli']->query("
    SELECT
        COUNT(*) as mismatched
    FROM reader_flux F
    LEFT JOIN (
        SELECT id_flux, id_user, COUNT(*) as cnt
        FROM reader_unread_cache
        GROUP BY id_flux, id_user
    ) C1 ON C1.id_flux = F.id AND C1.id_user = 1
    LEFT JOIN (
        SELECT id_flux, id_user, COUNT(*) as cnt
        FROM reader_unread_cache
        GROUP BY id_flux, id_user
    ) C2 ON C2.id_flux = F.id AND C2.id_user = 2
    WHERE F.unread_count_user_1 != COALESCE(C1.cnt, 0)
       OR F.unread_count_user_2 != COALESCE(C2.cnt, 0)
");

$result = $verify->fetch_assoc();
if ($result['mismatched'] == 0) {
    echo "✓ All counters are now consistent!\n";
} else {
    echo "⚠ Still " . $result['mismatched'] . " mismatched counters.\n";
}

echo "\n=== Done ===\n";
?>
