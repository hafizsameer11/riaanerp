# Separate Log Files for Each Parser

## Log File Structure

The cron job system now creates separate log files for each backup source type:

### Main Log File
- **Location:** `/var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt`
- **Purpose:** Combined log showing all parsers' summary
- **Content:** High-level status for all parsers

### Individual Log Files
- **Location:** `/var/www/html/erp/modules/backup_monitoring/cron/logs/`
- **Files:**
  - `client_log.txt` - CLIENT backup parser logs
  - `veeam_log.txt` - VEEAM backup parser logs
  - `veeamcloud_log.txt` - VEEAMCLOUD backup parser logs
  - `nas_log.txt` - NAS backup parser logs

## Log File Contents

### Main Log (`cron_log.txt`)
- Shows when each parser starts
- Shows summary results (SUCCESS/FAILED)
- Shows truncated output (first 5000 + last 2000 chars)
- Quick overview of all parsers

### Individual Logs (e.g., `nas_log.txt`)
- **Full detailed output** from the parser
- Complete email processing details
- All debug information
- Device extraction details
- Database insertion results
- Error messages (if any)

## Viewing Logs

### View Main Log
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt
```

### View NAS Parser Log
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/logs/nas_log.txt
```

### View VEEAM Parser Log
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/logs/veeam_log.txt
```

### View CLIENT Parser Log
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/logs/client_log.txt
```

### View VEEAMCLOUD Parser Log
```bash
tail -f /var/www/html/erp/modules/backup_monitoring/cron/logs/veeamcloud_log.txt
```

## Log Rotation

Log files will grow over time. You can:

1. **Clear individual logs:**
   ```bash
   > /var/www/html/erp/modules/backup_monitoring/cron/logs/nas_log.txt
   ```

2. **Clear all logs:**
   ```bash
   > /var/www/html/erp/modules/backup_monitoring/cron/cron_log.txt
   > /var/www/html/erp/modules/backup_monitoring/cron/logs/*.txt
   ```

3. **Archive old logs:**
   ```bash
   mv /var/www/html/erp/modules/backup_monitoring/cron/logs/nas_log.txt \
      /var/www/html/erp/modules/backup_monitoring/cron/logs/nas_log_$(date +%Y%m%d).txt
   ```

## Benefits

✅ **Easier Debugging:** Check specific parser logs without scrolling through all parsers  
✅ **Full Details:** Individual logs contain complete output (not truncated)  
✅ **Better Organization:** Separate concerns - each backup source has its own log  
✅ **Faster Troubleshooting:** Go directly to the relevant log file  

## Log File Locations Summary

```
/var/www/html/erp/modules/backup_monitoring/cron/
├── cron_log.txt              (Main combined log)
└── logs/
    ├── client_log.txt        (CLIENT parser)
    ├── veeam_log.txt         (VEEAM parser)
    ├── veeamcloud_log.txt    (VEEAMCLOUD parser)
    └── nas_log.txt           (NAS parser)
```

## Date Created
2026-01-29
