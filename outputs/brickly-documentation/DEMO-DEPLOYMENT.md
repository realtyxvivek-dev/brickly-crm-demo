# Brickly demo deployment — 6 September 2026

URL: https://demo.bihtech.in/login

Quick role login: https://demo.bihtech.in/quick-login

Host folder: `<deploy-root>/demo`

Database: a dedicated, empty demo database configured through `.env`.

Independent vendor directory and demo-only session domain configured. Clean application files and required static assets only; no production database, customer uploads, downloads, audio, or backup archives copied into the demo. Demo data: 5 users, 12 leads, 12 assignments, 3 visits, 5 follow-ups.

Verified over HTTPS: standard login and 15 role cards are available. Every one-click role login reaches its role-specific workspace with HTTP 200, including Lead Quality Auditor, Telecaller and Lead Manager. Dashboard, lead list, site visit report preview and branding JavaScript return 200. Requests to .env, composer.json and seeder paths return 403. All session cookies use demo.bihtech.in.

Fresh-install compatibility adjustments in demo migrations: shortened oversized Google Sheets import and login security index names; queue fields positioned after updated_at. Added missing nullable deleted_at columns to tasks, telecaller_tasks, follow_ups, meetings, site_visits, call_logs and crm_assignments to match their models. Preserve these adjustments in any future clean deployment package.

Production CRM files and database were not modified. This is a basic CRM demo seed, not an exhaustive HR/finance demo. End-to-end tests of every module and integration have not been performed. Hostinger CLI PHP intermittently crashes and emits an ionCube duplicate-loader warning; tested web requests succeeded.
