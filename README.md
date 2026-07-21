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
cd public_html
mariadb nursery < sql/schema.sql
php sql/seed.php

# run the dev server
php -S 127.0.0.1:8080 -t . router.php
```

Open http://127.0.0.1:8080 — demo logins (from the seed):

| Role   | Phone      | Password   |
|--------|------------|------------|
| Owner  | 0700000001 | owner1234  |
| Worker | 0700000002 | worker1234 |
| Worker | 0700000003 | worker1234 |

## Deploying to Hostinger

1. In hPanel create a MySQL database + user; note the credentials.
2. Upload the **contents of `public_html/`** into Hostinger's `public_html/`
   (skip `router.php` — it is dev-only). Upload `cron.php` one level above
   `public_html` (e.g. `/home/USER/cron.php` with `public_html` adjusted, or
   keep the same folder shape and fix the path inside `cron.php`).
3. Copy `includes/config.sample.php` to `includes/config.php`, fill in the DB
   credentials, and set `APP_ENV` to `'production'`.
4. Import `sql/schema.sql` via phpMyAdmin. For demo data run
   `php sql/seed.php` from an SSH session (or skip it and create the owner row
   manually with a bcrypt hash).
5. Add a daily cron job in hPanel: `/usr/bin/php /home/USER/nursery/cron.php`
   — it writes gzipped dumps to a non-public `backups/` folder, keeping 14 days.
6. Make sure the site is served over HTTPS (session cookies are `secure` when
   HTTPS is on).

## Structure

```
public_html/
  index.php          login
  app/               authenticated pages (dashboard, batches, sales, reports…)
  api/               JSON endpoints — auth + CSRF + role checks on every one
  includes/          config, PDO, auth/session/CSRF, helpers, report queries
  assets/css/main.css  all styling (design tokens in :root)
  assets/js/         app.js shared + one file per page
  sql/schema.sql     re-runnable schema
  sql/seed.php       demo data (CLI only)
cron.php             nightly DB backup (Hostinger cron)
```

Key security properties: PDO prepared statements only; CSRF token required on
every mutation; roles enforced server-side on every request; worker API
responses are stripped of cost fields (`strip_money_fields()` in
`includes/helpers.php`); login rate limiting (5 tries / 15 min per phone);
activity log records every mutation with before/after values.
