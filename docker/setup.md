# Docker setup — run and stop

Use **Docker** for this project when you want the stack in containers (MySQL + PHP 8.2 Apache). Your **live/XAMPP** `config.php` files stay as they are; Compose uses **`docker/config.php`** inside the containers only.

All commands below are run from the **project root** (`riaanerp/`), **not** from inside the `docker/` folder.

---

## Composer on Windows (system — not inside Docker)

The **`vendor/`** folder must exist **on your PC** in the project root. Helpdesk POP (Docker) and the app **read the same files** from the bind-mounted folder, so you install dependencies **once on Windows**.

**Why `composer` “does nothing” or says `php` is not recognized:** the global `composer.bat` runs `php`, but **XAMPP’s PHP is often not on your PATH**.

**Option A — helper batch (recommended):** from the repo root:

```bat
scripts\composer-xampp.bat install
scripts\composer-xampp.bat update
```

If XAMPP or Composer live elsewhere, set env vars once (PowerShell):

```powershell
$env:XAMPP_PHP = "D:\xampp\php\php.exe"
$env:COMPOSER_PHAR = "C:\path\to\composer.phar"
```

**Option B — PATH:** add **`C:\xampp\php`** to **Windows → Environment variables → Path**, then open a **new** terminal and run `composer install` from the project root.

**PHP `zip` extension:** Composer needs it for normal package downloads. In **`C:\xampp\php\php.ini`**, ensure **`extension=zip`** is enabled (not commented).

---

## Before the first run

1. Install **Docker Desktop** (Windows/Mac) or Docker Engine + Compose (Linux), and start Docker.
2. Copy **`.env.example`** to **`.env`** in the project root (same folder as `docker-compose.yml`).
3. If **XAMPP MySQL** is still using port **3306**, either stop it or set **`MYSQL_PORT=3307`** in `.env` so Docker MySQL maps to 3307 on your PC.

### “Connection refused” on localhost

Usually one of these:

| What you opened | Fix |
|-----------------|-----|
| **`http://localhost`** (no port) | Nothing listens on port **80** by default. Use **`http://localhost:8080`** for the ERP or **`http://localhost:8081`** for phpMyAdmin. |
| **`http://localhost:8081`** (phpMyAdmin) | The **phpMyAdmin** container was not running. From the project root run: `docker compose up -d` (starts every service) or `docker compose up -d phpmyadmin`. |
| Any URL | Docker is not running — start **Docker Desktop**, wait until it is ready, then `docker compose up -d` again. |

Check containers: `docker compose ps` — you should see **db**, **web**, and **phpmyadmin** as **Up**.

---

## Start the stack (turn Docker “on”)

From the **project root**:

```bash
docker compose up -d --build
```

- **`-d`** — runs in the background.
- **`--build`** — builds images if needed (omit later if nothing changed).

Then open:

- **ERP app:** **http://localhost:8080** (or **`WEB_PORT`** from `.env`)  
- **phpMyAdmin:** **http://localhost:8081** (or **`PMA_PORT`** from `.env`)

### phpMyAdmin (import `.sql` in the browser)

1. Go to **http://localhost:8081**.
2. Server is already **`db`**; log in as **root** with the password from **`MYSQL_ROOT_PASSWORD`** in `.env` (default **`clientzone`** if you did not change it).
3. Click **`clientzone`** (or create it if empty), then **Import** → choose your `.sql` file → **Go**.

Upload limit in the container is **2G** for large dumps. If the UI still times out, use the CLI import in **`docker/README.md`**.

Services that stay running:

- **`db`** — MySQL  
- **`web`** — Apache + PHP 8.2  

Helpdesk POP (**PHP 7.4**) is **not** scheduled locally; run **`helpdesk-pop-sync.ps1`** or **`helpdesk-pop-sync.cmd`** when you want to pull mail. **Linux live:** **`modules/helpdesk/POP_CRON_LINUX_SERVER.md`**.

---

## Check that it is running

```bash
docker compose ps
```

View logs:

```bash
docker compose logs -f
```

One service only, e.g. web:

```bash
docker compose logs -f web
```

---

## Stop the stack (turn Docker “off” for this project)

**Stop containers** but keep them (faster next start):

```bash
docker compose stop
```

**Stop and remove containers** (usual “switch off”; data in the MySQL volume is kept):

```bash
docker compose down
```

**Stop, remove containers, and delete the MySQL data volume** (fresh database next time — only if you really want to wipe DB data):

```bash
docker compose down -v
```

---

## After changing code or `.env`

Restart web (or everything):

```bash
docker compose up -d --build
```

If you only changed **`.env`** for database passwords, restart **`db`** and **`web`** so they pick up env (or run `docker compose up -d` again).

---

## Helpdesk POP (local — manual only)

When you want to sync the mailbox (Docker stack running):

```powershell
cd D:\projects\riaanerp
.\helpdesk-pop-sync.ps1
```

Or run **`helpdesk-pop-sync.cmd`**.

First time (or after changing the POP Dockerfile):

```bash
docker compose --profile tools build helpdesk-php74
```

**Linux production server** (`crontab` for POP + overdue/scheduled): **`modules/helpdesk/POP_CRON_LINUX_SERVER.md`**.  
Also: **`docker/cron/crontab.example`**, **`modules/helpdesk/cron/README_CRON.md`**.

---

## Where to read more

- **`docker/README.md`** — large SQL imports, Helpdesk POP, ports, config overlay  
- **`docker-compose.yml`** (project root) — services, ports, profiles  
