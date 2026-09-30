# Live WebSocket Test — Postman Guide

Ye guide **sirf production server** (`https://sms.induspearls.com/`) ke liye hai aur
sirf **live notification (Reverb WebSocket)** verify karti hai. Server ka setup
(docroot, `.env`, daemon, cron) `docs/REVERB_SERVER_SETUP.md` me hai — wo pehle
complete ho.

Do alag clients hain, isliye do parts:

| Part | Kaun | Auth |
|---|---|---|
| **1** | Super admin (web dashboard) | Session cookie + CSRF |
| **2** | Institute app (mobile/API) | Sanctum Bearer token |

Dono **same WebSocket endpoint** aur same Reverb app use karte hain — sirf channel
auth ka route alag hai (`/broadcasting/auth` vs `/api/broadcasting/auth`).

```
Postman  ──ws──▶  sms.induspearls.com  ──proxy──▶  Reverb daemon (127.0.0.1:8080)
```

---

## 1. Environment

Postman → **Environments → New → "SMS Live"**. Ye variables banao:

### Dono parts ke liye

| Variable | Value |
|---|---|
| `base_url` | `https://sms.induspearls.com` |
| `ws_url` | `ws://sms.induspearls.com/app/va8w8meuuausvn2ro2ak` |
| `reverb_api` | `https://sms.induspearls.com` |
| `app_id` | `559260` |
| `app_key` | `va8w8meuuausvn2ro2ak` |
| `app_secret` | `53mg5uhneldwvhmpgqlt` |
| `laravel_app_key` | live `.env` ka `APP_KEY` (`base64:...` ke saath) |
| `socket_id` | WebSocket connect ke baad `connection_established` se |
| `channel_auth` | channel auth response se aya `auth` value |
| `sign_query` | `reverb-sign` script khud banata hai |

### Part 1 — super admin

| Variable | Value |
|---|---|
| `admin_email` | live super admin email |
| `admin_password` | live super admin password |
| `user_id` | super admin ka user id (usually `1`) |

### Part 2 — institute app

| Variable | Value |
|---|---|
| `staff_email` / `staff_password` | wo teacher/staff jiska channel subscribe karna hai |
| `api_token` | `POST /api/login` se (staff) |
| `staff_user_id` | staff ka user id — `GET /api/user` se |
| `owner_email` / `owner_password` | doosra account (admin/owner) jo action karega |
| `api_token_owner` | `POST /api/login` se (admin/owner) |

> `app_secret` aur `laravel_app_key` sensitive hain. Collection **export** ya git me
> commit mat karo. Ye sirf aapke local Postman me rahe.

---

# Part 1 — Super admin (web session)

## P1.1 `GET {{base_url}}/login`

Cookie set karne ke liye. Koi header nahi.

**Expected:** `200` (Inertia login page) + response headers me `Set-Cookie:
XSRF-TOKEN` aur `Set-Cookie: <app-name>-session` (`APP_NAME` se banta hai, jaise
`sms-session`).

Cookie Postman ke cookie jar me automatically save ho jaate hain — bas **Send**
dabao, manually cookie paste karne ki zarurat nahi.

## P1.2 `POST {{base_url}}/login`

| | |
|---|---|
| Pre-request script | `xsrf` (section S1) |
| Headers | `Content-Type: application/json`<br>`Accept: application/json`<br>`X-XSRF-TOKEN: {{xsrf}}`<br>`X-Requested-With: XMLHttpRequest` |
| Body (raw JSON) | `{ "email": "{{admin_email}}", "password": "{{admin_password}}" }` |

**Expected:** `302` → `/dashboard` (Postman "Follow redirects" par ho to `200`
dashboard HTML).

| Problem | Matlab |
|---|---|
| `422` + validation errors | Email/password galat |
| `302` back + "This portal is for Super Admins only." | User `is_admin = false` ya inactive — web portal sirf super admins ke liye hai |
| `419` | CSRF token stale — `GET /login` dobara chalao |

