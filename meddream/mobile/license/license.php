<?php

	use Softneta\MedDream\Core\Backend;
	use Softneta\MedDream\Core\Constants;
	use Softneta\MedDream\Core\Logging;

	$path_prefix = __DIR__ . '/../../';
	require_once $path_prefix . 'autoload.php';

	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump("begin $modulename");

	/* require authenticated user */
	if (!strlen(session_id()))
		session_start();

	$backend = new Backend(array(), false);
	if (!$backend->authDB->isAuthenticated())
	{
		$log->asErr("$modulename: not authenticated");
//		$authDB->logoff();
//		$authDB->goHome(false);
		return;
	}

	include_once($path_prefix.'sharedData.php');
	if (isset($PRODUCT))
		$product = $PRODUCT;
	else
		$product = '';

	if (Constants::FOR_WORKSTATION)
		$file = "/$product.lic";
	else
		$file = "/meddream.lic";
	$licenseFile = dirname(dirname(__DIR__)) . $file;

	$r = meddream_license('', $licenseFile, $product);		/* 1st parameter is not used anyway */
	$r = str_replace(array("\n", "\r", "\t"), '', $r);
	$r = trim(str_replace('"', "'", $r));
	$simpleXml = simplexml_load_string($r);
	$json = json_encode($simpleXml);

	echo $json;

	$log->asInfo("returning: " . var_export($r, true));
	$log->asDump("end $modulename");
