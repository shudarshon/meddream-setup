<?php
/*
	Original name: getThumbnail.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		The "Get Thumbnail" service
 */
session_start();
include __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\Study;
use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Audit;


$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump('begin ' . $modulename);

$audit = new Audit('GET THUMBNAIL');


/* set error header and stop the script */
function exitWithError()
{
	header("HTTP/1.0 404 Not Found");
	exit;
}


/* create thumbnail and return path to temporary file
 *
 * @global Backend $backend
 * @global Logging $log
 * @param string $uid
 * @param int $thumbnailSize
 * @return string
 */
function getJpg($uid, $thumbnailSize)
{
	global $backend;
	global $log;

	if ($backend->pacsConfig->getRetrieveEntireStudy())
	{
		$retrieve = new RetrieveStudy(new Study(), $log);
		$err = $retrieve->verifyAndFetch($uid);
		if ($err)
			return '';
	}
	$meta = $backend->pacsStructure->instanceGetMetadata($uid);
	if ($meta['error'])
		return '';
	$path = $meta['path'];
	$xfersyntax = $meta['xfersyntax'];
	$bitsstored = $meta['bitsstored'];
	if ($bitsstored == '') $bitsstored = 8;
	$smooth = $backend->enableSmoothing;
	$uidClean = $meta['uid'];

	$thumbnail = __DIR__ . '/temp/' .$uidClean.'.thumbnail-'.$thumbnailSize.".jpg";

	if (!defined('MEDDREAM_THUMBNAIL_JPG'))
	{
		$log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
		return '';
	}
	$flags = 90 | MEDDREAM_THUMBNAIL_JPG;

	$log->asDump("meddream_thumbnail('$path', '$thumbnail', $thumbnailSize, '$xfersyntax', $bitsstored, $flags)");
	$r = meddream_thumbnail($path, $thumbnail, __DIR__, $thumbnailSize, $xfersyntax,
		$bitsstored, 0, 0, $smooth, $flags);
	$log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

	if (strlen($r) > 0)
	{
		if ($r[0] == "E")			/* path to an already existing thumbnail */
		{
			$r = substr($r, 5);
			if (file_exists($r))
				$thumbnail = $r;
		}
		else
			if ($r[0] == "2")		/* GD2 data */
			{
				if (function_exists('imagecreatefromstring'))
				{
					$r = substr($r, 5);
					$r = imagecreatefromstring($r);
					ob_start();
					imagejpeg($r, NULL, 90);
					$buf = ob_get_contents();
					file_put_contents($thumbnail, $buf);
					imagedestroy($r);
					ob_end_clean();
				}
				else
				{
					$log->asErr('GD2 extension is missing');
					return '';
				}
			}
			else
				if ($r[0] == 'J')	/* a ready to use JPEG */
				{
					$r = substr($r, 5);
					file_put_contents($thumbnail, $r);
				}
		/* otherwise return as is; for example, it could be '?PDF' -- indication
		   to display a PDF icon
		 */
	}
	else
	{
		$log->asErr("meddream_thumbnail failed on '$path'");
		$thumbnail = '';
	}

	/* also remove the source file that might be created by instanceGetMetadata() */
	$backend->pacsPreload->removeFetchedFile($path);

	$log->asDump('return: ', $thumbnail);
	return $thumbnail;
}


/* validate parameters */
$log->asDump('$_REQUEST = ', $_REQUEST);
if (!isset($_REQUEST['image']) || empty($_REQUEST['image']))
{
	$log->asErr('missing parameter(s)');
	$audit->log(false);
	exitWithError();
}
$imageUid = $_REQUEST['image'];
$size = 50;
if (isset($_REQUEST['size']) && !empty($_REQUEST['size']))
{
	$size = (int) $_REQUEST['size'];
	$size = min($size, 4320);
	$size = max($size, 50);
}
$log->asDump('$imageUid = ', $imageUid, ', $size = ', $size);

/* log in */
$backend = new Backend(array('Structure', 'Preload'));
$authDB = $backend->authDB;
$alreadyLoggedIn = $authDB->isAuthenticated();
if ($alreadyLoggedIn)
	$log->asDump('reusing and keeping an existing login');
else
{
	if (!file_exists(__DIR__ . '/external.php'))
	{
		$audit->log(false, $imageUid);
		$log->asErr("Can't login: missing or bad external.php");
		exitWithError();
	}
	include __DIR__ . '/external.php';
	if (!$authDB->login(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
	{
		$audit->log(false, $imageUid);
		$authDB->logoff();
		$log->asErr("Can't login: missing or bad external.php");
		exitWithError();
	}
}
$log->asDump('Auth: ', $authDB->isAuthenticated());

/* generate a thumbnail */
$thumbnailPath = getJpg($imageUid, $size);
if (!$alreadyLoggedIn)
	$authDB->logoff();
if (!strlen($thumbnailPath) || !@file_exists($thumbnailPath))
{
	$audit->log(false, $imageUid);
	exitWithError();
}

/**
 * Do not change header "Expires"
 *
 * Firefox Bug 583351
 * https://bugzilla.mozilla.org/show_bug.cgi?id=583351
 */
header('Pragma: public');
header('Expires: ' . date('D, d M Y H:i:s GMT', strtotime('+1 seconds')));
header('Cache-Control: maxage=0');
header('Content-Type: image/jpeg');
echo file_get_contents($thumbnailPath);
@unlink($thumbnailPath);
$audit->log(true, $imageUid);

$log->asDump('end ' . $modulename);
