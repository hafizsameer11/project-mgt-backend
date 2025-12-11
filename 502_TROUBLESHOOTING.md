# 502 Bad Gateway Troubleshooting Guide

## Changes Made

### 1. Switched from Unix Socket to TCP
- **Before**: `unix:/run/php-fpm.sock`
- **After**: `127.0.0.1:9000`
- **Why**: TCP is more reliable in Docker containers and easier to debug

### 2. Updated PHP-FPM Configuration
- Changed `listen` from socket to TCP port 9000
- Removed socket-specific permissions (not needed for TCP)

### 3. Improved Supervisor Startup Order
- Added 3-second delay for Nginx to ensure PHP-FPM starts first
- Increased startsecs for better stability

## How to Debug

### 1. Check if PHP-FPM is listening on port 9000

```bash
# Inside the container
docker exec -it <container-name> sh
netstat -tuln | grep 9000
# Should show: tcp 0 0 127.0.0.1:9000
```

### 2. Check Nginx error logs

```bash
docker exec -it <container-name> tail -f /var/log/nginx/error.log
```

Look for errors like:
- `connect() failed (111: Connection refused)`
- `upstream timed out`
- `FastCGI sent in stderr`

### 3. Check PHP-FPM status

```bash
docker exec -it <container-name> ps aux | grep php-fpm
# Should show php-fpm processes running
```

### 4. Test PHP-FPM directly

```bash
docker exec -it <container-name> sh
echo "<?php phpinfo(); ?>" > /var/www/html/public/test.php
curl http://localhost/test.php
# Should return PHP info page
```

### 5. Check Laravel logs

```bash
docker exec -it <container-name> tail -f /var/www/html/storage/logs/laravel.log
```

## Common Issues and Solutions

### Issue 1: "Connection refused" in Nginx logs
**Solution**: PHP-FPM isn't running or not listening on 9000
- Check: `docker exec <container> ps aux | grep php-fpm`
- Restart: The container should auto-restart via supervisor

### Issue 2: "Permission denied"
**Solution**: Check file permissions
```bash
docker exec -it <container-name> ls -la /var/www/html/public
# Should show www-data ownership
```

### Issue 3: Laravel errors
**Solution**: Check .env configuration
- Database connection
- APP_KEY is set
- APP_URL matches your domain

### Issue 4: Timeout errors
**Solution**: Increase FastCGI timeouts (already set to 300s)

## Quick Test Commands

```bash
# Test from inside container
docker exec -it <container-name> sh
curl http://localhost/api/health

# Test from host (if port is exposed)
curl http://localhost:80/api/health

# Test through Traefik
curl https://backend.hmstech.xyz/api/health
```

## Rebuild Instructions

After these changes, rebuild your container:

```bash
# In Dokploy, trigger a rebuild
# Or manually:
cd backend
docker build -t laravel-backend .
docker run -d -p 80:80 --name test-laravel laravel-backend
```

## Expected Behavior

After rebuild, you should see in logs:
1. PHP-FPM starting and listening on 127.0.0.1:9000
2. Nginx starting after a 3-second delay
3. Both services in RUNNING state
4. No connection errors in Nginx logs

## Still Getting 502?

1. **Check Traefik**: Verify Traefik is routing to the correct port (80)
2. **Check Container**: Ensure container is running: `docker ps`
3. **Check Logs**: Review all logs (supervisor, nginx, php-fpm, laravel)
4. **Test Locally**: Try accessing from inside the container first
5. **Verify .env**: Ensure all Laravel environment variables are set correctly

