# Firewall Monitor System

## 📋 Table of Contents
1. [Overview](#overview)
2. [Project Structure](#project-structure)
3. [Features](#features)
4. [Quick Start](#quick-start)
5. [System Requirements](#system-requirements)
6. [Database Setup](#database-setup)
7. [Configuration](#configuration)
8. [Web Interface](#web-interface)
9. [Monitoring Script](#monitoring-script)
10. [Documentation](#documentation)

---

## 📖 Overview

The **Firewall Monitor System** is a comprehensive PHP-based solution for monitoring firewall sites, tracking their status, and automatically managing failover operations. It provides both a web-based dashboard for management and a background monitoring service for continuous site health checks.

### Key Capabilities
- **Real-time Monitoring:** Continuous ping testing of firewall primary IPs
- **Status Tracking:** Monitors and displays site status (UP, DOWN, DEGRADED, UNKNOWN)
- **Automatic Failover:** Triggers failover when primary IP goes DOWN
- **Email Notifications:** Sends alerts on status changes and critical events
- **Action Logging:** Comprehensive logging of all monitoring activities
- **SSH Integration:** Executes commands via SSH for failover, failback, and reboot operations
- **Web Dashboard:** User-friendly interface for managing sites and viewing logs

---

## 📁 Project Structure

```
modules/firewall/
├── includes/              # Core PHP files
│   ├── config.php        # Configuration settings (database, SMTP, etc.)
│   ├── db.php            # Database connection handler
│   ├── functions.php     # Core functions (SSH, ping, encryption, email)
│   └── auth.php          # Authentication system
│
├── pages/                # Web interface pages
│   ├── index.php         # Main dashboard
│   ├── site_form.php     # Add/Edit site form
│   ├── logs.php          # View action and ping logs
│   ├── manage_emails.php # Manage notification emails
│   └── settings.php      # System settings
│
├── actions/              # Action handlers
│   ├── action.php        # Manual actions (failover, failback, reboot)
│   ├── save_site.php     # Save site handler
│   ├── delete_site.php   # Delete site handler
│   ├── save_email.php    # Save email handler
│   ├── delete_email.php  # Delete email handler
│   └── logout.php        # Logout handler
│
├── scripts/              # Background scripts
│   ├── monitor.php       # Continuous monitoring worker
│   └── README.md         # Detailed monitor.php documentation
│
├── tests/                # Test files
│   ├── test_ssh.php      # SSH connection test
│   └── test_ping.php     # Ping functionality test
│
├── docs/                 # Documentation
│   └── TESTING_GUIDE.md  # Testing instructions
│
├── db.sql                # Database schema
├── index.php             # Root redirect
└── README.md             # This file
```

---

## ✨ Features

### Web Dashboard
- **Site Management:** Add, edit, and delete firewall sites
- **Real-time Status:** View current status of all monitored sites
- **Action Controls:** Manual failover, failback, and reboot operations
- **Logs Viewer:** Browse action logs and ping history
- **Email Management:** Configure notification recipients
- **Settings:** Configure default ping settings

### Monitoring Service
- **Continuous Monitoring:** Runs 24/7 checking site health
- **Automatic Failover:** Triggers failover when primary IP fails
- **Status Updates:** Real-time status updates in database
- **Email Alerts:** Notifications on status changes
- **Comprehensive Logging:** All activities logged to database

### Security
- **Password Encryption:** SSH and login passwords encrypted using AES-256-CBC
- **Authentication:** PIN-based authentication system
- **Secure Storage:** Encrypted credentials in database

---

## 🚀 Quick Start

### 1. Database Setup
```bash
mysql -u root -p < db.sql
```

### 2. Configuration
Edit `includes/config.php`:
- Set database credentials
- Configure SMTP settings
- Set encryption key
- Adjust monitoring defaults

### 3. Access Web Interface
Navigate to:
```
http://your-domain/modules/firewall/pages/index.php
```

### 4. Add Sites
- Click "Add New Site"
- Enter site details (name, IPs, SSH credentials)
- Configure ping settings
- Enable monitoring

### 5. Start Monitoring
See `scripts/README.md` for detailed instructions on running the monitor script.

---

## 🔧 System Requirements

### Server Requirements
- **PHP 7.4+** or **PHP 8.x**
- **MySQL/MariaDB** 5.7+ or 10.3+
- **Web Server** (Apache/Nginx)
- **Linux/Unix** operating system

### PHP Extensions
- `php-ssh2` - For SSH command execution
- `php-openssl` - For password encryption
- `php-pdo` - For database connectivity
- `php-mysql` - For MySQL/MariaDB connection

### System Tools
- `ping` command (usually pre-installed)
- SSH access to monitored firewalls

---

## 💾 Database Setup

### Import Database Schema
```bash
mysql -u root -p < db.sql
```

### Database Tables

**`sites`** - Firewall site information
- Site details (name, location, IPs)
- SSH credentials (encrypted)
- Ping settings
- Status information
- Failover commands

**`notification_emails`** - Email recipients
- Email addresses for notifications
- Active/inactive status

**`ping_logs`** - Ping check history
- All ping test results
- Success/failure counts
- Status changes

**`site_actions`** - Action logs
- All actions (failover, failback, reboot)
- SSH command results
- Timestamps and status

---

## ⚙️ Configuration

### Database Configuration
Edit `includes/config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'firewall_monitor');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### Encryption Key
**IMPORTANT:** Generate and set a secure encryption key:

```php
define('ENC_KEY', 'YOUR_RANDOM_32_CHAR_KEY_HERE');
```

Generate key:
```bash
openssl rand -base64 32
```

### SMTP Configuration
```php
define('SMTP_HOST', 'your.smtp.server.com');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'monitor@yourdomain.com');
define('SMTP_FROM_NAME', 'Firewall Monitor');
```

### Monitoring Defaults
```php
define('PING_DEFAULT_COUNT', 10);        // Pings per check
define('PING_DEFAULT_INTERVAL_SEC', 5);  // Seconds between pings
```

### Authentication
```php
define('PIN_CODE', '4683'); // Change to match your ERP system
```

---

## 🌐 Web Interface

### Dashboard (`pages/index.php`)
- View all monitored sites
- See real-time status
- Filter by status
- Search sites
- Quick actions (failover, failback, reboot)

### Add/Edit Site (`pages/site_form.php`)
- Configure site details
- Set primary and secondary IPs
- Configure SSH credentials
- Set ping settings
- Define failover/failback/reboot commands

### Logs (`pages/logs.php`)
- View action logs
- View ping history
- Filter by site
- Pagination support

### Email Management (`pages/manage_emails.php`)
- Add notification emails
- Remove emails
- View active recipients

### Settings (`pages/settings.php`)
- Configure default ping settings
- System-wide defaults

---

## 🔄 Monitoring Script

The monitoring script (`scripts/monitor.php`) is the core worker that runs continuously in the background.

**For detailed documentation on running the monitor script, see:**
```
scripts/README.md
```

### Quick Reference

**Run directly:**
```bash
cd scripts
php monitor.php
```

**Run as service:**
```bash
sudo systemctl start firewall-monitor
```

**View logs:**
```bash
sudo journalctl -u firewall-monitor -f
```

---

## 📚 Documentation

### Main Documentation
- **This file** (`README.md`) - System overview and setup
- **`scripts/README.md`** - Detailed monitor.php documentation

### Additional Resources
- **`docs/TESTING_GUIDE.md`** - Testing instructions
- **`db.sql`** - Database schema

### Code Documentation
- **`includes/functions.php`** - Core function documentation
- **`includes/config.php`** - Configuration options

---

## 🔐 Security Considerations

1. **Encryption Key:** Always use a strong, random 32+ character encryption key
2. **Database Security:** Use strong passwords and restrict database access
3. **File Permissions:** Restrict access to config files (chmod 600)
4. **SSH Security:** Consider using SSH keys instead of passwords
5. **Network Security:** Ensure SSH connections are encrypted
6. **Web Access:** Protect web interface with proper authentication
7. **Log Rotation:** Implement log rotation to prevent disk fill-up

---

## 🆘 Support & Troubleshooting

### Common Issues

**Database Connection:**
- Verify MySQL is running
- Check credentials in `config.php`
- Test connection: `mysql -u root -p`

**SSH Commands Failing:**
- Verify SSH2 extension is installed
- Test SSH connection manually
- Check firewall rules

**Monitor Script Not Running:**
- Check PHP extensions are installed
- Verify file permissions
- Check logs for errors

### Getting Help

1. Check `scripts/README.md` for monitor script issues
2. Review logs in database or log files
3. Test individual components (ping, SSH, database)
4. Verify all dependencies are installed

---

## 📄 License

This monitoring system is part of the Sautech ERP System.

---

**Last Updated:** 2024-01-15  
**Version:** 1.0
