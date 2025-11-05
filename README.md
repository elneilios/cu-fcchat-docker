# cu-fcchat-docker

Lightweight Docker environment to run and upgrade a legacy phpBB forum (PHP 5.6 + Apache) for local testing.

## Prerequisites

- Fork/clone this repository to your local machine. If you're using GitHub, fork the repo then clone your fork (or clone this repo directly):

```bash
# example (replace with your fork URL or the repo URL)
git clone git@github.com:<your-username>/cu-fcchat-docker.git
cd cu-fcchat-docker
```

- Install Docker Desktop (Windows/macOS) or Docker Engine + Docker Compose (Linux): https://www.docker.com/products/docker-desktop
- Verify:

```pwsh
docker --version
docker compose version   # or: docker-compose --version
```

## Quick Start

1. Copy the live server's phpBB site files into this repo's `phpbb/` folder (FTP/SFTP; copy `/var/www/html`).
2. Export a SQL backup from the live server (phpMyAdmin or export tool) and place the `.sql` file at `db_init/001_phpbb_backup.sql`.
  A placeholder example `db_init/001_phpbb_backup.sql.example` is included — copy your real dump to `db_init/001_phpbb_backup.sql` (the real file is ignored by git).
3. Build and run the stack from the repo root:

```pwsh
Set-Location 'C:\cu-fcchat-docker'
docker compose up --build
# or detached: docker compose up -d --build
```

Note: MariaDB runs SQL files in `db_init/` only on first-volume initialization. If the DB volume exists, import the SQL into the running DB container instead.

## Backup and Restore

Use the included snapshot/restore scripts before upgrades.

- Windows (PowerShell): `snapshot.ps1`, `restore.ps1`
- Linux/macOS (sh): `snapshot.sh`, `restore.sh`

Create a snapshot (example):

```pwsh
docker compose up -d   # ensure DB is up
.\snapshot.ps1
```

Restore a snapshot (example):

```pwsh
.\restore.ps1 -SnapshotFolder <timestamp>
```

Backups are saved under `backups/` (this folder is ignored by git). Test restores in a disposable environment.

## Upgrade phpBB guidance

1. Take a snapshot (DB + files).
2. Update `phpbb/` code or mount a test copy.
 
Use a dedicated git branch for each upgrade (for example `feature/upgrade-phpbb`) so changes are isolated and easy to revert.
3. Run phpBB upgrade steps (ACP or CLI) and verify functionality.
4. Test thoroughly (logins, posts, extensions, themes). If rollback needed, restore the snapshot.

If upgrading PHP itself, perform the migration in a separate staging image and test all extensions/themes.

## Docker container details

- Web: `php` image `php:5.6-apache` (Dockerfile adjusts apt to use Debian archive mirrors to allow old packages).
- DB: `mariadb:10.5` (initialized from `db_init/` on first run).
- PHP extensions installed: gd, mysqli, mbstring, intl, zip, xml (see `Dockerfile`).
- Entrypoint (`docker-entrypoint.sh`) fixes permissions, clears cache/sessions and writes a small php ini for sessions/error logging.
- Volumes (persistent):
  - uploads: `phpbb_data_uploads` → `/var/www/html/files`
  - cache: `phpbb_data_cache` → `/var/www/html/cache`
  - sessions: `phpbb_data_sessions` → `/var/www/html/store`
  - db: `phpbb_db_data` → `/var/lib/mysql`
- Local override: `docker-overrides/config.php` points phpBB to the DB service (`db`, user `phpbbuser`, password `phpbbpass`) — dev only.

Security: this environment is for local testing. The Dockerfile relaxes apt/security checks to install legacy packages — do not use this image in production or expose it to untrusted networks.

---

For more detail see `Dockerfile`, `docker-entrypoint.sh`, `docker-compose.yml` and the scripts in the repo.
