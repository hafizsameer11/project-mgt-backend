# Docker 502 Bad Gateway Fix

## Issues Fixed

### 1. PHP-FPM Socket Configuration
- **Problem**: Nginx was trying to connect to `/run/php-fpm.sock` but PHP-FPM wasn't configured to use this socket
- **Fix**: Created `/docker/php-fpm/www.conf` to configure PHP-FPM to listen on the correct socket

### 2. Service Startup Order
- **Problem**: Nginx was starting before PHP-FPM was ready, causing connection failures
- **Fix**: Implemented Supervisor to manage both services and ensure proper startup order

### 3. Socket Directory Permissions
- **Problem**: Socket directory didn't exist or had wrong permissions
- **Fix**: Created `/run` directory with proper ownership in Dockerfile and entrypoint

### 4. Missing FastCGI Parameters
- **Problem**: Nginx config was missing some important FastCGI parameters
- **Fix**: Added proper `fastcgi_split_path_info` and `PATH_INFO` handling

## Files Created/Modified

1. **`docker/php-fpm/www.conf`** - PHP-FPM pool configuration
2. **`docker/supervisord.conf`** - Supervisor configuration to manage PHP-FPM and Nginx
3. **`docker/entrypoint.sh`** - Updated to use Supervisor
4. **`docker/nginx.conf`** - Enhanced with better error handling and logging
5. **`Dockerfile`** - Updated to copy PHP-FPM config and Supervisor config

## How It Works Now

1. Entrypoint script clears Laravel caches and sets permissions
2. Supervisor starts both PHP-FPM and Nginx as managed services
3. PHP-FPM listens on Unix socket `/run/php-fpm.sock`
4. Nginx connects to the same socket for FastCGI requests
5. Both services are monitored and auto-restarted by Supervisor

## Testing the Fix

After rebuilding and deploying:

1. Check if PHP-FPM is running:
   ```bash
   docker exec <container> ps aux | grep php-fpm
   ```

2. Check if Nginx is running:
   ```bash
   docker exec <container> ps aux | grep nginx
   ```

3. Check if socket exists:
   ```bash
   docker exec <container> ls -la /run/php-fpm.sock
   ```

4. Check Nginx error logs:
   ```bash
   docker exec <container> tail -f /var/log/nginx/error.log
   ```

5. Check PHP-FPM logs:
   ```bash
   docker exec <container> tail -f /var/log/php-fpm.log
   ```

## Common Issues

### If still getting 502:

1. **Check socket permissions**: The socket should be owned by `www-data:www-data` with mode `0660`
2. **Check PHP-FPM is running**: `docker exec <container> ps aux | grep php-fpm`
3. **Check Nginx can access socket**: Verify the socket path matches in both configs
4. **Check Laravel .env**: Make sure database and other configs are correct

### Alternative: Use TCP instead of Socket

If socket issues persist, you can change to TCP:

In `docker/php-fpm/www.conf`:
```
listen = 127.0.0.1:9000
```

In `docker/nginx.conf`:
```
fastcgi_pass 127.0.0.1:9000;
```

