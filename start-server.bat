@echo off
title Glossa Debian WSL Server & Cloudflare Tunnel
cd /d "%~dp0"

echo [1/3] Menyalakan Nginx, PHP-FPM, dan MariaDB di Debian WSL...
wsl -u root -d Debian bash -c "systemctl start mariadb php8.4-fpm nginx"

echo [2/3] Menjaga sesi WSL tetap aktif di background...
start /b "" wsl -u root -d Debian bash -c "sleep infinity"
ping 127.0.0.1 -n 3 >nul

echo [3/3] Menghubungkan Cloudflare Tunnel ke https://glossa.aoka.my.id ...
cloudflared tunnel run glossa
