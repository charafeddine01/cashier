@echo off
setlocal enabledelayedexpansion
title Vendra Commercial POS - Local Server

cd /d "%~dp0"

echo =======================================================
echo          VENDRA COMMERCIAL POS - STANDALONE
echo             Solar Energy Showroom Edition
echo =======================================================
echo.

:: 1. Check if PHP exists
if not exist "php\php.exe" (
    echo [ERROR] Embedded PHP engine not found in php\php.exe!
    pause
    exit /b 1
)

:: 2. Check if server already running on port 8080
netstat -ano | findstr ":8080 " >nul
if %ERRORLEVEL% equ 0 (
    echo [INFO] Vendra server is already running on port 8080.
) else (
    echo [STARTING] Launching local PHP engine on http://127.0.0.1:8080 ...
    start "" /b "php\php.exe" -c "php\php.ini" -S 127.0.0.1:8080 -t "app" "app\router.php" > "logs\server.log" 2>&1
    timeout /t 2 /nobreak >nul
)

:: 3. Launch Native App Window (Edge / Chrome / Browser)
echo [LAUNCHING] Opening Vendra POS Application...

if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" --app="http://127.0.0.1:8080" --window-size=1280,850
    goto :done
)

if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
    start "" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" --app="http://127.0.0.1:8080" --window-size=1280,850
    goto :done
)

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" --app="http://127.0.0.1:8080" --window-size=1280,850
    goto :done
)

if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
    start "" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" --app="http://127.0.0.1:8080" --window-size=1280,850
    goto :done
)

start "" "http://127.0.0.1:8080"

:done
echo [READY] Application is running.
echo To close, run Stop-VendraPOS.bat or close from system tray.
