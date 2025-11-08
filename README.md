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

You can set up the environment in three ways:

**Option A: Pull from live server**
1. Use the pull script to download the latest code and database from your live server:

```pwsh
.\pull-live.ps1 -ServerHost myserver.com -KeyPath ~/.ssh/id_rsa
```

This automatically downloads the phpBB files to `phpbb/` and exports the database to `db_init/001_phpbb_backup.sql`.

**Option B: Copy live server data manually**
1. Copy the live server's phpBB site files into this repo's `phpbb/` folder (FTP/SFTP; copy `/var/www/html`).
2. Export a SQL backup from the live server (phpMyAdmin or export tool) and place the `.sql` file at `db_init/001_phpbb_backup.sql`.
  A placeholder example `db_init/001_phpbb_backup.sql.example` is included — replace it with your real dump (SQL files are tracked via Git LFS).

**Option C: Restore from an existing backup in the repo**
1. List available backups in the `backups/` directory (e.g., `20251106_0102_3.2.11`).
2. Run the restore script:

```pwsh
# Interactive selection:
.\restore.ps1

# Or non-interactive by folder name (e.g. 20251106_0102_3.2.11):
.\restore.ps1 -SnapshotFolder 20251106_0102_3.2.11
```

This will restore both the phpBB files and database from the selected backup.

**Then build and run:**

3. Build and run the stack from the repo root:

```pwsh
Set-Location 'C:\cu-fcchat-docker'
docker compose up --build
# or detached: docker compose up -d --build
```

Note: MariaDB runs SQL files in `db_init/` only on first-volume initialization. If the DB volume exists, import the SQL into the running DB container instead.

## Backup and Restore

Use the included snapshot/restore scripts before upgrades.

Create a snapshot (example):

```pwsh
docker compose up -d   # ensure DB is up
.\snapshot.ps1

# Or with a descriptive label:
.\snapshot.ps1 "3.2.11_clean"
# Creates: backups/20251107_1430_3.2.11_clean/
```

Restore a snapshot (example):

```pwsh
# Interactive selection:
.\restore.ps1

# Or non-interactive by folder name (e.g. 20251106_0102_3.2.11):
.\restore.ps1 -SnapshotFolder 20251106_0102_3.2.11
```

Backups are saved under `backups/` and are tracked in the repository via Git LFS for colleague access. Test restores in a disposable environment.

## Upgrade phpBB guidance

This workflow allows you to safely upgrade a live phpBB installation by testing upgrades locally in Docker, then deploying the upgraded snapshot back to production.

### Full upgrade process

1. **Put board into maintenance mode** on the live server (ACP → General → Board settings)

2. **Pull live server code and database** to local environment
   - Use the pull script for automated download:
     ```pwsh
     .\pull-live.ps1 -ServerHost your-server.com -KeyPath ~/.ssh/id_rsa
     ```
   - Or manually via FTP/SFTP to `phpbb/` and database export to `db_init/001_phpbb_backup.sql`

3. **Build and deploy the Docker container**
   ```pwsh
   docker compose up --build -d
   ```
   - Access at http://localhost:8080 to verify the local copy matches live

4. **Remove custom styles** from code and database
   - Delete custom theme folders from `phpbb/styles/`
   - In the database, remove custom style records (or via ACP if functional)
   - This prevents upgrade conflicts with outdated themes

5. **Take a snapshot** of the clean baseline
   ```pwsh
   .\snapshot.ps1 "live__no_custom_styles"
   ```

6. **Download phpBB full version zip** into `updates/` folder
   - Get the next version from https://www.phpbb.com/downloads/
   - Example: `phpBB-3.0.14.zip`, `phpBB-3.1.12.zip`, etc.

7. **Use upgrade.ps1 to apply the version upgrade**
   ```pwsh
   .\upgrade.ps1
   ```
   - Select the downloaded zip from `updates/`
   - Script extracts, applies upgrade, runs database migrations
   - Test the upgraded forum thoroughly

8. **Once tested and happy, create a new snapshot**
   ```pwsh
   .\snapshot.ps1 "3.0.14"
   ```

9. **Repeat steps 6-8 until up-to-date**
    - Upgrade incrementally through each major/minor version
    - Example path: 3.0.12 → 3.0.14 → 3.1.12 → 3.2.11 → 3.3.x
    - Always snapshot after each successful upgrade

10. **Create new theme** (optional)
    - Install/customize a modern phpBB theme compatible with the final version
    - Test thoroughly and take another snapshot

11. **Use deploy.ps1 to deploy the last snapshot to production**
    ```pwsh
    .\deploy.ps1 -ServerHost your-server.com -KeyPath ~/.ssh/id_rsa
    ```
    - Script creates backups on the live server
    - Uploads and imports the upgraded database
    - Replaces live files with the upgraded snapshot
    - Supports dry-run mode (`-DryRun`) to preview changes
    - Turn off maintenance mode and verify live site

### Rollback strategy

If anything goes wrong during local testing:
- Use `.\restore.ps1` to revert to a previous snapshot

If deployment to production fails:
- The deploy script creates automatic backups in `/root/phpbb_backup_YYYYMMDD_HHMM/` on the live server
- Rollback instructions are displayed in the deploy output

### Important notes

- **Always upgrade incrementally** — jumping multiple major versions can cause database corruption
- **Test each upgrade thoroughly** before proceeding to the next version
- **Keep all snapshots** until the final production deployment is verified
- **Backup your backups** — snapshots are in Git LFS but also keep local copies
- **PHP version compatibility** — this Docker image uses PHP 5.6; upgrade PHP separately if targeting phpBB 3.3+

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
- Local override: `config/docker.config.php` is copied into `phpbb/config.php` by the entrypoint to point phpBB to the DB service (`db`, user `phpbbuser`, password `phpbbpass`) — dev only.

Security: this environment is for local testing. The Dockerfile relaxes apt/security checks to install legacy packages — do not use this image in production or expose it to untrusted networks.

## Custom Styles

Custom phpBB styles are maintained in the `custom-styles/` directory, separate from the volatile `phpbb/` folder. This ensures they are never lost during backups, restores, or upgrades.

**Current styles:**
- `cu-fcchat` - Custom Colchester United theme with Exo 2 and Orbitron fonts

**Syncing custom styles:**
```pwsh
# Sync all custom styles to phpbb/styles/
.\sync-custom-styles.ps1

# Sync specific style
.\sync-custom-styles.ps1 -StyleName "cu-fcchat"
```

The `restore.ps1` script automatically syncs custom styles after restoring a backup.

See `custom-styles/README.md` for detailed documentation.

## Version Control and Milestones

**Git Tagging:**
Use semantic versioning to mark significant milestones:

```pwsh
# Create a milestone tag
.\tag-milestone.ps1 -Version "1.0.0" -Message "Initial production release"

# Create and push to remote
.\tag-milestone.ps1 -Version "1.1.0" -Message "Added modern fonts" -Push
```

**Recommended tagging strategy:**
- `1.0.0` - Initial production deployment
- `1.x.0` - Minor updates (style changes, configuration tweaks)
- `2.0.0` - Major phpBB version upgrades
- `x.x.1` - Hotfixes and patches

**View tags:**
```pwsh
git tag -l                    # List all tags
git show v1.0.0              # Show tag details
```

---

For more detail see `Dockerfile`, `docker-entrypoint.sh`, `docker-compose.yml` and the scripts in the repo.
