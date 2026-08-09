# cu-fcchat-docker

A local Docker replica and maintenance toolkit for **cu-fcchat.com**, built around a legacy phpBB stack.

The repo is designed for a cautious workflow:

> **pull / snapshot → test locally → dry-run → change → verify → keep rollback**

It is not a production container image. Docker is the safe test environment; production changes are performed over SSH by the PowerShell maintenance scripts.

## 🚀 Quick start

### Prerequisites

- Git
- Docker Desktop / Docker Compose
- PowerShell
- OpenSSH client (`ssh` / `scp`)
- SSH key access to the production server for remote workflows — setup is covered below

Clone the repo and start from its root:

```powershell
git clone https://github.com/elneilios/cu-fcchat-docker.git
Set-Location .\cu-fcchat-docker
```

### Set up production SSH access

The production maintenance scripts connect over SSH using a key rather than a password.

If you already have a working production SSH key, you can use it directly with `-KeyPath`.

A typical dedicated key location is:

```powershell
$HOME\.ssh\cu-fcchat-prod
```

To create a new Ed25519 key:

```powershell
ssh-keygen -t ed25519 -f $HOME\.ssh\cu-fcchat-prod -C "cu-fcchat production"
```

The generated **public** key (`cu-fcchat-prod.pub`) must then be added to the production server's authorised SSH keys. Never commit the private key to this repository.

Test the connection before using the maintenance scripts:

```powershell
ssh -i $HOME\.ssh\cu-fcchat-prod root@cu-fcchat.com
```

For more detail, including Docker test-key usage and key rotation, see
[`docs/SSH-KEY-USAGE.md`](docs/SSH-KEY-USAGE.md).
### Create a fresh local copy from production

First stop the local stack if it is running:

```powershell
docker compose down
```

Dry-run the production pull:

```powershell
.\pull-live.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -DryRun
```

Then perform the real pull:

```powershell
.\pull-live.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod
```

Type `PULL` when prompted.

A real pull stages and validates both the database and phpBB filesystem before replacing the local copies. Remote temporary files are cleaned up afterwards.

Build a completely fresh local environment from the pulled data:

```powershell
docker compose down -v
docker compose up --build -d
```

Open:

**http://localhost:8080**

## 🧭 Which script should I use?

| Goal | Script | Changes production? |
|---|---|---:|
| Pull the current live forum into Docker | `pull-live.ps1` | No |
| Save the current local Docker state | `snapshot.ps1` | No |
| Restore a local snapshot | `restore.ps1` | No |
| Test/deploy a snapshot to the Docker target | `deploy-test.ps1` | No |
| Deploy a known snapshot to a remote server | `deploy.ps1` | **Yes** |
| Upgrade phpBB 3.3.x | `upgrade.ps1` | Docker or **Yes**, depending on target |
| Copy maintained custom styles into `phpbb/styles/` | `sync-custom-styles.ps1` | No |
| Create a Git milestone tag | `tag-milestone.ps1` | Git only |

For production work, see [`docs/ADMIN-RUNBOOK.md`](docs/ADMIN-RUNBOOK.md).

## 🐳 Local stack

The local environment mirrors the important parts of the production stack:

- PHP 7.2 + Apache
- MySQL 5.6
- phpBB source bind-mounted at `/var/www/html`
- persistent named volumes for uploads, cache/store runtime data, and MySQL
- production-compatible `latin1` / `latin1_swedish_ci` server defaults

Local ports are deliberately loopback-only:

| Service | Address |
|---|---|
| phpBB | `http://127.0.0.1:8080` |
| SSH test target | `127.0.0.1:2222` |
| MySQL | not published to the host |

The Docker SSH service is for deployment testing. It uses key authentication; password authentication is disabled.

## 📸 Snapshots and restore

Create a snapshot while the local stack is running:

```powershell
.\snapshot.ps1 'before_change'
```

A snapshot contains:

```text
backups/<timestamp>_<label>/
├── phpbb_db.sql
└── phpbb_files/
```

`snapshot.ps1` reads the effective Docker phpBB DB configuration, uses a temporary MySQL option file rather than exposing the password in the command line, and handles `mysqldump` clients with or without `--no-tablespaces`.

Preview a restore:

```powershell
.\restore.ps1 -SnapshotFolder '<snapshot-folder>' -DryRun
```

Restore:

```powershell
.\restore.ps1 -SnapshotFolder '<snapshot-folder>'
```

Type `RESTORE` when prompted.

A real restore deliberately rebuilds the local Docker state from the snapshot, including a fresh MySQL volume. Snapshot file/store data is restored where applicable, while cache and active login/session state are treated as disposable runtime data.

> Snapshots under `backups/` are local recovery artifacts and are ignored by Git.

## ⬇️ Pulling production to local

`pull-live.ps1` is the normal way to refresh the Docker replica.

