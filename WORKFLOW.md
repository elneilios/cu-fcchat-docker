# Custom Styles Workflow - Quick Reference

## Daily Development

### Making Style Changes
1. Edit files in `custom-styles/cu-fcchat/theme/`
2. Sync to phpBB: `.\sync-custom-styles.ps1`
3. Increment `assets_version` in phpBB ACP (System → Client Communication → Assets)
4. Test in browser (use incognito to bypass cache)
5. Commit when happy:
   ```pwsh
   git add custom-styles/
   git commit -m "style: update header colors"
   git push
   ```

### After Restoring a Backup
```pwsh
.\restore.ps1
# Custom styles are automatically synced!
```

### Manual Style Sync
```pwsh
# Sync all styles
.\sync-custom-styles.ps1

# Sync specific style
.\sync-custom-styles.ps1 -StyleName "cu-fcchat"
```

## Version Control

### Creating Milestone Tags

**Minor updates** (style tweaks, config changes):
```pwsh
.\tag-milestone.ps1 -Version "1.1.0" -Message "Updated footer styling" -Push
```

**Major updates** (phpBB upgrades, significant features):
```pwsh
.\tag-milestone.ps1 -Version "2.0.0" -Message "Upgraded to phpBB 3.3.x" -Push
```

**Hotfixes**:
```pwsh
.\tag-milestone.ps1 -Version "1.0.1" -Message "Fixed mobile menu bug" -Push
```

### Viewing Tags
```pwsh
# List all tags
git tag -l

# Show tag details
git show v1.0.0

# View on GitHub
# https://github.com/elneilios/cu-fcchat-docker/releases
```

### Working with Tags
```pwsh
# Checkout a specific version
git checkout v1.0.0

# Return to latest
git checkout master

# Delete a tag (if needed)
git tag -d v1.0.0                    # Local
git push origin --delete v1.0.0      # Remote
```

## File Locations

| Location | Purpose | In Git? |
|----------|---------|---------|
| `custom-styles/cu-fcchat/` | **Source of truth** for custom style | ✅ Yes |
| `phpbb/styles/cu-fcchat/` | Working copy (synced) | ❌ No (ignored) |
| `phpbb/styles/prosilver/` | Parent theme | ✅ Yes |
| `phpbb/styles/all/` | Core phpBB styles | ✅ Yes |

## Common Scenarios

### Scenario 1: Pull from Live Server
```pwsh
.\pull-live.ps1 -ServerHost myserver.com -KeyPath ~/.ssh/id_rsa
.\sync-custom-styles.ps1
docker compose up -d
```

### Scenario 2: Test New Style Changes
```pwsh
# Edit custom-styles/cu-fcchat/theme/stylesheet.css
.\sync-custom-styles.ps1
# Increment assets_version in ACP
# Test at http://localhost:8080
git add custom-styles/ && git commit -m "style: new changes"
```

### Scenario 3: Deploy to Production
```pwsh
# After testing locally
.\tag-milestone.ps1 -Version "1.2.0" -Message "New style features" -Push
.\deploy.ps1 -ServerHost myserver.com -KeyPath ~/.ssh/id_rsa
```

### Scenario 4: Restore Old Version
```pwsh
# Find the tag you want
git tag -l

# Checkout that version
git checkout v1.0.0

# Restore the database/files
.\restore.ps1 -SnapshotFolder <backup-from-that-time>

# When done, return to latest
git checkout master
```

## Tagging Strategy

| Version | Use Case | Example |
|---------|----------|---------|
| **1.0.0** | Initial production release | First deployment |
| **1.x.0** | Minor updates | Style changes, new features |
| **2.0.0** | Major updates | phpBB version upgrades |
| **x.x.1** | Patches | Bug fixes, hotfixes |

## Protection Mechanisms

1. ✅ **Custom styles** in `custom-styles/` (never touched by restores)
2. ✅ **Git tags** for milestone rollback
3. ✅ **Automated sync** after restore
4. ✅ **Ignored working copy** (`phpbb/styles/cu-fcchat/`)
5. ✅ **Git LFS** for large backups and images

## Troubleshooting

**Style not showing after restore?**
```pwsh
.\sync-custom-styles.ps1
docker compose restart web
```

**Style changes not appearing in browser?**
- Increment `assets_version` in phpBB ACP
- Use incognito mode
- Clear phpBB cache: `docker exec phpbb rm -rf /var/www/html/cache/*`

**Need to revert a style change?**
```pwsh
git log custom-styles/  # Find the commit
git checkout <commit-hash> custom-styles/
.\sync-custom-styles.ps1
```

## Next Steps

- Create tags for major milestones: `.\tag-milestone.ps1`
- Keep `custom-styles/` committed regularly
- Document style changes in commit messages
- Use semantic versioning consistently
