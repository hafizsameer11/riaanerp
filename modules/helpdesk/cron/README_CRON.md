# Helpdesk cron (split runtime)

Use split runtime:

- `pop_sync.php` -> **PHP 7.4** + IMAP extension
- `run_scheduled.php` -> **PHP 8.2/8.3**

Suggested server crontab (every minute, as per client requirement):

```cron
# IMAP parser must use 7.4
* * * * * /usr/bin/php7.4 /path/to/riaanerp/modules/helpdesk/cron/pop_sync.php >> /path/to/riaanerp/modules/helpdesk/cron/logs/pop_cron.out 2>&1

# Non-IMAP scheduler can use 8.2/8.3
* * * * * /usr/bin/php8.2 /path/to/riaanerp/modules/helpdesk/cron/run_scheduled.php >> /path/to/riaanerp/modules/helpdesk/cron/logs/scheduled.out 2>&1
```

Local Docker option for IMAP (keeps host PHP at 8.2/8.3):

```bash
cd /path/to/riaanerp
docker build -t php74-imap -f modules/helpdesk/cron/Dockerfile.php74-imap .
./modules/helpdesk/cron/php74 modules/helpdesk/cron/pop_sync.php
php modules/helpdesk/cron/run_scheduled.php
```

1. Import schema: `modules/helpdesk/sql/migrate.sql`
2. Optionally seed admin permissions: `modules/helpdesk/sql/seed_permissions.sql`
3. Configure POP/SMTP under **Helpdesk → Mail Settings**

POP behaviour: messages are **deleted from the mailbox** after a successful import (same pattern as backup monitoring).