Safety features include:

- `-DryRun`
- SSH `BatchMode`
- remote DB credentials read by PHP from the live `config.php`
- temporary mode-600 MySQL option file
- portable `mysqldump` handling
- complete staging before local replacement
- validation of downloaded DB/files
- cleanup of remote `/tmp` artifacts
- refusal to replace the local bind-mounted phpBB tree while the local `phpbb` container is running

The real operation requires the explicit `PULL` confirmation.

## ⬆️ phpBB 3.3.x upgrades

The current upgrade engine is intentionally restricted to the **phpBB 3.3.x** branch.

Put the official full phpBB ZIP in `updates/`, for example:

```text
updates/phpBB-3.3.17.zip
```

### Docker example

```powershell
.\upgrade.ps1 `
  -Target Docker `
  -Package .\updates\phpBB-3.3.17.zip `
  -ExpectedSourceVersion 3.3.15
```

### Production dry-run

Put the board into maintenance mode first, then:

```powershell
.\upgrade.ps1 `
  -Target Remote `
  -Package .\updates\phpBB-3.3.17.zip `
  -ExpectedSourceVersion 3.3.15 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -DryRun
```

Run without `-DryRun` only after the dry-run and local testing pass.

The remote engine creates a rollback backup under:

```text
/root/phpbb_upgrade_backup_<timestamp>
```

It also supports explicit rollback validation and execution with `-Rollback`.

### What about phpBB 4.x?

Do **not** simply loosen the package-version check.

A future major-version migration must first be reviewed against phpBB's official migration requirements, including PHP/database compatibility, extensions, and the custom style. Only then should `upgrade.ps1` be deliberately extended and tested against a production snapshot in Docker.

## 🚚 Snapshot deployment

`deploy.ps1` deploys a known snapshot to a remote phpBB target.

Always dry-run production first:

```powershell
.\deploy.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -SnapshotFolder '<snapshot-folder>' `
  -DryRun
```

For a real production deployment:

1. put the board into maintenance mode;
2. run the dry-run;
3. run the real command;
4. type `DEPLOY`;
5. smoke-test the site and ACP before re-enabling the board.

The deployment engine:

- stages and SHA-256 verifies uploaded artifacts;
- backs up the live DB/files before modification;
- preserves the live `config.php`;
- preserves target-specific server/cookie/maintenance configuration;
- preserves target ownership/modes for writable phpBB directories;
- clears imported snapshot sessions;
- automatically rolls back on failure unless explicitly disabled;
- retains the rollback backup under `/root/phpbb_deploy_backup_<timestamp>`.

Use `deploy-test.ps1` to exercise the same path against the Docker SSH target before trusting a deployment change.

## 🎨 Custom styles

The maintained project style lives outside the volatile runtime copy:

```text
custom-styles/cu-fcchat/
```

Sync it into the local phpBB tree with:

```powershell
.\sync-custom-styles.ps1
```

This keeps the maintained style separate from phpBB files pulled from production or replaced by upgrade/restore operations.

## 📁 Repository layout

```text
.
├── Dockerfile
├── docker-compose.yml
├── docker-entrypoint.sh
├── config/
│   └── docker.config.php
├── custom-styles/
├── db_init/
├── phpbb/
├── updates/
├── pull-live.ps1
├── snapshot.ps1
├── restore.ps1
├── deploy.ps1
├── deploy-test.ps1
├── upgrade.ps1
├── sync-custom-styles.ps1
└── docs/
```

Runtime/sensitive data such as `phpbb/config.php`, the production DB init dump, snapshots, SSH keys, and logs are excluded from Git.

Text files use LF line endings through `.gitattributes`, including on Windows checkouts.

## 🔐 Safety notes

- Never commit production credentials or private SSH keys.
- Treat a credential that has ever appeared in Git history as exposed and rotate it; removing the current file is not enough.
- Do not use `-NoHostKeyCheck` against production.
- Do not use `-AllowEnabledBoard` to bypass maintenance mode on production.
- Do not use `-NoRollback` for routine production deployments.
- Keep production rollback backups until the change has been verified.
- Major phpBB upgrades require a fresh compatibility review.

See [`docs/SECURITY.md`](docs/SECURITY.md) and [`docs/SSH-KEY-USAGE.md`](docs/SSH-KEY-USAGE.md).

## 📚 Documentation

- [`docs/ADMIN-RUNBOOK.md`](docs/ADMIN-RUNBOOK.md) — production/local operating procedures
- [`docs/WORKFLOW.md`](docs/WORKFLOW.md) — decision guide and workflow diagrams
- [`docs/SSH-KEY-USAGE.md`](docs/SSH-KEY-USAGE.md) — production and Docker SSH usage
- [`docs/SECURITY.md`](docs/SECURITY.md) — repository security and secret-handling notes
