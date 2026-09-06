# Brickly CRM Demo

Brickly CRM is a unified real-estate operations platform for managing leads, sales activity, site visits, post-sales work, HR, finance, reporting and integrations from one system.

## Live demo

- Product: https://demo.bihtech.in
- Role-based quick login: https://demo.bihtech.in/quick-login
- Website: https://bihtech.in

The public demo contains synthetic data only. Quick login is restricted in code to the `demo.bihtech.in` host.

## Core modules

- Centralized lead capture, assignment, follow-up and audit workflows
- Meetings, site visits, verification and sales pipeline tracking
- Lead bank, source/project reporting and CSV/PDF exports
- Marketing, Meta/Instagram/WhatsApp and webhook integration workflows
- Employee, attendance, payroll, targets and productivity workflows
- Booking, post-sales, collections, expenses and purchase orders
- Role-based dashboards, permissions, notifications and audit history

Some external integrations require the account owner's credentials and provider approval. Roadmap or in-development capabilities should not be represented as live without verification.

## Technology

- PHP 8.1+ and Laravel 10
- MySQL
- Vite frontend assets
- Optional Redis queues/cache
- Optional Pusher-compatible broadcasting and Firebase/Web Push

## Local setup

1. Copy `.env.example` to `.env` and add local values.
2. Run `composer install`.
3. Run `npm install`.
4. Run `php artisan key:generate`.
5. Run `php artisan migrate`.
6. Run `php artisan db:seed` for base reference data.
7. Run `npm run build`.
8. Run `php artisan serve`.

Fresh installs may also use `/install` to configure the environment, test the database, run migrations and create the first admin.

## Optional demo data

`DemoPitchSeeder` refuses to run unless the database is empty and its exact name is supplied through `DEMO_SEED_DATABASE` in `.env`.

```bash
php artisan db:seed --class=DemoPitchSeeder
```

Never point the demo seeder at a production database.

## Repository safety

This repository intentionally excludes environment files, credentials, databases, SQL dumps, logs, backups, runtime uploads, customer media, generated exports, dependencies and compiled app bundles. Firebase service-worker values are placeholders and must be configured for each deployment.

See `outputs/brickly-documentation/DEMO-DEPLOYMENT.md` for the verified demo deployment summary.

## License

Proprietary source provided for product evaluation. No redistribution or production use without written permission from the owner.
