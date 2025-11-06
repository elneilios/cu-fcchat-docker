-- Fix MySQL 8.0 charset compatibility with PHP 5.6
-- Run this after importing the database

-- Change database default charset to utf8 (not utf8mb4)
ALTER DATABASE railway CHARACTER SET utf8 COLLATE utf8_general_ci;

-- Change all tables to use utf8
-- This script will be generated dynamically, but here's the pattern:
SET @DATABASE_NAME = 'railway';

SELECT CONCAT('ALTER TABLE `', table_name, '` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;')
FROM information_schema.TABLES
WHERE table_schema = @DATABASE_NAME
AND table_type = 'BASE TABLE';

-- Note: You'll need to run the output of the above SELECT as SQL commands
-- Or use this procedure to do it automatically:

DELIMITER $$

DROP PROCEDURE IF EXISTS convert_all_tables_to_utf8$$
CREATE PROCEDURE convert_all_tables_to_utf8()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tbl_name VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT table_name 
        FROM information_schema.TABLES 
        WHERE table_schema = 'railway' 
        AND table_type = 'BASE TABLE';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO tbl_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

-- Run the procedure
CALL convert_all_tables_to_utf8();

-- Clean up
DROP PROCEDURE IF EXISTS convert_all_tables_to_utf8;

-- Verify the changes
SELECT table_name, table_collation 
FROM information_schema.TABLES 
WHERE table_schema = 'railway' 
AND table_type = 'BASE TABLE';
