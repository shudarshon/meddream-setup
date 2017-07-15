<?php
/*
	Original name: printVersions.php

	Copyright: Softneta, 2015

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Support for standalone inquiry of component versions
 */

require_once('sharedData.php');
require_once('version.php');


function versionsFromFiles($prefix, $ext)
{
	$files = glob("$prefix*.$ext");
	if ($files === false)
		return 'ERR';
	if (!count($files))
		return 'N/A';

	$files = str_replace("$prefix-", '', $files);	/* an additional separator in new meddream.swf */
	$files = str_replace($prefix, '', $files);		/* md-html, md-mob and old meddream.swf */
	$files = str_replace(".$ext", '', $files);
	foreach ($files as $k => $v)
		if (!strlen($v))
			$files[$k] = 'UNKNOWN';		/* likely meddream.swf+version.txt (no support anymore) */
	return join(',', $files);
}


/* extract all versions */
$data = array();
$data['md-version'] = $VERSION;
$data['md-core'] = $COREVERSION;
if (function_exists('meddream_version'))
	$data['md-php-ext'] = meddream_version();
else
	$data['md-php-ext'] = 'N/A';
if (function_exists('meddream_api_version'))
	$extApiVer = meddream_api_version();
else
	$extApiVer = NULL;
$data['md-swf'] = versionsFromFiles('swf/meddream', 'swf');
$data['md-html'] = versionsFromFiles('md5/js/md.viewer.min.', 'js');
$data['md-mob'] = versionsFromFiles('mobile/js/mdmob.min.', 'js');

/* disable CORS checking for use from some external frontends */
header('Access-Control-Allow-Origin: *');

/* output in the requested format */
if (isset($_REQUEST['JSON']))
{
	header('Content-Type: application/json');
	echo json_encode($data);
}
else
{
	header('Content-Type: text/plain');
	echo "Version:       '" . $data['md-version'] . "'\n";
	echo "Core:          '" . $data['md-core'] . "'\n";
	echo "PHP extension: '" . $data['md-php-ext'] . "', API " . var_export($extApiVer, true) . "\n";
	echo "Flash viewer:  '" . $data['md-swf'] . "'\n";
	echo "HTML viewer:   '" . $data['md-html'] . "'\n";
	echo "Mobile viewer: '" . $data['md-mob'] . "'\n";
}

?>
