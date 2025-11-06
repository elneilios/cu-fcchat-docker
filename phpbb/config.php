<?php
// phpBB 3.0.x configuration file for Railway deployment

$dbms = 'mysqli';
$dbhost = 'cu-fcchat-docker.railway.internal';
$dbport = '3306';
$dbname = 'railway';
$dbuser = 'phpbbuser';
$dbpasswd = 'phpbbpass';
$table_prefix = 'phpbb_';
$acm_type = 'file';
$load_extensions = '';

@define('PHPBB_INSTALLED', true);
// @define('DEBUG', true);
// @define('DEBUG_EXTRA', true);
?>