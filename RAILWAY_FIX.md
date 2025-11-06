# Railway Environment Variables Fix

Railway deploys docker-compose services separately and may not use the same networking as local Docker.

## Quick Fix - Add Environment Variables in Railway

1. Go to your Railway dashboard: https://railway.app/dashboard
2. Click on your **phpbb** service (the web app, not database)
3. Go to **Variables** tab
4. Click **New Variable** and add these:

### Required Variables:

```
DB_HOST=db.railway.internal
DB_PORT=3306
DB_NAME=phpbb
DB_USER=phpbbuser
DB_PASSWORD=phpbbpass
```

**Important:** The `DB_HOST` might need to be:
- `db.railway.internal` (Railway's internal DNS)
- Or the specific hostname Railway assigned to your database service
- Check your database service's **Settings → Networking** for the internal hostname

## Alternative: Check Database Service Name

If the above doesn't work:

1. Click on your **db** (database) service
2. Go to **Settings** → **Networking**
3. Look for **Private Networking** or **Internal Hostname**
4. Copy that exact hostname
5. Use it as the `DB_HOST` value in your phpbb service variables

## After Adding Variables:

1. Railway will automatically redeploy the phpbb service
2. Wait 2-3 minutes for redeployment
3. Visit your URL again
4. The connection error should be fixed

## Still Not Working?

### Check Railway Logs:

1. Click on **phpbb** service
2. Go to **Deployments** tab
3. Click latest deployment
4. Check logs for error messages about database connection

### Verify Database is Running:

1. Click on **db** service
2. Check that it shows "Active" status
3. View its logs to ensure MySQL started successfully
4. Look for "ready for connections" message

### Test Database Connection:

Railway might have a **Connect** tab on the database service:
1. Click **db** service → **Connect**
2. Try connecting with the credentials to verify database is accessible
3. Check if the SQL import completed (should have phpbb tables)

## Common Railway Issues:

### Issue 1: Services Not Linked
**Symptom:** Can't resolve database hostname
**Fix:** Both services must be in the same Railway project/environment

### Issue 2: Wrong Internal Hostname
**Symptom:** "Name or service not known"
**Fix:** Use the exact internal hostname from Railway (not just "db")

### Issue 3: Database Not Initialized
**Symptom:** Connection works but no tables
**Fix:** Check db logs for SQL import progress - 683MB file takes 5-10 minutes

### Issue 4: Git LFS File Not Downloaded
**Symptom:** Database has no data
**Fix:** Check build logs - Railway should show LFS download

## Debugging Commands in Railway:

If Railway provides shell access:

```bash
# Test DNS resolution
nslookup db.railway.internal

# Test database connection
mysql -h db.railway.internal -u phpbbuser -pphpbbpass phpbb -e "SELECT 1;"

# Check config
cat /var/www/html/config.php
```

Let me know what you find in the Railway dashboard and I can provide more specific guidance!
