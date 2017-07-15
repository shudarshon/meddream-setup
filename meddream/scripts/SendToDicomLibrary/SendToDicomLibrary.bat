@echo off
cd /d "%~dp0"

rem set php.exe file full path
set phpPath="\path\to\php\php.exe"

%phpPath% wrapper.php >>SendToDicomLibrary.log 2>&1
