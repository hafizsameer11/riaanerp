# Docker stack (MySQL + PHP 8.2)

Use this when **XAMPP MySQL fails** or you need **large SQL imports**.

## Quick start

1. **Stop XAMPP MySQL** (and free port **3306**, or set `MYSQL_PORT=3307` in `.env`).
2. Copy **`.env.example`** → **`.env`** and set passwords (defaults work for local dev).
3. From the project root:

   ```bash
   docker compose up -d --build
   ```

4. Open the **app:** **http://localhost:8080** (`WEB_PORT` in `.env`).  
   Open **phpMyAdmin:** **http://localhost:8081** (`PMA_PORT` in `.env`). Log in as **root** / **`MYSQL_ROOT_PASSWORD`** (default `clientzone`).

### Config files on live vs Docker

**`config.php`** and **`modules/config.php`** in the repo are **unchanged** for production and XAMPP.

Compose **bind-mounts** **`docker/config.php`** over those paths **inside the containers only**, so Docker can use `DB_HOST=db` and passwords from `.env` without touching your deployed PHP config.

## Limits (large imports)

- **MySQL:** `max_allowed_packet=1G` and related timeouts — see `docker/mysql/conf.d/large-imports.cnf`.
- **PHP:** `upload_max_filesize` / `post_max_size` **2G**, `memory_limit` **1024M**, no execution time cap — see `docker/php/php-large-limits.ini`.
- **Apache:** `LimitRequestBody 0` — see `docker/apache/000-default.conf`.

## Import a database dump

Bash:

```bash
docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" clientzone < your_dump.sql
```

PowerShell:

```powershell
Get-Content .\your_dump.sql -Raw | docker compose exec -T db mysql -uroot -pclientzone clientzone
```

(Use the same password as `MYSQL_ROOT_PASSWORD` in `.env`.)

## Helpdesk POP (PHP 7.4 + IMAP)

With this stack, MySQL hostname inside Docker is **`db`**. POP sync:

```bash
docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php
```

Or from Windows: **`.\helpdesk-pop-sync.ps1`**

See also **`modules/helpdesk/cron/README_CRON.md`**.

## XAMPP + Docker together

- Run **only one** MySQL on **3306**, or map Docker MySQL to **3307** (`MYSQL_PORT=3307` in `.env`).
- The **web** container uses **`DB_HOST=db`** via `docker-compose.yml`; **`docker/config.php`** (bind-mounted in the container) reads `DB_HOST` / `DB_PASS` / etc.
