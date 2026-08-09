# Admin runbook

Operational notes for maintaining **cu-fcchat.com** with this repository.

The guiding rule is simple:

> **Never make production the first place a workflow is tested.**

## 1. Safety checklist

Before any production-changing operation:

- start from a clean Git working tree;
- confirm the local Docker copy works;
- create/identify a known-good snapshot;
- put phpBB into maintenance mode;
- run the relevant remote `-DryRun`;
- confirm the target host/path/version printed by the script;
- keep the rollback backup until browser/ACP checks pass.

Avoid these production overrides unless there is a specific recovery reason:

```text
-AllowEnabledBoard
-NoRollback
-NoHostKeyCheck
```

## 2. Refresh local Docker from production

Stop the local web container before a real code pull.

Production SSH access should already be configured using the dedicated key described in
[`SSH-KEY-USAGE.md`](SSH-KEY-USAGE.md).

```powershell
docker compose down
```

Dry-run:

```powershell
.\pull-live.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -DryRun
```

Real pull:

```powershell
.\pull-live.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod
```

Type:

```text
PULL
```

Then rebuild from a fresh DB volume:

```powershell
docker compose down -v
docker compose up --build -d
docker compose ps
```

Verify the running phpBB code version:

```powershell
docker exec phpbb php -r 'define("IN_PHPBB", true); $table_prefix="phpbb_"; include "/var/www/html/includes/constants.php"; echo PHPBB_VERSION, PHP_EOL;'
```

Browser smoke test:

```text
http://localhost:8080
```

Check:

- homepage;
- forum/topic navigation;
- login;
- avatars/images/attachments;
- ACP where relevant.

## 3. Create a local snapshot

With Docker running:

```powershell
.\snapshot.ps1 'descriptive_label'
```

Verify the new folder under `backups/` contains:

```text
phpbb_db.sql
phpbb_files/
```

The script validates the dump/tree before reporting success.

Snapshots are ignored by Git. Copy especially important snapshots somewhere else if they are needed beyond the local workstation.

## 4. Restore a local snapshot

Preview:

```powershell
.\restore.ps1 `
  -SnapshotFolder '<snapshot-folder>' `
  -DryRun
```

Real restore:

```powershell
.\restore.ps1 -SnapshotFolder '<snapshot-folder>'
```

Type:

```text
RESTORE
```

A real restore is intentionally destructive to the local Docker state: it removes volumes, restores the snapshot DB/files, initializes a fresh MySQL volume, and repopulates runtime file/store data.

Afterwards:

```powershell
docker compose ps
docker exec phpbb php -r 'define("IN_PHPBB", true); $table_prefix="phpbb_"; include "/var/www/html/includes/constants.php"; echo PHPBB_VERSION, PHP_EOL;'
```

Then perform the browser smoke test.

## 5. Upgrade phpBB 3.3.x locally

Place the official full package under `updates/`.

Example:

```powershell
.\upgrade.ps1 `
  -Target Docker `
  -Package .\updates\phpBB-3.3.17.zip `
  -ExpectedSourceVersion 3.3.15
```

After success:

1. verify the code/DB version;
2. browse/login/test ACP;
3. test the custom style;
4. create a new snapshot.

The current upgrade script intentionally rejects non-3.3.x packages. A future phpBB 4.x migration is a separate compatibility project, not a regex change.

## 6. Upgrade production

### Before starting

- test the same source → target upgrade against the Docker clone;
- put the live board into maintenance mode;
- make sure the production SSH key is available locally.

Dry-run first:

```powershell
.\upgrade.ps1 `
  -Target Remote `
  -Package .\updates\phpBB-3.3.17.zip `
  -ExpectedSourceVersion 3.3.15 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -DryRun
```

If clean, run the same command without `-DryRun`.

The upgrade engine creates:

```text
/root/phpbb_upgrade_backup_<timestamp>
```

Keep that directory after the successful upgrade.

### Explicit rollback validation

Before performing an explicit rollback, dry-run it:

```powershell
.\upgrade.ps1 `
  -Target Remote `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -Rollback /root/phpbb_upgrade_backup_<timestamp> `
  -DryRun
```

Only remove `-DryRun` if rollback is genuinely required.

## 7. Deploy a snapshot

`deploy.ps1` is for deploying a known snapshot, not the normal replacement for `upgrade.ps1`.

Test deployment logic against Docker first:

```powershell
.\deploy-test.ps1 -DryRun
```

A real Docker test may use:

```powershell
.\deploy-test.ps1 -AllowEnabledBoard
```

`-AllowEnabledBoard` exists for isolated test targets. Do not use it as the normal production path.

### Production dry-run

```powershell
.\deploy.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -SnapshotFolder '<snapshot-folder>' `
  -DryRun
```

### Production deployment

With the board disabled, run the command without `-DryRun` and type:

```text
DEPLOY
```

The engine:

1. validates the target;
2. uploads the snapshot and verifies SHA-256 hashes when supported by the target;
3. creates a live rollback backup;
4. imports the snapshot DB (unless skipped);
5. restores target-specific phpBB environment values;
6. clears imported sessions;
7. replaces the phpBB files;
8. reapplies target writable-directory ownership/modes;
9. validates the resulting code/DB versions.

Backup location:

```text
/root/phpbb_deploy_backup_<timestamp>
```

The script prints recovery information for that specific target.

## 8. When a deployment fails

Do not immediately retry.

First read the output. The deployment engine automatically rolls back after a modifying step fails unless `-NoRollback` was deliberately used.

For the Docker target, verify container state:

```powershell
docker compose ps
```

Then confirm the code version:

```powershell
docker exec phpbb php -r 'define("IN_PHPBB", true); $table_prefix="phpbb_"; include "/var/www/html/includes/constants.php"; echo PHPBB_VERSION, PHP_EOL;'
```

Confirm the DB version / a known data count as appropriate, then browser-test.

For a production failure, use the deployment output and retained remote backup to establish whether automatic rollback completed. Verify the live site and relevant version/database state over SSH before retrying anything.

If the retained backup has a manifest, verify it on the target:

```bash
cd /root/phpbb_deploy_backup_<timestamp>
sha256sum -c backup_manifest.sha256
```

Do not delete a rollback backup until the recovered/deployed site is verified.

## 9. Common diagnostics

### Container status

```powershell
docker compose ps
```

### phpBB logs / Apache output

```powershell
docker logs phpbb --tail 100
docker logs phpbb-db --tail 100
```

### phpBB code version

```powershell
docker exec phpbb php -r 'define("IN_PHPBB", true); $table_prefix="phpbb_"; include "/var/www/html/includes/constants.php"; echo PHPBB_VERSION, PHP_EOL;'
```

### Writable directory ownership

If phpBB reports cache/file write failures:

```powershell
docker exec phpbb sh -lc 'ls -ld /var/www/html/cache /var/www/html/files /var/www/html/store /var/www/html/images/avatars/upload 2>&1'
```

The web-server user must be able to write the relevant paths.

### Nested Docker mount points

`cache`, `files`, and `store` can be separate named volumes beneath `/var/www/html`.

Do **not** blindly remove those directory mount points with a blanket `rm -rf /var/www/html/*`. The deployment/restore tooling contains mount-aware handling.

## 10. After a successful production change

- browser-test the homepage, forum pages, login and ACP;
- verify custom style/assets;
- re-enable the board;
- keep the rollback backup for at least several days / until confidence is high;
- commit any tooling/docs changes separately from runtime snapshots or secrets.
