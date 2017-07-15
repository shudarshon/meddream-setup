<?php
	use Softneta\MedDream\Core\Logging;
	use Softneta\MedDream\Core\System;

	include_once(__DIR__ . '/../autoload.php');

	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump("begin $modulename");

	$sys = new System();
	$r = $sys->license('');
	if ($r == '')
		return;

	$r = str_replace(array("\n", "\r", "\t"), '', $r);
	$r = trim(str_replace('"', "'", $r));
	$simpleXml = simplexml_load_string($r);
	$json = json_encode($simpleXml);

	echo $json;

	$log->asDump("returning: ", $r);
	$log->asDump("end $modulename");
?>
