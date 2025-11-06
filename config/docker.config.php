<?php
// phpBB 3.0.x Docker-specific configuration file
// Supports environment variables for cloud deployments (Render, Railway, etc.)

$dbms = 'mysqli';
$dbhost = getenv('DB_HOST') ?: 'db';                    // Docker service name or external DB host
$dbport = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'phpbb';
$dbuser = getenv('DB_USER') ?: 'phpbbuser';
$dbpasswd = getenv('DB_PASSWORD') ?: 'phpbbpass';
$table_prefix = 'phpbb_';
$acm_type = 'file';
$load_extensions = '';

@define('PHPBB_INSTALLED', true);
// @define('DEBUG', true);
// @define('DEBUG_EXTRA', true);
?>