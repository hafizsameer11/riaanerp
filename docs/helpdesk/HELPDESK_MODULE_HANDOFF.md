# Helpdesk module — handoff guide (done, gaps, docs, new machine)

Use this document when **moving the project to another machine** or briefing a client/developer. It summarizes what was built, what is optional follow-up, where documentation lives, and how to get running.

---

## 1) What this module is

The Helpdesk is an **ERP-embedded** module (not a separate app). It appears under the main **Helpdesk** sidebar in `index.php` and loads inside the ERP iframe like other modules.

**Main capabilities (aligned with `Helpdesk Module (1).pdf` and client clarifications):**

| Area | Behaviour |
|------|------------|
| Dashboard | Summary stats, technician workload, open ticket queue, My/Global/Assets views; New Request modal on dashboard |
| Manual tickets | Client, requester, technician, due date, subject, description, attachments |
| Email tickets | POP sync creates tickets / threads replies; attachments; duplicate emails → separate tickets (merge in UI) |
| Technician view | Status (open / on hold / closed), assignment, email reply with threading, internal notes/worklog, client-visible updates, timesheet start/stop, service & pricing from billing catalog |
| Merge | From dedicated page and from **ticket view** (modal); moves related rows; handles service-line conflict |
| Scheduled calls | CRUD, edit, attachments copied when “Log again”; cron creates tickets |
| Requesters | CRUD, auto portal login, welcome email template |
| Requester portal | Login; list/view own tickets only |
| Admin | POP/SMTP settings, HTML templates, notification rules |
| Reports | CSV exports (incl. on-hold reason), billing-style report, bulk delete closed + files |
| Overdue | Flag based on due date and 48 working hours (Mon–Fri) logic |
| Permissions | Granular keys under page `helpdesk` in Role Permissions |

---

## 2) What has been done (implementation status)

**Done — core & integration**

- Database schema and patches under `modules/helpdesk/sql/`
- All primary PHP screens under `modules/helpdesk/` (dashboard, tickets, merge, requesters, scheduled, admin, reports, portal, attachment handler)
- ERP shell: Helpdesk menu + sub-tabs in root `index.php`
- Permission definitions in `modules/reports/roles/permissions.php`; seed script for admin role
- POP cron (`pop_sync.php`) with PHP 7.4 / IMAP constraint; scheduled runner (`run_scheduled.php`) for PHP 8.x
- Docker-based PHP 7.4 + IMAP option for local Mac / split runtime
- UI pass: shared styles (`hd_ui_css` in `includes/functions.php`), dashboard modal for new request, Assets tab loads Clients inside parent iframe, merge modal on ticket page
- Documentation: under **`docs/helpdesk/`** (POP/SMTP, cron, handoff, changed-files list)

**Done — spec-oriented fixes (high level)**

- Randomized assignment tied to permission pool + improved pick logic (least load, then recency)
- On-hold: no client email for generic status path where specified; on-hold reason in exports
- Merge hardened (query checks, service row uniqueness conflict handling)
- Optional client-facing **status note** on status save (logged as outbound client-visible message)

---

## 3) What may still need attention (optional / follow-up)

These are **not blockers** for “module works,” but worth tracking for production hardening or stricter spec interpretation:

| Topic | Notes |
|-------|--------|
| **Security** | Requester/staff passwords follow existing ERP patterns (e.g. plaintext in `registers` for portal). Hardening would be a separate project. |
| **Assign & reassign permission** | Spec mentions distinct permission; current gating uses `edit ticket` for assignment. Add a separate permission if the client requires it. |
| **Manual time entry** | Spec mentions manual time entry alongside timesheet; current UI is start/stop timer style. |
| **Assets tab** | Bridge to Clients module, not a dedicated Helpdesk asset DB. Deeper asset linking is future work. |
| **POP notifications on 7.4-only cron** | `MailService` loads PHPMailer without full Composer autoload on 7.4 for POP path; verify SMTP sends on your server’s PHP 7.4 cron or run notification-capable path if needed. |
| **UAT** | Full client sign-off should still run on **their** mail server, roles, and data (see section 6). |

---

## 4) Documentation index (read these on the new machine)

| Document | Path | Purpose |
|----------|------|---------|
| **This handoff guide** | `docs/helpdesk/HELPDESK_MODULE_HANDOFF.md` | Done / gaps / setup / doc index |
| **Changed files list** | `docs/helpdesk/HELPDESK_CHANGED_FILES.md` | Inventory of touched paths |
| **POP + SMTP production** | `docs/helpdesk/POP_SMTP_LIVE_SETUP.md` | Live mail, DB, cron, troubleshooting |
| **Linux live cron (POP + scheduler)** | `docs/helpdesk/POP_CRON_LINUX_SERVER.md` | Production `crontab`, PHP 7.4/8.2 split, Docker option |
| **Cron reference** | `docs/helpdesk/README_CRON.md` | `pop_sync.php` vs `run_scheduled.php`, Docker wrappers |
| **Spec vs code** | `docs/helpdesk/REQUIREMENTS_TRACEABILITY.md` | PDF §§1–11 mapped to files; gaps, partials, extras |
| **Functional spec** | `Helpdesk Module (1).pdf` (project root) | Original requirements |
| **SQL — fresh DB** | `modules/helpdesk/sql/migrate.sql` | Full Helpdesk tables + seeds |
| **SQL — admin permissions** | `modules/helpdesk/sql/seed_permissions.sql` | Grant Helpdesk perms to admin role |
| **SQL — existing DB** | `modules/helpdesk/sql/patch_existing_db_helpdesk_notifications.sql` | Safe incremental updates |

