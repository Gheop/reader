-- ============================================================================
-- OPTIMIZATION 1: Convert reader_unread_cache from MEMORY to InnoDB
-- ============================================================================
--
-- Why: MEMORY engine loses all data on server restart
-- Solution: InnoDB with ROW_FORMAT=COMPRESSED for good performance + persistence
--

-- Create backup table first
CREATE TABLE reader_unread_cache_backup LIKE reader_unread_cache;
INSERT INTO reader_unread_cache_backup SELECT * FROM reader_unread_cache;

-- Convert to InnoDB with compression
ALTER TABLE reader_unread_cache
    ENGINE=InnoDB
    ROW_FORMAT=COMPRESSED
    KEY_BLOCK_SIZE=8;

-- Verify data is still there
SELECT
    'reader_unread_cache' as table_name,
    ENGINE,
    ROW_FORMAT,
    TABLE_ROWS as rows,
    ROUND(DATA_LENGTH / 1024, 2) as data_kb,
    ROUND(INDEX_LENGTH / 1024, 2) as index_kb
FROM information_schema.TABLES
WHERE TABLE_NAME = 'reader_unread_cache'
  AND TABLE_SCHEMA = 'gheop';

-- ============================================================================
-- OPTIMIZATION 2: Remove duplicate index on reader_user_item
-- ============================================================================
--
-- Current state:
--   - reader_user_item_id_user_IDX (id_user, id_item) -- UNIQUE
--   - itemuserdateIX (id_item, id_user, date)         -- UNIQUE
--
-- Problem: First index is redundant, second covers same columns + date
-- Solution: Drop first index, keep second (more complete)
--

ALTER TABLE reader_user_item DROP INDEX reader_user_item_id_user_IDX;

-- Verify indexes
SELECT
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
    NON_UNIQUE,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_NAME = 'reader_user_item'
  AND TABLE_SCHEMA = 'gheop'
  AND INDEX_NAME != 'PRIMARY'
GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE;

-- Show stats
SELECT
    'OPTIMIZATION COMPLETED' as status,
    NOW() as timestamp;
