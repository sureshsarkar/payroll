# Enable HTTPS — required for Live Classes (2026-06-02)

**Why:** Zoom's Web SDK and all WebRTC camera/mic/screen-share APIs use
`navigator.mediaDevices`, which browsers expose **only on a secure context
(HTTPS, or `localhost`)**. On a plain `http://` site `navigator.mediaDevices`
is `undefined`, so the live class fails with:

> Failed to start meeting: Cannot use 'in' operator to search for 'getDisplayMedia' in undefined

That is why it works **offline** (`http://localhost` = secure context) but
fails **online** (`http://yourdomain` = insecure). **There is no code
workaround** — the browser hard-blocks media on insecure origins. The site must
serve HTTPS. Do the steps below in order.

---

## Step 1 — Get an SSL certificate on the server (pick the path that matches your hosting)

### Path A — Cloudflare (fastest, if your domain's DNS is on Cloudflare)
1. Cloudflare dashboard → your domain → **SSL/TLS** → set mode to **Full** (use **Full (strict)** if your origin already has a valid cert).
2. **SSL/TLS → Edge Certificates → Always Use HTTPS = ON.**
3. (Recommended) **Automatic HTTPS Rewrites = ON** (auto-fixes http asset links).
> Because the browser ↔ Cloudflare leg is HTTPS, `navigator.mediaDevices`
> becomes available even if Cloudflare ↔ origin is still HTTP. With Cloudflare
> in front you are behind a proxy → also do Step 3c (TrustProxies).

### Path B — cPanel / shared hosting (most common in India)
1. cPanel → **SSL/TLS Status** (or **Let's Encrypt™ SSL**) → select the domain → **Run AutoSSL** / **Issue**.
2. Wait until the domain shows a valid (green) certificate.
3. cPanel → **Domains** → toggle **Force HTTPS Redirect = ON** for the domain.

### Path C — VPS with Apache (root SSH)
```bash
sudo apt install certbot python3-certbot-apache       # Debian/Ubuntu
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
# certbot installs the cert AND adds the http->https redirect automatically.
sudo certbot renew --dry-run                           # confirm auto-renewal
```

## Step 2 — Point Laravel at HTTPS
1. In the production `.env`:
   ```
   APP_URL=https://yourdomain.com
   ```
2. Rebuild config cache:
   ```bash
   php artisan config:clear && php artisan config:cache
   php artisan route:cache && php artisan view:clear
   ```
The app already force-upgrades generated URLs to https whenever `APP_URL` is
`https://…` (added in `AppServiceProvider::boot()`), so assets/links won't be
mixed-content.

## Step 3 — Force every visitor onto HTTPS

### 3a/3b — Apache / cPanel: add to `public/.htaccess` (top, inside `<IfModule mod_rewrite.c>`)
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3c — Behind a proxy / Cloudflare / load balancer (REQUIRED for Path A)
Edit `app/Http/Middleware/TrustProxies.php` and trust the proxy so Laravel reads
the forwarded `https` scheme (otherwise it still thinks requests are http →
redirect loops + http URLs):
```php
protected $proxies = '*';   // or the specific proxy/Cloudflare IP ranges
```
(The forwarded-header detection is already configured in that middleware.)

## Step 4 — Verify
1. Open `https://yourdomain.com` → browser shows the **padlock**, no "Not secure".
2. Open DevTools Console and run:
   ```js
   window.isSecureContext          // must be: true
   navigator.mediaDevices          // must be: an object (NOT undefined)
   ```
3. Open a **live class** → no `getDisplayMedia` error; the meeting joins.
   - If HTTPS is still missing, the page now shows a clear
     *"Live classes require a secure (HTTPS) connection…"* message (added in
     `zoom.blade.php`) instead of the cryptic SDK crash.
4. Console shows **no "Mixed Content"** warnings.

## Notes
- The Zoom cross-origin-isolation / permissions-policy headers are already
  applied to live-class routes (`ZoomLiveClassHeaders` middleware) — no change
  needed there.
- Camera/mic also require HTTPS, so this same fix unblocks the instructor host
  view (it shares `zoom.blade.php`).
- After enabling HTTPS, re-run a quick payment + login smoke (URLs/redirects now
  use https).
