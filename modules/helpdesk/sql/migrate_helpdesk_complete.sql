-- =============================================================================
-- Helpdesk — single migration (schema + seeds + ERP roles + sample user)
-- =============================================================================
-- Run against your ERP database (often `clientzone`):
--   mysql -u DB_USER -p clientzone < modules/helpdesk/sql/migrate_helpdesk_complete.sql
--
-- Safe to run on:
--   • Empty DB / first install — creates all Helpdesk tables and seeds.
--   • Existing DB that already ran migrate.sql / patches — idempotent ALTERs
--     and ON DUPLICATE KEY updates fill gaps.
--
-- Before run: edit section (8) variables — username, email, and password.
-- Passwords in `registers` are plain text in this ERP (see modules/auth/login-backend.php).
--
-- Includes:
--   • Full Helpdesk schema (same as migrate.sql)
--   • Extra email templates + notification rules (status / on hold / closed)
--   • Column/index fixes for older installs
--   • Role id 100 = Helpdesk Requester (portal)
--   • Role "Helpdesk Technician" + permissions
--   • All Helpdesk permissions for Admin role (by name `admin` or id 3)
--   • One sample ERP user linked to Helpdesk Technician
-- =============================================================================

-- One collation for literals + user variables vs mixed table collations (avoids #1267).
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 1) Mail configuration (single POP + SMTP row, id=1)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_mail_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pop_host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pop_port` int NOT NULL DEFAULT 110,
  `pop_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pop_password` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pop_ssl` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_port` int NOT NULL DEFAULT 587,
  `smtp_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_password` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_ssl` tinyint(1) NOT NULL DEFAULT 1,
  `smtp_from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Helpdesk',
  `randomize_incoming` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `helpdesk_mail_config` (`id`) VALUES (1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ---------------------------------------------------------------------------
-- 2) Requesters
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_requesters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `work_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `register_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) Tickets
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `requester_id` int DEFAULT NULL,
  `assigned_user_id` int DEFAULT NULL,
  `status` enum('open','on_hold','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `due_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  `on_hold_reason` text COLLATE utf8mb4_unicode_ci,
  `is_overdue` tinyint(1) NOT NULL DEFAULT 0,
  `source` enum('manual','email','scheduled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `merged_into_ticket_id` int DEFAULT NULL,
  `email_subject` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `random_assigned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status_assigned` (`status`,`assigned_user_id`),
  KEY `idx_status_due` (`status`,`due_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_merged` (`merged_into_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4) Messages / thread
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `direction` enum('in','out','internal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in',
  `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `is_client_visible` tinyint(1) NOT NULL DEFAULT 1,
  `add_to_worklog` tinyint(1) NOT NULL DEFAULT 0,
  `worklog_description` text COLLATE utf8mb4_unicode_ci,
  `email_message_id` varchar(998) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_in_reply_to` varchar(998) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_references` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_msgid` (`email_message_id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5) Attachments
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `message_id` int DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6) Service line on ticket
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_ticket_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `service_category_id` int DEFAULT NULL,
  `category_price_id` int DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7) Timesheets
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_timesheets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `is_running` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_user` (`ticket_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 8) Scheduled recurring tickets
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_scheduled_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `requester_id` int DEFAULT NULL,
  `assigned_user_id` int DEFAULT NULL,
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `schedule_type` enum('daily','weekly','monthly','periodic','once') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'once',
  `schedule_interval_days` int DEFAULT NULL,
  `next_run_at` datetime NOT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_next` (`next_run_at`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `helpdesk_scheduled_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scheduled_job_id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sched_job` (`scheduled_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 9) Email templates + notification rules (base)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `helpdesk_email_templates` (`code`,`name`,`subject`,`body_html`,`is_active`) VALUES
