# Firewall Monitor Script - monitor.php

## 📋 Table of Contents
1. [Overview](#overview)
2. [What Does monitor.php Do?](#what-does-monitorphp-do)
3. [System Requirements](#system-requirements)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Running the Monitor Script](#running-the-monitor-script)
7. [Running as a Service](#running-as-a-service)
8. [Troubleshooting](#troubleshooting)
9. [Monitoring & Logs](#monitoring--logs)
10. [Script Workflow](#script-workflow)

---

## 📖 Overview

The `monitor.php` script is the **core monitoring worker** of the Firewall Monitor System. It runs continuously in the background to monitor firewall sites, check their status, and automatically trigger failover when needed.

### Key Features
- ✅ **Continuous Monitoring:** Runs 24/7 in an infinite loop
- ✅ **Ping Testing:** Performs ping tests on primary IPs at configurable intervals
- ✅ **Status Tracking:** Updates site status (UP, DOWN, DEGRADED, UNKNOWN)
- ✅ **Automatic Failover:** Triggers failover when primary IP goes DOWN
- ✅ **Email Notifications:** Sends alerts on status changes
- ✅ **Database Logging:** Logs all activities to database
- ✅ **SSH Command Execution:** Executes failover commands via SSH

---

## 🔍 What Does monitor.php Do?

### Main Workflow

1. **Infinite Loop:** The script runs continuously, checking sites every 5 seconds
2. **Site Selection:** Queries database for all sites with `monitoring_enabled=1`
3. **Ping Testing:** For each enabled site:
   - Pings the primary IP address
   - Uses site-specific ping count and interval settings
   - Determines status based on ping success rate
4. **Status Update:** Updates site status in database:
   - **UP:** All pings successful
   - **DOWN:** All pings failed
   - **DEGRADED:** Some pings successful, some failed
5. **Automatic Failover:** When status changes from UP/DEGRADED to DOWN:
   - Executes failover command via SSH to secondary IP
   - Logs the action to database
   - Sends email notification
6. **Logging:** Records every ping check to `ping_logs` table

### Code Structure

```php
while (true) {
    // 1. Get all enabled sites
    $sites = db()->query("SELECT * FROM sites WHERE monitoring_enabled=1");
    
    // 2. Loop through each site
    foreach ($sites as $s) {
        // 3. Perform ping test
        $ping = pingHost($s['primary_ip'], $count, $interval);
        
        // 4. Determine status
        if ($ping['successes'] === $count) {
            $status = "UP";
        } elseif ($ping['successes'] === 0) {
            $status = "DOWN";
        } else {
            $status = "DEGRADED";
        }
        
        // 5. Update database
        UPDATE sites SET status=?, last_ping_time=NOW()...
        
        // 6. Trigger failover if DOWN
        if ($status === "DOWN" && $oldStatus !== "DOWN") {
            runSshCommand(...); // Execute failover
            sendNotificationEmail(...); // Send alert
        }
    }
    
    sleep(5); // Wait 5 seconds before next check
}
```

---

## 🔧 System Requirements

### Required Software
- **PHP 7.4+** or **PHP 8.x** (CLI version)
- **MySQL/MariaDB** database server
- **Linux/Unix** operating system
- **SSH2 PHP Extension** (for SSH command execution)
- **OpenSSL PHP Extension** (for password encryption/decryption)
- **ping** command (usually pre-installed on Linux)

### PHP Extensions Required
```bash
php-ssh2      # For SSH command execution
php-openssl   # For password encryption/decryption
php-pdo       # For database connectivity
php-mysql     # For MySQL/MariaDB connection
```

### Verify Extensions
```bash
php -m | grep -E "ssh2|openssl|pdo|mysql"
```

Expected output:
```
mysql
openssl
pdo
pdo_mysql
ssh2
```

---

## 📦 Installation

### Step 1: Install PHP Extensions

#### Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-ssh2 php-openssl php-mysql php-pdo
```

#### CentOS/RHEL/Fedora:
```bash
sudo yum install php-ssh2 php-openssl php-mysql php-pdo
# OR for newer versions:
sudo dnf install php-ssh2 php-openssl php-mysql php-pdo
```

#### If SSH2 extension is not available via package manager:
```bash
# Install dependencies
sudo apt-get install libssh2-1-dev php-dev

# Install via PECL
sudo pecl install ssh2

# Add to PHP configuration
echo "extension=ssh2.so" | sudo tee /etc/php/8.x/cli/conf.d/20-ssh2.ini

# Verify installation
php -m | grep ssh2
```

### Step 2: Set File Permissions
```bash
cd /path/to/your/project/modules/firewall/scripts
chmod +x monitor.php
```

### Step 3: Test Database Connection
```bash
cd /path/to/your/project/modules/firewall
php -r "require 'includes/config.php'; require 'includes/db.php'; echo 'Database connected successfully!';"
```

### Step 4: Test Script Syntax
```bash
cd /path/to/your/project/modules/firewall/scripts
php -l monitor.php
```

---

## ⚙️ Configuration

### 1. Database Configuration
Edit `../includes/config.php`:

```php
define('DB_HOST', '127.0.0.1');        // Database host
define('DB_NAME', 'firewall_monitor'); // Database name
define('DB_USER', 'root');             // Database username
define('DB_PASS', 'your_password');    // Database password
```

### 2. Encryption Key
**IMPORTANT:** Change the encryption key to a secure random 32-character string:

```php
define('ENC_KEY', 'YOUR_RANDOM_32_CHAR_KEY_HERE');
```

Generate a secure key:
```bash
openssl rand -base64 32
```

### 3. SMTP Configuration
Configure email notifications:

```php
define('SMTP_HOST', 'your.smtp.server.com');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'monitor@yourdomain.com');
define('SMTP_FROM_NAME', 'Firewall Monitor');
```

### 4. Monitoring Defaults
Adjust default ping settings:

```php
define('PING_DEFAULT_COUNT', 10);        // Number of pings per check
define('PING_DEFAULT_INTERVAL_SEC', 5);  // Seconds between pings
```

**Note:** These defaults are used when a site doesn't have custom ping settings configured.

---

## 🚀 Running the Monitor Script

### Method 1: Direct Execution (Foreground)
```bash
cd /path/to/your/project/modules/firewall/scripts
php monitor.php
```

**Output:** You'll see real-time monitoring output in the terminal.

**Stop:** Press `Ctrl+C` to stop the script.

### Method 2: Background Process (nohup)
```bash
cd /path/to/your/project/modules/firewall/scripts
nohup php monitor.php > monitor.log 2>&1 &
```

**Check if running:**
```bash
ps aux | grep monitor.php
```

**View output:**
```bash
tail -f monitor.log
```

**Stop the process:**
```bash
pkill -f monitor.php
```

### Method 3: Using Screen (Recommended for Testing)
```bash
# Install screen if not available
sudo apt-get install screen  # Ubuntu/Debian
sudo yum install screen      # CentOS/RHEL

# Start a new screen session
screen -S firewall-monitor

# Run the monitor
cd /path/to/your/project/modules/firewall/scripts
php monitor.php

# Detach from screen: Press Ctrl+A, then D
# Reattach: screen -r firewall-monitor
# Kill session: screen -X -S firewall-monitor quit
```

### Method 4: Using Tmux
```bash
# Install tmux if not available
sudo apt-get install tmux  # Ubuntu/Debian
sudo yum install tmux      # CentOS/RHEL

# Start a new tmux session
tmux new -s firewall-monitor

# Run the monitor
cd /path/to/your/project/modules/firewall/scripts
php monitor.php

# Detach: Press Ctrl+B, then D
# Reattach: tmux attach -t firewall-monitor
# Kill session: tmux kill-session -t firewall-monitor
```

---

## 🔄 Running as a Service (Systemd)

### Step 1: Create Systemd Service File
```bash
sudo nano /etc/systemd/system/firewall-monitor.service
```

### Step 2: Add Service Configuration
```ini
[Unit]
Description=Firewall Monitor Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/your/project/modules/firewall/scripts
ExecStart=/usr/bin/php /path/to/your/project/modules/firewall/scripts/monitor.php
Restart=always
RestartSec=10
StandardOutput=append:/var/log/firewall-monitor.log
StandardError=append:/var/log/firewall-monitor-error.log

[Install]
WantedBy=multi-user.target
```

**Important:** 
- Replace `/path/to/your/project` with your actual project path
- Replace `www-data` with your web server user (may be `apache`, `nginx`, etc.)
- Replace `/usr/bin/php` with your PHP CLI path (find with `which php`)

### Step 3: Enable and Start Service
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable firewall-monitor.service

# Start the service
sudo systemctl start firewall-monitor.service

# Check status
sudo systemctl status firewall-monitor.service

# View logs
sudo journalctl -u firewall-monitor.service -f
```

### Step 4: Service Management Commands
```bash
# Start service
sudo systemctl start firewall-monitor

# Stop service
sudo systemctl stop firewall-monitor

# Restart service
sudo systemctl restart firewall-monitor

# Check status
sudo systemctl status firewall-monitor

# View logs
sudo journalctl -u firewall-monitor -n 100        # Last 100 lines
sudo journalctl -u firewall-monitor -f            # Follow logs
sudo journalctl -u firewall-monitor --since today # Today's logs
```

---

## 🔍 Troubleshooting

### Issue 1: SSH2 Extension Not Found
**Error:** `SSH2 extension not installed` or `Call to undefined function ssh2_connect()`

**Solution:**
```bash
# Install SSH2 extension
sudo pecl install ssh2

# Add to php.ini
echo "extension=ssh2.so" | sudo tee /etc/php/8.x/cli/conf.d/20-ssh2.ini

# Verify
php -m | grep ssh2

# Restart PHP-FPM if using web server
sudo systemctl restart php8.x-fpm
```

### Issue 2: Permission Denied
**Error:** `Permission denied` when running script

**Solution:**
```bash
# Check file permissions
ls -l monitor.php

# Make executable
chmod +x monitor.php

# Run with proper user
sudo -u www-data php monitor.php
```

### Issue 3: Database Connection Failed
**Error:** `SQLSTATE[HY000] [2002] Connection refused` or `Access denied`

**Solution:**
```bash
# Check MySQL is running
sudo systemctl status mysql
# OR
sudo systemctl status mariadb

# Start MySQL if not running
sudo systemctl start mysql

# Test connection
mysql -u root -p -e "SHOW DATABASES;"

# Verify config.php settings
cd /path/to/your/project/modules/firewall
cat includes/config.php | grep DB_
```

### Issue 4: Ping Command Not Found
**Error:** `ping: command not found` or `sh: ping: command not found`

**Solution:**
```bash
# Install ping utility
sudo apt-get install iputils-ping  # Ubuntu/Debian
sudo yum install iputils           # CentOS/RHEL

# Verify installation
which ping
ping -c 1 127.0.0.1
```

### Issue 5: Script Stops Unexpectedly
**Check logs:**
```bash
# If using systemd
sudo journalctl -u firewall-monitor -n 50

# If using nohup
tail -f monitor.log

# Check PHP errors
php -l monitor.php

# Check PHP error log
tail -f /var/log/php_errors.log
```

**Common causes:**
- Database connection lost
- SSH connection timeout
- Memory limit exceeded
- PHP fatal error

### Issue 6: SSH Commands Failing
**Verify SSH2 extension:**
```bash
php -r "if(extension_loaded('ssh2')) echo 'SSH2 loaded\n'; else echo 'SSH2 NOT loaded\n';"
```

**Test SSH connection:**
```bash
cd /path/to/your/project/modules/firewall
php tests/test_ssh.php
```

**Common SSH issues:**
- Wrong credentials
- Firewall blocking SSH port
- SSH service not running on target
- Network connectivity issues

### Issue 7: Script Not Detecting Sites
**Check:**
```bash
# Verify sites exist in database
mysql -u root -p firewall_monitor -e "SELECT id, name, monitoring_enabled FROM sites;"

# Check if monitoring is enabled
mysql -u root -p firewall_monitor -e "SELECT * FROM sites WHERE monitoring_enabled=1;"
```

---

## 📊 Monitoring & Logs

### View Real-Time Output

**If using systemd:**
```bash
sudo journalctl -u firewall-monitor -f
```

**If using nohup:**
```bash
tail -f monitor.log
```

**If using screen:**
```bash
screen -r firewall-monitor
```

**If using tmux:**
```bash
tmux attach -t firewall-monitor
```

### Check Process Status
```bash
# Check if monitor is running
ps aux | grep monitor.php

# Check resource usage
top -p $(pgrep -f monitor.php)

# Check memory usage
ps aux | grep monitor.php | awk '{print $6/1024 " MB"}'
```

### Database Logs
Monitor activity is logged in the database:

- **`ping_logs` table:** All ping check results
  - Every ping test is logged
  - Includes success/failure counts
  - Records status changes
  
- **`site_actions` table:** All actions (failover, failback, reboot)
  - Automatic failover actions
  - Manual actions from dashboard
  - SSH command results
  
- **`sites` table:** Current status and last ping time
  - Real-time status (UP/DOWN/DEGRADED/UNKNOWN)
  - Last ping timestamp
  - Success/failure counts

**Query logs:**
```sql
-- View recent ping logs
SELECT * FROM ping_logs ORDER BY created_at DESC LIMIT 50;

-- View recent actions
SELECT * FROM site_actions ORDER BY created_at DESC LIMIT 50;

-- View current site status
SELECT name, status, last_ping_time, last_ping_successes, last_ping_failures 
FROM sites WHERE monitoring_enabled=1;
```

### View Logs via Web Interface
Access the dashboard at:
```
http://your-domain/modules/firewall/pages/logs.php
```

---

## 📝 Example Output

When running correctly, you should see output like:

```
========================================
Firewall Monitor Started
========================================
2024-01-15 10:30:00 - Monitoring active...

[10:30:00] Checking 2 site(s)...
  → Yahoo (192.168.1.1): Pinging 10 times with 5s interval... ✅ 10/10 successful
  → Google (192.168.1.2): Pinging 10 times with 5s interval... ✅ 10/10 successful

[10:30:55] Checking 2 site(s)...
  → Yahoo (192.168.1.1): Pinging 10 times with 5s interval... ❌ 0/10 successful [STATUS CHANGED: UP → DOWN]
    ⚠️  PRIMARY IP DOWN - Triggering automatic failover...
    ✅ Failover command executed successfully
    📧 Email notification sent
  → Google (192.168.1.2): Pinging 10 times with 5s interval... ✅ 10/10 successful

[10:31:50] Checking 2 site(s)...
  → Yahoo (192.168.1.1): Pinging 10 times with 5s interval... ⚠️  5/10 successful [STATUS CHANGED: DOWN → DEGRADED]
  → Google (192.168.1.2): Pinging 10 times with 5s interval... ✅ 10/10 successful
```

---

## 🔄 Script Workflow

### Detailed Step-by-Step Process

1. **Initialization**
   - Loads required functions from `../includes/functions.php`
   - Displays startup message
   - Initializes iteration counter

2. **Main Loop (Infinite)**
   - Queries database for enabled sites
   - If no sites found, displays message (only on first iteration)
   - Loops through each enabled site

3. **For Each Site:**
   - **Get Settings:** Retrieves ping count and interval (uses defaults if not set)
   - **Get Old Status:** Stores current status for comparison
   - **Perform Ping Test:** Calls `pingHost()` function
   - **Determine Status:**
     - All pings successful → `UP` ✅
     - All pings failed → `DOWN` ❌
     - Some successful → `DEGRADED` ⚠️
   - **Log Ping Check:** Records to `ping_logs` table
   - **Update Database:** Updates site status and ping statistics
   - **Check for Failover:** If status changed to DOWN:
     - Decrypts SSH password
     - Executes failover command via SSH
     - Logs action to `site_actions` table
     - Sends email notification

4. **Wait Period**
   - Sleeps for 5 seconds before next iteration

### Status Determination Logic

```php
if ($ping['successes'] === $count) {
    $status = "UP";           // All pings successful
} elseif ($ping['successes'] === 0) {
    $status = "DOWN";         // All pings failed
} else {
    $status = "DEGRADED";     // Partial success
}
```

### Automatic Failover Trigger

Failover is triggered when:
- Current status is `DOWN`
- Previous status was NOT `DOWN` (to avoid repeated failover attempts)

---

## 🔐 Security Considerations

1. **Change Encryption Key:** Always use a strong, random encryption key (32+ characters)
2. **Secure Database:** Use strong database passwords and restrict access
3. **File Permissions:** Restrict access to config files (chmod 600)
4. **SSH Keys:** Consider using SSH keys instead of passwords
5. **Firewall Rules:** Restrict access to monitoring ports
6. **Log Rotation:** Implement log rotation to prevent disk fill-up
7. **User Permissions:** Run script with minimal required permissions
8. **Network Security:** Ensure SSH connections are encrypted

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the logs for error messages
3. Verify all dependencies are installed
4. Test individual components (ping, SSH, database)
5. Check database connectivity
6. Verify site configuration in dashboard

---

## 📄 File Location

This README is located at:
```
modules/firewall/scripts/README.md
```

The monitor script is located at:
```
modules/firewall/scripts/monitor.php
```

---

**Last Updated:** 2024-01-15  
**Version:** 1.0  
**Script:** monitor.php

