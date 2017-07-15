#!/bin/sh
#		all parameters mandatory:
#		$1	status file (must not exist)
#		$2	input file (must exist)
#		$3	target connection string
#		$4	local connection string
#		$5	number of lines in the input file

if [ -z $5 ]; then
	echo 1
	exit
fi
if [ -e $1 ]; then
	echo 3
	exit
fi
if [ ! -e $2 ]; then
	echo 2
	exit
fi

echo 0 1 > $1
fwdind=1
while read filename; do
    if [ -n "$filename" ]; then
        ./dcm4che/bin/dcmsnd $3 -L $4 "$filename"  2>&1
        echo $fwdind $5 > $1
        fwdind=`expr $fwdind + 1`
    fi
done < $2
