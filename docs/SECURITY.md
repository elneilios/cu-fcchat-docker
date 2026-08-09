# Security

This repository contains operational tooling for a real phpBB installation. Treat changes to remote scripts as production infrastructure changes.

## Secrets

The repository must not contain:

- `phpbb/config.php` from production;
- production database dumps;
- local snapshots;
- SSH keys;
- generated logs containing operational details.

The relevant runtime paths are excluded by `.gitignore`.

A file being deleted or ignored **does not remove secrets from Git history**. If a password/key was ever committed, rotate the credential even after cleaning the current tree.

## Local Docker exposure

The web and SSH test ports are bound to loopback only:

```text
127.0.0.1:8080
127.0.0.1:2222
```

MySQL is not published to the host.

Do not broaden those bindings unless there is a deliberate need and the security impact is understood.

## SSH

Remote scripts are designed around SSH keys.

- keep host-key checking enabled for production;
- use `-NoHostKeyCheck` only for disposable test targets;
- never commit private keys;
- Docker password authentication is disabled.

## Production safety controls

Production-changing workflows should retain:

- phpBB maintenance mode;
- dry-run first;
- explicit `DEPLOY` / upgrade confirmation where applicable;
- automatic rollback;
- retained rollback backup;
- browser/ACP verification before re-enabling the board.

Avoid production use of:

```text
-AllowEnabledBoard
-NoRollback
-NoHostKeyCheck
```

unless there is a specific, reviewed recovery reason.

## Database credentials

The hardened remote workflows read database settings from phpBB `config.php` on the target and use temporary MySQL option files rather than embedding the password in a `mysql` or `mysqldump` command line.

Temporary credential files are removed by the remote helper cleanup path.

## phpBB major versions

The current upgrade engine is deliberately limited to phpBB 3.3.x packages.

A future phpBB 4.x migration should be treated as a new compatibility exercise. Review at least:

- required PHP version;
- supported database versions;
- official phpBB migration procedure;
- installed extensions;
- custom styles/templates;
- production host dependencies.

Test the migration against a fresh production snapshot in Docker before enabling the new version in `upgrade.ps1`.

## Out-of-scope infrastructure

These scripts do not replace general production hardening.

Operating-system lifecycle, TLS/HTTPS configuration, firewall policy, server patching, database service hardening, and off-host backups should be reviewed separately from the phpBB maintenance workflow.
