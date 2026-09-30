# Live Server Setup — Reverb Realtime Notifications

Ye document **sirf production server** (`https://sms.induspearls.com/`) ke liye hai.
Local development ke liye `.env` me local profile active rahega — use mat badlo.

Domain: `sms.induspearls.com`
App key: `va8w8meuuausvn2ro2ak`
App id: `559260`

Setup ke baad live WebSocket test ke liye `docs/REVERB_POSTMAN_GUIDE.md` dekhein —
dono clients (super admin web + institute mobile API) ke liye.

---

## Checklist

- [ ] 1. cPanel me document root `public/` folder par set kiya
- [ ] 2. Live `.env` me live profile set kiya + `config:clear`
- [ ] 3. Code deploy kiya (`.htaccess` + PHP files)
- [ ] 4. Frontend bundle live par rebuild/upload kiya
- [ ] 5. Reverb daemon + queue worker ke cron jobs banaye
- [ ] 6. `curl` se `101 Switching Protocols` verify kiya

---

## Step 1 — Document root (cPanel)

**cPanel → Domains → `sms.induspearls.com` → Document Root**

```
/home/induspearls/sms.induspearls.com/public
```

Project ke folder ka exact naam hosting par confirm kar lein (`public/` folder jahan
hai). Document root galat hone se do problem hoti hain:

1. `public/.htaccess` ki proxy rules kabhi read nahi hoti → WebSocket 404
2. Project ki source files public ho jaati hain (`/composer.json`, `/.env`, `/artisan`)

Verify:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://sms.induspearls.com/composer.json
# 404 = sahi (docroot fix ho gaya)
# 200 = abhi bhi galat docroot hai
```

---

## Step 2 — Live `.env`

Project root me `.env` edit karein. Ye 8 lines set karein:

```dotenv
# Reverb DAEMON ka bind address (sirf server par hi chalta hai)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Laravel PUBLISH karta hai yahan — public endpoint
REVERB_HOST=sms.induspearls.com
REVERB_PORT=443
REVERB_SCHEME=https

# Browser ka WebSocket endpoint
VITE_REVERB_HOST=sms.induspearls.com
VITE_REVERB_PORT=8080
VITE_REVERB_HTTPS_PORT=443
VITE_REVERB_SCHEME=https
```

Rules:

- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` jo pehle se hain unhe
  **bilkul mat badlo** — inke bina private channel auth fail hota hai.
- Har key `.env` me **ek hi baar** ho. Duplicate keys na ho, warna kaunsa value
  Laravel lega ye unpredictable hota hai.
- `BROADCAST_CONNECTION=reverb` hona chahiye.
- Ye values `config/reverb.php` me pehle se hard-coded defaults hain, koi config
  change ki zarurat nahi.

Clear karein:

```bash
php artisan config:clear
```

> `php artisan config:cache` mat chalao jab tak `.env` sahi na ho — cached config me
> purani values atki rehti hain.

---

## Step 3 — Code deploy

Ye files live par pahunchani hain:

| File | Kyun |
|---|---|
| `public/.htaccess` | Reverb reverse proxy rules (Step 4) |
| `app/Http/Controllers/Web/NotificationController.php` | polling fallback ka JSON feed |
| `routes/web.php` | `GET /notifications/feed` route |
| `routes/api.php` | `POST /api/broadcasting/auth` — token clients (mobile) ke liye channel auth |
| `resources/js/echo.js` | live endpoint resolution |
| `resources/js/hooks/useRealtimeNotifications.js` | socket + polling fallback |
| `resources/js/Layouts/AdminLayout.jsx` | tray ka transport status |

Deploy ke baad:

```bash
git pull origin main
php artisan migrate --force
```

`laravel/reverb` package install hai ya nahi check karein:

```bash
php artisan list | grep reverb
```

Agar `reverb:start` command na mile to `composer install --no-dev` chalao.

---

## Step 4 — `public/.htaccess`

Ye file **public folder** me honi chahiye (document root wahi hai). Proxy rules
Laravel ke front controller se **pehle** honi chahiye, warna handshake par `404`
aata hai.

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Proxy Reverb before the front controller, otherwise the WebSocket
    # handshake is answered by index.php with 404 instead of 101.
    # Reverb: WebSocket on /app, HTTP API on /apps, SSE on /events.
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteCond %{REQUEST_URI} ^/(app|apps|events)(/|$) [NC]
    RewriteRule ^/(.*)$ http://127.0.0.1:8080/$1 [P,L]

    RewriteCond %{REQUEST_URI} ^/(apps|events)(/|$) [NC]
    RewriteRule ^/(.*)$ http://127.0.0.1:8080/$1 [P,L]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

`[P]` flag LiteSpeed ke external proxy (`mod_proxy`) support par depend karta hai.
Agar Step 6 me `101` na mile to hosting support se confirm karo, ya subdomain
approach use karo (`reverb.sms.induspearls.com`).

---

## Step 5 — Reverb daemon aur queue worker

`BaseNotification` `ShouldQueue` implement karta hai, isliye **dono** chahiye:

- **Reverb daemon** — WebSocket server (`127.0.0.1:8080`)
- **Queue worker** — notification ko daemon tak pahuchata hai

