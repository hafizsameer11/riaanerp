-- Run this SQL to update existing database with new columns and table

USE firewall_monitor;

-- Add new columns to site_actions table
ALTER TABLE site_actions 
ADD COLUMN IF NOT EXISTS ssh_success TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS command_executed TEXT;

-- Create ping_logs table if it doesn't exist
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

