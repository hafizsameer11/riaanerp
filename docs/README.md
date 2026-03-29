# Project documentation

Markdown for operators and developers lives here, **outside** `modules/`, so production deployments can omit or restrict this tree if desired.

| Folder | Contents |
|--------|----------|
| **`helpdesk/`** | POP/SMTP, Linux cron, handoff, requirements traceability, changed-files list |
| **`backup_monitoring/`** | Cron, logs layout, debugging and fix summaries |
| **`docker/`** | Compose stack, large SQL imports, Helpdesk POP in Docker |
| **`development/`** | Windows helpers (e.g. Composer + XAMPP) |

Start here for Helpdesk: **`helpdesk/HELPDESK_MODULE_HANDOFF.md`**.
