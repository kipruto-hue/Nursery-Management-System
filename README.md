# Seedling Nursery Management System

Mobile-first web app for a seedling nursery: workers record daily operations
(batches, losses, sales, input usage) on their phones; the owner monitors stock,
costs, debtors, and profit per batch. Built per the KibandaLabs Master Prompt v1.0.

Plain PHP 8.1+ · MySQL/MariaDB via PDO · vanilla HTML/CSS/JS. No frameworks,
no Composer, no build step. Deploys to Hostinger shared hosting.

## Local development (WSL Ubuntu)

```sh
# once: packages + database
sudo apt install php-cli php-mysql mariadb-server mariadb-client
sudo service mariadb start
sudo mariadb -e "CREATE DATABASE nursery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'nursery'@'127.0.0.1' IDENTIFIED BY 'nursery_dev';
  GRANT ALL ON nursery.* TO 'nursery'@'127.0.0.1';"

# load schema + demo data (re-runnable)
mariadb nursery < sql/schema.sql
php sql/seed.php

# run the dev server
php -S 127.0.0.1:8080 -t . router.php
```

The app lives at the repository root (it used to sit under `public_html/`).
URLs are extension-less — `/app/dashboard`, `/api/sales` — mapped back to the
real files by `.htaccess` on Apache, `router.php` locally, and
`api/index.php` on Vercel. See "Hosted demo" below for why.

Open http://127.0.0.1:8080 — demo logins (from the seed):

| Role   | Phone      | Password   |
|--------|------------|------------|
| Owner  | 0700000001 | owner1234  |
| Worker | 0700000002 | worker1234 |
| Worker | 0700000003 | worker1234 |

## Deploying to Hostinger

1. In hPanel create a MySQL database + user; note the credentials.
2. Upload the **contents of the repository root** into Hostinger's
   `public_html/` (skip `router.php` — it is dev-only). `.htaccess` denies
   `includes/`, `sql/`, `backups/` and `cron.php`, which now sit inside the
   document root; keep it.
3. Copy `includes/config.sample.php` to `includes/config.php`, fill in the DB
   credentials, and set `APP_ENV` to `'production'`.
4. Import `sql/schema.sql` via phpMyAdmin. For demo data run
   `php sql/seed.php` from an SSH session (or skip it and create the owner row
   manually with a bcrypt hash).
5. Add a daily cron job in hPanel: `/usr/bin/php /home/USER/nursery/cron.php`
   — it writes gzipped dumps to a non-public `backups/` folder, keeping 14 days.
6. Make sure the site is served over HTTPS (session cookies are `secure` when
   HTTPS is on).

## Hosted demo (Vercel)

A read-only demo runs at `nursery-management-system.vercel.app` with **no
database server**. If `DB_NAME` is unset the app builds a SQLite database in
the temp directory from `sql/schema.sqlite.sql`, seeds it with `sql/seed.php`,
and serves the demo from that — no signup, credentials or environment
variables. Set `DB_NAME` and MySQL is used exactly as on Hostinger; the demo
path is a fallback, not a fork.

No query was rewritten for SQLite. `includes/db_sqlite.php` registers the
MySQL functions the app uses (`CURDATE`, `YEAR`, `MONTH`, `DATEDIFF`,
`DATE_FORMAT`, `FIELD`, `SUBSTRING_INDEX`, `NOW`) as SQLite functions and
rewrites the few statements SQLite cannot parse (`FOR UPDATE`,
`ON DUPLICATE KEY UPDATE`, `IF()`, `CAST AS UNSIGNED`, `TRUNCATE`, and the
`INTERVAL` literal inside `DATE_SUB`).

Two constraints worth knowing:

- **Demo data resets.** The temp directory is per-instance and recycled when
  idle, so anything entered during a demo eventually disappears and the seed
  data returns. Suitable for viewing, not for recording real information.
- **Vercel denies any URL ending in `.php`** with 403, before routing. That is
  why every URL is extension-less and `vercel.json` routes them through
  `api/index.php`. It also denies `.php` and `includes/`, `sql/`, `backups/`
  from being served as static source, which the document-root layout would
  otherwise expose.

## Structure

```
index.php              login
app/                   authenticated pages (dashboard, batches, sales, reports…)
api/                   JSON endpoints — auth + CSRF + role checks on every one
api/index.php          front controller (Vercel only)
includes/              config, PDO, auth/session/CSRF, helpers, report queries
includes/db_sqlite.php MySQL-compatibility layer for the no-database demo
includes/session_db.php sessions in the database, not on disk
assets/css/main.css    all styling (design tokens in :root)
assets/js/             app.js shared + one file per page
sql/schema.sql         re-runnable schema (MySQL — the real one)
sql/schema.sqlite.sql  SQLite mirror, demo mode only
sql/seed.php           demo data
cron.php               nightly DB backup (Hostinger cron)
.htaccess              Apache: clean URLs + denies code/data dirs
vercel.json            Vercel: routing, and denies .php being served as source
```

Key security properties: PDO prepared statements only; CSRF token required on
every mutation; roles enforced server-side on every request; worker API
responses are stripped of cost fields (`strip_money_fields()` in
`includes/helpers.php`); login rate limiting (5 tries / 15 min per phone);
activity log records every mutation with before/after values.
