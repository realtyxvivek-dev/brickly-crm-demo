# Instagram Automation Phase 6 Runbook

This runbook covers the application-side production steps. Meta Dashboard configuration and app review are handled separately after this phase.

## 1. Production `.env`

Set these values on the production server:

```env
INSTAGRAM_GRAPH_VERSION=v21.0
INSTAGRAM_CLIENT_ID=
INSTAGRAM_CLIENT_SECRET=
INSTAGRAM_REDIRECT_URI=https://your-domain.com/admin/instagram-automation/callback
INSTAGRAM_SCOPES=instagram_business_basic,instagram_business_manage_comments,instagram_business_manage_messages

INSTAGRAM_WEBHOOK_VERIFY_TOKEN=
INSTAGRAM_APP_SECRET=
INSTAGRAM_WEBHOOK_SIGNATURE_ENABLED=true

QUEUE_CONNECTION=redis
```

Do not expose client secrets, verify tokens, app secrets, or access tokens in screenshots or support messages.

## 2. Deploy Commands

Run after code deployment:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run application-side readiness:

```bash
php artisan instagram:health-check
```

The command should have no `ERROR` rows before launch. `WARNING` rows can be acceptable only when they are understood, for example no connected account before Meta setup.

## 3. Queue Worker

Production must process Instagram webhook jobs outside the HTTP request.

Recommended worker:

```bash
php artisan queue:work redis --queue=instagram,default --tries=3 --backoff=10 --timeout=120
```

Supervisor settings:

```text
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
```

Check queue health:

```bash
php artisan queue:failed
php artisan queue:work redis --queue=instagram,default --once
```

## 4. Scheduler

Laravel scheduler must run every minute:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Instagram token refresh is scheduled daily in `app/Console/Kernel.php`.

Manual check:

```bash
php artisan instagram:refresh-tokens
```

## 5. Admin Readiness Panel

Open:

```text
Instagram Automation -> Settings -> Production Readiness
```

Review:

```text
Instagram app credentials
Webhook security
Production queue
Connected account
Active DM flow
Active automation rule
Token expiry
Recent webhook failures
Recent API failures
Failed Instagram jobs
Human attention queue
```

## 6. Recommended MVP Setup

DM flow:

```text
Step 1 message: Hi, aapko details chahiye thi. Aap kis city se hain?
Step 1 save_reply_as: city

Step 2 message: Please apna mobile number share kar dein.
Step 2 save_reply_as: phone

Step 3 message: Thanks. Aapki details receive ho gayi hain, hamari team jald contact karegi.
Step 3 save_reply_as: empty
```

Automation rule:

```text
Keywords: price, details, info
Public reply: Details DM me bhej diye, please check.
Priority: 100
Status: active
Active: enabled
```

## 7. Live Acceptance Tests

Happy path:

```text
Comment keyword on Instagram post
Public reply is sent
First DM is sent
Reply city
Phone question is sent
Reply valid phone
CRM lead is created with source = meta
Conversation is completed
```

Duplicate path:

```text
Use an existing phone number
No new CRM lead is created
Conversation status becomes duplicate_skipped
duplicate_lead_id is set
```

Invalid phone path:

```text
Reply invalid phone
Retry message is sent
Retries increment
After max retries, status becomes needs_human
```

Human takeover path:

```text
Click Take Over
Inbound messages are stored
Bot does not reply
Click Resume to allow automation again
```

## 8. Meta Review Assets To Prepare Later

Prepare before submitting review:

```text
Privacy Policy URL
Terms URL
Data Deletion URL
Demo Instagram Business/Creator account
Test post or reel
Demo CRM login
Screencast video
Permission explanations
```

Permissions:

```text
instagram_business_basic
instagram_business_manage_comments
instagram_business_manage_messages
```
