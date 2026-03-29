# Helpdesk module — files touched / added

This document lists files that were **created or modified** for the Helpdesk implementation and related ERP integration. Paths are relative to the project root (`riaanerp/`).

---

## ERP shell and shared config (outside `modules/helpdesk/`)

| File | Role |
|------|------|
| `config.php` | DB connection behaviour (e.g. host fallback, mysqli reporting). |
| `modules/config.php` | Same as above for modules that include this copy. |
| `index.php` | Main ERP sidebar: Helpdesk menu block, sub-tabs, iframe targets. |
| `modules/reports/roles/permissions.php` | Helpdesk permission keys under page `helpdesk`. |

---

## Helpdesk module — application PHP

| File | Role |
|------|------|
| `modules/helpdesk/index.php` | Dashboard (My/Global/Assets), stats, open queue, inline assign, New Request modal, Assets → Clients iframe bridge. |
| `modules/helpdesk/ticket_new.php` | Standalone manual ticket form (full page). |
| `modules/helpdesk/ticket_view.php` | Ticket detail: status, assign, thread, reply, worklog, timesheet, service/pricing, attachments, merge modal. |
| `modules/helpdesk/merge.php` | Merge two tickets (messages, attachments, timesheets, services with conflict handling), audit, optional redirect after merge. |
| `modules/helpdesk/requesters.php` | Requester CRUD, portal login provisioning, password reset email. |
| `modules/helpdesk/scheduled.php` | Scheduled recurring tickets: add/edit, attachments, log-again, delete. |
| `modules/helpdesk/attachment.php` | Secure attachment download. |
| `modules/helpdesk/admin/mail_settings.php` | POP/SMTP mail configuration UI. |
| `modules/helpdesk/admin/templates.php` | HTML email template editor. |
| `modules/helpdesk/admin/rules.php` | Notification rules (event → template). |
| `modules/helpdesk/reports/index.php` | Reports hub with filters and export links. |
| `modules/helpdesk/reports/export.php` | CSV export (open/closed/on_hold/overdue, incl. on-hold reason column). |
| `modules/helpdesk/reports/billing_report.php` | Billing-style closed-ticket report. |
| `modules/helpdesk/reports/bulk_delete.php` | Bulk delete closed tickets and files. |
| `modules/helpdesk/requester_portal/login.php` | Requester portal login. |
| `modules/helpdesk/requester_portal/index.php` | Requester ticket list. |
| `modules/helpdesk/requester_portal/ticket.php` | Requester ticket thread view. |
| `modules/helpdesk/requester_portal/logout.php` | Portal logout. |

---

## Helpdesk — includes (shared logic)

| File | Role |
|------|------|
| `modules/helpdesk/includes/bootstrap.php` | Module bootstrap, paths, includes. |
| `modules/helpdesk/includes/functions.php` | Permissions helpers, overdue logic, threading, assignment pools, mail config, scheduled helpers, merge/copy helpers, **UI helpers** (`hd_ui_css`, status badges). |
| `modules/helpdesk/includes/MailService.php` | PHPMailer SMTP send, ticket threading headers, raw/template sends. |
| `modules/helpdesk/includes/NotificationService.php` | Rule-driven notifications. |

---

## Helpdesk — cron / POP / scheduled jobs

| File | Role |
|------|------|
| `modules/helpdesk/cron/pop_sync.php` | POP3 ingest, threading, attachments, ticket create/reply. |
| `modules/helpdesk/cron/run_scheduled.php` | Overdue refresh + scheduled job runner. |
| Root `docker-compose.yml` | `db` (MySQL), `web` (PHP 8.2 Apache), `helpdesk-php74` (profile `tools` for POP). |
| `docker/README.md` | How to run stack, import dumps, ports. |
| `modules/helpdesk/cron/php74` | Bash wrapper: `docker compose run` (or legacy `USE_LEGACY_PHP74_DOCKER=1` + `docker run`). |
| `modules/helpdesk/cron/docker-php74.ps1` | PowerShell: `docker compose run` for POP sync. |
| `modules/helpdesk/cron/pop-sync-docker.cmd` | Windows CMD: `docker compose run` POP (manual). |
| `helpdesk-pop-sync.ps1` (project root) | Manual POP sync (Docker), no schedule. |
| `helpdesk-pop-sync.cmd` (project root) | Same as `.ps1`, for CMD double-click. |
| `.dockerignore` (project root) | Smaller Docker build context. |
| `modules/helpdesk/cron/Dockerfile.php74-imap` | Docker image for PHP 7.4 CLI with IMAP. |
| `modules/helpdesk/cron/README_CRON.md` | Cron setup notes. |

**Note:** Files under `modules/helpdesk/cron/logs/` are **runtime log output**, not hand-edited source.

---

## Helpdesk — database SQL

| File | Role |
|------|------|
| `modules/helpdesk/sql/migrate.sql` | Full schema + seeds (fresh install). |
| `modules/helpdesk/sql/seed_permissions.sql` | Seed Helpdesk permissions for admin role. |
| `modules/helpdesk/sql/patch_existing_db_helpdesk_notifications.sql` | Incremental patch for existing DBs (rules, columns, scheduled attachments table, etc.). |

---

## Helpdesk — documentation (in module)

| File | Role |
|------|------|
| `modules/helpdesk/POP_SMTP_LIVE_SETUP.md` | Live POP/SMTP setup, PHP 7.4 split-runtime notes. |
| `modules/helpdesk/POP_CRON_LINUX_SERVER.md` | Linux production `crontab` for POP + `run_scheduled.php`. |
| `modules/helpdesk/HELPDESK_CHANGED_FILES.md` | This file — inventory of changed/added paths. |
| `modules/helpdesk/HELPDESK_MODULE_HANDOFF.md` | Handoff: what’s done, gaps, doc index, new-machine setup. |
| `modules/helpdesk/REQUIREMENTS_TRACEABILITY.md` | Spec (PDF) vs implementation matrix, gaps, optional enhancements. |

---

## Reference specification (unchanged; used for requirements)

| File | Role |
|------|------|
| `modules/helpdesk/Helpdesk Module (1).pdf` | Functional specification (may duplicate at project root). |

---

## Quick count

- **ERP / shared:** 4 files (`config.php`, `modules/config.php`, `index.php`, `modules/reports/roles/permissions.php`).
- **Helpdesk module:** 30+ tracked source files (PHP, SQL, Markdown, Dockerfile, cron wrapper) under `modules/helpdesk/`, excluding generated `cron/logs/*`.

If you use Git: `modules/helpdesk/` may appear as a single untracked tree until committed; the four modified files above are tracked edits at repo root level.
