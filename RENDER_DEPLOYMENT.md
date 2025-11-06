# Deploying to Render

This guide explains how to deploy your phpBB forum to Render.

## Prerequisites

- GitHub repository with your code (✅ Done)
- Render account

## Step 1: Create a MySQL Database on Render

1. Go to https://dashboard.render.com
2. Click **New +** → **MySQL**
3. Configure:
   - **Name**: `phpbb-database`
   - **Database**: `phpbb`
   - **User**: `phpbbuser`
   - **Region**: Choose closest to your users
   - **Plan**: Choose appropriate plan (Free tier available)
4. Click **Create Database**
5. **Important**: Copy the connection details (you'll need them):
   - Internal Database URL (for use within Render)
   - External Database URL (if needed)
   - Host, Port, Username, Password

## Step 2: Import Your Database

You need to import your `001_phpbb_backup.sql` file into the Render database.

### Option A: Using Render Shell (Recommended)

1. After database is created, go to the database page
2. Click **Connect** → **External Connection**
3. Use the provided credentials with your local MySQL client:
   ```bash
   mysql -h <hostname> -P <port> -u <user> -p<password> phpbb < db_init/001_phpbb_backup.sql
   ```

### Option B: Using phpMyAdmin or MySQL Workbench

1. Use the External Connection details from Render
2. Connect to the database
3. Import the SQL file through the GUI

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

4. **Environment Variables** - Add these (using values from Step 1):
   ```
   DB_HOST=<your-render-database-internal-hostname>
   DB_PORT=3306
   DB_NAME=phpbb
   DB_USER=phpbbuser
   DB_PASSWORD=<your-database-password>
   ```

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
- Verify all environment variables are set correctly
- Check that DB_HOST uses the **internal hostname** (e.g., `dpg-xxx-a`)
- Ensure the database import completed successfully
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

Render pricing (as of 2024):
- **MySQL Database**: $7-15/month (Starter plan)
- **Web Service**: $7/month (Starter plan) or free with limitations
- **Total**: ~$14-22/month for basic deployment

## Alternative: Use Docker Compose on Other Platforms

If you need multi-container support (web + database together), consider:
- **Railway.app** - Supports docker-compose.yml
- **DigitalOcean App Platform** - Supports multiple services
- **AWS ECS** - Full docker-compose support
- **Azure Container Apps** - Multi-container support

These platforms can deploy your `docker-compose.yml` directly without needing environment variables.
