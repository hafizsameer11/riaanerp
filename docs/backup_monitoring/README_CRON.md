# Backup Monitoring - Cron Job Setup Guide

This guide explains how to set up automatic email parsing using cron jobs (scheduled tasks).

## 📋 Overview

The cron job system automatically checks for new backup emails and parses them into the database. This ensures your backup monitoring data is always up-to-date without manual intervention.

## 📁 Files

- **`run_all_parsers.php`** - Master script that runs all email parsers
- **`run_parsers.bat`** - Windows batch file for Task Scheduler
- **`cron_log.txt`** - Log file (created automatically)

## 🪟 Windows Setup (XAMPP)

### Method 1: Using Windows Task Scheduler (Recommended)

1. **Open Task Scheduler**
   - Press `Win + R`, type `taskschd.msc`, press Enter

2. **Create Basic Task**
   - Click "Create Basic Task" in the right panel
   - Name: `Backup Monitoring Email Parser`
   - Description: `Automatically parse backup emails every 5 minutes`

3. **Set Trigger**
   - Trigger: `Daily` (or `When the computer starts`)
   - Start date: Today
   - Recur every: `5 minutes` (or your preferred interval)
   - Duration: `Indefinitely`

4. **Set Action**
   - Action: `Start a program`
   - Program/script: `C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron\run_parsers.bat`
   - Start in: `C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron`

5. **Finish**
   - Check "Open the Properties dialog for this task when I click Finish"
   - In Properties:
     - General tab: Check "Run whether user is logged on or not"
     - Settings tab: 
       - Check "Allow task to be run on demand"
       - Check "Run task as soon as possible after a scheduled start is missed"
       - Check "If the task fails, restart every: 1 minute" (up to 3 times)

6. **Test**
   - Right-click the task → "Run"
   - Check `cron_log.txt` for results

### Method 2: Using Command Line (Advanced)

Create a scheduled task via command line:

```cmd
schtasks /create /tn "Backup Monitoring Email Parser" /tr "C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron\run_parsers.bat" /sc minute /mo 5 /ru SYSTEM
```

## 🐧 Linux/Unix Setup

### Using Cron

1. **Edit crontab**
   ```bash
   crontab -e
   ```

2. **Add cron job** (runs every 5 minutes)
   ```cron
   */5 * * * * /usr/bin/php7.4 /var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php >> /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt 2>&1
   ```
   
   **IMPORTANT:** Use `php7.4` instead of `php` because PHP 7.4 has IMAP extension enabled, while PHP 8.2 does not.

3. **Save and exit**

### Using Systemd Timer (Alternative)

Create `/etc/systemd/system/backup-monitoring-parser.service`:
```ini
[Unit]
Description=Backup Monitoring Email Parser
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/bin/php7.4 /var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php
User=www-data
```

Create `/etc/systemd/system/backup-monitoring-parser.timer`:
```ini
[Unit]
Description=Run Backup Monitoring Parser every 5 minutes

[Timer]
OnBootSec=5min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
```

Enable and start:
```bash
sudo systemctl enable backup-monitoring-parser.timer
sudo systemctl start backup-monitoring-parser.timer
```

## ⚙️ Configuration

### Change Check Interval

**Windows Task Scheduler:**
- Edit the task → Triggers → Edit
- Change "Recur every" to your desired interval

**Linux Cron:**
- Edit crontab and change `*/5` to your desired interval:
  - `*/1` = Every 1 minute
  - `*/5` = Every 5 minutes
  - `*/10` = Every 10 minutes
  - `*/15` = Every 15 minutes
  - `*/30` = Every 30 minutes

### Recommended Intervals

- **Development/Testing:** Every 1-2 minutes
- **Production:** Every 5-15 minutes
- **Low Priority:** Every 30 minutes to 1 hour

## 📊 Monitoring

### Check Logs

View the log file:
```bash
# Windows
type C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron\cron_log.txt

# Linux
tail -f /path/to/sautech/modules/billing/backup_monitoring/cron/cron_log.txt
```

### Log File Location

The log file is automatically created at:
```
modules/billing/backup_monitoring/cron/cron_log.txt
```

### Log Format

```
[2026-01-17 10:30:00] ========================================
[2026-01-17 10:30:00] CRON JOB STARTED - Email Parsing
[2026-01-17 10:30:00] ========================================
[2026-01-17 10:30:01] Starting parser: CLIENT
[2026-01-17 10:30:01] Script: C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron\read_client_emails.php
[2026-01-17 10:30:05] SUCCESS: Processed 3 email(s) for CLIENT
[2026-01-17 10:30:07] Starting parser: VEEAM
...
[2026-01-17 10:30:15] CRON JOB COMPLETED
[2026-01-17 10:30:15] Results:
[2026-01-17 10:30:15]   CLIENT: SUCCESS
[2026-01-17 10:30:15]   VEEAM: SUCCESS
[2026-01-17 10:30:15]   VEEAMCLOUD: SUCCESS
[2026-01-17 10:30:15]   NAS: SUCCESS
```

## 🔍 Troubleshooting

### Cron Job Not Running

1. **Check Task Scheduler (Windows)**
   - Open Task Scheduler
   - Find your task
   - Check "Last Run Result" (should be `0x0` for success)
   - Check "Last Run Time"

2. **Check Logs**
   - Open `cron_log.txt`
   - Look for error messages

3. **Test Manually**
   ```cmd
   cd C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron
   C:\xampp\php\php.exe run_all_parsers.php
   ```

4. **Check PHP Path**
   - Verify PHP path in `run_parsers.bat`
   - Update if PHP is installed elsewhere

### No Emails Being Parsed

1. **Check Email Connection**
   - Verify IMAP credentials in parser scripts
   - Test email connection manually

2. **Check Email Format**
   - Emails must have `[Success]`, `[Warning]`, or `[Failed]` in subject
   - Emails must contain `Name: DeviceName` in body

3. **Check Database**
   - Verify database connection
   - Check if backup_jobs exist for the device types

### Permission Issues

**Windows:**
- Run Task Scheduler as Administrator
- Set task to "Run whether user is logged on or not"
- Use SYSTEM account or a service account

**Linux:**
- Ensure PHP has read/write permissions to log file
- Ensure PHP can access database
- Check file ownership: `chown www-data:www-data cron_log.txt`

## 📝 Notes

- The cron job runs all parsers sequentially (CLIENT → VEEAM → VEEAMCLOUD → NAS)
- There's a 2-second delay between parsers to avoid overwhelming the email server
- Only **Success** status emails are shown in main tabs
- **Warning** and **Failed** emails are shown in "Undefined Mails" tab
- The cron job is independent of the web interface - it runs automatically in the background

## 🔄 Manual Execution

You can also run the parsers manually:

```cmd
# Windows
cd C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron
C:\xampp\php\php.exe run_all_parsers.php

# Linux
cd /var/www/html/erp/modules/backup_monitoring/cron
php7.4 run_all_parsers.php
```

Or run individual parsers:

```cmd
# Windows
C:\xampp\php\php.exe read_client_emails.php
C:\xampp\php\php.exe read_veeam_emails.php
C:\xampp\php\php.exe read_veeamcloud_emails.php
C:\xampp\php\php.exe read_nas_emails.php
```

## ✅ Verification

After setting up the cron job:

1. **Wait for first run** (or trigger manually)
2. **Check log file** - Should show "CRON JOB COMPLETED"
3. **Check database** - New emails should appear in `backup_logs` table
4. **Check web interface** - Data should appear in tables automatically

---

**Last Updated:** 2026-01-17
