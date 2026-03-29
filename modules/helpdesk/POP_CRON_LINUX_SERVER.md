# Helpdesk POP + scheduler on a Linux production server

Use this on **live Linux** (VPS, bare metal, etc.) with **cron**.  
**Local Windows dev:** do **not** schedule POP; run **`helpdesk-pop-sync.ps1`** (or the Docker command below) only when you want to pull mail.

---

## What to run

| Job | Script | PHP |
|-----|--------|-----|
| **POP / IMAP** (incoming mail) | `modules/helpdesk/cron/pop_sync.php` | **7.4 + IMAP** (see below) |
| **Overdue + scheduled tickets** | `modules/helpdesk/cron/run_scheduled.php` | **8.2 or 8.3** |

---

## Option A — Native PHP on the server

### 1) PHP 7.4 CLI with IMAP (POP only)

Install PHP 7.4 and enable the **imap** extension for CLI, for example (Debian/Ubuntu):

```bash
sudo apt-get update
sudo apt-get install -y php7.4-cli php7.4-imap
php7.4 -m | grep -i imap
```

### 2) PHP 8.2 (or 8.3) CLI for `run_scheduled.php`

Use the same major version as your web stack:

```bash
sudo apt-get install -y php8.2-cli
```

### 3) Crontab (every minute)

```bash
sudo crontab -e
```

Use **absolute paths**. Replace `/var/www/riaanerp` with your deploy path and adjust PHP binaries if yours differ.

```cron
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

* * * * * /usr/bin/php7.4 /var/www/riaanerp/modules/helpdesk/cron/pop_sync.php >> /var/www/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

* * * * * /usr/bin/php8.2 /var/www/riaanerp/modules/helpdesk/cron/run_scheduled.php >> /var/www/riaanerp/modules/helpdesk/cron/logs/scheduled_cron.out 2>&1
```

Create the log directory once:

```bash
mkdir -p /var/www/riaanerp/modules/helpdesk/cron/logs
chown www-data:www-data /var/www/riaanerp/modules/helpdesk/cron/logs
```

(`www-data` → your web user if different.)

### 4) Database

`pop_sync.php` and `run_scheduled.php` load **`config.php`** at the project root. On the server, `config.php` must point MySQL **host/user/pass/database** to your live DB (not `docker/config.php` unless you deploy that intentionally).

---

## Option B — POP only via Docker (PHP 7.4 + IMAP), rest native

If you do not want PHP 7.4 on the host, run **only** POP in a container; keep **`run_scheduled.php`** on PHP 8.2 CLI.

Example: image built from `modules/helpdesk/cron/Dockerfile.php74-imap`, project mounted at `/app`, DB reachable from the container (same Docker network as MySQL, or host gateway — see `POP_SMTP_LIVE_SETUP.md`).

```cron
* * * * * cd /var/www/riaanerp && docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php >> /var/www/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

* * * * * /usr/bin/php8.2 /var/www/riaanerp/modules/helpdesk/cron/run_scheduled.php >> /var/www/riaanerp/modules/helpdesk/cron/logs/scheduled_cron.out 2>&1
```

Adjust for your Compose file location and service names.

---

## Verify

1. **Mail:** Send a test message to the POP mailbox; within a minute a ticket or reply should appear.
2. **Logs:** `tail -f modules/helpdesk/cron/logs/pop_cron.out`
3. **Scheduled:** Create a test **scheduled** job due soon; confirm a ticket is created.

---

## Related docs

- **`POP_SMTP_LIVE_SETUP.md`** — POP/SMTP UI, mail server, troubleshooting  
- **`cron/README_CRON.md`** — Split runtime, Docker details, Windows notes  
- **`HELPDESK_MODULE_HANDOFF.md`** — Overall module index  
