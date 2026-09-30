# Frontend: live API notifications

Yeh guide mobile ya kisi alag frontend app ko Reverb ke zariye logged-in user ki
notifications receive karne ke liye hai. Frontend ko **private channel** par
subscribe karna hota hai; sirf WebSocket connect karna kaafi nahi.

## 1. Environment values

| Setting | Local | Production |
|---|---|---|
| API base URL | `http://127.0.0.1:8000` | `https://sms.induspearls.com` |
| WebSocket URL | `ws://127.0.0.1:8080/app/{APP_KEY}?protocol=7&client=js&version=8.6.0&flash=false` | `ws://sms.induspearls.com/app/{APP_KEY}?protocol=7&client=js&version=8.6.0&flash=false` |
| App key | Local `.env` ka `REVERB_APP_KEY` | Production `.env` ka `REVERB_APP_KEY` |

`APP_KEY` frontend mein use karna public hai. `REVERB_APP_SECRET` kabhi frontend
mein na rakhein. Local test ke liye project mein `composer run dev` chalta rehna
chahiye; isse API server, queue worker aur Reverb start hote hain.

## 2. Login aur user ID

`POST {API_BASE_URL}/api/login` ko JSON credentials bhejein:

```json
{"email":"user@example.com","password":"user-password"}
```

Response se `data.token` aur `data.id` save karein. Token har API request mein
`Authorization: Bearer {token}` ke saath bhejna hai.

## 3. WebSocket connect

Reverb WebSocket URL par connect karein. Connection ke baad server
`pusher:connection_established` frame bhejega. Iske `data` JSON string ko parse
karke `socket_id` nikalein, misal: `988087808.412936199`.

## 4. Private channel authorize aur subscribe

Har connection/user ke liye yeh request bhejein:

`POST {API_BASE_URL}/api/broadcasting/auth`

Headers:

```text
Accept: application/json
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer {token}
```

Form body:

```text
socket_id={socket_id}
channel_name=private-App.Models.User.{user_id}
```

Response ka `auth` field WebSocket subscribe message mein bhejein:

```json
{"event":"pusher:subscribe","data":{"auth":"{response.auth}","channel":"private-App.Models.User.{user_id}"}}
```

`pusher_internal:subscription_succeeded` ka matlab subscription tayyar hai.
Sirf token wale user ke apne ID ka channel authorize hoga. Naya WebSocket
connection bane to uske naye `socket_id` ke liye auth dobara karein.

## 5. Notification event handle karein

Subscribed connection par `Illuminate\\Notifications\\Events\\BroadcastNotificationCreated`
event sunain. Event ke `data` field mein yeh payload aata hai:

```json
{
  "id": "notification-id",
  "title": "New class assignment",
  "body": "You have been assigned as a teacher...",
  "priority": "standard",
  "category": "academics",
  "type": "teacher_allocated",
  "action_text": null,
  "action_url": null,
  "data": {"user_id": 12}
}
```

Notification milte hi app UI (badge/toast/list) update karein. `id` se duplicate
events filter karna achha rahega. App foreground se background mein jaane ya
network reconnect hone par WebSocket reconnect karke private channel ko dobara
authorize/subscribe karein.

## 6. Existing notifications aur fallback

WebSocket sirf nayi live notifications deta hai. Purani/list notifications ke
liye authenticated API endpoints use karein:

- `GET {API_BASE_URL}/api/institutes/notifications` — list aur unread count
- `GET {API_BASE_URL}/api/institutes/notifications/unread-count` — badge count

In requests par bhi `Authorization: Bearer {token}` bhejein. Socket down ho to
list/unread endpoints ko refresh ya poll karke UI updated rakhein.

## Troubleshooting

- `101 Switching Protocols` + `pusher:connection_established`: socket connected.
- `pusher_internal:subscription_succeeded`: private channel subscribed.
- Auth `401`: token missing/expired; login dobara karein.
- Auth `403`: token user ID aur `user_id` channel match nahi karte.
- Subscribe ho gaya par event nahi: doosre user/admin se aisa action karein jo
  isi subscribed user ko notify karta ho; saath hi queue worker aur Reverb
  process chalte hone chahiye.

Postman ke exact local/production request examples ke liye
[`REVERB_POSTMAN_GUIDE.md`](REVERB_POSTMAN_GUIDE.md) dekhein.
