@echo off
cd /d "%~dp0"

php artisan queue:work --queue=ocr_v2 --tries=3 --sleep=3 --timeout=90 --max-jobs=1000 --max-time=3600

pause