### Pehli baar manually check

```bash
cd /home/induspearls/sms.induspearls.com
php artisan reverb:start
```

`0.0.0.0:8080` sunne wala output aana chahiye. `Ctrl+C` se band karein (cron
khud manage karega).

### cPanel → Cron Jobs

PHP ka full path pehle se dekh lein (`which php` ya `php -v` me dikhta hai),
aur cron me wahi use karein. Common paths: `/usr/local/bin/php`,
`/opt/cpanel/ea-php84/root/usr/bin/php`.

**1) Queue worker — har minute**

```bash
* * * * * cd /home/induspearls/sms.induspearls.com && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

**2) Reverb daemon watchdog — har minute**

```bash
* * * * * cd /home/induspearls/sms.induspearls.com && pgrep -f "artisan reverb:start" > /dev/null || (nohup /usr/local/bin/php artisan reverb:start >> storage/logs/reverb.log 2>&1 &)
```

**3) Housekeeping — roz subah** (optional, log bloat na ho)

```bash
15 3 * * * cd /home/induspearls/sms.induspearls.com && php artisan queue:prune-failed --hours=48 >> /dev/null 2>&1
```

---

## Step 6 — Verify

### 6.1 WebSocket handshake (`101` = success)

```bash
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: x3JJHMbDL1EzLkh9GBhXDw==" https://sms.induspearls.com/app/va8w8meuuausvn2ro2ak
```

| Response | Matlab |
|---|---|
| `101 Switching Protocols` | Sab sahi, realtime live hai |
| `404` | Proxy active nahi (Step 1 docroot ya Step 4 `.htaccess`) |
| `403` | Document root abhi bhi project root hai |
| `301` | HTTPS/redirect rule — full `-L` ke saath dekhein |

### 6.2 Daemon port

```bash
ss -ltnp | grep 8080
```

`127.0.0.1:8080` ya `0.0.0.0:8080` sunna chahiye. Agar `0.0.0.0:8080` public
expose ho raha hai to firewall me 8080 block kar dein — proxy loopback use karta
hai, public exposure ki zarurat nahi.

### 6.3 Publishing path

```bash
php artisan tinker --execute="app(Illuminate\Contracts\Broadcasting\Factory::class)->connection()->broadcast(['reverb-check'], 'ping', ['ok' => true]); echo 'published';"
```

`published` ke saath koi error nahi aaye to Laravel → Reverb path theek hai.
`/apps/...` par HTML 404 aana matlab proxy abhi missing hai.

### 6.4 Logs

```bash
tail -n 50 storage/logs/laravel.log
tail -n 50 storage/logs/reverb.log
```

### 6.5 Browser

1. `php artisan config:clear && php artisan cache:clear`
2. Browser me hard refresh (`Ctrl+Shift+R`), ya DevTools → Network → "Disable cache"
   on karke reload
3. Notification create karein
4. Notification tray ka tooltip **Live** dikhna chahiye. Agar **Polling** dikh raha
   hai to stale bundle chal raha hai — Step 3 me bundle rebuild karein

---

## Troubleshooting

| Problem | Cause | Fix |
|---|---|---|
| `/app` par 404 | Proxy rules nahi chal rahi | Document root `public/` karo; `.htaccess` me rules front controller se pehle rakho |
| `/app` par 403 | Document root project root hai | Step 1 |
| `Connection is unauthorized` (4009) | `REVERB_APP_KEY` / `SECRET` mismatch | Live `.env` me original values rakho; daemon restart karo |
| Notifications hi nahi aati | Queue worker nahi chal raha | `php artisan queue:work` chalao; `storage/logs/laravel.log` dekho |
| Tooltip "Polling" | WebSocket fail ya stale bundle | `curl` se 101 verify karo; browser cache clear karo |
| `EADDRINUSE :8080` | Daemon pehle se chal raha hai | Ye error ignore karein — naya start fail hua, purana chal raha hai |
| Logs me `Connection refused` | Daemon band hai | Cron watchdog (Step 5) check karein |

---

## Security

- Document root **hamesha** `public/` hona chahiye. Iske bina `.env`, `composer.json`,
  `artisan`, `storage/logs/laravel.log` public ho jaate hain.
- Agar pehle docroot galat tha aur log expose hua hai: `storage/logs/laravel.log`
  clear karein (`> storage/logs/laravel.log`) aur `APP_KEY` + `REVERB_APP_SECRET`
  rotate karein.
- `config/reverb.php` me `'allowed_origins' => ['*']` hai. Domain lock karne ke liye
  use badal dein:

```php
'allowed_origins' => ['https://sms.induspearls.com'],
```

- Port `8080` firewall me bahar se band rakho.
- `storage/` aur `vendor/` ko kabhi document root se serve na karwayein.

---

## Graceful degradation

WebSocket down hone par app nahi toot-ta. Notification tray har
`VITE_REALTIME_POLL_INTERVAL` (default 15s) par `GET /notifications/feed` se data
leta rehta hai aur `Polling` mode me toast dikhata hai. Isliye:

- Reverb down ho tab bhi notifications late hi sahi, par pahunchengi
- `queue:work` aur proxy dono critical hain — inke bina realtime nahi hoga
