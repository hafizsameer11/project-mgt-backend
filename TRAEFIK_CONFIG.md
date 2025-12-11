# Traefik Configuration for Laravel Backend

## Current Port Configuration

- **Container Port**: `80`
- **Protocol**: HTTP
- **Nginx**: Listening on port 80 inside the container

## Traefik Labels for Dokploy

When deploying with Traefik in Dokploy, you need to add the following labels to your container:

### Basic Traefik Configuration

```yaml
# Traefik Labels
traefik.enable: "true"
traefik.http.routers.laravel-backend.rule: "Host(`api.yourdomain.com`)"
traefik.http.routers.laravel-backend.entrypoints: "web"
traefik.http.services.laravel-backend.loadbalancer.server.port: "80"
```

### Complete Traefik Configuration with HTTPS

```yaml
# Enable Traefik
traefik.enable: "true"

# HTTP Router
traefik.http.routers.laravel-backend-http.rule: "Host(`api.yourdomain.com`)"
traefik.http.routers.laravel-backend-http.entrypoints: "web"
traefik.http.routers.laravel-backend-http.middlewares: "redirect-to-https"

# HTTPS Router
traefik.http.routers.laravel-backend.rule: "Host(`api.yourdomain.com`)"
traefik.http.routers.laravel-backend.entrypoints: "websecure"
traefik.http.routers.laravel-backend.tls.certresolver: "letsencrypt"

# Service Configuration
traefik.http.services.laravel-backend.loadbalancer.server.port: "80"

# Redirect HTTP to HTTPS
traefik.http.middlewares.redirect-to-https.redirectscheme.scheme: "https"
traefik.http.middlewares.redirect-to-https.redirectscheme.permanent: "true"
```

## Dokploy Configuration Steps

### Option 1: Using Dokploy UI

1. Go to your application settings in Dokploy
2. Navigate to **Environment Variables** or **Labels** section
3. Add the following labels:

```
traefik.enable=true
traefik.http.routers.laravel-backend.rule=Host(`api.yourdomain.com`)
traefik.http.routers.laravel-backend.entrypoints=web
traefik.http.services.laravel-backend.loadbalancer.server.port=80
```

4. If you want HTTPS (recommended):
   - Add `traefik.http.routers.laravel-backend.entrypoints=websecure`
   - Add `traefik.http.routers.laravel-backend.tls.certresolver=letsencrypt`

### Option 2: Using docker-compose.yml (if Dokploy supports it)

If Dokploy allows docker-compose configuration, you can add:

```yaml
services:
  app:
    # ... your existing config ...
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.laravel-backend.rule=Host(`api.yourdomain.com`)"
      - "traefik.http.routers.laravel-backend.entrypoints=web"
      - "traefik.http.services.laravel-backend.loadbalancer.server.port=80"
      # For HTTPS:
      - "traefik.http.routers.laravel-backend.entrypoints=websecure"
      - "traefik.http.routers.laravel-backend.tls.certresolver=letsencrypt"
```

## Important Notes

1. **Port 80**: Your container exposes port 80, which Traefik will route to
2. **Domain**: Replace `api.yourdomain.com` with your actual domain
3. **Entrypoints**: 
   - `web` = HTTP (port 80)
   - `websecure` = HTTPS (port 443)
4. **Certificate Resolver**: Use `letsencrypt` for automatic SSL certificates

## Testing

After configuration:

1. **Check Traefik is routing**: Visit `http://api.yourdomain.com/api/health` (or any API endpoint)
2. **Check HTTPS**: Visit `https://api.yourdomain.com/api/health`
3. **Verify Laravel**: The API should respond with Laravel routes

## Troubleshooting

### If you get 502 Bad Gateway:
- Verify the container is running: `docker ps`
- Check container logs: `docker logs <container-name>`
- Verify port 80 is exposed: Check `EXPOSE 80` in Dockerfile
- Check Traefik service: `docker ps | grep traefik`

### If domain doesn't resolve:
- Verify DNS points to your server
- Check Traefik dashboard (usually `http://your-server:8080`)
- Verify the Host rule matches your domain exactly

### If HTTPS doesn't work:
- Ensure Let's Encrypt resolver is configured in Traefik
- Check Traefik logs for certificate issues
- Verify port 443 is open on your server

## Environment Variables for Laravel

Make sure your Laravel `.env` has:

```env
APP_URL=https://api.yourdomain.com
```

This ensures Laravel generates correct URLs for API responses.

