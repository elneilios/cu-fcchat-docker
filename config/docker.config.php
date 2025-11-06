<?php
// phpBB 3.0.x configuration file
// For local: uses 'db' hostname
// For Railway: this file is overwritten by phpbb/config.php during build

$dbms = 'mysqli';
$dbhost = 'db';
$dbport = '3306';
$dbname = 'phpbb';
$dbuser = 'phpbbuser';
$dbpasswd = 'phpbbpass';
$table_prefix = 'phpbb_';
$acm_type = 'file';
$load_extensions = '';

@define('PHPBB_INSTALLED', true);
// @define('DEBUG', true);
// @define('DEBUG_EXTRA', true);
?>