#!/usr/bin/env php
<?php
/**
 * STEP 5 ANALYSIS: Refactor hardcoded user columns
 */

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              STEP 5: REFACTOR USER COLUMNS                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "CURRENT PROBLEM:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "reader_flux has hardcoded columns:\n";
echo "  - unread_count_user_1 INT\n";
echo "  - unread_count_user_2 INT\n\n";

echo "This design:\n";
echo "  ✗ Doesn't scale beyond 2 users\n";
echo "  ✗ Requires conditional queries based on user_id\n";
echo "  ✗ Requires ALTER TABLE to add a 3rd user\n";
echo "  ✗ Wastes space for feeds only subscribed by 1 user\n\n";

echo "PROPOSED SOLUTION:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "New table: reader_flux_user_stats\n";
echo "  - id_user INT NOT NULL\n";
echo "  - id_flux SMALLINT UNSIGNED NOT NULL\n";
echo "  - unread_count INT NOT NULL DEFAULT 0\n";
echo "  - PRIMARY KEY (id_user, id_flux)\n\n";

echo "Benefits:\n";
echo "  ✓ Scales to unlimited users\n";
echo "  ✓ No conditional queries\n";
echo "  ✓ Cleaner schema\n";
echo "  ✓ No wasted space\n\n";

echo "QUERY CHANGES:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "BEFORE (menu query):\n";
echo "  SELECT F.id, F.title, F.unread_count_user_1 as n\n";
echo "  FROM reader_flux F\n";
echo "  WHERE F.unread_count_user_1 > 0\n\n";

echo "AFTER (menu query):\n";
echo "  SELECT F.id, F.title, S.unread_count as n\n";
echo "  FROM reader_flux F\n";
echo "  INNER JOIN reader_flux_user_stats S ON S.id_flux = F.id\n";
echo "  WHERE S.id_user = 1 AND S.unread_count > 0\n\n";

echo "PERFORMANCE IMPACT:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Potential concerns:\n";
echo "  - Menu query now requires a JOIN (was direct column access)\n";
echo "  - Additional index lookups\n\n";

echo "Mitigations:\n";
echo "  - PRIMARY KEY (id_user, id_flux) = very fast lookup\n";
echo "  - reader_flux_user_stats is small (only subscribed feeds)\n";
echo "  - Index on id_user makes WHERE S.id_user = 1 very fast\n\n";

echo "Expected outcome: Minimal performance impact (< 10%)\n\n";

echo "MIGRATION STEPS:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1. Create reader_flux_user_stats table\n";
echo "2. Populate with data from unread_count_user_1 and unread_count_user_2\n";
echo "3. Update api.php menu query to use new table\n";
echo "4. Update read.php counter updates to use new table\n";
echo "5. Benchmark to verify performance\n";
echo "6. Drop old columns (optional - can keep for rollback safety)\n\n";
?>