## P1.3 `GET {{base_url}}/dashboard`

Sirf `{{user_id}}` nikaalne ke liye. Response HTML me Inertia props JSON hota hai;
usme `"id":` search karo — wahi super admin ka user id hai.

## P1.4 WebSocket connect

**New → WebSocket**, URL `{{ws_url}}`:

```
ws://sms.induspearls.com/app/va8w8meuuausvn2ro2ak
```

**Expected frame:**

```json
{"event":"pusher:connection_established","data":"{\"socket_id\":\"123456.789012\",\"activity_timeout\":30}"}
```

Isi `socket_id` ko environment me `socket_id` variable me daal do.

| Response | Meaning |
|---|---|
| `101` + `connection_established` | Sab sahi — proxy kaam kar raha hai |
| `404` | Reverse proxy rules missing (setup doc Step 1 + Step 4) |
| `403` | Document root abhi bhi project root hai (setup doc Step 1) |
| `401 / Connection is unauthorized` | `app_key` mismatch — daemon `.env` ke saath restart nahi hua |
| TLS / certificate error | Live site HTTPS-only hai, `ws://` ka option nahi — certificate chain ya SNI issue hosting support se check karo |

## P1.5 `POST {{base_url}}/broadcasting/auth`

Private channel ka signature yahin se milta hai (web session ke liye).

| | |
|---|---|
| Pre-request script | `xsrf` (section S1) |
| Headers | `Content-Type: application/x-www-form-urlencoded`<br>`Accept: application/json`<br>`X-XSRF-TOKEN: {{xsrf}}`<br>`X-Requested-With: XMLHttpRequest` |
| Body (form-urlencoded) | `socket_id={{socket_id}}`<br>`channel_name=private-App.Models.User.{{user_id}}` |

**Expected:**

```json
{ "auth": "va8w8meuuausvn2ro2ak:9f2c1ab4e7d34c0f8a6b5d4e3c2b1a0f9e8d7c6b5" }
```

**Test script** (optional) — value `channel_auth` variable me save ho jaati hai:

```js
const json = pm.response.json();
pm.environment.set('channel_auth', json.auth);
```

| Problem | Matlab |
|---|---|
| `403` | `user_id` logged-in user ka nahi hai (channel callback reject) |
| `419` | CSRF token stale — `GET /login` dobara chalao |
| `302` login page | Session nahi — P1.1 aur P1.2 dobara chalao |

## P1.6 Subscribe

WebSocket window ke message box me bhejo (WS message box me variables resolve nahi
hote — value copy-paste karo):

```json
{"event":"pusher:subscribe","data":{"auth":"<P1.5 ka auth value>","channel":"private-App.Models.User.1"}}
```

**Expected frame:**

```json
{"event":"pusher_internal:subscription_succeeded","channel":"private-App.Models.User.1","data":"{\"socket_id\":\"123456.789012\"}"}
```

`pusher:error` aaye to `auth` value stale hai ya channel name user id se match nahi
karta — P1.5 dobara chalao.

## P1.7 Test notification inject (app ke bina)

Ye **full socket path** test karta hai — DB me koi row nahi banti.

`POST {{reverb_api}}/apps/{{app_id}}/events?{{sign_query}}`

| | |
|---|---|
| Pre-request script | `reverb-sign` (section S2) — `sign_query` banata hai |
| Headers | `Content-Type: application/json` |
| Body (raw JSON, **ek hi line**) | neeche |

```json
{"name":"Illuminate\\Notifications\\Events\\BroadcastNotificationCreated","channel":"private-App.Models.User.{{user_id}}","data":"{\"id\":\"postman-check\",\"title\":\"Postman test\",\"body\":\"Reverb live hai\",\"priority\":\"standard\",\"category\":\"general\",\"type\":\"postman_check\",\"action_text\":null,\"action_url\":null,\"data\":{}}"}
```

`data` **double-encoded** hota hai (andar ka payload ek JSON *string* hai) — Reverb
use `string` maangta hai, warna `422` aata hai.

