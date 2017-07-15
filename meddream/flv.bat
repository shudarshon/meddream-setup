if Exist %4 GOTO 2
:1
"%~dp0\ffmpeg\ffmpeg.exe" -i %1 %5 %6 %7 %8 %2
GOTO 3
:2
copy %4 %2
GOTO 3
:3
if Exist %3 del %3
ren %2 %3
