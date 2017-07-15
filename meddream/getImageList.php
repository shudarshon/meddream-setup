<?php
/*
	Original name: getImageList.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		The "Image List" service
 */

session_start();
include __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;

$log = new Logging();
$log->asDump('begin ' . __FILE__);

$log->asDump('$_REQUEST = ', $_REQUEST);
$http = 'http://';
if (isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == 'on'))
	$http = 'https://';
$scriptUrl = $http. $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
$serverUrl = dirname($scriptUrl);


/* set error header and stop the script */
function exitWithError()
{
	header('HTTP/1.0 404 Not Found');
	exit;
}


/* build a single array with image UID and URL to a corresponding thumbnail
 *
 * @global type $serverUrl
 * @global type $size
 * @param type $imageUid
 * @return type
 */
function formThumbnailItem($imageUid)
{
	global $serverUrl, $size;
	return array('imageUID' => $imageUid,
		'url' => $serverUrl . '/getThumbnail.php?image=' . $imageUid . '&size=' . $size);
}


/* validate parameters */
if (!isset($_REQUEST['study']) || empty($_REQUEST['study']))
{
	$log->asErr('missing parameter(s)');
	exitWithError();
}
$studyUid = $_REQUEST['study'];
$size = 50;
if (isset($_REQUEST['size']) && !empty($_REQUEST['size']))
{
	$size = (int) $_REQUEST['size'];
	$size = min($size, 4320);
	$size = max($size, 50);
}
$result = 0;
if (isset($_REQUEST['result']) && !empty($_REQUEST['result']))
{
	$result = (int) $_REQUEST['result'];
	if (($result > 2) || ($result < 0))
		$result = 0;
}
$log->asDump('$studyUid = ', $studyUid, ', $size = ', $size, ', $result = ', $result);

/* log in */
$backend = new Backend(array('Structure'));
$authDB = $backend->authDB;
$alreadyLoggedIn = $authDB->isAuthenticated();
if ($alreadyLoggedIn)
	$log->asDump('reusing and keeping an existing login');
else
{
	if (!file_exists(__DIR__ . '/external.php'))
	{
		$log->asErr("Can't login: missing or bad external.php");
		exitWithError();
	}
	include __DIR__ . '/external.php';
	if (!$authDB->login(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
	{
		$authDB->logoff();
		$log->asErr("Can't login: missing or bad external.php");
		exitWithError();
	}
}
$log->asDump('Auth: ', $authDB->isAuthenticated());

/* obtain study structure */
$data = $backend->pacsStructure->studyGetMetadata($studyUid);
if ($data['error'])
	exitWithError();
if (!$alreadyLoggedIn)
	$authDB->logoff();

/* prepare the corresponding output structure */
$list = array();
$count = $data['count'];
if ($count)
	switch ($result)
	{
		case 1:     /* first image from every series */
			for ($i = 0; $i < $count; $i++)
			{
				if (!empty($data[$i][0]['id']))
					$list[] = formThumbnailItem($data[$i][0]['id']);
			}
			break;

		case 2:     /* all images */
			for ($i = 0; $i < $count; $i++)
			{
				if (!empty($data[$i]['count']))
					for ($j = 0; $j < $data[$i]['count']; $j++)
						$list[] = formThumbnailItem($data[$i][$j]['id']);
			}
			break;

		default:    /* first image from first series */
			if (!empty($data[0][0]['id']))
					$list[] = formThumbnailItem($data[0][0]['id']);
	}
unset($data);
$log->asDump('$list = ', $list);
if (!count($list))
	exitWithError();

/* output the final result */
$data = array(
	'studyUID' => $studyUid,
	'thumbnails' => $list
);
header('Content-Type: application/json');
echo json_encode($data);

$log->asDump('end ' . __FILE__);
