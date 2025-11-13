<?php
/**
 * Rebuild MEMORY cache after server restart
 * This script should be run on startup or periodically
 */

include('/www/conf.php');

echo "Rebuilding reader_unread_cache...\n";

// Check if cache is empty (after server restart)
$result = $_SESSION['mysqli']->query("SELECT COUNT(*) as cnt FROM reader_unread_cache");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    echo "Cache is empty, rebuilding...\n";
    
    // Rebuild for user 1
    $sql1 = "INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
             SELECT 1, i.id_flux, i.id, i.pubdate
             FROM reader_item i
             LEFT JOIN reader_user_item ui ON ui.id_item = i.id AND ui.id_user = 1
             WHERE ui.id IS NULL";
    $_SESSION['mysqli']->query($sql1);
    
    // Rebuild for user 2
    $sql2 = "INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
             SELECT 2, i.id_flux, i.id, i.pubdate
             FROM reader_item i
             LEFT JOIN reader_user_item ui ON ui.id_item = i.id AND ui.id_user = 2
             WHERE ui.id IS NULL";
    $_SESSION['mysqli']->query($sql2);
    
    // Check result
    $result = $_SESSION['mysqli']->query("SELECT id_user, COUNT(*) as cnt FROM reader_unread_cache GROUP BY id_user");
    while ($row = $result->fetch_assoc()) {
        echo "User {$row['id_user']}: {$row['cnt']} unread articles\n";
    }
    
    echo "Cache rebuilt successfully!\n";
} else {
    echo "Cache already populated ({$row['cnt']} entries), skipping rebuild.\n";
}
?>
