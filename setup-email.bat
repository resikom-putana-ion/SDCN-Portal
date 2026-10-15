@echo off
cd /d "%~dp0"
where php >nul 2>nul
if %errorlevel%==0 (
    php artisan school:setup-gmail
) else if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" artisan school:setup-gmail
) else (
    echo PHP tidak ditemukan. Pasang PHP atau XAMPP terlebih dahulu.
)
pause
