# Fin360 Backend

Laravel 8 REST API for **Fin360 by SYSNAC** — complete financial control: invoicing, quotations, bills, income & expenses, banking, double-entry journals, GST reports, and role-based access.

Pairs with [`fin360-frontend`](../fin360-frontend).

## What changed from Lead CRM (v2.0)

The project started as a Lead CRM with an accounting module. Fin360 is finance-only:

- **Removed:** leads, follow-ups, call logs, notes, CRM customers, lead sources/statuses, round-robin assignment, the CRM dashboard, and their models, migrations, seeders and routes.
- **Removed (security):** `debug.php`, `test.php`, `public/install.php`, `public/updater.php` (unauthenticated ZIP upload) and `public/clearcache.php` (hardcoded key).
- **Notifications** are finance alerts only: overdue / due-this-week invoices, overdue bills, quotations about to expire.
- **New endpoint:** `GET /api/accounting/dashboard/overview?from=&to=` returns KPIs (with change vs the previous period), a 6-month income/expense series, expense breakdown, recent cash movements and upcoming invoices in one call. Income = invoice payments + income entries; expenses = bill payments + expense entries; payments on deleted documents are excluded.
- **Roles:** Super Admin, Admin, Finance Manager, Accountant, Viewer (see `RolesAndPermissionsSeeder`).
- **Migration `2026_10_26_000001_remove_crm_permissions`** deletes CRM permission definitions and their grants. It does **not** drop CRM data tables (`leads`, `followups`, …) — archive and drop them yourself when ready.

## Requirements

PHP 8.0+ (8.2 tested) with `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`; MySQL 5.7+ / MariaDB 10.4+; Composer.

## Local setup

```bash
composer install
cp .env.example .env          # set DB_* for your database
php artisan key:generate
php artisan migrate
php artisan db:seed           # fresh install: roles, demo users, accounting reference data
php artisan storage:link
php artisan serve             # http://127.0.0.1:8000/api
```

Upgrading an existing Lead CRM database instead? Skip `db:seed`, run `php artisan migrate`, then `php artisan db:seed --class=RolesAndPermissionsSeeder` to add the Finance Manager / Accountant / Viewer roles.

Demo logins after a fresh seed (password `password` — change immediately): `superadmin@fin360.app`, `admin@fin360.app`, `finance@fin360.app`, `accountant@fin360.app`.

## API layout

| Area | Prefix |
|---|---|
| Auth, profile, password reset | `/api/login`, `/api/me`, `/api/password/*` |
| Users, roles, permissions, departments, job titles | `/api/users`, `/api/roles`, `/api/permissions`, … |
| Company settings (branding) | `/api/settings/company` |
| Notifications | `/api/notifications` |
| Finance (invoices, bills, quotations, proforma, income, expenses, banking, journals, taxes, currencies, branches, financial years, reports, dashboard) | `/api/accounting/*` |

Scheduled commands (`app/Console/Commands`): expire quotations, process recurring transactions, recalculate account balances.

Historic CRM-era documentation is kept in `docs/legacy/` for reference only.
