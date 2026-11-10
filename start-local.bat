@echo off
title Glossa Local Server (Nginx + PHP 8.4 + MariaDB)
cd /d "%~dp0"

echo =========================================================
echo             GLOSSA LOCAL SERVER STARTER
echo =========================================================
echo.
echo [1/3] Menyalakan MariaDB, PHP 8.4-FPM, dan Nginx di WSL Debian...
wsl -u root -d Debian bash -c "systemctl start mariadb php8.4-fpm nginx"

echo [2/3] Memastikan sesi background aktif...
start /b "" wsl -u root -d Debian bash -c "sleep infinity"
ping 127.0.0.1 -n 2 >nul

echo [3/3] Membuka Glossa di browser: http://localhost:8000 ...
start http://localhost:8000

echo.
echo =========================================================
echo  [SUKSES] Server Lokal Glossa Sedang Berjalan!
echo =========================================================
echo  Web URL  : http://localhost:8000
echo  Database : MariaDB 127.0.0.1:3306
echo  DB Name  : glossa
echo  DB User  : glossa_user
echo =========================================================
echo.
echo  Catatan:
echo  - Untuk menghentikan server, Anda bisa menjalankan stop-server.bat
echo.
echo Tekan tombol apa saja untuk menutup jendela peluncur ini.
pause >nul
