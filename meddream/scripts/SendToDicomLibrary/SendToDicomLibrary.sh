#!/bin/bash

LOGFILE=SendToDicomLibrary.log

function log {
	str=`date`'\t'$1
#	echo -e $str
	echo -e $str>>$LOGFILE
}

function cleanup {
	rm -f /var/lock/SendToDicomLibrary.lck
}

############################################################################

# make sure we always remove the lockfile that indicates we're running
trap cleanup EXIT INT TERM

cd `dirname $0`

# uncomment these two if the script is temporarily not needed
#log "disabled"
#exit 0

# must not do anything during a few minutes after system startup
uptime | grep -e " up \([0-1]\) min," >/dev/null 2>&1
if [[ $? -eq 0 ]]
then
	log "grace period after system reboot, allowing dependencies to start"
	exit 0
fi

php wrapper.php >>$LOGFILE 2>&1
