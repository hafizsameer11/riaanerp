# All Parser Scripts Updated - PHP 7.4 Protection

## Summary
All scripts that use IMAP functions have been updated with PHP 7.4 version checks and IMAP availability verification to prevent "undefined function" errors.

## Files Updated

### Email Parser Scripts (Main)
1. ✅ **read_nas_emails.php** - NAS backup email parser
2. ✅ **read_client_emails.php** - Client backup email parser
3. ✅ **read_veeam_emails.php** - Veeam backup email parser
4. ✅ **read_veeamcloud_emails.php** - VeeamCloud backup email parser

### Master Script
5. ✅ **run_all_parsers.php** - Master script that runs all parsers

### Test/Diagnostic Scripts
6. ✅ **test_email_connection.php** - Email connection testing script
7. ✅ **quick_test.php** - Quick email parsing test
8. ✅ **diagnose.php** - Diagnostic script for system checks

## Protection Added to Each Script

All scripts now include:

1. **PHP Version Check**
   - Verifies PHP 7.4 is being used
   - Rejects PHP 8.2 (which doesn't have IMAP)
   - Shows clear error message if wrong version

2. **IMAP Extension Check**
   - Verifies IMAP extension is loaded
   - Exits with error if not available

3. **IMAP Function Check**
   - Verifies `imap_open()` function exists
   - Prevents "undefined function" errors

## Error Messages

If wrong PHP version is used, scripts show:
```
ERROR: This script must run with PHP 7.4 (IMAP extension required)
Current PHP version: 8.2.28
PHP 8.2 does not have IMAP extension enabled.
Please run this script with: php7.4 [script_name].php
```

## Verification Tests

### ✅ Test 1: PHP 8.2 Rejection
All scripts correctly reject PHP 8.2:
```bash
php8.2 read_nas_emails.php          # ✅ Rejected
php8.2 read_client_emails.php       # ✅ Rejected
php8.2 read_veeam_emails.php         # ✅ Rejected
php8.2 read_veeamcloud_emails.php    # ✅ Rejected
php8.2 test_email_connection.php     # ✅ Rejected
php8.2 quick_test.php                # ✅ Rejected
php8.2 diagnose.php                  # ✅ Rejected
```

### ✅ Test 2: PHP 7.4 Acceptance
All scripts work correctly with PHP 7.4:
```bash
php7.4 read_nas_emails.php          # ✅ Works
php7.4 read_client_emails.php       # ✅ Works
php7.4 read_veeam_emails.php         # ✅ Works
php7.4 read_veeamcloud_emails.php    # ✅ Works
php7.4 test_email_connection.php     # ✅ Works
php7.4 quick_test.php                # ✅ Works
php7.4 diagnose.php                  # ✅ Works
```

## Files NOT Updated (No IMAP Usage)

These files don't use IMAP functions, so no updates needed:
- ✅ **check_missing_backups.php** - Only uses database, no IMAP
- ✅ **EmailParserHelper.php** - Helper class, checks IMAP but doesn't require it
- ✅ **test_php74.php** - Test script, already has checks
- ✅ **README_CRON.md** - Documentation only
- ✅ **CRON_SETUP_SUMMARY.md** - Documentation only
- ✅ **FIX_SUMMARY.md** - Documentation only

## Current Status

✅ **All IMAP-using scripts are protected:**
- Will not crash with "undefined function" errors
- Clear error messages if wrong PHP version
- Cron job uses PHP 7.4 explicitly
- All scripts verified and tested

## Cron Job Configuration

The cron job is configured to use PHP 7.4:
```cron
* * * * * /usr/bin/php7.4 /var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php >> /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt 2>&1
```

This ensures all child scripts (parsers) are called with PHP 7.4.

## Date Updated
2026-01-29

## Notes

- All scripts now have consistent error handling
- Protection is at the script level (not just cron level)
- Scripts can be run manually and will still be protected
- Clear, helpful error messages guide users to use PHP 7.4
