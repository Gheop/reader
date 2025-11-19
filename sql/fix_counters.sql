-- Fix Unread Counters
-- This script recalculates all unread counters from the cache and cleans orphaned entries

-- Clean orphaned cache entries (feeds not subscribed)
DELETE C FROM reader_unread_cache C
LEFT JOIN reader_user_flux UF ON UF.id_user = C.id_user AND UF.id_flux = C.id_flux
WHERE UF.id_flux IS NULL;

-- Reset all counters to 0 first
UPDATE reader_flux SET unread_count_user_1 = 0, unread_count_user_2 = 0;

-- Recalculate counters from cache for user 1
UPDATE reader_flux F
INNER JOIN (
    SELECT id_flux, COUNT(*) as cnt
    FROM reader_unread_cache
    WHERE id_user = 1
    GROUP BY id_flux
) C ON F.id = C.id_flux
SET F.unread_count_user_1 = C.cnt;

-- Recalculate counters from cache for user 2
UPDATE reader_flux F
INNER JOIN (
    SELECT id_flux, COUNT(*) as cnt
    FROM reader_unread_cache
    WHERE id_user = 2
    GROUP BY id_flux
) C ON F.id = C.id_flux
SET F.unread_count_user_2 = C.cnt;

-- Show results
SELECT 'User 1 feeds with unread:' as status, COUNT(*) as count FROM reader_flux WHERE unread_count_user_1 > 0
UNION ALL
SELECT 'User 2 feeds with unread:' as status, COUNT(*) as count FROM reader_flux WHERE unread_count_user_2 > 0
UNION ALL
SELECT 'User 1 total unread articles:' as status, SUM(unread_count_user_1) as count FROM reader_flux
UNION ALL
SELECT 'User 2 total unread articles:' as status, SUM(unread_count_user_2) as count FROM reader_flux;
