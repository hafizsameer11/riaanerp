-- Helpdesk test technician (plain-text password — matches ERP login-backend.php).
-- Uses role "Helpdesk Technician" (non-admin). Apply migration first:
--   Get-Content modules/helpdesk/sql/migration_helpdesk_technician_role.sql | docker compose exec -T db mysql -uroot -pclientzone clientzone
-- Then seed this user:
--   Get-Content docker\seed_helpdesk_technician.sql | docker compose exec -T db mysql -uroot -pclientzone clientzone

SET @role_id := (SELECT id FROM roles WHERE LOWER(TRIM(name)) = 'helpdesk technician' LIMIT 1);
SET @next_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM registers);

INSERT INTO registers (id, name, surname, address, email, username, password, role_id)
SELECT @next_id, 'Test', 'Technician', '-', 'technician@example.com', 'technician', 'ChangeMe!123', @role_id
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM registers WHERE username = 'technician'
)
AND @role_id IS NOT NULL;