('ticket_created','New ticket','Ticket {{ticket_id}}: {{subject}}','<p>Hello {{requester_name}},</p><p>Your request has been logged as ticket <strong>#{{ticket_id}}</strong>.</p><p>{{subject}}</p>',1),
('ticket_reply','Reply from helpdesk','Re: {{subject}}','<p>{{body}}</p>',1),
('requester_welcome','Requester welcome','Your helpdesk login','<p>Hello {{name}},</p><p>Login URL: {{login_url}}<br>Username: {{username}}<br>Password: {{password}}</p>',1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

CREATE TABLE IF NOT EXISTS `helpdesk_notification_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event` (`event_code`),
  KEY `idx_event` (`event_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `helpdesk_notification_rules` (`event_code`, `template_id`, `is_active`)
SELECT 'ticket_created', t.id, 1
FROM helpdesk_email_templates t
WHERE t.code = 'ticket_created'
ON DUPLICATE KEY UPDATE `template_id` = VALUES(`template_id`);

-- ---------------------------------------------------------------------------
-- 10) Merge audit
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `helpdesk_merge_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_ticket_id` int NOT NULL,
  `into_ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `merged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 11) Role for portal requesters (fixed id — used in Helpdesk PHP filters)
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`) VALUES (100, 'Helpdesk Requester')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ---------------------------------------------------------------------------
-- 12) Older installs: ensure mail config column + unique rule per event
-- ---------------------------------------------------------------------------
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'helpdesk_mail_config'
    AND column_name = 'randomize_incoming'
);
SET @sql_col := IF(
  @has_col = 0,
  'ALTER TABLE `helpdesk_mail_config` ADD COLUMN `randomize_incoming` tinyint(1) NOT NULL DEFAULT 0 AFTER `smtp_from_name`',
  'SELECT 1'
);
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @has_uniq := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'helpdesk_notification_rules'
    AND index_name = 'uniq_event'
);
SET @sql_uniq := IF(
  @has_uniq = 0,
  'ALTER TABLE `helpdesk_notification_rules` ADD UNIQUE KEY `uniq_event` (`event_code`)',
  'SELECT 1'
);
PREPARE stmt_uniq FROM @sql_uniq;
EXECUTE stmt_uniq;
DEALLOCATE PREPARE stmt_uniq;

-- ---------------------------------------------------------------------------
-- 13) Extra templates + rules (optional notifications; some off by default)
-- ---------------------------------------------------------------------------
INSERT INTO `helpdesk_email_templates` (`code`,`name`,`subject`,`body_html`,`is_active`) VALUES
('ticket_status_changed','Ticket status changed','Ticket {{ticket_id}} status: {{status}}','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> status changed to <strong>{{status}}</strong>.</p>',1),
('ticket_on_hold','Ticket on hold','Ticket {{ticket_id}} is on hold','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> is on hold.</p><p>Reason: {{on_hold_reason}}</p>',1),
('ticket_closed','Ticket closed','Ticket {{ticket_id}} closed','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> has been closed.</p>',1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `subject` = VALUES(`subject`);

INSERT INTO `helpdesk_notification_rules` (`event_code`, `template_id`, `is_active`)
SELECT 'ticket_status_changed', t.id, 0
FROM helpdesk_email_templates t
WHERE t.code = 'ticket_status_changed'
ON DUPLICATE KEY UPDATE `template_id` = VALUES(`template_id`);

INSERT INTO `helpdesk_notification_rules` (`event_code`, `template_id`, `is_active`)
SELECT 'ticket_on_hold', t.id, 0
FROM helpdesk_email_templates t
WHERE t.code = 'ticket_on_hold'
ON DUPLICATE KEY UPDATE `template_id` = VALUES(`template_id`);

INSERT INTO `helpdesk_notification_rules` (`event_code`, `template_id`, `is_active`)
SELECT 'ticket_closed', t.id, 0
FROM helpdesk_email_templates t
WHERE t.code = 'ticket_closed'
ON DUPLICATE KEY UPDATE `template_id` = VALUES(`template_id`);

-- ---------------------------------------------------------------------------
-- 14) Grant all Helpdesk permissions to Admin role
-- ---------------------------------------------------------------------------
SET @admin_role_id := (
  SELECT id FROM roles
  WHERE (LOWER(TRIM(name)) COLLATE utf8mb4_unicode_ci) = CONVERT('admin' USING utf8mb4) COLLATE utf8mb4_unicode_ci
  LIMIT 1
);
SET @admin_role_id := IFNULL(@admin_role_id, 3);

