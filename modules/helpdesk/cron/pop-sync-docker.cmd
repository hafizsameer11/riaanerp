@echo off
REM POP sync: root docker-compose.yml + profile tools (MySQL service name: db)
setlocal
cd /d "%~dp0..\..\.."
docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php
exit /b %ERRORLEVEL%