**Expected:** `200 {}` aur WebSocket window me ye frame:

```json
{"event":"Illuminate\\Notifications\\Events\\BroadcastNotificationCreated","channel":"private-App.Models.User.1","data":"{\"id\":\"postman-check\",\"title\":\"Postman test\",\"body\":\"Reverb live hai\",\"priority\":\"standard\",\"category\":\"general\",\"type\":\"postman_check\",\"action_text\":null,\"action_url\":null,\"data\":{}}"}
```

Ye wahi shape hai jo `BaseNotification::toBroadcast()` bhejta hai
(`app/Notifications/BaseNotification.php:64`), isliye frontend ka tray ise waise hi
render karta hai.

| Response | Meaning |
|---|---|
| `200 {}` | Publish ho gaya — frame WebSocket window me aana chahiye |
| `401 Authentication signature invalid.` | `app_secret` galat, ya `body_md5` raw body se match nahi kar raha |
| `404 No matching application for ID [559260].` | Daemon ki app id se mismatch — daemon restart / `.env` check karo |
| `422` | `name` ya `data` missing, ya `data` plain object hai string ke bajaye |

## P1.8 Asli notification (queue + database ke saath)

P1.7 sirf socket path check karta hai. Poora chain (queue → database → broadcast)
test karne ke liye live server par SSH se:

1. WebSocket window me subscribed rehne do
2. Live server par ek **real** notification bhejo:

   ```bash
   cd /home/induspearls/sms.induspearls.com
   php artisan tinker --execute="App\Services\NotificationService::toSuperAdmins(new App\Notifications\StudentAdmittedNotification('Test Institute', 'Ali Khan', 'Grade 1 - Section A'));"
   ```

   Ye har super admin ko `database + broadcast` dono channels par bhejta hai
   (`app/Services/NotificationService.php:58`).
3. Frame **tab** aayega jab `queue:work` cron chal raha ho — `BaseNotification`
   `ShouldQueue` implement karta hai (`app/Notifications/BaseNotification.php:12`),
   isliye worker band ho to kuch nahi aayega
4. `GET {{base_url}}/notifications/feed` par ab ye notification dikhna chahiye, aur
   live dashboard ka tray bhi badge update karega

---

# Part 2 — Institute app (API + Bearer token)

Mobile app Sanctum token se authenticate hota hai aur uska apna channel
`App.Models.User.{id}` hota hai. Channel auth ke liye route `routes/api.php` me
register hota hai:

```
POST /api/broadcasting/auth   →  middleware: auth:sanctum
```

## P2.1 `POST {{base_url}}/api/login`

| | |
|---|---|
| Headers | `Content-Type: application/json`<br>`Accept: application/json` |
| Body (raw JSON) | `{ "email": "{{staff_email}}", "password": "{{staff_password}}" }` |

