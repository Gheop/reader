# Manual Configuration Required

## /www/conf.php

This file is outside the git repository. Manual changes required:

### Replace session_set_cookie_params

**OLD**:
```php
session_set_cookie_params(3600);
```

**NEW**:
```php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,      // Only send over HTTPS
    'httponly' => true,    // Prevent JavaScript access (XSS protection)
    'samesite' => 'Strict' // CSRF protection
]);
```

## Location

File: `/www/conf.php` (lines 6-8)

## Verification

After making changes, test cookie security:
1. Open https://reader.gheop.com
2. DevTools (F12) → Application → Cookies
3. Check PHPSESSID has: Secure ✓, HttpOnly ✓, SameSite: Strict ✓
