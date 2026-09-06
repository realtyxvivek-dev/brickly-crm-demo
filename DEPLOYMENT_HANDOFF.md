# Brickly CRM Deployment Handoff

## Requirements

- PHP 8.1+ with `pdo_mysql`, `curl`, `mbstring`, `openssl`, `fileinfo` and `gd`
- MySQL
- Composer
- Node.js and npm for frontend builds
- A scheduler entry for Laravel tasks
- A queue worker when `QUEUE_CONNECTION` is not `sync`

Redis, SMTP, broadcasting, Firebase and third-party integrations are optional until their related modules are enabled.

## Fresh deployment

1. Clone this repository and check out `main`.
2. Copy `.env.example` to `.env` and enter environment-specific values.
3. Run `composer install --no-dev --optimize-autoloader`.
4. Run `npm install` and `npm run build`.
5. Run `php artisan key:generate`.
6. Run `php artisan migrate --force`.
7. Run the required base seeders.
8. Run `php artisan storage:link` when public storage is needed.
9. Grant write access to `storage/` and `bootstrap/cache/`.
10. Run `php artisan config:cache`, `php artisan route:cache` and `php artisan view:cache`.

The `/install` browser flow is also available for a fresh installation.

## Optional demo data

Demo data must use a separate, empty database. Set its exact database name in `DEMO_SEED_DATABASE`, then run:

```bash
php artisan db:seed --class=DemoPitchSeeder
```

The seeder stops if the configured database name does not match or if users already exist.

## Background processing

Run the Laravel scheduler every minute:

```bash
php artisan schedule:run
```

When queues are enabled, keep a supervised queue worker running and restart it after each deployment.

## Credentials and integrations

Keep all credentials outside Git. Configure provider-specific environment variables and upload service-account files directly to the server. Firebase values in the public service-worker files are placeholders in this repository and must be replaced during deployment.

Before enabling an integration, verify its webhook URL, signature/verification secret, account ownership, retry behavior and least-privilege permissions.

## Security checklist

- Use `APP_ENV=production` and `APP_DEBUG=false` outside local development.
- Use HTTPS and secure session cookies.
- Never upload `.env`, SQL dumps, customer data, logs, backups or private keys.
- Restrict database users to the application database only.
- Protect admin, export, install and deployment routes.
- Back up the database separately and test restore procedures.

## Post-deployment checks

- Login and role redirects work.
- Dashboard, lead list, lead detail and visit reports load.
- Queue, scheduler, mail and notification health checks pass when enabled.
- Storage links and permissions are correct.
- Requests for `.env`, source manifests and private application paths are blocked.
- The deployed revision matches the intended Git commit.