**Expected:**

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "id": 12,
    "name": "Ali Khan",
    "email": "ali@example.com",
    "is_institute": true,
    "is_admin": false,
    "verified": true,
    "type": "Bearer",
    "token": "1|abcdefghijklmnopqrstuvwxyz0123456789..."
  }
}
```

**Test script** — token aur user id dono save:

```js
const json = pm.response.json();
pm.environment.set('api_token', json.data.token);
pm.environment.set('staff_user_id', String(json.data.id));
```

| Problem | Matlab |
|---|---|
| `401` "Invalid credentials" | Email/password galat |
| `403` "Please verify your email address" | Email verify nahi hai |
| `403` "Your account has been deactivated" | User inactive |
| `422` | Password 6 char se chhota ya email invalid |
| `429` | `throttle:5,1` — ek minute me 5 login se zyada |

**Doosra token** (admin/owner — action karne wala) wahi request `{{owner_email}}` /
`{{owner_password}}` se banao, test script me `api_token_owner` set karo.

## P2.2 `GET {{base_url}}/api/user`

Token verify karne ke liye. Headers: `Authorization: Bearer {{api_token}}`.

**Expected:** user object (`id`, `name`, `email`, …) — `id` aur `staff_user_id`
match hona chahiye.

## P2.3 WebSocket connect

Wahi URL, wahi Reverb app — Part 1 ke WebSocket request ko hi reuse kar sakte ho:

```
ws://sms.induspearls.com/app/va8w8meuuausvn2ro2ak
```

`socket_id` naya milega (naya connection) — wo `socket_id` variable me daal do.
Do clients ek saath connect ho sakte hain, har apna alag `socket_id` rakhega.

## P2.4 `POST {{base_url}}/api/broadcasting/auth`

| | |
|---|---|
| Headers | `Content-Type: application/x-www-form-urlencoded`<br>`Accept: application/json`<br>`Authorization: Bearer {{api_token}}` |
| Body (form-urlencoded) | `socket_id={{socket_id}}`<br>`channel_name=private-App.Models.User.{{staff_user_id}}` |

CSRF ki zarurat nahi — Bearer token sanitize hota hai. Same
`{ "auth": "key:signature" }` response milega; wahi value `channel_auth` me daal do.

| Problem | Matlab |
|---|---|
| `401 Unauthenticated.` | Token missing/expired — P2.1 dobara chalao |
| `403` | `staff_user_id` token wale user ka nahi hai |

## P2.5 Subscribe

```json
{"event":"pusher:subscribe","data":{"auth":"<P2.4 ka auth value>","channel":"private-App.Models.User.12"}}
```

`pusher_internal:subscription_succeeded` aana chahiye.

## P2.6 Asli notification bhejo (doosre account se)

Notification **us user ko** jaata hai jise action affect karta hai — isliye action
`{{owner_email}}` se karo, jabki socket `{{staff_email}}` ke channel par subscribed
hai. Example: admin teacher ko subject assign karta hai, teacher ko notification
milta hai.

`POST {{base_url}}/api/institutes/subject-teachers`

| | |
|---|---|
| Headers | `Content-Type: application/json`<br>`Accept: application/json`<br>`Authorization: Bearer {{api_token_owner}}` |
| Body (raw JSON) | `{ "session_id": 1, "class_id": 3, "section_id": 2, "allocations": [{ "subject_id": 1, "teacher_id": {{staff_user_id}} }] }` |

**Expected:** `201` "Subject teachers assigned successfully" aur WebSocket window
me frame:

```json
{"event":"Illuminate\\Notifications\\Events\\BroadcastNotificationCreated","channel":"private-App.Models.User.12","data":"{\"title\":\"New class assignment\",\"body\":\"You have been assigned as a teacher for Mathematics in Grade 1 Section A.\",\"priority\":\"standard\",\"category\":\"academics\",\"type\":\"teacher_allocated\",\"action_text\":null,\"action_url\":null,\"data\":{\"user_id\":12,\"details\":\"Mathematics in Grade 1 Section A\"}}"}
```

`teacher_id` `{{staff_user_id}}` hi rakho — warna notification kisi aur ke channel
par jayegi.

### Aur triggers (same pattern)

Ye sab `NotificationService` se jaati hain — teeno me **doosre** user ko notify
karta hai, isliye `{{api_token_owner}}` use karo:

| Action | Notification | Koh jaata hai |
|---|---|---|
| `POST /api/institutes/subject-teachers` | `TeacherAllocatedNotification` | assigned teacher |
| `POST /api/institutes/section-teachers` | `TeacherAllocatedNotification` | assigned teacher |
| `POST /api/institutes/room-teachers` | `TeacherAllocatedNotification` | homeroom teacher |
| `POST /api/institutes/students` | `StudentAdmittedNotification` | institute ke admin/receptionist (requestor ko chhod kar) |
| `PUT /api/institutes/timetable/...` (publish) | `TimetablePublishedNotification` | affected teachers |
| `POST /api/institutes/users` | `StaffCreatedNotification` + `StaffWelcomeNotification` | naya staff |
| `PUT /api/institutes/roles/{id}` | `PermissionsUpdatedNotification` | role holders |

`priority: high` wale (`StaffWelcome`, `Invoice`, `Payment*`) email bhi bhejte
hain — live par test karte waqt spam mat karo.

### Bina action ke test

Part 1 ke `reverb-sign` wala request (`POST /apps/559260/events`) yahin bhi chalta
hai — sirf `channel` me `private-App.Models.User.{{staff_user_id}}` daal do. Us
event ki koi DB row nahi banti, isliye feed/list API me nahi dikhega.

## P2.7 Notification REST endpoints

Ye sab `Authorization: Bearer` se chalti hain aur sirf `auth:sanctum` maangti hain
(subscription active hona zaroori nahi):

| Method | URL | Kaam |
|---|---|---|
| `GET` | `/api/institutes/notifications` | List (`per_page`, `status=read\|unread`, `priority`, `category`) |
| `GET` | `/api/institutes/notifications/unread-count` | Badge count |
| `PATCH` | `/api/institutes/notifications/{id}/read` | Ek mark as read |
| `PATCH` | `/api/institutes/notifications/read-all` | Sab mark as read |
| `DELETE` | `/api/institutes/notifications/{id}` | Delete |

`GET /api/institutes/notifications` ka response:

```json
{
  "status": "success",
  "message": "Notifications retrieved successfully",
  "data": {
    "notifications": [
      {
        "id": "9b1d...",
        "type": "teacher_allocated",
        "title": "New class assignment",
        "body": "You have been assigned as a teacher for Mathematics in Grade 1 Section A.",
        "priority": "standard",
        "category": "academics",
        "action_text": null,
        "action_url": null,
        "data": { "user_id": 12, "details": "Mathematics in Grade 1 Section A" },
        "read_at": null,
        "created_at": "2026-09-27 10:12:44",
        "created_at_diff": "1 minute ago"
      }
    ],
    "pagination": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 },
    "meta": { "unread_count": 1 }
  }
}
```

`{id}` me wahi `id` daalo jo list me mila. Ye table usi
`notifications` table ko padhti hai jo broadcast ke time populate hoti hai — isliye
P2.6 ke turant baad yahan nayi row dikhni chahiye.

---

## S. Scripts

Dono parts me same do scripts chalti hain.

### S.1 `xsrf` (pre-request) — sirf Part 1

Laravel `XSRF-TOKEN` cookie ka value encrypt karke rakhta hai, aur header me
**decrypted** token bhejna padta hai. Isliye live `APP_KEY` chahiye:

```js
// XSRF-TOKEN cookie ko APP_KEY se AES-256-CBC decrypt karke plain token nikalo.
const cookie = pm.cookies.get('XSRF-TOKEN');

