# Deploying to Render

This guide explains how to deploy your phpBB forum to Render.

## Prerequisites

- GitHub repository with your code (✅ Done)
- Render account

## Step 1: Create a Database

**Render only offers PostgreSQL**, not MySQL. You have two options:

### Option A: Use an External MySQL Provider (Recommended for phpBB)

Since phpBB works best with MySQL/MariaDB, use an external provider:

**1. PlanetScale (Free tier available)**
   - Go to https://planetscale.com
   - Create a free database
   - Get connection details (host, username, password, database name)
   - Use the external hostname in your Render environment variables

**2. AWS RDS MySQL (Paid)**
   - Create a MySQL instance on AWS RDS
   - Use the endpoint as DB_HOST

**3. Railway (Supports MySQL)**
   - Railway.app offers MySQL databases
   - May be easier for full docker-compose deployment

### Option B: Migrate to PostgreSQL (Requires Work)

If you want to use Render's PostgreSQL:
1. Click **New +** → **PostgreSQL**
2. You'll need to convert your MySQL database to PostgreSQL
3. Modify phpBB to use PostgreSQL driver (`phpbb_db_driver_postgres`)
4. This is complex and not recommended unless necessary

## Step 2: Import Your Database

### If using PlanetScale:
1. Install PlanetScale CLI or use their web interface
2. Import your SQL file:
   ```bash
   pscale database restore-dump <database> <branch> --dump db_init/001_phpbb_backup.sql
   ```

### If using AWS RDS or other MySQL service:
Use the external connection details to import:
```bash
mysql -h <hostname> -P <port> -u <user> -p<password> phpbb < db_init/001_phpbb_backup.sql
```

### If using Railway:
1. Railway can import the SQL automatically if you include it in your deployment
2. Or use their database connection URL with MySQL client

## Step 3: Deploy the Web Service

1. In Render Dashboard, click **New +** → **Web Service**
2. Connect your GitHub repository: `elneilios/cu-fcchat-docker`
3. Configure:
   - **Name**: `phpbb-forum`
   - **Region**: Same as your database
   - **Branch**: `master`
   - **Root Directory**: Leave empty
   - **Runtime**: `Docker`
   - **Plan**: Choose appropriate plan

4. **Environment Variables** - Add these (using values from your MySQL provider):
   ```
   DB_HOST=<your-mysql-hostname>
   DB_PORT=3306
   DB_NAME=phpbb
   DB_USER=phpbbuser
   DB_PASSWORD=<your-database-password>
   ```
   
   **Important**: Use the **external/public hostname** from your MySQL provider (e.g., PlanetScale, AWS RDS)

5. Click **Create Web Service**

## Step 4: Wait for Deployment

Render will:
1. Clone your repository from GitHub
2. Pull the large SQL file via Git LFS
3. Build your Docker image
4. Deploy the container
5. This may take 5-10 minutes

## Step 5: Verify

1. Once deployed, visit your service URL (e.g., `https://phpbb-forum.onrender.com`)
2. Your phpBB forum should load successfully
3. Test login and basic functionality

## Troubleshooting

### Database Connection Issues

If you see "SQL ERROR" or connection errors:
- Verify all environment variables are set correctly in Render
- Check that DB_HOST uses the correct **external/public hostname**
- Ensure your MySQL provider allows connections from Render's IP addresses
- For PlanetScale: Make sure SSL/TLS is properly configured
- Check the Render logs for specific error messages

### Git LFS Issues

If the SQL file doesn't download:
- Ensure Git LFS is enabled on your GitHub repository
- Check Render build logs for LFS-related errors
- Verify the file is tracked in `.gitattributes`

### Performance Issues

- Render's free tier has limited resources
- Consider upgrading to a paid plan for better performance
- Enable phpBB caching features
- Consider using a CDN for static assets

## Local vs Cloud Configuration

The `docker.config.php` file now supports both:
- **Local Docker Compose**: Uses default values (`db`, `3306`, etc.)
- **Cloud Deployment**: Uses environment variables when set

This means you can still run locally with:
```bash
docker compose up
```

No configuration changes needed!

## Cost Estimate

### Option A: Render + PlanetScale
- **PlanetScale Database**: Free tier available (1 database, 5GB storage, 1 billion reads/month)
- **Render Web Service**: $7/month (Starter) or free with limitations
- **Total**: $0-7/month for basic deployment

### Option B: Render + AWS RDS MySQL
- **AWS RDS MySQL**: ~$15-25/month (t3.micro instance)
- **Render Web Service**: $7/month (Starter)
- **Total**: ~$22-32/month

## Better Alternative: Railway

Since Render doesn't offer MySQL, consider **Railway.app** instead:
- Supports docker-compose.yml directly
- Offers MySQL databases natively
- No need to split database and web service
- Simpler deployment process
- Free tier: $5 credit/month
- Paid: ~$5-10/month for small apps

**To deploy on Railway:**
1. Push your code to GitHub
2. Go to https://railway.app
3. Click "New Project" → "Deploy from GitHub repo"
4. Select your repository
5. Railway will detect docker-compose.yml and deploy both services
6. Add environment variables if needed
7. Done!
