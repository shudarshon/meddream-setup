<?php
/*
	Original name: SendToDicomLibrary.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		shell wrapper for SendToDicomLibrary class
 */
namespace Softneta\MedDream\scripts;
require __DIR__ . '/SendToDicomLibrary.php';

if (!session_id() ||
		(function_exists('session_status') && (session_status() !== PHP_SESSION_ACTIVE)))
	@session_start();

$processor = new SendToDicomLibrary();
$r = $processor->run();
if (strlen($r))
	echo "$r\n";