if (!cookie) {
    throw new Error('XSRF-TOKEN cookie nahi mili — pehle GET {{base_url}}/login chalao');
}

const crypto = typeof CryptoJS !== 'undefined' ? CryptoJS : pm.require('npm:crypto-js@4.2.0');
const appKey = pm.environment.get('laravel_app_key');

if (!appKey) {
    throw new Error('laravel_app_key environment variable khaali hai');
}

// Cookie value = base64(JSON { iv, value, mac, tag })
const payload = JSON.parse(atob(decodeURIComponent(cookie.value)));
const key = crypto.enc.Utf8.parse(crypto.enc.Base64.parse(appKey.replace('base64:', '')));

const decrypted = crypto.AES.decrypt(
    { ciphertext: crypto.enc.Base64.parse(payload.value) },
    key,
    {
        iv: crypto.enc.Base64.parse(payload.iv),
        mode: crypto.mode.CBC,
        padding: crypto.pad.NoPadding,
    }
);

// Laravel CookieValuePrefix 41 char ka hash prefix jodta hai — hata do.
pm.environment.set('xsrf', decrypted.toString(crypto.enc.Utf8).slice(41));
```

Ye script `P1.2` aur `P1.5` dono par lagein. Part 2 (Bearer token) me iski
zarurat nahi.

### S.2 `reverb-sign` (pre-request) — dono parts

Reverb ka HTTP API Pusher signature verify karta hai:

```
HMAC-SHA256("<METHOD>\n<path>\n<sorted query incl. body_md5>", app_secret)
```

```js
// Reverb HTTP API ka Pusher signature banao aur ?{{sign_query}} ke liye query string
// bana do. Method/path/body current request se hi padhe jaate hain.
const crypto = typeof CryptoJS !== 'undefined' ? CryptoJS : pm.require('npm:crypto-js@4.2.0');

