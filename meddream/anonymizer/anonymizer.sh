#!/bin/sh
cygwin=false;
case "`uname`" in
    CYGWIN*)
        cygwin=true
        ;;
esac

# For Cygwin, ensure paths are in UNIX format before anything is touched
if $cygwin ; then
    [ -n "$JAVA_HOME" ] &&
        JAVA_HOME=`cygpath --unix "$JAVA_HOME"`
fi
# Setup the JVM
if [ "x$JAVA_HOME" != "x" ]; then
    JAVA=$JAVA_HOME/bin/java
else
    JAVA="java"
fi

# For Cygwin, switch paths to Windows format before running java
if $cygwin; then
    JAVA=`cygpath --path --windows "$JAVA"`
fi
MYPATH="`dirname \"$0\"`"              # relative
MYPATH="`( cd \"$MYPATH\" && pwd )`"  # absolutized and normalized
if [ -z "$MYPATH" ] ; then
  exit 1  # fail
fi

# Execute the JVM
exec $JAVA -jar "$MYPATH/anonymizer.jar" -in=$1 -out=$2 -ssUID=$3 -stUID=$4 -imUID=$5
