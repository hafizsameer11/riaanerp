# Backup Monitoring Cron Job Setup Summary

## ✅ Setup Completed

The backup monitoring email parser cron job has been successfully configured to use PHP 7.4 with IMAP extension support.

## 🔧 Changes Made

### 1. Updated `run_all_parsers.php`
- Modified to use `/usr/bin/php7.4` instead of default `php` command
- PHP 7.4 has IMAP extension enabled (required for email parsing)
- PHP 8.2 does NOT have IMAP extension (cannot be used)

### 2. Cron Job Configuration
- **Schedule:** Every 1 minute (`* * * * *`)
- **Command:** `/usr/bin/php7.4 /var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php`
- **Log File:** `/var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt`
- **User:** root

### 3. Updated Documentation
- Updated `README_CRON.md` with correct PHP 7.4 paths
- Added notes about why PHP 7.4 must be used

## 📋 Current Cron Job

```cron
* * * * * /usr/bin/php7.4 /var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php >> /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt 2>&1
```

## 🔍 Verification

### Check Cron Job Status
```bash
crontab -l
```

### View Logs
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt
```

### Test Manually
```bash
cd /var/www/html/erp/modules/backup_monitoring/cron
php7.4 run_all_parsers.php
```

### Verify PHP 7.4 Has IMAP
```bash
php7.4 -m | grep imap
# Should output: imap
```

## ⚠️ Important Notes

1. **PHP Version:** The cron job MUST use `php7.4` because:
   - PHP 7.4 has IMAP extension enabled ✅
   - PHP 8.2 does NOT have IMAP extension ❌
   - Default `php` command points to PHP 8.2

2. **Email Configuration:** Make sure email credentials are properly configured in the database:
   - Table: `backup_mails`
   - Fields: `email_address`, `app_password`, `imap_host`
   - Status: `is_active = 1`

3. **Log File:** The log file is automatically created and appended to. Check it regularly for errors.

4. **Cron Service:** Ensure the cron service is running:
   ```bash
   systemctl status cron
   # or
   service cron status
   ```

## 🐛 Troubleshooting

### Cron Job Not Running
- Check cron service: `systemctl status cron`
- Verify cron job exists: `crontab -l`
- Check log file for errors: `tail -f cron_log.txt`

### IMAP Connection Errors
- Verify email credentials in database
- Check if IMAP is enabled in email provider settings (Gmail, etc.)
- Test IMAP connection manually using `test_email_connection.php`

### PHP Version Issues
- Always use `php7.4` explicitly in cron commands
- Verify IMAP is available: `php7.4 -m | grep imap`

## 📅 Schedule Options

To change the schedule, edit the crontab:
```bash
crontab -e
```

Common schedules:
- Every 1 minute: `* * * * *` (current)
- Every 5 minutes: `*/5 * * * *`
- Every 10 minutes: `*/10 * * * *`
- Every 15 minutes: `*/15 * * * *`
- Every 30 minutes: `*/30 * * * *`
- Every hour: `0 * * * *`

## 📝 Last Updated
2026-01-29
