#!/bin/bash
#	all parameters mandatory
#	$1	input
#	$2	output for ffmpeg (temporary file)
#	$3	ready to use .flv in ./temp
#	$4	ready to use .flv near input (a prepared file)
#	$5-$8	command options

if [ -f "$4" ]; then
	cp -f $4 $2
else
	ffmpeg -i "$1" $5 $6 $7 $8 $2
fi
mv -f $2 $3
