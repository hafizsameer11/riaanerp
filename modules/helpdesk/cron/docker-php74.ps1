# Run Helpdesk POP sync: PHP 7.4 + IMAP (Docker). Uses root docker-compose.yml + profile tools.
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $PhpArguments
)

$ErrorActionPreference = "Stop"
$CronDir = $PSScriptRoot
$Root = (Resolve-Path (Join-Path $CronDir "..\..\..")).Path
$ComposeFile = Join-Path $Root "docker-compose.yml"

if ($null -eq $PhpArguments -or $PhpArguments.Count -eq 0) {
    $PhpArguments = @("modules/helpdesk/cron/pop_sync.php")
}

$PhpArgsUnix = $PhpArguments | ForEach-Object { $_ -replace "\\", "/" }

docker compose -f $ComposeFile --profile tools run --rm helpdesk-php74 php @PhpArgsUnix
exit $LASTEXITCODE