const url = pm.variables.replaceIn(pm.request.url);
const path = url.split('?')[0].replace(/^https?:\/\/[^/]+/, '');
const method = pm.request.method.toUpperCase();

// replaceIn zaroori hai: Postman body me {{staff_user_id}} jaise variables bhejne
// se pehle replace karta hai. MD5 bhi isi resolved text ka banega, warna 401 aayega.
const body = pm.request.body.mode === 'raw'
    ? pm.variables.replaceIn(pm.request.body.raw || '')
    : '';

const params = {
    auth_key: pm.environment.get('app_key'),
    auth_timestamp: Math.floor(Date.now() / 1000).toString(),
    auth_version: '1.0',
};

if (body !== '') {
    params.body_md5 = crypto.MD5(body).toString(crypto.enc.Hex);
}

// Sign karne wale same keys/values query me jate hain (auth_signature add hota hai).
const canonical = Object.keys(params)
    .sort()
    .map((key) => `${key}=${params[key]}`)
    .join('&');

const signature = crypto
    .HmacSHA256([method, path, canonical].join('\n'), pm.environment.get('app_secret'))
    .toString(crypto.enc.Hex);

const query = Object.keys({ ...params, auth_signature: signature })
    .map((key) => `${key}=${key === 'auth_signature' ? signature : params[key]}`)
    .join('&');

pm.environment.set('sign_query', query);
```

Script aur body ko **same request** me run karo — `body_md5` raw body se banta hai,
body badli to signature fail ho jaata hai.

---

## Troubleshooting

| Problem | Cause | Fix |
|---|---|---|
| WS `404` | Reverse proxy rules nahi | Setup doc Step 1 (docroot) + Step 4 (`.htaccess`) |
| WS `403` | Docroot project root hai | Setup doc Step 1 |
| WS `Connection is unauthorized` (4009) | Daemon ki app key mismatch | Live `.env` me `REVERB_APP_KEY` check karo, daemon restart |
| `pusher_internal:subscription_succeeded` nahi | `auth` stale ya channel name galat | Latest `socket_id` ke saath auth dobara |
| `403` par channel auth | Channel ka `{id}` logged-in user se match nahi | Part 1: `user_id`; Part 2: `staff_user_id` — dono apne user ki id |
| `401` par `/api/broadcasting/auth` | Route live nahi hai | `routes/api.php` deploy nahi hua — `git pull` + `php artisan config:clear` |
| `401 Unauthenticated` par API | Token expire / galat | P2.1 dobara chalao |
| Frame aata hai par list API me nahi | Test event DB me store nahi hota | P2.6 wala real action use karo |
| Frame hi nahi aaya | `queue:work` cron band | `php artisan queue:work` chalao; `storage/logs/laravel.log` dekho |
| `401` par `/apps/.../events` | `app_secret` ya `body_md5` galat | `.env` se match karo, script ko body ke saath chalao |
| `419 Token Mismatch` | CSRF token expire | `GET {{base_url}}/login` dobara chalao |
| Sab theek par UI me "Polling" | Purana bundle live hai | `php artisan config:clear`, phir hard refresh |

Logs, jahan zaroorat pade:

```bash
tail -n 50 storage/logs/laravel.log
tail -n 50 storage/logs/reverb.log
```

---

# Appendix — Local testing (sirf development ke liye)

Live ka pura flow local pe bhi chalta hai, bas URL badalte hain — koi reverse proxy
nahi hai, daemon seedha `127.0.0.1:8080` par sunta hai.

## L.0 Environment "SMS Local"

| Variable | Value |
|---|---|
| `base_url` | `http://sms.test` |
| `ws_url` | `ws://127.0.0.1:8080/app/va8w8meuuausvn2ro2ak` |
| `reverb_api` | `http://127.0.0.1:8080` |
| `app_id` | `559260` |
| `app_key` | `va8w8meuuausvn2ro2ak` |
| `app_secret` | `53mg5uhneldwvhmpgqlt` |
| `laravel_app_key` | local `.env` ka `APP_KEY` |
| baaki vars | same (staff/owner credentials local ke hisaab se) |

