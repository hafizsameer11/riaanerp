# Helpdesk cron (split runtime)

**Linux live server:** start with **`../POP_CRON_LINUX_SERVER.md`** (copy-paste `crontab`, PHP 7.4 + 8.2, optional Docker POP).

**Local Windows:** POP is **manual** — run **`helpdesk-pop-sync.ps1`** or **`helpdesk-pop-sync.cmd`** at the project root when you want to pull mail (Docker).

---

This matches the **functional spec / handoff**: everything runs on **PHP 8.2 or 8.3** except **POP email ingestion**, which must run on **PHP 7.4 with the IMAP extension** (`pop_sync.php`).

| Job | Script | PHP |
|-----|--------|-----|
| POP / IMAP ingest | `modules/helpdesk/cron/pop_sync.php` | **7.4 + IMAP** (native or **Docker**) |
| Overdue + scheduled tickets | `modules/helpdesk/cron/run_scheduled.php` | **8.2 / 8.3** |

---

## 1) Docker: project root `docker-compose.yml`

The **root** `docker-compose.yml` defines:

- **`db`** — MySQL 8 with large import settings (`docker/mysql/conf.d/`)
- **`web`** — PHP 8.2 + Apache (high upload / time limits)
- **`helpdesk-php74`** — POP sync image (**profile `tools`**, not started by `up` by default)

Build POP image:

```bash
docker compose --profile tools build helpdesk-php74
```

Image name: **`riaanerp-helpdesk-php74:latest`**.

Plain Docker (no compose):

```bash
docker build -t php74-imap -f modules/helpdesk/cron/Dockerfile.php74-imap .
```

---

## 2) Manual test (before cron)

### MySQL in Docker (recommended with root compose)

Start stack: `docker compose up -d --build`

POP sync reaches MySQL at hostname **`db`** (set automatically for `helpdesk-php74`):

```powershell
.\helpdesk-pop-sync.ps1
```

Or:

```bash
./modules/helpdesk/cron/php74
```

```powershell
.\modules\helpdesk\cron\docker-php74.ps1
```

```cmd
modules\helpdesk\cron\pop-sync-docker.cmd
```

### MySQL still on host (XAMPP)

Use **`DB_HOST_OVERRIDE=host.docker.internal`** and a manual `docker run` (see Option A2 below), or run **`php7.4`** on the host.

**Scheduler (`run_scheduled.php`):**

- Host PHP 8.2: `php modules/helpdesk/cron/run_scheduled.php`
- Or inside **web** container: `docker compose exec web php modules/helpdesk/cron/run_scheduled.php`

---

## 3) Crontab: production-style (every minute)

Create log dir: `mkdir -p /path/to/riaanerp/modules/helpdesk/cron/logs`

### Option A — POP via root **Docker Compose** (MySQL service `db`)

```cron
* * * * * cd /ABS/PATH/riaanerp && docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

* * * * * cd /ABS/PATH/riaanerp && docker compose exec -T web php modules/helpdesk/cron/run_scheduled.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/scheduled.out 2>&1
```

(Second line assumes `web` container is always `up`.)

### Option A2 — POP via **`docker run`** (MySQL on Windows/Mac host)

```cron
* * * * * docker run --rm -v /ABS/PATH/riaanerp:/app -w /app -e DB_HOST_OVERRIDE=host.docker.internal riaanerp-helpdesk-php74:latest php modules/helpdesk/cron/pop_sync.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1
```

On **Linux** Docker Engine, add `--add-host=host.docker.internal:host-gateway` if needed.

### Option B — POP via **native PHP 7.4** (IMAP on the OS)

```cron
* * * * * /usr/bin/php7.4 /ABS/PATH/riaanerp/modules/helpdesk/cron/pop_sync.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

* * * * * /usr/bin/php8.2 /ABS/PATH/riaanerp/modules/helpdesk/cron/run_scheduled.php >> /ABS/PATH/riaanerp/modules/helpdesk/cron/logs/scheduled.out 2>&1
```

### Wrapper + Linux Docker Engine

```cron
* * * * * env EXTRA_DOCKER_RUN_ARGS='--add-host=host.docker.internal:host-gateway' /ABS/PATH/riaanerp/modules/helpdesk/cron/php74 modules/helpdesk/cron/pop_sync.php >> ... 
```

(`EXTRA_DOCKER_RUN_ARGS` only applies when `USE_LEGACY_PHP74_DOCKER=1`.)

---

## 4) Windows (local — no POP schedule)

Run POP only when needed: **`helpdesk-pop-sync.ps1`** or **`helpdesk-pop-sync.cmd`** from the project root.

To refresh overdue / scheduled tickets locally (optional):

```powershell
docker compose exec web php modules/helpdesk/cron/run_scheduled.php
```

---

## 5) Database + mail

Schema: `modules/helpdesk/sql/migrate.sql`. Full Docker guide: **`docker/README.md`**.

POP deletes messages from the mailbox after successful import. Details: **`POP_SMTP_LIVE_SETUP.md`**.
