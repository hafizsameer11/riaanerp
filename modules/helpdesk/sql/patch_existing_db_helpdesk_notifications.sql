-- Incremental patch for EXISTING Helpdesk databases.
-- Safe to run multiple times.
-- Compatible with MySQL 5.7+ / MariaDB.

SET NAMES utf8mb4;

-- 1) Ensure notification rules table exists
CREATE TABLE IF NOT EXISTS `helpdesk_notification_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Ensure randomize_incoming column exists in mail config
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

-- 3) Ensure unique index on event_code (for UPSERT logic in rules.php)
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

-- 4) Ensure scheduled attachments table exists
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

-- 5) Ensure required templates exist
INSERT INTO `helpdesk_email_templates` (`code`,`name`,`subject`,`body_html`,`is_active`) VALUES
('ticket_created','New ticket','Ticket {{ticket_id}}: {{subject}}','<p>Hello {{requester_name}},</p><p>Your request has been logged as ticket <strong>#{{ticket_id}}</strong>.</p><p>{{subject}}</p>',1),
('ticket_reply','Reply from helpdesk','Re: {{subject}}','<p>{{body}}</p>',1),
('requester_welcome','Requester welcome','Your helpdesk login','<p>Hello {{name}},</p><p>Login URL: {{login_url}}<br>Username: {{username}}<br>Password: {{password}}</p>',1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `subject` = VALUES(`subject`);

-- Optional templates used by notification rules:
INSERT INTO `helpdesk_email_templates` (`code`,`name`,`subject`,`body_html`,`is_active`) VALUES
('ticket_status_changed','Ticket status changed','Ticket {{ticket_id}} status: {{status}}','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> status changed to <strong>{{status}}</strong>.</p>',1),
('ticket_on_hold','Ticket on hold','Ticket {{ticket_id}} is on hold','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> is on hold.</p><p>Reason: {{on_hold_reason}}</p>',1),
('ticket_closed','Ticket closed','Ticket {{ticket_id}} closed','<p>Hello {{requester_name}},</p><p>Your ticket <strong>#{{ticket_id}}</strong> has been closed.</p>',1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `subject` = VALUES(`subject`);

-- 6) Seed default rules if missing
INSERT INTO `helpdesk_notification_rules` (`event_code`, `template_id`, `is_active`)
SELECT 'ticket_created', t.id, 1
FROM helpdesk_email_templates t
WHERE t.code = 'ticket_created'
ON DUPLICATE KEY UPDATE `template_id` = VALUES(`template_id`), `is_active` = VALUES(`is_active`);

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

