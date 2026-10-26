' Vendra Commercial POS - Silent Desktop Launcher
' Runs background PHP engine silently and opens application in borderless desktop window
Option Explicit

Dim WshShell, fso, scriptDir, edgePath, chromePath, phpExe, cmd

Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)

' Ensure logs folder exists
If Not fso.FolderExists(scriptDir & "\logs") Then
    fso.CreateFolder(scriptDir & "\logs")
End If

' Use php-win.exe if present (runs completely headless), else php.exe
If fso.FileExists(scriptDir & "\php\php-win.exe") Then
    phpExe = scriptDir & "\php\php-win.exe"
Else
    phpExe = scriptDir & "\php\php.exe"
End If

' Check if port 8080 is already responding
Dim http
On Error Resume Next
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
http.open "GET", "http://127.0.0.1:8080/api/index.php", False
http.setTimeouts 500, 500, 500, 500
http.send
Dim isRunning
isRunning = (Err.Number = 0)
Err.Clear
On Error GoTo 0

If Not isRunning Then
    ' Start PHP Built-in Server hidden (window style 0, do not wait)
    cmd = """" & phpExe & """ -c """ & scriptDir & "\php\php.ini"" -S 127.0.0.1:8080 -t """ & scriptDir & "\app"" """ & scriptDir & "\app\router.php"""
    WshShell.Run cmd, 0, False
    WScript.Sleep 1500
End If

' Find Edge or Chrome to launch in App Mode (borderless desktop window)
edgePath = WshShell.ExpandEnvironmentStrings("%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe")
If Not fso.FileExists(edgePath) Then
    edgePath = WshShell.ExpandEnvironmentStrings("%ProgramFiles%\Microsoft\Edge\Application\msedge.exe")
End If

chromePath = WshShell.ExpandEnvironmentStrings("%ProgramFiles%\Google\Chrome\Application\chrome.exe")
If Not fso.FileExists(chromePath) Then
    chromePath = WshShell.ExpandEnvironmentStrings("%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe")
End If

If fso.FileExists(edgePath) Then
    WshShell.Run """" & edgePath & """ --app=""http://127.0.0.1:8080"" --window-size=1280,850", 1, False
ElseIf fso.FileExists(chromePath) Then
    WshShell.Run """" & chromePath & """ --app=""http://127.0.0.1:8080"" --window-size=1280,850", 1, False
Else
    WshShell.Run "http://127.0.0.1:8080", 1, False
End If

Set WshShell = Nothing
Set fso = Nothing
