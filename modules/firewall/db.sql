-- -------------------------------------------------------
-- DATABASE: firewall_monitor
-- -------------------------------------------------------

CREATE DATABASE IF NOT EXISTS firewall_monitor;

USE firewall_monitor;

-- -------------------------------------------------------
-- TABLE: sites
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    primary_ip VARCHAR(45) NOT NULL,
    secondary_ip VARCHAR(45) NOT NULL,
    ssh_username VARCHAR(255) NOT NULL,
    ssh_password_enc TEXT NOT NULL,
    description TEXT,
    login_url VARCHAR(255),
    login_username VARCHAR(255),
    login_password_enc TEXT,
    ping_count INT DEFAULT NULL,
    ping_interval_sec INT DEFAULT NULL,
    monitoring_enabled TINYINT(1) DEFAULT 1,
    failover_command TEXT NOT NULL,
    failback_command TEXT NOT NULL,
    reboot_command TEXT NOT NULL,
    status ENUM('UP','DEGRADED','DOWN','UNKNOWN') DEFAULT 'UNKNOWN',
    last_ping_time DATETIME NULL,
    last_ping_successes INT DEFAULT 0,
    last_ping_failures INT DEFAULT 0,
    last_action VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
 
-- -------------------------------------------------------
-- TABLE: notification_emails
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- TABLE: site_actions (logs)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    initiated_by VARCHAR(50) NOT NULL,
    status_before VARCHAR(20),
    status_after VARCHAR(20),
    ssh_output TEXT,
    ssh_success TINYINT(1) DEFAULT 0,
    command_executed TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- TABLE: ping_logs (ping check history)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS ping_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    ping_count INT NOT NULL,
    ping_interval_sec INT NOT NULL,
    successes INT NOT NULL,
    failures INT NOT NULL,
    status ENUM('UP','DEGRADED','DOWN') NOT NULL,
    status_changed TINYINT(1) DEFAULT 0,
    previous_status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_created (site_id, created_at)
);

CREATE TABLE IF NOT EXISTS ping_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    ping_count INT NOT NULL,
    ping_interval_sec INT NOT NULL,
    successes INT NOT NULL,
    failures INT NOT NULL,
    status ENUM('UP','DEGRADED','DOWN') NOT NULL,
    status_changed TINYINT(1) DEFAULT 0,
    previous_status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_created (site_id, created_at)
);


USE firewall_monitor;

-- Add new columns to site_actions table
ALTER TABLE site_actions 
ADD COLUMN IF NOT EXISTS ssh_success TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS command_executed TEXT;

