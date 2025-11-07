# SSH Key Authentication Guide

## Overview
This project uses SSH key authentication to securely connect to the production server without passwords.

## Key Locations
- **Private Key**: `~/.ssh/cu-fcchat-prod` (Windows: `C:\Users\<YourUsername>\.ssh\cu-fcchat-prod`)
- **Public Key**: `~/.ssh/cu-fcchat-prod.pub`

⚠️ **IMPORTANT**: Never share your private key! It's like a password.

## Using the SSH Key

### Basic SSH Connection
```powershell
# Connect to the server
ssh -i ~/.ssh/cu-fcchat-prod root@cu-fcchat.com

# Run a single command
ssh -i ~/.ssh/cu-fcchat-prod root@cu-fcchat.com "ls -la /var/www/html"
```

### Using with deploy.ps1
```powershell
# Dry run (recommended first)
.\deploy.ps1 -ServerHost cu-fcchat.com -KeyPath ~/.ssh/cu-fcchat-prod -DryRun -AutoConfirm

# Real deployment
.\deploy.ps1 -ServerHost cu-fcchat.com -KeyPath ~/.ssh/cu-fcchat-prod

# Files-only deployment (skip database)
.\deploy.ps1 -ServerHost cu-fcchat.com -KeyPath ~/.ssh/cu-fcchat-prod -SkipDatabase

# View all options
.\deploy.ps1 -Help
```

## SSH Config (Optional - Makes Commands Shorter)

Create or edit `~/.ssh/config`:
```
Host cu-fcchat
    HostName cu-fcchat.com
    User root
    IdentityFile ~/.ssh/cu-fcchat-prod
    Port 22
```

Then you can simply use:
```powershell
# SSH connection
ssh cu-fcchat

# Deployment
.\deploy.ps1 -ServerHost cu-fcchat
```

## Key Backup & Recovery

### Backup Your Key
**CRITICAL**: Back up your private key to a secure location:
1. Copy `C:\Users\neila\.ssh\cu-fcchat-prod` to an encrypted USB drive or password manager
2. Store in a secure cloud storage (encrypted)
3. Keep a copy on another trusted computer

### Restore Key on Another Computer
1. Copy `cu-fcchat-prod` to `~/.ssh/` on the new computer
2. Set correct permissions:
   ```powershell
   # Windows (in PowerShell)
   icacls $HOME\.ssh\cu-fcchat-prod /inheritance:r /grant:r "$($env:USERNAME):(R)"
   ```

### If You Lose Your Key
If you lose access to your private key:
1. SSH to the server using password authentication (if enabled)
2. Generate a new key pair: `ssh-keygen -t ed25519 -f ~/.ssh/cu-fcchat-prod-new`
3. Run `.\install-ssh-key.ps1` to install the new key
4. Update your deployment scripts to use the new key path

## Troubleshooting

### "Permission denied (publickey)"
- Check key path is correct
- Verify key is installed on server: `cat ~/.ssh/authorized_keys`
- Ensure private key permissions are correct

### "Connection refused"
- Check server is running and accessible
- Verify port number (default: 22)
- Check firewall settings

### Multiple Password Prompts
- Your private key may have a passphrase
- Use `ssh-add ~/.ssh/cu-fcchat-prod` to add it to ssh-agent (once per session)
- Or regenerate key without passphrase for automation

## Security Best Practices

1. ✅ **Never share your private key** (`cu-fcchat-prod`)
2. ✅ **Keep your private key secure** (encrypted backups only)
3. ✅ **Use different keys for different servers** (if managing multiple)
4. ✅ **Regularly rotate keys** (generate new keys every 6-12 months)
5. ✅ **Remove old keys from server** when no longer needed
6. ❌ **Never commit private keys to Git**
7. ❌ **Never email or message private keys**

## Where is the Public Key Stored?

Your public key is installed on the server at:
```
/root/.ssh/authorized_keys
```

To view what keys are authorized on the server:
```powershell
ssh -i ~/.ssh/cu-fcchat-prod root@cu-fcchat.com "cat ~/.ssh/authorized_keys"
```

## Generating New Keys

If you need to generate a new key pair:
```powershell
# Generate new key
ssh-keygen -t ed25519 -f ~/.ssh/cu-fcchat-prod-new -C "cu-fcchat-deployment"

# Install it on the server
.\install-ssh-key.ps1 -KeyPath ~/.ssh/cu-fcchat-prod-new.pub

# Test it
ssh -i ~/.ssh/cu-fcchat-prod-new root@cu-fcchat.com

# Update deploy scripts to use new key
```

## Related Scripts

- `deploy.ps1` - Main deployment script (uses SSH keys)
- `deploy-test.ps1` - Test deployment to local Docker container
- `install-ssh-key.ps1` - Install your public key on a server

---

**Last Updated**: November 7, 2025