`.env` me local profile already active hai (`REVERB_HOST=127.0.0.1`,
`REVERB_PORT=8080`, `REVERB_SCHEME=http`), isliye kuch badalne ki zarurat nahi.

Daemon aur queue chalte hone chahiye:

```bash
php artisan reverb:start     # 127.0.0.1:8080
php artisan queue:work       # notifications deliver hoti hain
```

Ya `composer dev` (dono + Vite ek saath).

## L.1 Sabse tez test — login ke bagair

Sirf socket path check karna hai to Part 1.7 wala request chalao. Live par jo URL
tha, yahan seedha daemon par jayega:

```
POST http://127.0.0.1:8080/apps/559260/events?{{sign_query}}
```

Body wahi rahega (`channel` me apna user id daalo). `{{sign_query}}` script se banta
hai — `reverb-sign` (S.2) **badalta nahi**, kyunki `reverb_api` variable hi badla
hai. `200 {}` aaye to daemon healthy hai.

WS connect karke `pusher:connection_established` + `pusher_internal:subscription_succeeded`
dekh lo, phir ye request se frame aa jayega.

## L.2 Recommended test — API + token (CSRF se bachao)

Local pe **Part 2** flow sabse aasan hai, kyunki Bearer token me CSRF ki zarurat
nahi hoti:

| # | Request | URL |
|---|---|---|
| 1 | `POST` login | `http://sms.test/api/login` |
| 2 | `GET` me verify | `http://sms.test/api/user` |
| 3 | WS connect | `ws://127.0.0.1:8080/app/va8w8meuuausvn2ro2ak` |
| 4 | channel auth | `POST http://sms.test/api/broadcasting/auth` |
| 5 | subscribe frame | WS window me `pusher:subscribe` |
| 6 | real notification | `POST http://sms.test/api/institutes/subject-teachers` (doosre token se) |
| 7 | list verify | `GET http://sms.test/api/institutes/notifications` |

Bodies/headers Part 2.1–P2.7 me hain — bas `base_url` `http://sms.test` ho.

## L.3 Web session track bhi chalta hai

Part 1 (super admin) local pe bhi chalta hai — `GET http://sms.test/login`,
`POST http://sms.test/login`, `POST http://sms.test/broadcasting/auth`. Bas
`laravel_app_key` local `.env` wala hona chahiye taaki `xsrf` script token decrypt
kar sake.

Local `allowed_origins` `['*']` hai aur `app/Http/Controllers/Web/LoginController.php`
sirf `is_admin` users ko allow karta hai — to Part 1 ke liye super admin account
chahiye, warna "This portal is for Super Admins only." milega.

## L.4 Local troubleshooting

| Problem | Fix |
|---|---|
| WS `ECONNREFUSED` | `php artisan reverb:start` nahi chal raha |
| `401` par `/apps/.../events` | `.env` ke `REVERB_APP_SECRET` se `app_secret` match nahi kar raha |
| `404` par `/apps/.../events` | `REVERB_SERVER_PORT` 8080 nahi hai |
| `Connection refused` log me | Queue worker band — `php artisan queue:work` chalao |
| `laravel_app_key` galat | `php artisan config:clear` ke baad `.env` dobara padho |
