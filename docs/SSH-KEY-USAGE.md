# SSH key usage

The remote maintenance scripts use OpenSSH (`ssh` and `scp`).

## Production key

Use a dedicated local key file where practical, for example:

```powershell
$HOME\.ssh\cu-fcchat-prod
```

Pass it explicitly:

```powershell
.\pull-live.ps1 `
  -ServerHost cu-fcchat.com `
  -KeyPath $HOME\.ssh\cu-fcchat-prod `
  -DryRun
```

The same `-KeyPath` pattern is used by `deploy.ps1` and remote `upgrade.ps1`.

Never put a private key in the repo.

## Test the connection directly

```powershell
ssh -i $HOME\.ssh\cu-fcchat-prod root@cu-fcchat.com
```

The scripts use SSH `BatchMode` so an unexpected password prompt becomes a failure rather than an unattended hang.

## Docker SSH target

The local phpBB container exposes SSH only on:

```text
127.0.0.1:2222
```

`deploy-test.ps1` is the wrapper for this target. It creates/injects a test key into the container when required and then calls the real `deploy.ps1`.

The generated local `.ssh/` folder is ignored by Git.

Example:

```powershell
.\deploy-test.ps1 -DryRun
```

For an intentional real Docker deployment test:

```powershell
.\deploy-test.ps1 -AllowEnabledBoard
```

The `-AllowEnabledBoard` switch is for isolated testing; production should be put into phpBB maintenance mode instead.

## Host-key checking

Normal production use should retain SSH host-key checking.

`-NoHostKeyCheck` exists for disposable test targets where host keys change frequently. Do not make it the default for production.

If a production host key unexpectedly changes, investigate the change instead of suppressing the warning.

## Docker authentication

The container SSH daemon is configured for key-based root access:

```text
PermitRootLogin prohibit-password
PasswordAuthentication no
```

There is no Docker root password workflow to maintain.

## Key rotation

If a production private key is lost, copied to an untrusted machine, or otherwise suspected to be exposed:

1. generate a replacement;
2. add the new public key to the target;
3. verify the new key works;
4. remove the old public key from `authorized_keys`;
5. remove the old private key from local systems/backups where appropriate.

Never attempt to solve a key problem by committing a key to Git or enabling password SSH.
