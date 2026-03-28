-- Helpdesk module schema (MySQL 5.7 / MariaDB friendly — utf8mb4_unicode_ci)
-- Run once: mysql -u user -p clientzone < modules/helpdesk/sql/migrate.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Mail configuration (single POP + SMTP row, id=1)
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
-- Requesters
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
-- Tickets
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
-- Messages / thread (no UNIQUE on message_id — duplicate deliveries = separate tickets per business rule)
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
-- Attachments
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
-- Service line on ticket (category + unit price snapshot)
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
-- Timesheets
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
-- Scheduled recurring tickets
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
-- Notification templates (HTML)
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

-- Rules: trigger code -> template id (simple JSON or rows)
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
-- Merge audit
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
-- Role for portal users (adjust if ID collides — check SELECT MAX(id) FROM roles)
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`) VALUES (100, 'Helpdesk Requester')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
