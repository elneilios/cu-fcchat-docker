# Railway MySQL Version Downgrade Guide

## Problem
Railway deployed MySQL 9.4.0 which has charset compatibility issues with PHP 5.6.
We need MySQL 8.0 or 5.7 for compatibility.

## Solution: Replace MySQL Service with Older Version

### Step 1: Export Current Database (Backup)
Your data is safe in `db_init/001_phpbb_backup.sql`, but let's be cautious.

### Step 2: Delete Current MySQL Service
1. In Railway dashboard, click on your **MySQL** service
2. Go to **Settings** tab
3. Scroll down and click **Delete Service**
4. Confirm deletion

### Step 3: Add New MySQL Service with Specific Version
1. Click **+ New** in your Railway project
2. Select **Database**
3. Choose **MySQL**
4. **IMPORTANT:** Look for version selection:
   - Try to select **MySQL 8.0** (recommended)
   - Or **MySQL 5.7** if available
   - If no version selector appears, Railway might default to latest

### Step 4: Check If Railway Supports Older MySQL
If Railway doesn't let you select MySQL version, you have alternatives:

**Option A: Use Docker MySQL in Railway**
Deploy MySQL as a separate Docker service with your preferred version.

**Option B: Use MariaDB instead**
MariaDB 10.5 (from your docker-compose) is fully compatible with phpBB and PHP 5.6.

### Step 5: Get New Connection Details
Once the new MySQL service is created:
1. Click on the MySQL service
2. Note the new connection details:
   - `MYSQLHOST` (hostname)
   - `MYSQLPORT` (port)
   - `MYSQLUSER` (user)
   - `MYSQLPASSWORD` (password)
   - `MYSQLDATABASE` (database name)

### Step 6: Update config.php
Update `phpbb/config.php` with new connection details.

### Step 7: Re-import Database
```powershell
docker run --rm -i -v ${PWD}/db_init:/db_init mysql:8.0 mysql -h <NEW_HOST> -P <NEW_PORT> -u <NEW_USER> -p<NEW_PASSWORD> <NEW_DATABASE> < db_init/001_phpbb_backup.sql
```

### Step 8: Revert Code Changes
Remove the custom mysqli.php changes we made:
```bash
git revert HEAD
git push origin master
```

## Alternative: Deploy Your Own MySQL via Docker in Railway

If Railway doesn't support older MySQL versions, you can:
1. Create a new service from your docker-compose.yml
2. Deploy just the MariaDB 10.5 container
3. This gives you full control over the MySQL version

Would you like help with this approach?