DELETE FROM permissions WHERE page = 'helpdesk' AND role_id = @admin_role_id;

INSERT INTO permissions (role_id, page, function_name, allowed) VALUES
(@admin_role_id, 'helpdesk', 'dashboard', 1),
(@admin_role_id, 'helpdesk', 'create ticket', 1),
(@admin_role_id, 'helpdesk', 'edit ticket', 1),
(@admin_role_id, 'helpdesk', 'delete ticket', 1),
(@admin_role_id, 'helpdesk', 'merge tickets', 1),
(@admin_role_id, 'helpdesk', 'close ticket', 1),
(@admin_role_id, 'helpdesk', 'reply email', 1),
(@admin_role_id, 'helpdesk', 'admin mail', 1),
(@admin_role_id, 'helpdesk', 'reports', 1),
(@admin_role_id, 'helpdesk', 'bulk delete', 1),
(@admin_role_id, 'helpdesk', 'scheduled', 1),
(@admin_role_id, 'helpdesk', 'requesters', 1),
(@admin_role_id, 'helpdesk', 'randomize assignment', 1),
(@admin_role_id, 'helpdesk', 'timesheet', 1);

-- ---------------------------------------------------------------------------
-- 15) "Helpdesk Technician" role (limited — full access use Admin or Role Permissions UI)
-- ---------------------------------------------------------------------------
SET @next_role_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM roles);

INSERT INTO roles (id, name)
SELECT @next_role_id, 'Helpdesk Technician'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM roles
    WHERE (LOWER(TRIM(name)) COLLATE utf8mb4_unicode_ci) = CONVERT('helpdesk technician' USING utf8mb4) COLLATE utf8mb4_unicode_ci
);

SET @hd_tech_role_id := (
    SELECT id FROM roles
    WHERE (LOWER(TRIM(name)) COLLATE utf8mb4_unicode_ci) = CONVERT('helpdesk technician' USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ORDER BY id DESC LIMIT 1
);

DELETE FROM permissions WHERE role_id = @hd_tech_role_id AND page = 'helpdesk';

INSERT INTO permissions (role_id, page, function_name, allowed) VALUES
(@hd_tech_role_id, 'helpdesk', 'dashboard', 1),
(@hd_tech_role_id, 'helpdesk', 'create ticket', 1),
(@hd_tech_role_id, 'helpdesk', 'close ticket', 1),
(@hd_tech_role_id, 'helpdesk', 'reply email', 1),
(@hd_tech_role_id, 'helpdesk', 'timesheet', 1);

-- ---------------------------------------------------------------------------
-- 16) Sample ERP user → Helpdesk Technician (EDIT values)
-- ---------------------------------------------------------------------------
-- Plain-text password matches modules/auth/login-backend.php behaviour.
SET @hd_seed_username := 'helpdesk_tech';
SET @hd_seed_password := 'ChangeMe!123';
SET @hd_seed_email := 'helpdesk@example.com';

SET @next_reg_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM registers);

INSERT INTO registers (id, name, surname, address, email, username, password, role_id)
SELECT @next_reg_id, 'Helpdesk', 'Technician', '-', @hd_seed_email, @hd_seed_username, @hd_seed_password, @hd_tech_role_id
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM registers r
    WHERE r.username COLLATE utf8mb4_unicode_ci
        = CAST(@hd_seed_username AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
  )
  AND @hd_tech_role_id IS NOT NULL;

-- =============================================================================
-- Done. Log in as @hd_seed_username / @hd_seed_password → Helpdesk module.
-- Change password via ERP user management after first login if required.
-- =============================================================================
