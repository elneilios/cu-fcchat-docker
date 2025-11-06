<?php
// phpBB 3.0.x Docker-specific configuration file
$dbms = 'mysqli';
$dbhost = 'db';           // Docker service name
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