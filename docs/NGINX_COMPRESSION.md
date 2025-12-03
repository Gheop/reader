# Nginx Compression Setup

## Current Status

Pre-compressed gzip assets are now generated automatically.

**Compression Ratios Achieved:**
- `lib.min.js`: 52 KB → 16 KB (70% reduction)
- `common.min.css`: 12 KB → 3.7 KB (69% reduction)
- `fontawesome/css/all.min.css`: 59 KB → 22 KB (63% reduction)
- **Total assets**: ~85 KB → ~28 KB (67% reduction)

## How It Works

The `compress_assets.sh` script creates `.gz` versions of all minified assets. When nginx is configured with `gzip_static on`, it will automatically serve these pre-compressed files instead of compressing on-the-fly.

### Benefits of Pre-compression
1. **Zero CPU overhead** - compression done once during build
2. **Maximum compression** - uses gzip level 9 (best compression)
3. **Faster response times** - no compression delay

## Nginx Configuration

### Option 1: Enable gzip_static (Recommended)

Add to your nginx site config (`/etc/nginx/sites-available/reader.conf`):

```nginx
server {
    # ... existing config ...

    location / {
        gzip_static on;  # Serve pre-compressed .gz files
    }

    location ~* \.(js|css|svg|json)$ {
        gzip_static on;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Then reload nginx:
```bash
sudo nginx -t && sudo nginx -s reload
```

### Option 2: Enable Dynamic Gzip (If gzip_static not available)

Edit `/etc/nginx/nginx.conf` and change:

```nginx
gzip off;  # Change this line
```

To:

```nginx
gzip on;
gzip_vary on;
gzip_comp_level 6;
gzip_min_length 1000;
gzip_types text/plain text/css text/javascript application/javascript application/json application/manifest+json image/svg+xml;
```

## Maintenance

Run `./compress_assets.sh` after:
- Minifying JS/CSS files
- Updating themes
- Changing manifest.json or icon.svg

Or integrate into your build process.

## Testing Compression

Test if compression is working:

```bash
# Test with gzip
curl -H "Accept-Encoding: gzip" -I https://reader.gheop.com/lib.min.js

# Should see: Content-Encoding: gzip
```

Check file size:
```bash
curl -H "Accept-Encoding: gzip" -so /dev/null -w '%{size_download}\n' https://reader.gheop.com/lib.min.js
# Should show ~15-16KB instead of ~52KB
```

## Future: Brotli Compression

For even better compression (5-15% smaller than gzip), consider compiling nginx with brotli module:

```bash
# Install brotli module
sudo apt-get install nginx-module-brotli

# Then enable in nginx.conf
brotli on;
brotli_static on;
brotli_comp_level 6;
brotli_types text/plain text/css application/javascript application/json image/svg+xml;
```

Brotli would reduce assets by additional 5-10% compared to gzip.
