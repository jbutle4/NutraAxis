@echo off
setlocal
if "%CRON_SECRET%"=="" (
  echo CRON_SECRET is not configured.
  exit /b 1
)
curl -fsS -H "X-Cron-Secret: %CRON_SECRET%" "https://nutraaxisweb.azurewebsites.net/cron/process-watcher.php"
exit /b %ERRORLEVEL%
