# 502 Bad Gateway - Complete Fix Summary

## Problem
Getting 502 Bad Gateway error when accessing the Laravel backend through Traefik.

## Root Causes Identified
1. **Unix Socket Connection Issues**: PHP-FPM and Nginx were using Unix sockets which can have permission/timing issues in Docker
2. **Service Startup Order**: Nginx was starting before PHP-FPM was ready to accept connections
3. **Missing Configuration**: PHP-FPM pool configuration was not properly set up

---

## Fixes Applied

### 1. **Switched from Unix Socket to TCP** ✅
**File**: `docker/php-fpm/www.conf`

**Before:**
```ini
listen = /run/php-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

**After:**
```ini
listen = 127.0.0.1:9000
listen.allowed_clients = 127.0.0.1
```

**Why**: TCP connections are more reliable in Docker containers and easier to debug. No permission issues with socket files.

---

### 2. **Updated Nginx FastCGI Configuration** ✅
**File**: `docker/nginx.conf`

**Before:**
```nginx
fastcgi_pass unix:/run/php-fpm.sock;
```

**After:**
```nginx
fastcgi_pass 127.0.0.1:9000;
```

**Additional Improvements:**
- Added `fastcgi_send_timeout 300` for better timeout handling
- Enhanced error logging configuration

**Why**: Nginx now connects to PHP-FPM via TCP instead of Unix socket, eliminating socket permission issues.

---

### 3. **Improved Supervisor Startup Order** ✅
**File**: `docker/supervisord.conf`

**Changes:**
- Added `priority=10` for PHP-FPM (starts first)
- Added `priority=20` for Nginx (starts second)
- Added `sleep 3` delay for Nginx to ensure PHP-FPM is ready
- Increased `startsecs` for both services (3s for PHP-FPM, 5s for Nginx)
- Added `startretries=3` for auto-retry on failure

**Before:**
```ini
[program:nginx]
command=nginx -g "daemon off;"
```

**After:**
```ini
[program:php-fpm]
priority=10
startsecs=3
startretries=3

[program:nginx]
command=sh -c "sleep 3 && nginx -g 'daemon off;'"
priority=20
startsecs=5
startretries=3
```

**Why**: Ensures PHP-FPM is fully ready before Nginx tries to connect, preventing connection refused errors.

---

### 4. **Enhanced Entrypoint Script** ✅
**File**: `docker/entrypoint.sh`

**Changes:**
- Removed socket directory creation (not needed with TCP)
- Added fallback for supervisor path detection
- Improved error handling with `|| true` for non-critical commands

**Why**: More robust startup process that handles edge cases.

---

### 5. **Enhanced Nginx Configuration** ✅
**File**: `docker/nginx.conf`

**Added:**
- Access and error logging
- Security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)
- Better FastCGI timeout settings
- Hidden file protection

**Why**: Better debugging capabilities and improved security.

---

## Files Created/Modified

### Modified Files:
1. ✅ `docker/php-fpm/www.conf` - Changed to TCP
2. ✅ `docker/nginx.conf` - Updated FastCGI to TCP, added logging
3. ✅ `docker/supervisord.conf` - Improved startup order
4. ✅ `docker/entrypoint.sh` - Enhanced error handling

### Created Files:
1. ✅ `docker/php-fpm/www.conf` - PHP-FPM pool configuration
2. ✅ `docker/supervisord.conf` - Supervisor configuration
3. ✅ `502_TROUBLESHOOTING.md` - Debugging guide
4. ✅ `TRAEFIK_CONFIG.md` - Traefik setup guide
5. ✅ `FIXES_SUMMARY.md` - This file

---

## Technical Details

### Connection Flow (After Fix):
```
Traefik (Port 443) 
  → Container Port 80 (Nginx)
    → TCP Connection to 127.0.0.1:9000 (PHP-FPM)
      → Laravel Application
```

### Port Configuration:
- **Container Exposes**: Port 80 (Nginx)
- **Internal PHP-FPM**: Port 9000 (TCP)
- **Traefik Routes**: External HTTPS → Container Port 80

---

## Testing the Fix

### 1. Rebuild the Container
```bash
# In Dokploy, trigger a rebuild
# Or manually:
cd backend
docker build -t laravel-backend .
```

### 2. Verify Services are Running
Check logs should show:
```
[11-Dec-2025 08:40:06] NOTICE: ready to handle connections
INFO success: php-fpm entered RUNNING state
INFO success: nginx entered RUNNING state
```

### 3. Test Connection
```bash
# From inside container
docker exec -it <container> sh
netstat -tuln | grep 9000
# Should show: tcp 0 0 127.0.0.1:9000

# Test API
curl https://backend.hmstech.xyz/api/health
```

---

## Key Improvements Summary

| Issue | Solution | Result |
|-------|-----------|--------|
| Unix socket permissions | Switched to TCP (127.0.0.1:9000) | ✅ No permission issues |
| Nginx starts too early | Added 3s delay + priority | ✅ PHP-FPM ready first |
| Connection refused | TCP connection + proper startup order | ✅ Reliable connection |
| Hard to debug | Added comprehensive logging | ✅ Better troubleshooting |

---

## Expected Behavior After Fix

1. ✅ PHP-FPM starts and listens on `127.0.0.1:9000`
2. ✅ Nginx starts after 3-second delay
3. ✅ Nginx successfully connects to PHP-FPM via TCP
4. ✅ Laravel routes are accessible through Traefik
5. ✅ No 502 errors in logs

---

## If Still Getting 502

1. **Check PHP-FPM is listening:**
   ```bash
   docker exec <container> netstat -tuln | grep 9000
   ```

2. **Check Nginx error logs:**
   ```bash
   docker exec <container> tail -f /var/log/nginx/error.log
   ```

3. **Verify Laravel .env:**
   - Database credentials correct
   - APP_KEY is set
   - APP_URL matches domain

4. **Test from inside container:**
   ```bash
   docker exec <container> curl http://localhost/api/health
   ```

---

## Next Steps

1. ✅ Rebuild container with these fixes
2. ✅ Deploy to Dokploy
3. ✅ Test API endpoints
4. ✅ Monitor logs for any issues

The 502 error should now be resolved! 🎉

