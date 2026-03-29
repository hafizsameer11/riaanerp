@echo off
REM Manual POP sync (Docker). From project root. Ensure: docker compose up -d
cd /d "%~dp0"
docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php
exit /b %ERRORLEVEL%
