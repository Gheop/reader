-- Migration: Expand password column to accommodate bcrypt hashes
-- Date: 2025-11-28
-- Issue: pwd column was VARCHAR(40), but bcrypt hashes are 60 characters
--        This caused password hashes to be truncated, preventing login
--
-- Solution: Expand pwd column to VARCHAR(255) to accommodate bcrypt and future hash algorithms

ALTER TABLE users MODIFY COLUMN pwd VARCHAR(255);

-- Note: After running this migration, users with truncated password hashes
--       will need to reset their passwords using reset_password.php
