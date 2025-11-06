-- Set MySQL 8.0 to use utf8 (not utf8mb4) for PHP 5.6 compatibility
-- This should be run once after database creation

SET GLOBAL character_set_server = utf8;
SET GLOBAL collation_server = utf8_general_ci;

-- Show current settings
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';
