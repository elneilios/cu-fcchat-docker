# Deploying to Railway

Railway is the easiest platform for deploying this phpBB Docker setup because it natively supports docker-compose.yml and MySQL databases.

## Prerequisites

✅ GitHub repository with your code (Done!)
✅ Railway account (Free tier available)

## Step-by-Step Deployment

### Step 1: Create a Railway Account

1. Go to https://railway.app
2. Click **Login** (can use GitHub login)
3. Verify your account

### Step 2: Create a New Project

1. Click **New Project**
2. Select **Deploy from GitHub repo**
3. Authorize Railway to access your GitHub account
4. Select your repository: `elneilios/cu-fcchat-docker`
5. Click **Deploy Now**

### Step 3: Railway Auto-Detection

Railway will automatically:
- ✅ Detect your `docker-compose.yml`
- ✅ Create both services (phpbb web app + MySQL database)
- ✅ Set up networking between services
- ✅ Pull the large SQL file via Git LFS

**Wait 5-10 minutes for:**
- Docker image build
- Database initialization
- SQL import (683 MB file)

### Step 4: Configure Environment Variables (If Needed)

Railway should work with defaults, but you can override:

1. Click on your **phpbb** service
2. Go to **Variables** tab
3. Add these if needed:
   ```
   DB_HOST=db
   DB_PORT=3306
   DB_NAME=phpbb
   DB_USER=phpbbuser
   DB_PASSWORD=phpbbpass
   ```

**Note**: These match your docker-compose.yml defaults, so you likely don't need to set them.

### Step 5: Get Your Public URL

1. Click on your **phpbb** service
2. Go to **Settings** tab
3. Scroll to **Networking**
4. Click **Generate Domain** to get a public URL
5. Your site will be accessible at: `https://your-app-name.up.railway.app`

### Step 6: Verify Deployment

1. Visit your Railway URL
2. You should see your phpBB forum
3. Test login functionality
4. Check that all features work

## Troubleshooting

### Build Failures

**Issue**: Build fails or times out
**Solution**: 
- Check the build logs in Railway dashboard
- Ensure Git LFS file downloaded correctly
- May need to increase build timeout in Settings

### Database Connection Errors

**Issue**: "SQL ERROR" or connection failures
**Solution**:
- Railway automatically handles service networking
- The service name `db` from docker-compose.yml should work
- Check that both services are running in the Railway dashboard
- Verify environment variables if you set any custom ones

### SQL Import Taking Too Long

**Issue**: Database initialization is very slow
**Solution**:
- The 683 MB SQL file takes time to import (5-10 minutes)
- Check database service logs for progress
- Be patient on first deployment

### Git LFS Issues

**Issue**: Large SQL file not downloading
**Solution**:
- Verify `.gitattributes` exists in repo
- Check Railway build logs for LFS errors
- May need to manually upload SQL file via Railway CLI

## Monitoring & Logs

### View Logs
1. Click on a service (phpbb or db)
2. Go to **Deployments** tab
3. Click on latest deployment
4. View real-time logs

### Check Resource Usage
1. Click on your project
2. View **Metrics** tab
3. Monitor CPU, Memory, Network usage

## Costs

### Free Tier (Hobby Plan)
- **$5 credit per month** (trial)
- Enough for small sites with light traffic
- Services sleep after inactivity

### Paid Plans (Developer)
- **$5/month** base + usage
- ~$10-20/month for a small phpBB forum
- No sleep/downtime
- Better performance

### Usage Breakdown
- **Web Service (phpbb)**: ~$3-5/month
- **Database (MySQL)**: ~$3-5/month
- **Data Transfer**: Usually minimal

## Managing Your Deployment

### Update Your Site

When you push changes to GitHub:
1. Railway automatically detects the push
2. Rebuilds and redeploys your services
3. Zero-downtime deployment

### Manual Redeploy

1. Go to Railway dashboard
2. Click your service
3. Click **Deployments**
4. Click **•••** menu → **Redeploy**

### Access Database Directly

1. Click on **phpbb-db** service
2. Go to **Data** tab (if available) or **Connect** tab
3. Use provided credentials with MySQL client:
   ```bash
   mysql -h <railway-host> -P <port> -u <user> -p<password> phpbb
   ```

### Backup Your Database

Railway doesn't provide automatic backups on free tier. Options:

**Option 1: Use Railway CLI**
```bash
railway run mysqldump -u phpbbuser -pphpbbpass phpbb > backup.sql
```

**Option 2: Connect Externally**
```bash
mysql -h <railway-host> -P <port> -u <user> -p<password> phpbb
mysqldump phpbb > backup.sql
```

**Option 3: Use Your Local Snapshot Script**
- Keep using your `snapshot.ps1` script locally
- Download data periodically for backups

## Advanced Configuration

### Custom Domain

1. Go to your **phpbb** service
2. Navigate to **Settings** → **Networking**
3. Click **Add Custom Domain**
4. Follow DNS configuration instructions
5. Railway provides free SSL certificates

### Scale Up Resources

1. Click on your service
2. Go to **Settings**
3. Adjust **Resources**:
   - CPU allocation
   - Memory limits
   - Disk size

### Private Networking

Railway automatically creates private networking between your services:
- `db` hostname resolves to the MySQL container
- No public exposure of database
- Secure by default

## Alternative: Railway CLI

For advanced users, deploy via CLI:

```bash
# Install Railway CLI
npm install -g @railway/cli

# Login
railway login

# Initialize project
railway init

# Deploy
railway up
```

## Comparison: Railway vs Render vs Others

| Feature | Railway | Render | DigitalOcean |
|---------|---------|--------|--------------|
| MySQL Support | ✅ Native | ❌ PostgreSQL only | ✅ Native |
| docker-compose | ✅ Yes | ❌ Single container | ✅ Yes |
| Git LFS | ✅ Supported | ✅ Supported | ✅ Supported |
| Free Tier | $5 credit/mo | Limited free | ❌ No free tier |
| Setup Complexity | ⭐ Easy | ⭐⭐ Medium | ⭐⭐⭐ Complex |
| Auto-deploy | ✅ Yes | ✅ Yes | ✅ Yes |

**Railway is the best fit for your phpBB deployment!** 🎯

## Next Steps

1. ✅ Push your code to GitHub (Done!)
2. 🚀 Create Railway account and deploy
3. 🌐 Generate public domain
4. 🧪 Test your forum
5. 🎨 Optional: Add custom domain
6. 📊 Monitor usage and costs

Need help with any step? Check Railway's excellent documentation at https://docs.railway.app
