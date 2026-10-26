@echo off
title Stop Vendra POS Engine

echo Stopping Vendra POS background engine...

:: Kill PHP process running on port 8080
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8080 "') do (
    taskkill /F /PID %%a >nul 2>&1
)

:: Also kill any residual php-win.exe or php.exe started from this folder
taskkill /F /IM php-win.exe >nul 2>&1

echo Vendra POS server stopped.
timeout /t 2 >nul
