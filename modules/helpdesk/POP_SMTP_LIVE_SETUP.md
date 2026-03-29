# Helpdesk POP + SMTP Live Setup (Production)

This guide explains how to make Helpdesk email ingestion (POP) and outgoing mail (SMTP) work on a live server.

## 1) Runtime model (important)

Use this split-runtime model:

- **Web app / UI / reports / normal requests:** PHP **8.2 or 8.3**
- **IMAP POP ingestion only:** PHP **7.4**
  - `modules/helpdesk/cron/pop_sync.php`

`run_scheduled.php` does not require IMAP, so it can run on PHP 8.2/8.3.

## 2) Server prerequisites

- PHP CLI **7.4** installed and available as `php7.4`
- PHP IMAP extension enabled for PHP 7.4 CLI
- MySQL/MariaDB access to the `clientzone` database
- Outbound firewall open for mail ports

Check PHP + IMAP (if you run native 7.4 on server):

```bash
php7.4 -v
php7.4 -m | rg -i imap
```

If IMAP is missing, install/enable it for PHP 7.4 CLI, then restart PHP/Apache as needed.

## 3) Database update

If this is a new environment:

```bash
mysql -u DB_USER -p clientzone < modules/helpdesk/sql/migrate.sql
mysql -u DB_USER -p clientzone < modules/helpdesk/sql/seed_permissions.sql
```

If this is an existing environment (already had helpdesk tables):

```bash
mysql -u DB_USER -p clientzone < modules/helpdesk/sql/patch_existing_db_helpdesk_notifications.sql
```

## 4) Configure Helpdesk mail settings in UI

Go to:

- `Helpdesk -> Mail Settings`

Fill these fields:

### Incoming (POP)
- `Host`: POP server hostname (example `pop.yourdomain.com`)
- `Port`: usually `110` (plain) or `995` (SSL)
- `User`: mailbox username/email
- `Password`: mailbox password/app-password
- `POP SSL`: tick if using SSL (`995`)
- `Randomize technician on new email tickets`: optional

### Outgoing (SMTP)
- `Host`: SMTP server hostname
- `Port`: usually `587` (STARTTLS), `465` (SSL), or `25`
- `User`: SMTP username
- `Password`: SMTP password/app-password
- `Use STARTTLS`: tick for `587` if provider requires it
- `From email`: sender address for outbound helpdesk mail
- `From name`: sender display name

Click `Save`.

## 5) Configure notification rules/templates

Go to:

- `Helpdesk -> Email Templates`
- `Helpdesk -> Notification Rules`

Recommended defaults:
- Enable `ticket_created`
- Enable others only after testing:
  - `ticket_status_changed`
  - `ticket_on_hold`
  - `ticket_closed`

## 6) Cron jobs (every minute)

**Spec / architecture:** PHP **8.2 or 8.3** for the web app and for `run_scheduled.php`. **Only** `pop_sync.php` must run on **PHP 7.4 + IMAP** (native binary or **Docker** — see below).

**Linux production:** copy-paste guide — **`modules/helpdesk/POP_CRON_LINUX_SERVER.md`**.  
More variants (Docker POP, `host-gateway`, etc.): **`modules/helpdesk/cron/README_CRON.md`**.  
**Local Windows:** run POP manually — **`helpdesk-pop-sync.ps1`** / **`helpdesk-pop-sync.cmd`** (no Task Scheduler in repo).

Minimal native split (when `php7.4` exists on the server):

```cron
# IMAP POP parser -> PHP 7.4 only
* * * * * /usr/bin/php7.4 /path/to/riaanerp/modules/helpdesk/cron/pop_sync.php >> /path/to/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

# Non-IMAP scheduler -> PHP 8.2/8.3
* * * * * /usr/bin/php8.2 /path/to/riaanerp/modules/helpdesk/cron/run_scheduled.php >> /path/to/riaanerp/modules/helpdesk/cron/logs/scheduled.out 2>&1
```

