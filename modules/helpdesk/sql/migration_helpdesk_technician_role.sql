-- Helpdesk: "Helpdesk Technician" role with minimal permissions (non-admin UAT).
-- Does NOT grant: edit ticket, delete, merge, admin mail, reports, bulk delete,
-- scheduled, requesters, randomize assignment — so ERP shell stays limited to
-- what Role Permissions allow elsewhere (typically helpdesk only).
-- App behaviour: users without "edit ticket" get technician-scoped UI (own tickets
-- only, no team workload table, no Global/Assets tabs, new tickets assign to self).
--
-- Run (example Docker):
--   Get-Content modules/helpdesk/sql/migration_helpdesk_technician_role.sql | docker compose exec -T db mysql -uroot -pPASSWORD DATABASE
--
-- After run: users on this role must log out and log in again (session permissions).
-- Optional: maps registers.username = 'technician' to this role if that row exists (Docker seed user).

SET @next_role_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM roles);

INSERT INTO roles (id, name)
SELECT @next_role_id, 'Helpdesk Technician'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE LOWER(TRIM(name)) = 'helpdesk technician'
);

SET @hd_tech_role_id := (
    SELECT id FROM roles WHERE LOWER(TRIM(name)) = 'helpdesk technician' ORDER BY id DESC LIMIT 1
);

DELETE FROM permissions WHERE role_id = @hd_tech_role_id AND page = 'helpdesk';

INSERT INTO permissions (role_id, page, function_name, allowed) VALUES
(@hd_tech_role_id, 'helpdesk', 'dashboard', 1),
(@hd_tech_role_id, 'helpdesk', 'create ticket', 1),
(@hd_tech_role_id, 'helpdesk', 'close ticket', 1),
(@hd_tech_role_id, 'helpdesk', 'reply email', 1),
(@hd_tech_role_id, 'helpdesk', 'timesheet', 1);

UPDATE registers
SET role_id = @hd_tech_role_id
WHERE username = 'technician'
  AND @hd_tech_role_id IS NOT NULL;
