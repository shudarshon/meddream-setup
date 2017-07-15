@echo off
cd /d "%~dp0"
rem You might need to add the full path to php.exe below
php.exe deleteTemp.php >>..\log\deleteTemp.log 2>&1
