# Manual Helpdesk POP sync (local Windows + Docker). Run only when you want to pull the mailbox.
# Requires: Docker Desktop, stack up (docker compose up -d), helpdesk-php74 image built once.
#
# First time (or after Dockerfile changes):
#   docker compose --profile tools build helpdesk-php74
#
# Then any time:
#   .\helpdesk-pop-sync.ps1

$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot
$Compose = Join-Path $Root "docker-compose.yml"

if (-not (Test-Path $Compose)) {
    Write-Error "Missing $Compose"
}

Set-Location $Root
docker compose --profile tools run --rm helpdesk-php74 php modules/helpdesk/cron/pop_sync.php
exit $LASTEXITCODE
