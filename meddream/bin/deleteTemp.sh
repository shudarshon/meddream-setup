#!/bin/sh

cd `dirname $0`

dt=$(date '+%Y%m%d')
log="../log/php-$dt.log"

addLog () {
    ms=$(($(date +%N)/1000))
    now=$(date '+%T')
    echo "[E $now.$ms] $1" >> "$log"
    echo $1
}

command -v php >/dev/null 2>&1 || {
    addLog "PHP executable not in PATH for deleteTemp.sh"
    exit 1
}

error=$(php deleteTemp.php 2>&1)
if [ $? -ne 0 ]
then
    addLog "$error"
fi
