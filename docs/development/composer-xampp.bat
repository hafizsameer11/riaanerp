@echo off
REM Composer using XAMPP PHP (Windows). Use when "composer" fails with 'php' is not recognized.
REM Usage (from repo root):  docs\development\composer-xampp.bat install
setlocal
if not defined XAMPP_PHP set "XAMPP_PHP=C:\xampp\php\php.exe"
if not defined COMPOSER_PHAR set "COMPOSER_PHAR=C:\ProgramData\ComposerSetup\bin\composer.phar"
if not exist "%XAMPP_PHP%" (
  echo Edit docs\development\composer-xampp.bat: XAMPP_PHP not found at %XAMPP_PHP%
  exit /b 1
)
if not exist "%COMPOSER_PHAR%" (
  echo Edit docs\development\composer-xampp.bat: composer.phar not found at %COMPOSER_PHAR%
  exit /b 1
)
pushd "%~dp0..\.."
"%XAMPP_PHP%" "%COMPOSER_PHAR%" %*
set "ERR=%ERRORLEVEL%"
popd
exit /b %ERR%