---

## 5) Running on a new machine (checklist)

### 5.1 Prerequisites

- **Web stack:** PHP **8.2 or 8.3** for the main app (Apache/nginx + mod_php or php-fpm).
- **Database:** MySQL or MariaDB; create database (often named `clientzone` in this project).
- **Composer:** Run at project root if vendor is missing: `composer install` (PHPMailer etc.).
- **PHP extensions (web):** `mysqli`, `openssl`, `json`, etc. (typical LAMP).
- **POP email cron:** PHP **7.4 CLI** with **imap** extension **or** use the **Docker** image + `modules/helpdesk/cron/php74` wrapper (see `docs/helpdesk/README_CRON.md`).

### 5.2 Application config

- Copy or edit **`config.php`** (root) and **`modules/config.php`** with the new host’s DB host, user, password, database name.
- If MySQL listens only on TCP from Docker/cron, you may need `DB_HOST_OVERRIDE` (see `docs/helpdesk/POP_SMTP_LIVE_SETUP.md` / env notes in your deployment).

### 5.3 Database

**Option A — Full dump (e.g. `clientzone.sql`)**  
If your dump already includes `helpdesk_*` tables and is compatible with your MySQL version, import it:

```bash
mysql -u USER -p DATABASE < /path/to/clientzone.sql
```

**Option B — Fresh schema + Helpdesk only**  
```bash
mysql -u USER -p DATABASE < modules/helpdesk/sql/migrate.sql
mysql -u USER -p DATABASE < modules/helpdesk/sql/seed_permissions.sql
```

**Option C — Existing DB without latest Helpdesk columns/tables**  
```bash
mysql -u USER -p DATABASE < modules/helpdesk/sql/patch_existing_db_helpdesk_notifications.sql
```

Reconcile collation if imports fail (e.g. replace `utf8mb4_0900_ai_ci` with `utf8mb4_unicode_ci` on older MySQL).

### 5.4 Filesystem

- Ensure **`modules/helpdesk/uploads/`** exists and is **writable** by the web server (attachments).
- Optional: `modules/helpdesk/cron/logs/` for cron output.

### 5.5 ERP permissions (first login)

- Log in as admin → **Reporting and Admin → Role Management → Role Permissions**.
- Enable **helpdesk** checkboxes per role (`dashboard`, `create ticket`, `merge tickets`, etc.).

### 5.6 Helpdesk mail (after UI loads)

- **Helpdesk → Mail Settings:** POP + SMTP.
- **Email Templates** / **Notification Rules:** enable and map events as needed.

### 5.7 Cron (production Linux)

**Spec:** `pop_sync.php` → **PHP 7.4 + IMAP** only; `run_scheduled.php` and the web app → **PHP 8.2 or 8.3**.

Step-by-step for a **Linux server:** **`docs/helpdesk/POP_CRON_LINUX_SERVER.md`**.  
Extra detail: **`docs/helpdesk/README_CRON.md`**, **`docs/helpdesk/POP_SMTP_LIVE_SETUP.md`**.

**Local Windows dev:** run POP manually when needed — **`helpdesk-pop-sync.ps1`** at the project root (Docker), not Task Scheduler.

### 5.8 Requester portal URL

Welcome emails use a built URL for `requester_portal/login.php`. After moving hosts, confirm the **base URL** (HTTPS, domain) matches production so links in emails are correct.

---

## 6) Client sign-off (suggested UAT topics)

Run through as **admin** and as a **technician** role:

1. Dashboard: My vs Global, filters, New Request modal, open list.  
2. Create ticket: attachments, due date, random assign (if permission on).  
3. Ticket view: status, on-hold note, optional status note, reply email, worklog, timesheet, service line.  
4. Merge: from ticket modal and from merge page; confirm target ticket shows combined thread.  
5. POP: send test mail; confirm ticket or reply thread.  
6. Requester: add user, receive welcome, portal login, only own tickets.  
7. Scheduled: create job, Log again, optional attachment copy.  
8. Reports: export CSV, billing report, bulk delete (on a test range).  

---

## 7) Quick reference — module entry URLs (inside ERP)

Loaded via sidebar iframe (paths relative to site root):

- Dashboard: `modules/helpdesk/index.php`  
- New request (full page): `modules/helpdesk/ticket_new.php`  
- Portal (standalone): `modules/helpdesk/requester_portal/login.php`  

---

*Last updated for handoff: module guide + new-machine steps. For a raw file list, see `docs/helpdesk/HELPDESK_CHANGED_FILES.md`.*
