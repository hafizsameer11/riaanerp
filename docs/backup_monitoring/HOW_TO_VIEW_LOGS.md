# How to View Backup Logs in Frontend

## Understanding the Display System

The backup monitoring system has two levels of data display:

### 1. Main Table View (Backup Jobs)
- **Location:** Main tabs (CLIENT, VEEAM, VEEAMCLOUD, NAS)
- **Shows:** Backup jobs from `backup_jobs` table
- **Displays:** 
  - Client name
  - Contact name
  - Device type
  - Latest status (Success/Warning/Failed)
  - Latest backup date
  - Latest backup size
- **Note:** This shows the JOB status, not individual log entries

### 2. Detailed Log View (Individual Logs)
- **Location:** Click "View Details" button (👁️) on any backup job
- **Shows:** All backup logs from `backup_logs` table for that job
- **Displays:**
  - All individual backup log entries
  - Each email that was processed
  - Full email body
  - Complete history of backups

## How Backup Logs Are Stored

1. **Email Processing:** Cron job runs every minute and processes emails
2. **Data Extraction:** Extracts device name, status, date, size from emails
3. **Job Matching:** Finds matching backup job by device_type
4. **Log Creation:** Creates entry in `backup_logs` table
5. **Job Update:** Updates `backup_jobs.latest_status` and `latest_backup_at`

## Viewing Logs

### Step 1: Go to Backup Monitoring
Navigate to: Backup Monitoring → [Tab: CLIENT/VEEAM/VEEAMCLOUD/NAS]

### Step 2: Find Your Backup Job
Look for the job in the main table (shows latest status)

### Step 3: Click "View Details"
Click the blue "View Details" button (👁️ icon) on the job row

### Step 4: See All Logs
In the modal that opens:
- **Top Section:** Job information and statistics
- **Bottom Section:** "All Backup Logs" table showing all individual log entries

## Troubleshooting

### Logs Not Showing?

1. **Check if logs exist in database:**
   ```sql
   SELECT COUNT(*) FROM backup_logs WHERE backup_job_id = [JOB_ID];
   ```

2. **Check if job exists:**
   ```sql
   SELECT * FROM backup_jobs WHERE id = [JOB_ID];
   ```

3. **Check if device name matches:**
   - Device name in email must match `device_type` in `backup_jobs` table
   - Check for case sensitivity issues
   - Check for extra spaces or special characters

4. **Check undefined_mails:**
   - If device name doesn't match any job, email is saved to `undefined_mails`
   - Go to "Undefined Mails" tab to see unmatched emails
   - Create a job from undefined mail if needed

### Common Issues

**Issue:** "No backup logs found" in View Details
- **Cause:** No emails processed yet, or device name doesn't match
- **Solution:** Check cron logs, verify device name in email matches job

**Issue:** Logs exist but not showing
- **Cause:** Wrong backup_job_id, or logs for different job
- **Solution:** Verify backup_job_id matches the job you're viewing

**Issue:** Multiple logs for same email
- **Cause:** Duplicate detection not working
- **Solution:** Check duplicate detection logic (email_subject + backup_date)

## Database Structure

### backup_logs table:
- `id` - Log entry ID
- `backup_job_id` - Links to backup_jobs.id
- `status` - Success/Warning/Error
- `backup_date` - When backup occurred
- `backup_size` - Size of backup
- `email_subject` - Email subject line
- `email_body` - Full email content
- `source` - CLIENT/VEEAM/VEEAMCLOUD/NAS
- `created_at` - When log was created

### backup_jobs table:
- `id` - Job ID
- `device_type` - Must match device name in emails
- `backup_source` - CLIENT/VEEAM/VEEAMCLOUD/NAS
- `latest_status` - Most recent status
- `latest_backup_at` - Most recent backup date
- `latest_backup_size` - Most recent backup size

## Summary

✅ **Backup logs ARE being saved to database**  
✅ **Logs are visible in "View Details" modal**  
✅ **Main table shows job status, not individual logs**  
✅ **Click "View Details" to see all log entries for a job**

The system is working correctly - logs are stored and displayed in the details view!
