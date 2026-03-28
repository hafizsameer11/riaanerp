-- Optional: grant Helpdesk permissions to Admin role.
-- Replace @admin_role_id with your admin role id (often 3).

SET @admin_role_id := (SELECT id FROM roles WHERE LOWER(name) = 'admin' LIMIT 1);
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
