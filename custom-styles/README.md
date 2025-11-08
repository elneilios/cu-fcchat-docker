# Custom Styles

This directory contains custom phpBB styles that are **protected in version control** and separate from the volatile `phpbb` folder.

## Why This Structure?

The `phpbb` folder is transitional and may be:
- Overwritten by backup restores
- Replaced by live server pulls
- Reset during upgrades

Keeping custom styles here ensures they are **never lost** and are always tracked in Git.

## Current Styles

### cu-fcchat
- **Version**: 1.0.0
- **Parent**: prosilver
- **Copyright**: © 2025 Neil Alderson
- **Features**:
  - Modern Exo 2 font for headers
  - Orbitron font for tagline with wide letter spacing
  - Cambridge United gold accent color (rgb(217, 191, 94))
  - Custom background and logo

## Usage

### Sync to phpBB
After restoring a backup or pulling from live:

```powershell
.\sync-custom-styles.ps1
```

Sync specific style:
```powershell
.\sync-custom-styles.ps1 -StyleName "cu-fcchat"
```

### Making Changes

1. Edit files in `custom-styles/cu-fcchat/`
2. Sync to phpBB: `.\sync-custom-styles.ps1`
3. Test in browser (increment `assets_version` in phpBB ACP to bust cache)
4. Commit changes: `git add custom-styles/ && git commit`

### After Restore/Pull
The `restore.ps1` script automatically runs `sync-custom-styles.ps1` after restoring a backup.

## Directory Structure

```
custom-styles/
└── cu-fcchat/
    ├── style.cfg                    # Style metadata
    └── theme/
        ├── colours.css              # Color scheme
        ├── stylesheet.css           # Main styles (Exo 2 + Orbitron fonts)
        └── images/
            ├── logo.png             # Cambridge United logo
            ├── background.webp      # Main background
            ├── background_bwa.webp  # Alternate background
            └── background_stripes.png # Stripe pattern
```

## Git Tracking

- ✅ **Tracked**: All files in `custom-styles/`
- ❌ **Ignored**: `phpbb/styles/cu-fcchat/` (synced copy, not source of truth)

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Nov 2025 | Initial release with Exo 2 + Orbitron fonts |
