-- ===============================================
-- phpBB Local Dev Adjustments for Docker
-- ===============================================

-- 1. Reset server hostname (no protocol, but include port)
UPDATE phpbb_config
SET config_value = 'localhost:8080'
WHERE config_name = 'server_name';

-- 2. Set the protocol for links
UPDATE phpbb_config
SET config_value = 'http://'
WHERE config_name = 'server_protocol';

-- 3. Set the correct server port for Docker
UPDATE phpbb_config
SET config_value = '8080'
WHERE config_name = 'server_port';

-- 4. Set script path to root (important for redirects)
UPDATE phpbb_config
SET config_value = '/'
WHERE config_name = 'script_path';

-- 5. Force phpBB to use these server variables
UPDATE phpbb_config
SET config_value = 1
WHERE config_name = 'force_server_vars';

-- 6. Clear cookie domain for local testing
UPDATE phpbb_config
SET config_value = ''
WHERE config_name = 'cookie_domain';

-- 7. Reset cookie path
UPDATE phpbb_config
SET config_value = '/'
WHERE config_name = 'cookie_path';

-- 8. Allow cookies over HTTP (important for localhost)
UPDATE phpbb_config
SET config_value = 0
WHERE config_name = 'cookie_secure';

-- 9. Optional: disable session IP check (avoids session issues in Docker)
UPDATE phpbb_config
SET config_value = 0
WHERE config_name = 'ip_check';

-- 10. Optional: disable session browser check (for easier local logins)
UPDATE phpbb_config
SET config_value = 0
WHERE config_name = 'check_browser';
