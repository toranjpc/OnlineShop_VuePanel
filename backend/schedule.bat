@echo off
set SCRIPT_PATH=%~dp0
cd /d "%SCRIPT_PATH%"

:loop
php artisan schedule:run
if errorlevel 1 (
    echo [%date% %time%] Error running schedule:run
    timeout /t 5
) else (
    timeout /t 60 /nobreak
)
goto loop
