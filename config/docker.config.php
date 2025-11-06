<?php
// phpBB 3.0.x Docker-specific configuration file
// Supports environment variables for cloud deployments (Render, Railway, etc.)

$dbms = 'mysqli';
$dbhost = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?: $_SERVER['DB_HOST'] ?: 'db';
$dbport = getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?: $_SERVER['DB_PORT'] ?: '3306';
$dbname = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?: $_SERVER['DB_NAME'] ?: 'phpbb';
$dbuser = getenv('DB_USER') ?: $_ENV['DB_USER'] ?: $_SERVER['DB_USER'] ?: 'phpbbuser';
$dbpasswd = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?: $_SERVER['DB_PASSWORD'] ?: 'phpbbpass';
$table_prefix = 'phpbb_';
$acm_type = 'file';
$load_extensions = '';

@define('PHPBB_INSTALLED', true);
// @define('DEBUG', true);
// @define('DEBUG_EXTRA', true);
?>