@echo off
setlocal enabledelayedexpansion
title Vendra POS - 1-Click Desktop Installer

cd /d "%~dp0"
set "APP_DIR=%~dp0"
:: Strip trailing backslash
if "%APP_DIR:~-1%"=="\" set "APP_DIR=%APP_DIR:~0,-1%"

echo ========================================================
echo         VENDRA COMMERCIAL POS - 1-CLICK INSTALLER
echo             Solar Energy Showroom Edition
echo ========================================================
echo.
echo Installing desktop shortcuts and configuring local system...
echo.

:: PowerShell script to create Desktop shortcut
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ws = New-Object -ComObject WScript.Shell; " ^
    "$desktop = [Environment]::GetFolderPath('Desktop'); " ^
    "$shortcut = $ws.CreateShortcut(\"$desktop\Vendra Solar POS.lnk\"); " ^
    "$shortcut.TargetPath = \"wscript.exe\"; " ^
    "$shortcut.Arguments = \"`\"%APP_DIR%\Launch-VendraPOS.vbs`\"\"; " ^
    "$shortcut.WorkingDirectory = \"%APP_DIR%\"; " ^
    "$shortcut.IconLocation = \"%APP_DIR%\vendra.ico, 0\"; " ^
    "$shortcut.Description = \"Vendra Commercial POS - Solar Showroom\"; " ^
    "$shortcut.Save(); " ^
    "$programs = [Environment]::GetFolderPath('Programs'); " ^
    "$startShortcut = $ws.CreateShortcut(\"$programs\Vendra Solar POS.lnk\"); " ^
    "$startShortcut.TargetPath = \"wscript.exe\"; " ^
    "$startShortcut.Arguments = \"`\"%APP_DIR%\Launch-VendraPOS.vbs`\"\"; " ^
    "$startShortcut.WorkingDirectory = \"%APP_DIR%\"; " ^
    "$startShortcut.IconLocation = \"%APP_DIR%\vendra.ico, 0\"; " ^
    "$startShortcut.Description = \"Vendra Commercial POS - Solar Showroom\"; " ^
    "$startShortcut.Save();"

if %ERRORLEVEL% equ 0 (
    echo.
    echo ========================================================
    echo [SUCCESS] Vendra Solar POS is installed successfully!
    echo.
    echo - Desktop Shortcut created: 'Vendra Solar POS'
    echo - Start Menu Shortcut created.
    echo ========================================================
    echo.
    echo Launching Vendra Solar POS now...
    start "" wscript.exe "%APP_DIR%\Launch-VendraPOS.vbs"
) else (
    echo [WARNING] Could not create Windows shortcut via PowerShell.
    echo You can run Vendra directly by double-clicking VendraPOS.bat.
    pause
)
