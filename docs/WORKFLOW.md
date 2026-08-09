# Workflows

## Decision guide

| I need to… | Use |
|---|---|
| refresh Docker from the current live site | `pull-live.ps1` |
| checkpoint the current local Docker state | `snapshot.ps1` |
| return local Docker to a previous checkpoint | `restore.ps1` |
| test a deployment against Docker over SSH | `deploy-test.ps1` |
| deploy a known snapshot to a remote target | `deploy.ps1` |
| update phpBB within the validated 3.3.x branch | `upgrade.ps1` |
| refresh the custom style in the local phpBB tree | `sync-custom-styles.ps1` |

## Normal local refresh

```mermaid
flowchart LR
    A[Production] -->|pull-live.ps1| B[Staged local files + DB]
    B --> C[docker compose down -v]
    C --> D[Fresh Docker build]
    D --> E[Browser smoke test]
    E --> F[snapshot.ps1]
```

## Safe phpBB upgrade

```mermaid
flowchart TD
    A[Pull current production] --> B[Fresh local Docker clone]
    B --> C[Create baseline snapshot]
    C --> D[upgrade.ps1 -Target Docker]
    D --> E{Local tests pass?}
    E -- No --> F[restore.ps1]
    F --> D
    E -- Yes --> G[Create upgraded snapshot]
    G --> H[Put production in maintenance mode]
    H --> I[upgrade.ps1 -Target Remote -DryRun]
    I --> J{Dry-run clean?}
    J -- No --> K[Fix/test locally]
    K --> I
    J -- Yes --> L[Remote upgrade]
    L --> M[Browser + ACP smoke test]
    M --> N[Re-enable board]
```

## Snapshot deployment

```mermaid
flowchart TD
    A[Known-good snapshot] --> B[deploy-test.ps1]
    B --> C{Docker deployment clean?}
    C -- No --> D[Fix deploy tooling]
    D --> B
    C -- Yes --> E[Production maintenance mode]
    E --> F[deploy.ps1 -DryRun]
    F --> G{Pre-flight clean?}
    G -- No --> H[Stop and investigate]
    G -- Yes --> I[deploy.ps1]
    I --> J[Target backup]
    J --> K[DB/files replacement]
    K --> L{Post-deploy validation}
    L -- Failure --> M[Automatic rollback]
    L -- Success --> N[Browser + ACP smoke test]
    N --> O[Re-enable board]
```

## What belongs in Git?

```text
Tracked:
  tooling
  Docker configuration
  maintained source/custom styles
  docs
  approved update packages

Local/ignored:
  production config.php
  production DB init dump
  snapshots
  SSH private/public test keys
  logs
  phpBB cache/store/upload runtime data
```

## Production-changing vs read-only operations

### Read/stage only

```text
pull-live.ps1
deploy.ps1 -DryRun
upgrade.ps1 ... -DryRun
```

These still connect to production and may create short-lived temporary files under `/tmp`, but they do not intentionally change the live phpBB application/database state.

### Production-changing

```text
deploy.ps1
upgrade.ps1 -Target Remote
upgrade.ps1 ... -Rollback   # when not a dry-run
```

Require the production safety checklist in `ADMIN-RUNBOOK.md`.
