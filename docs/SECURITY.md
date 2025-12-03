# Security Configuration Guide

This document addresses the security issues found in the audit.

## Issues Fixed

### ✅ Critical: File Exposure
**Status**: Fixed via .htaccess and nginx config

Files protected:
- `.git/` directory (CRITICAL)
- `.env*` files
- `config.json`, `web.config*`
- `package.json`, `docker-compose.yml`
- Backup files (`.bak`, `.swp`, etc.)

**Implementation**:
1. `.htaccess` blocks access to sensitive files
2. `docs/nginx-security.conf` provides nginx-level protection

### ✅ High: Cookie Security Flags
**Status**: Fixed in /www/conf.php

Changes:
- `secure`: true (HTTPS only)
- `httponly`: true (XSS protection)
- `samesite`: 'Strict' (CSRF protection)

### ✅ High/Medium: Security Headers
**Status**: Fixed in index.php

Headers added:
- `Strict-Transport-Security` (HSTS)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy` (frame-ancestors)

### ⚠️ Low: Server Version Exposure
**Status**: Requires nginx configuration

## Installation

### 1. Apache (.htaccess)

The `.htaccess` file is already in place. Verify it's enabled:

```bash
# Check if .htaccess is active
curl -I https://reader.gheop.com/.git/config

# Should return: 404 Not Found or 403 Forbidden
```

### 2. Nginx Configuration

Add the contents of `docs/nginx-security.conf` to your nginx site configuration:

```bash
# Edit nginx config
sudo nano /etc/nginx/sites-available/reader.gheop.com

# Add the security directives from docs/nginx-security.conf
# Then test and reload
sudo nginx -t
sudo systemctl reload nginx
```

**Key nginx directives to add**:

```nginx
server {
    # Hide nginx version
    server_tokens off;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # Block .git and sensitive files
    location ~ /\.git {
        deny all;
    }

    location ~ \.(env|config|bak)$ {
        deny all;
    }
}
```

### 3. PHP-FPM Pool Configuration (Optional)

For additional cookie security, edit your PHP-FPM pool:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Add:
```ini
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.cookie_secure] = 1
php_admin_value[session.cookie_samesite] = "Strict"
```

Then restart:
```bash
sudo systemctl restart php8.2-fpm
```

## Verification

### Test Security Headers

```bash
curl -I https://reader.gheop.com | grep -E "(Strict-Transport|X-Content|X-Frame|X-XSS)"
```

Should show:
```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
```

### Test File Protection

```bash
# These should ALL return 403 or 404:
curl -I https://reader.gheop.com/.git/config
curl -I https://reader.gheop.com/.env
curl -I https://reader.gheop.com/web.config
curl -I https://reader.gheop.com/config.json
```

### Test Cookie Flags

1. Open https://reader.gheop.com in browser
2. Open DevTools (F12) → Application → Cookies
3. Check PHPSESSID cookie:
   - ✅ Secure: true
   - ✅ HttpOnly: true
   - ✅ SameSite: Strict

### Test Server Version Hidden

```bash
curl -I https://reader.gheop.com | grep Server
```

Should show:
```
Server: nginx
```

NOT:
```
Server: nginx/1.18.0  ❌
```

## Security Checklist

- [x] `.htaccess` file created and active
- [ ] Nginx security config applied
- [ ] Nginx server_tokens off
- [x] Cookie flags: secure, httponly, samesite
- [x] Security headers in PHP
- [ ] Test all endpoints return correct headers
- [ ] Verify .git is blocked
- [ ] Verify cookies have security flags
- [ ] Server version hidden

## Additional Recommendations

### 1. Enable HTTPS Redirect

Ensure ALL traffic uses HTTPS:

**Nginx**:
```nginx
server {
    listen 80;
    server_name reader.gheop.com;
    return 301 https://$server_name$request_uri;
}
```

### 2. Content Security Policy

Consider adding a stricter CSP:

```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; frame-ancestors 'self'");
```

### 3. Rate Limiting

Add rate limiting to prevent brute force:

**Nginx**:
```nginx
limit_req_zone $binary_remote_addr zone=reader_limit:10m rate=10r/s;

location / {
    limit_req zone=reader_limit burst=20 nodelay;
}
```

### 4. IP Whitelist for Admin

If you have admin endpoints, whitelist your IP:

```nginx
location /admin {
    allow 1.2.3.4;  # Your IP
    deny all;
}
```

### 5. Database Security

Ensure MySQL uses strong passwords and isn't exposed:

```bash
# Check MySQL isn't public
netstat -tln | grep 3306

# Should only listen on 127.0.0.1:3306
```

## Monitoring

### Log Failed Access Attempts

Monitor nginx error logs for blocked attempts:

```bash
sudo tail -f /var/log/nginx/error.log | grep -E "(\.git|\.env|config\.json)"
```

### Security Scan Tools

Regularly scan with:
- https://securityheaders.com
- https://observatory.mozilla.org
- https://www.ssllabs.com/ssltest/

## Maintenance

### Regular Updates

```bash
# Update system packages
sudo apt update && sudo apt upgrade

# Update PHP
sudo apt install php8.2-fpm php8.2-cli

# Update nginx
sudo apt install nginx
```

### Audit Schedule

- **Monthly**: Run security scanner
- **Quarterly**: Review and update dependencies
- **Yearly**: Full security audit

## Contact

For security issues, contact: jonathan@ai2h.tech

**DO NOT** disclose security vulnerabilities publicly. Report them privately first.
