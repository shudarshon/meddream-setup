<?php
/*
	Original name: disconnect.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Releases the current connection, so that another one can be made in
		case of a limited-connection license. Called from an event handler
		in swf/index.php.
 */

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\System;

require_once __DIR__ . '/autoload.php';

if (!strlen(session_id()))
	@session_start();

$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump("begin $modulename");

$system = new System();
$system->removeWindow();

if (!empty($_SESSION['windows']))
	exit();

if (Constants::FOR_WORKSTATION)
{
	$dir = dirname(__FILE__);
	$script = $dir . DIRECTORY_SEPARATOR . 'sendStatistic.php';
	$cmd = 'CMD /C ""' . dirname(dirname($dir)) . DIRECTORY_SEPARATOR . 'php' .
		DIRECTORY_SEPARATOR . 'php.exe" "' . $script . '""';
	@pclose(@popen("start /b $cmd", 'r'));
}

$system->disconnect();

$log->asDump("end $modulename");
exit();
?>
