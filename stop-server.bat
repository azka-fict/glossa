@echo off
title Glossa Server Stopper
cd /d "%~dp0"

echo =========================================================
echo             GLOSSA SERVER STOPPER
echo =========================================================
echo.
echo Menghentikan Nginx, PHP 8.4-FPM, dan MariaDB di WSL Debian...
wsl -u root -d Debian bash -c "systemctl stop nginx php8.4-fpm mariadb"

echo.
echo =========================================================
echo  [SUKSES] Seluruh layanan server telah dihentikan.
echo =========================================================
echo.
pause
