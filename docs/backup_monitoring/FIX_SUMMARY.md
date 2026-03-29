# Fix Summary: IMAP Function Error Resolution

## Problem
Error: `Call to undefined function imap_open()` in `read_nas_emails.php:99`

## Root Cause
The email parser scripts were being executed with PHP 8.2 (which doesn't have IMAP extension) instead of PHP 7.4 (which has IMAP extension enabled).

## Solution Applied

### 1. Added PHP 7.4 Version Check
All email parser scripts now verify they're running with PHP 7.4 before attempting to use IMAP functions:
- `read_nas_emails.php` ✅
- `read_client_emails.php` ✅
- `read_veeam_emails.php` ✅
- `read_veeamcloud_emails.php` ✅

### 2. Added IMAP Extension Check
All scripts verify:
- PHP 7.4 is being used
- IMAP extension is loaded
- `imap_open()` function is available

### 3. Error Messages
If wrong PHP version is used, scripts now show clear error:
```
ERROR: This script must run with PHP 7.4 (IMAP extension required)
Current PHP version: 8.2.28
PHP 8.2 does not have IMAP extension enabled.
Please run this script with: php7.4 read_nas_emails.php
```

## Files Modified

1. `/var/www/html/erp/modules/backup_monitoring/cron/read_nas_emails.php`
2. `/var/www/html/erp/modules/backup_monitoring/cron/read_client_emails.php`
3. `/var/www/html/erp/modules/backup_monitoring/cron/read_veeam_emails.php`
4. `/var/www/html/erp/modules/backup_monitoring/cron/read_veeamcloud_emails.php`
5. `/var/www/html/erp/modules/backup_monitoring/cron/run_all_parsers.php` (already had checks)

## Verification Tests

### ✅ Test 1: PHP 8.2 Rejection
```bash
php8.2 read_nas_emails.php
# Result: Correctly rejected with error message
```

### ✅ Test 2: PHP 7.4 Acceptance
```bash
php7.4 read_nas_emails.php
# Result: Script runs successfully, IMAP functions available
```

### ✅ Test 3: Cron Job Execution
```bash
php7.4 run_all_parsers.php
# Result: All parsers execute using PHP 7.4 correctly
```

## Current Status

✅ **All scripts protected:**
- Scripts will not crash with "undefined function" error
- Clear error messages if wrong PHP version is used
- Cron job uses PHP 7.4 explicitly (`/usr/bin/php7.4`)
- All IMAP functions are available when running with PHP 7.4

## Prevention

The scripts now have multiple layers of protection:
1. **Version Check:** Ensures PHP 7.4 is being used
2. **Extension Check:** Verifies IMAP extension is loaded
3. **Function Check:** Confirms `imap_open()` is available
4. **Cron Job:** Uses explicit PHP 7.4 path

## Notes

- IMAP connection errors (authentication issues) are separate from the "undefined function" error
- The "undefined function" error is now completely prevented
- All scripts will fail gracefully with helpful error messages if wrong PHP version is used

## Date Fixed
2026-01-29