Important:
- Use full absolute paths
- Use PHP **7.4** only for `pop_sync.php` (or the **php74-imap** Docker image running that script)
- Use PHP **8.2/8.3** for `run_scheduled.php` and the web runtime
- Ensure `modules/helpdesk/cron/logs/` is writable by the cron user

**Docker POP line (copy/paste):** replace `/ABS/PATH/riaanerp` and add `--add-host=host.docker.internal:host-gateway` on Linux Docker Engine if needed:

```cron
* * * * * docker run --rm -v /ABS/PATH/riaanerp:/app -w /app -e DB_HOST_OVERRIDE=host.docker.internal php74-imap php modules/helpdesk/cron/pop_sync.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1
```

## 7) Full stack in Docker (MySQL + PHP 8.2 + POP tools)

If XAMPP MySQL is unreliable, use the root **`docker-compose.yml`**: MySQL with large import limits and PHP 8.2 Apache. See **`docker/README.md`**.

POP sync uses **`docker compose --profile tools run --rm helpdesk-php74`** (same file). **`modules/helpdesk/cron/README_CRON.md`** has crontab lines.

---

## 8) Docker for POP only (recommended: local dev, or servers without PHP 7.4)

Use Docker **only** for `pop_sync.php`. Keep **PHP 8.2/8.3** for Apache/nginx and for `run_scheduled.php`.

1. Build the image once (from project root):

```bash
cd /path/to/riaanerp
docker build -t php74-imap -f modules/helpdesk/cron/Dockerfile.php74-imap .
```

2. Test POP sync:

```bash
./modules/helpdesk/cron/php74 modules/helpdesk/cron/pop_sync.php
```

**Windows (PowerShell):**

```powershell
.\modules\helpdesk\cron\docker-php74.ps1
```

3. Test scheduler on the host (PHP 8.2/8.3):

```bash
php modules/helpdesk/cron/run_scheduled.php
```

4. Install **crontab** entries from **`README_CRON.md`** (Docker or native), or on Windows use **Task Scheduler** as documented there.

MySQL on the host (XAMPP, local MariaDB, Docker Desktop): the wrappers set `DB_HOST_OVERRIDE=host.docker.internal` so the container can reach the database. On Linux Docker Engine, use `--add-host=host.docker.internal:host-gateway` (see `README_CRON.md`).

## 9) POP behavior notes

- POP worker imports all messages it finds
- If email is a reply (`In-Reply-To` / `References` match), it appends to existing ticket
- If no match, it creates a new ticket
- Duplicate deliveries can create two tickets (as requested)
- Empty subject is stored as `(no subject)`
- Attachments are saved to `modules/helpdesk/uploads/`
- Imported POP messages are deleted from mailbox after successful processing

## 10) Live verification checklist

1. Create manual ticket -> confirm visible in dashboard
2. Send test email to POP mailbox -> new ticket appears within 1 min
3. Reply to ticket email -> reply threads into same ticket
4. Send email with no subject -> ticket still created
5. Send same email twice -> two tickets created
6. Add attachment by email -> file appears on ticket
7. Change ticket status to On Hold / Closed -> verify configured notifications
8. Reports export (Open/Closed/On Hold/Overdue) works
9. Bulk delete closed tickets removes DB rows and attachment files

## 11) Troubleshooting

- No incoming tickets:
  - Check cron output logs (`pop_cron.out`)
  - Verify IMAP extension on PHP 7.4 runtime (native or Docker image)
  - Verify POP host/port/SSL/user/password
- No outgoing emails:
  - Check SMTP host/port/TLS/user/password
  - Confirm outbound ports allowed by server firewall
- Permissions issue:
  - Ensure role has `helpdesk` permissions in `Reporting and Admin -> Role Permissions`

## 12) Security recommendations

- Use app passwords for POP/SMTP where possible
- Restrict file permissions on project directory
- Rotate mailbox credentials periodically
- Restrict direct public access to admin/dev utility scripts in project root

