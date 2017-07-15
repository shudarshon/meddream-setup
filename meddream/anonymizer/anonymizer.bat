@echo off
IF NOT EXIST "%~dp0\..\..\jre" GOTO SKIP_JRE
@set JAVA_HOME=%~dp0\..\..\jre
:SKIP_JRE

if not "%JAVA_HOME%" == "" goto HAVE_JAVA_HOME

set JAVA=java
goto SKIP_SET_JAVA_HOME

:HAVE_JAVA_HOME

set JAVA=%JAVA_HOME%\bin\java

:SKIP_SET_JAVA_HOME

"%JAVA%" -jar  "%~dp0\anonymizer.jar" -in=%1 -out=%2 -ssUID=%3 -stUID=%4 -imUID=%5