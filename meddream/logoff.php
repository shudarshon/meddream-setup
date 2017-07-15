<?php
/*
	Original name: logoff.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>

	Description:
		Server-side support for the "Log off" UI button. Disconnects from
		the licensing mechanism, displays the login form anew.
 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\System;
use Softneta\MedDream\Core\Logging;

require_once('autoload.php');

if (!strlen(session_id()))
	@session_start();

$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump("begin $modulename");
$log->asDump('initial session ID: ', session_id());

$audit = new Audit('LOGOFF');

$backend = new Backend(array(), false);

$system = new System($backend);
$system->disconnect();

$backend->authDB->logoff();

$audit->log();


/**
 * for dcmsys
 */
if (isset($_COOKIE['suid']))
{
    $url = 'https://localhost/api/upload/logout';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //can get protected content SSL
    curl_setopt( $ch, CURLOPT_COOKIE, 'suid='.$_COOKIE['suid'] );
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    unset($_COOKIE['suid']);
    setcookie('suid', '', time() - 3600);
}

if (isset($_COOKIE[session_name()]))
	setcookie(session_name(), '', time() - 3600*12, '/');
	/* see issue #7068 for additional ideas */
$log->asDump('final session ID: ', session_id());
$out = session_destroy();
$log->asDump('session_destroy: ', $out);
$log->asDump("end $modulename");
$backend->authDB->goHome(false);
	/* 'false' means losing all query parameters; let's begin from a clean slate */

?>
