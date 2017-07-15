@echo off
rem		all parameters mandatory:
rem		%1	status file (must not exist)
rem		%2	input file (must exist)
rem		%3	target connection string
rem		%4	local connection string
rem		%5	number of lines in the input file

if -%5==- goto err1
if exist %1 goto err3
if not exist %2 goto err2

echo 0 1 > %1
set fwdind=1
for /f "delims=" %%t in (%2) do call :single %1 %2 %3 %4 %5 "%%t"
exit

:err1
echo 1
exit

:err2
echo 2
exit

:err3
echo 3
exit

:single
if -%6==- exit /b
call dcm4che\bin\dcmsnd.bat %3 -L %4 -caret -caret %6  2>&1
echo %fwdind% %5 > %1
set /a fwdind=fwdind+1
