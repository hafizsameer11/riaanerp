# Backup Monitoring - Debugging Guide

## Why Data Might Not Be Showing

### Common Issues:

1. **Backend URL Path Issue**
   - The `BACKEND_URL` might not be resolving correctly
   - Check browser console for the actual URL being used
   - Verify the path: `modules/backup_monitoring/backend.php`

2. **AJAX Request Failing**
   - Check browser Network tab (F12 → Network)
   - Look for requests to `backend.php`
   - Check the response status (200 = success, 404 = not found, 500 = server error)

3. **No Data in Database**
   - The query might be working but returning no rows
   - Check if `backup_jobs` table has data for the source (NAS, VEEAM, etc.)
   - Check if `backup_logs` table has data

4. **Response Not Being Handled**
   - Check browser console for JavaScript errors
   - Verify the response is HTML (table rows) not JSON

## How to Debug:

### Step 1: Check Browser Console
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Look for:
   - "NAS fetchJobs called"
   - "Making AJAX request to: ..."
   - "AJAX Success" or "AJAX Error"
   - Any red error messages

### Step 2: Check Network Tab
1. Open Developer Tools (F12)
2. Go to Network tab
3. Refresh the page
4. Look for request to `backend.php`
5. Click on it and check:
   - **Status**: Should be 200
   - **Response**: Should show HTML table rows
   - **Headers**: Check request/response headers

### Step 3: Check Error Logs
1. Check: `modules/backup_monitoring/logs/backend_errors.log`
2. Look for recent entries with timestamps
3. Check for ERROR or INFO messages

### Step 4: Test Backend Directly
Try accessing: `http://your-domain/modules/backup_monitoring/backend.php?action=test`

You should see: `{"success":true,"message":"Backend is working",...}`

### Step 5: Check Database
Run these SQL queries to check if data exists:

```sql
-- Check backup_jobs for NAS
SELECT COUNT(*) FROM backup_jobs WHERE backup_source = 'NAS';

-- Check backup_logs for NAS
SELECT COUNT(*) FROM backup_logs WHERE source = 'NAS';

-- Check if there are any jobs at all
SELECT backup_source, COUNT(*) as count FROM backup_jobs GROUP BY backup_source;

-- Check if there are any logs at all
SELECT source, COUNT(*) as count FROM backup_logs GROUP BY source;
```

## Expected Behavior:

1. **If data exists in backup_jobs:**
   - Should show jobs with client names, device types, status, etc.

2. **If no data in backup_jobs but data in backup_logs:**
   - Should show log entries with "N/A" for client/contact
   - Should show "(Log Entry)" indicator

3. **If no data at all:**
   - Should show: "No backup jobs found for NAS" with "Add Backup Job" button

## Error Messages to Look For:

- **"Error Loading Data"** - Backend returned an error
- **"No data received from server"** - AJAX call succeeded but response was empty
- **"Empty response from server"** - Response was empty string
- **HTTP 404** - Backend file not found (wrong path)
- **HTTP 500** - Server error (check error logs)
- **HTTP 403** - Permission denied

## Quick Fixes:

1. **If BACKEND_URL is wrong:**
   - Check the console log for "Final Backend URL:"
   - Verify it matches your server structure

2. **If AJAX is failing:**
   - Check CORS issues
   - Verify session is active
   - Check permissions

3. **If response is empty:**
   - Check error logs
   - Verify database connection
   - Check if query is executing

## Contact Support:

If issues persist, provide:
1. Browser console output
2. Network tab screenshot
3. Error log contents
4. Database query results
