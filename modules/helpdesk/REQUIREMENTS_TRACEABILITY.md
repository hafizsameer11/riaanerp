# Helpdesk — requirements traceability (spec vs implementation)

**Spec:** [Helpdesk Module (1).pdf](Helpdesk%20Module%20(1).pdf)  
**Supporting:** [HELPDESK_MODULE_HANDOFF.md](HELPDESK_MODULE_HANDOFF.md), [HELPDESK_CHANGED_FILES.md](HELPDESK_CHANGED_FILES.md)

**Status legend:** **Met** | **Partial** | **Gap** | **N/A** (not in PDF)

---

## §1 General overview

| Requirement (PDF) | Status | Evidence / notes |
|-------------------|--------|------------------|
| Built in existing ERP under Helpdesk tab | **Met** | Root [index.php](../../index.php) sidebar + iframe targets |
| Manual ticket logging | **Met** | [ticket_new.php](ticket_new.php), dashboard modal in [index.php](index.php) |
| Email-based ticket logging | **Met** | [cron/pop_sync.php](cron/pop_sync.php), [admin/mail_settings.php](admin/mail_settings.php) |
| Technician assignment (manual & randomized) | **Met** | Assignment forms; `hd_pick_random_assignee` / pool in [includes/functions.php](includes/functions.php) |
| Requester self-service | **Met** | [requester_portal/](requester_portal/) |
| Notifications | **Met** | [includes/NotificationService.php](includes/NotificationService.php), [admin/rules.php](admin/rules.php) |
| Scheduling | **Met** | [scheduled.php](scheduled.php), [cron/run_scheduled.php](cron/run_scheduled.php) |
| Reporting | **Met** | [reports/](reports/) |
| Role-based permissions | **Met** | [../reports/roles/permissions.php](../reports/roles/permissions.php), `hd_can()` in [includes/functions.php](includes/functions.php) |
| Merge tickets (client follow-up; PDF text sparse on p.3–4) | **Met*** | [merge.php](merge.php), merge modal in [ticket_view.php](ticket_view.php); *confirm visually on PDF pp.3–4 if screenshots exist |

---

## §2 Home page (dashboard)

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| Admin sees all open calls on home | **Partial** | [index.php](index.php) L197–200: **Global** tab without `role===admin` still filters to `assigned_user_id = me OR NULL`. **Admin** (`$_SESSION['role']` admin) on **Global** sees all. Aligns if “admin” = ERP admin role. |
| Non-admin only sees calls assigned to them | **Partial** | **My** tab: `assigned_user_id = uid OR unassigned` (L194–196). Spec text “only assigned” may **exclude** unassigned queue; current product **includes** unassigned for technicians. UAT with client. |
| Key info per call (subject, requester, tech, status, due, etc.) | **Met** | Dashboard table columns |
| Button to log new request | **Met** | Modal + navigation to [ticket_new.php](ticket_new.php) |

---

## §3 New request (manual)

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| Client dropdown from Client DB | **Met** | [ticket_new.php](ticket_new.php), [index.php](index.php) modal |
| Requester from Requesters | **Met** | Same |
| Technician from User Logins | **Met** | `registers` query (excludes portal role 100) |
| Due by date | **Met** | Default next working day helper `hd_dashboard_next_due()` |
| Subject, description, file upload, Add Request | **Met** | Forms + handlers |

---

## §4 Technician view (minimal list in PDF)

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| Subject, requester, due, created, ID, notes | **Met** | [ticket_view.php](ticket_view.php) — **exceeds** spec (thread, email, worklog, timesheet, service line, merge) |

---

## §5 Admin (Helpdesk tab)

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| POP incoming (same pattern as Backup Monitoring POP) | **Met** | [cron/pop_sync.php](cron/pop_sync.php), [admin/mail_settings.php](admin/mail_settings.php) |
| SMTP outgoing | **Met** | Mail settings + [MailService.php](includes/MailService.php) |
| Configurable notification rules | **Met** | [admin/rules.php](admin/rules.php) |
| HTML templates, customizable | **Met** | [admin/templates.php](admin/templates.php) |
| Templates able to include images | **Partial** | HTML `<textarea>` — **`<img src="https://...">` works**; no built-in image upload / CID attachment UI. Client must paste hosted URLs or extend mail layer for inline CID. |
| §5.3 Requester welcome email (link, login, password) | **Met** | [requesters.php](requesters.php), templates |
| Requesters only login + view own calls | **Met** | Portal queries scoped by requester |

---

## §6 Scheduled calls

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| List: subject, requester, created, edit / log again / delete | **Met** | [scheduled.php](scheduled.php) |
| Add: client, requester, technician, subject, description, upload | **Met** | Forms + [sql/migrate.sql](sql/migrate.sql) `helpdesk_scheduled_attachments` |
| Schedule: daily, weekly, monthly, periodic, one-time | **Met** | `schedule_type` enum in schema |

---

## §7 Requesters management

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| List fields; add / edit / delete | **Met** | [requesters.php](requesters.php) |
| Dropdown on new call | **Met** | Wired from `helpdesk_requesters` |
| On save: auto portal login, email = login, random password | **Met** | Requester save flow |
| Edit: reset password option | **Met** | Requester UI |

---

