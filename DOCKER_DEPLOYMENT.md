# Docker Deployment Guide

This guide explains how to deploy the Laravel backend using Docker and Dokploy.

## Files Created

- `Dockerfile` - Main Docker image configuration
- `.dockerignore` - Files to exclude from Docker build
- `docker-entrypoint.sh` - Entrypoint script for container initialization
- `docker-compose.yml` - Docker Compose configuration (optional, for local development)
- `docker/nginx/default.conf` - Nginx configuration (if using nginx)

## Prerequisites

1. Docker installed on your system
2. Dokploy account and access
3. Database (MySQL/PostgreSQL) accessible from your deployment environment
4. Environment variables configured

## Environment Variables

Make sure to set these environment variables in Dokploy:

```
APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Add other required Laravel environment variables
```

## Deployment Steps for Dokploy

### 1. Build Configuration

In Dokploy, when creating a new application:

- **Dockerfile Path**: `backend/Dockerfile`
- **Build Context**: `backend/` (or root if building from project root)
- **Port**: `9000` (PHP-FPM port) - Dokploy will handle reverse proxy

### 2. Environment Setup

1. Add all required environment variables in Dokploy's environment section
2. Make sure `APP_KEY` is generated (run `php artisan key:generate` locally and copy the key)

### 3. Database Migrations

After deployment, you may need to run migrations. You can do this by:

- Using Dokploy's terminal/SSH access
- Or adding a one-time job to run migrations
- Or uncommenting the migration line in `docker-entrypoint.sh` (not recommended for production)

### 4. Storage Permissions

The Dockerfile automatically sets correct permissions for:
- `storage/` directory (775)
- `bootstrap/cache/` directory (775)

### 5. Volume Mounts (if needed)

If you need persistent storage for uploaded files, configure volume mounts in Dokploy:
- `./storage/app/public` - For public file uploads
- `./storage/logs` - For log files

## Local Development with Docker Compose

If you want to test locally:

```bash
cd backend
docker-compose up -d
```

This will start:
- PHP-FPM container (app)
- Nginx container (port 8000)
- MySQL container (port 3306)

## Building the Docker Image

To build the image manually:

```bash
cd backend
docker build -t laravel-backend .
```

## Running the Container

```bash
docker run -d \
  --name laravel-app \
  -p 9000:9000 \
  -e APP_ENV=production \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=your-db \
  -e DB_USERNAME=your-user \
  -e DB_PASSWORD=your-password \
  laravel-backend
```

## Troubleshooting

### Permission Issues
If you encounter permission issues, ensure the storage directories are writable:
```bash
docker exec -it laravel-app chmod -R 775 storage bootstrap/cache
```

### Database Connection Issues
- Verify database credentials in environment variables
- Ensure database is accessible from the container
- Check firewall rules

### Cache Issues
Clear Laravel cache:
```bash
docker exec -it laravel-app php artisan cache:clear
docker exec -it laravel-app php artisan config:clear
```

## Notes

- The Dockerfile uses PHP 8.1 FPM Alpine for a smaller image size
- Composer dependencies are installed during build (production mode, no dev dependencies)
- The entrypoint script automatically caches Laravel config, routes, and views for better performance
- For production, ensure `APP_DEBUG=false` and `APP_ENV=production`