## §8 Reports

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| §8.1 Billing: date range, monthly-style use | **Met** | [reports/billing_report.php](reports/billing_report.php) — `date_from` / `date_to` on **closed_at**; fields align (client, ID, time, dates, tech, subject, worklog, service, unit price) |
| §8.1 Technician filter on billing | **Gap** | Billing report has **no** technician dropdown; CSV hub has tech filter but billing page does not. Add if client requires parity with §8.2 wording. |
| §8.2 Open calls CSV: filter by technician, date range | **Met** | [reports/index.php](reports/index.php) → [export.php](reports/export.php): `tech`, `date_from`, `date_to` on **created_at** |
| §8.2 Closed calls CSV: same | **Met** | Same export |
| §8.2 On-hold: export all, **include on-hold description** | **Met** | `on_hold_reason` column in CSV (export.php) |
| §8.2 Overdue: export all | **Met** | Export type `overdue`; optional tech + date filters (stricter than PDF; acceptable) |

---

## §9 Role permissions (Reporting & Admin section)

| Permission / behaviour (PDF) | Status | Evidence / notes |
|------------------------------|--------|------------------|
| Create / edit / delete / merge / close calls | **Met** | Keys in [../reports/roles/permissions.php](../reports/roles/permissions.php) |
| Randomize assignment | **Met** | `randomize assignment` + pool logic |
| Timesheet access | **Met** | `timesheet` |
| **Manual time entry** | **Gap** | PDF lists it; UI is **start/stop only** ([ticket_view.php](ticket_view.php)); no manual duration row |
| **Assign & reassign** separate from edit | **Gap** | Assignment uses **`edit ticket`** (e.g. ticket_view L89, index inline assign) |
| Randomize: auto-assign, rotate, least open prioritized | **Met** | Handoff + `hd_pick_random_assignee` |

---

## §10 Technician call actions

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| Status: Close, On Hold | **Met** | ticket_view status form |
| On hold: description mandatory | **Met** | L49–50 validation |
| On hold: **client must not receive email** for that path | **Met** | `ticket_status_changed` **skipped** when `st === 'on_hold'` (L73–78); closed still triggers `ticket_closed` |
| Worklog: not visible to client; add-to-worklog checkbox | **Met** | Internal messages, `add_to_worklog` |
| Timesheet: start/end; play/pause style | **Met** | Start/Stop buttons |
| Users with timesheet permission cannot close without time | **Met** | L51–52: if `hd_can('timesheet')` and no completed timesheet row → block close |
| Time not manually altered (timer only) | **Met** | No edit of `started_at`/`ended_at` in UI |
| Service category + unit price from Admin Services | **Met** | `save_service` + `billing_category_prices` |
| Internal worklog description | **Met** | Internal / worklog fields |
| Optional client-facing status note | **Extra** | `status_note` → outbound client-visible message (handoff) |

---

## §11 Overdue logic

| Requirement | Status | Evidence / notes |
|-------------|--------|------------------|
| Overdue only after 48 **working** hours | **Met** | `hd_add_working_hours`, Mon–Fri in [includes/functions.php](includes/functions.php) |
| Weekends excluded | **Met** | Same helper |

---

## Handoff gaps (non-PDF or operational)

| Topic | Status | Notes |
|-------|--------|-------|
| Security (passwords / hashing) | **Gap** | Legacy ERP patterns; hardening is separate project |
| Assets tab | **Partial** | Bridge to Clients module, not dedicated asset DB |
| POP cron + PHPMailer on PHP 7.4 | **Risk** | Verify SMTP notifications on production split runtime ([HELPDESK_MODULE_HANDOFF.md](HELPDESK_MODULE_HANDOFF.md)) |
| Production UAT | **Pending** | Client-run checklist §6 handoff |

---

## PDF pages 3–4 — manual review checklist

Extracted text for printed pages **3–4** is mostly empty; layout/merge/email rules may be **screenshot-only**.

- [ ] Open PDF visually and record any **merge** or **threading** rules not captured above.
- [ ] Compare **notification rules** UI to any screenshot referenced in PDF §5.2.
- [ ] Confirm **dashboard wireframe** vs current My/Global/Assets tabs.

---

## Optional enhancements (not in PDF) — effort hint

| Idea | Value | Rough effort |
|------|--------|--------------|
| SLA dashboards (first response, resolution, backlog charts) | Ops visibility | Medium–high |
| Ticket priority / category fields | Triage, reporting | Low–medium |
| Knowledge base or canned replies | Technician speed | Medium |
| Inbound webhook / API to create tickets | Integrations | Medium–high |
| Audit log UI (beyond `helpdesk_merge_audit`) | Compliance | Medium |
| Portal 2FA + password hashing | Security baseline | High |
| IMAP IDLE / provider push instead of 1-min POP | Mail latency | Provider-specific |

---

## Related docs

- [POP_CRON_LINUX_SERVER.md](POP_CRON_LINUX_SERVER.md) — production `crontab`
- [POP_SMTP_LIVE_SETUP.md](POP_SMTP_LIVE_SETUP.md) — mail + troubleshooting
- [cron/README_CRON.md](cron/README_CRON.md) — PHP 7.4 / 8.2 split, Docker

*Last generated for requirements analysis; update rows as client signs off or scope changes.*
