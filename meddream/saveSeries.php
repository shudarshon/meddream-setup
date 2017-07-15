<?php
/*
	Original name: saveSeries.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Support for the Save Images dialog in the series mode
 */
if (!strlen(session_id()))
	@session_start();

require_once(__DIR__ . '/autoload.php');

use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;
use Softneta\MedDream\Core\PathUtils;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

define('SLOW_DOWN', 0);			/* for SWS: 10000 */
set_time_limit(0);
ini_set('memory_limit', '1024M');
ignore_user_abort(true);		/* we handle this ourselves */

$audit = new Audit('SAVE SERIES');		/* after session_start() for proper session ID tracking */

$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump('begin ' . $modulename);

$backend = new Backend(array('Structure', 'Preload'));

$uidParam = '';
if (isset($_GET['uid']))
	$uidParam = urldecode($_GET['uid']);
$type = '.jpg';
if (isset($_GET['type']))
	$type = urldecode($_GET['type']);
$smooth = $backend->enableSmoothing;
if (isset($_REQUEST['smooth']))
	$smooth = intval(urldecode($_REQUEST['smooth']));
$log->asDump('$uidParam = ', $uidParam, ', $type = ', $type, ', $smooth = ', $smooth);

if (!strlen($uidParam))
{
	$audit->log(false);
	$err = 'required parameter is missing';
	$log->asErr($err);
	exit($err);
}
if (!$backend->authDB->isAuthenticated())
{
	$audit->log(false, $uidParam);
	$err = 'not authenticated';
	$log->asErr($err);
	exit($err);
}
if ($backend->pacs == 'FILESYSTEM')
{
	$err = 'Saving series not implemented for the FileSystem pseudo-PACS';
	$log->asErr($err);
	$audit->log(false, $uidParam);
	trigger_error($err, E_USER_ERROR);
		/* In older versions of study.php, $uid is "fake.series.id". In newer
		   ones it's a real UID. But neither one will help much in opening
		   a particular *series* (if, say, Study::getSeriesList() is improved
		   accordingly).

		   This parameter must instead contain both UID (for Flash) and full
		   path to the file (for this script). If the entire directory was
		   opened, then its full path among with the UID is required.
		   The suggested separator is '|'. Of course Flash must know how to
		   strip the path.

		   The main problem is in data.php: both strings might require at
		   most 64+1+256=321 bytes (and up to 4 times more for UTF-8). But
		   the serialized study format provides only 255.
		 */
}

/***** internal functions and classes >>>> *****/

require_once 'ZipFile.php';

function addFile(&$zipfile, $uid, &$img, $tmp, $clientid, $dir, $ext = ".dcm")
{
	$img['uid'] = $uid;
	$img['tmp'] = $tmp;
	$img['realpath'] = $img['path'];
	$img['client'] = $clientid;

	if ($img["xfersyntax"] == "1.2.840.10008.1.2.4.100")
	{
		$ext = '.mpg';
		if ($clientid == '')
			return;
	}
	if ($img["xfersyntax"] == "1.2.840.10008.1.2.4.103")
	{
		$ext = '.mp4';
		if ($clientid == '')
			return;
	}

	$img['name'] = $uid . $ext;

	if (strtolower($img["xfersyntax"]) == "mp4")
	{
		$img['name'] = $uid . '.mp4';
		$ext = '.mp4dcm';
	}
	if (strtolower($img["xfersyntax"]) == "mpg")
	{
		$img['name'] = $uid . '.mpg';
		$ext = '.mpgdcm';
	}
	if (strtolower($img["xfersyntax"]) == "jpg")
	{
		$img['name'] = $uid . '.jpg';
		if ($ext != '.tiff')
			$ext = '.jpg';
	}

	if ($dir != null)
		$img['name'] = $dir . '/' . $img['name'];
	$img['ext'] = $ext;

	if (connection_aborted())
		return;
	$zipfile->set_file($img);
}


function addFileDCM(&$zipfile, $uid, &$img, $dir, $ext, $clientid)
{
	$img['name'] = $uid . $ext;
	$img['realpath']= $img['path'];
	$img['client'] = $clientid;

	if (strtolower($img["xfersyntax"]) == "mp4")
	{
		$img['name'] = $uid . '.mp4';
		$ext = '.mp4dcm';
	}
	if (strtolower($img["xfersyntax"]) == "jpg")
	{
		$img['name'] = $uid . '.jpg';
		$ext = '.jpg';
	}
	if ($dir != null)
		$img['name'] = $dir . '/' . $img['name'];
	$img['ext'] = $ext;

	$zipfile->set_file($img);
}


function zipFiles(&$backend, &$files, $filename, $ext = ".dcm")
{
	global $smooth, $log;

	if (count($files) == 0)
	{
		echo 'Series contains no images';
		return false;
	}
	error_reporting(E_ERROR);

	set_time_limit(0);

	$zipfile = new zipfile();
	$zipfile->smooth = $smooth;
	$zipfile->slowDown = SLOW_DOWN;

	$tmpdir = __DIR__.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
	$tempname = $tmpdir.'images_'.rand(100, 10000).'.zip';

	$clientid = '';
	if (isset($_SESSION['clientIdMD']))
		$clientid = $_SESSION['clientIdMD'];

	if ($clientid != '')
	{
		$_SESSION[$clientid] = array();
		$_SESSION[$clientid]['total'] = sizeof($files);
		$_SESSION[$clientid]['completed'] = 0;
		$_SESSION[$clientid]['action'] = 0; //0 - preparing, 1 - extracting, 2 - adding to zip
	}

	$multiSeries = count($files) > 1;
	foreach (array_keys($files) as $seriesDir)
	{
		$errors = $backend->pacsPreload->fetchAndSortSeries($files[$seriesDir]);
		if ($errors != '')
		{
			echo $errors;
			return false;
		}

		$dir = $multiSeries ? $seriesDir : null;
		foreach ($files[$seriesDir] as $uid => $img)
		{
			if (($ext == '.dcm') ||
					((strtolower($img["xfersyntax"]) == 'jpg') && ($ext != '.dcm')))
				addFileDCM($zipfile, $uid, $img, $dir, $ext, $clientid);
			else
				addFile($zipfile, $uid, $img, $tmpdir, $clientid, $dir, $ext);
		}
	}
	$speed = 64;
	$size = $zipfile->create_zip($tempname, $speed);

	if (!file_exists($tempname))
		return false;
	if (connection_aborted())
		return false;

	/* remove the source files that might be created by fetchAndSortSeries() */
	foreach ($files as $series)
		foreach ($series as $image)
			$backend->pacsPreload->removeFetchedFile($image['path']);

	//if (($clientid != '') && isset($_SESSION[$clientId]))
	//	unset($_SESSION[$clientId]);
	$fp = @fopen($tempname, 'rb');
	if (!$fp)
	{
		$log->asErr('unreadable: ' . var_export($tempname, true));
		echo 'Failed to read the temporary file';
		return false;
	}

	if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE"))
	{
		$contentType = "application/force-download";
		$filename = str_replace(".", "_", $filename);
		$filename .= ".zip";
		$disposition = "Content-Disposition: file; filename=\"$filename\"";
	}
	else
	{
		$contentType = "application/x-zip";
		$filename .= ".zip";
		$disposition = "Content-Disposition: attachment; filename=\"$filename\"";
	}
	header("Content-Length: " . $size);
	header("Cache-Control: cache, must-revalidate");
	header("Pragma: public");
	header($disposition);
	header("Content-Type: application/octet-stream");

	$readSize = round($speed*1024);

	while (!feof($fp) && !connection_aborted() )
	{
		echo fread($fp, $readSize);
		flush();
		if (SLOW_DOWN)
			usleep(SLOW_DOWN);
	}
	fclose($fp);
	unlink($tempname);
	return true;
}

/***** <<<<< internal functions and classes *****/

$name = null;
$files = array();
$uidArray = explode(';', $uidParam);
$multiSeries = count($uidArray) > 1;

if ($backend->pacsConfig->getRetrieveEntireStudy())
{
	/* assume that all given series belong to the same study, and use the
	   study reference from the first one
	 */
	$parts = explode('*', $uidArray[0]);
	$studyUid = end($parts);

	$retrieve = new RetrieveStudy(new Study(), $log);
	$err = $retrieve->verifyAndFetch($studyUid);
	if ($err)
	{
		$audit->log(false, $uidParam);
		exit($err);
	}
}
$j = 0;
foreach ($uidArray as $uid)
{
	$st= $backend->pacsStructure->seriesGetMetadata($uid);
	if (strlen($st['error']))
	{
		$audit->log(false, $uidParam);
		exit($st['error']);
	}

	if (!$multiSeries && $name == null)
		$name = PathUtils::getName($st);

	/* the following are not required any more and might confuse further code */
	unset($st['fullname']);
	unset($st['firstname']);
	unset($st['lastname']);
	unset($st['error']);
	unset($st['count']);

	$files["series-" . sprintf("%06d", $j++)] = $st;
}

session_write_close();
$name = PathUtils::escapeFileName($name);
if (!zipFiles($backend, $files, ($name ?: 'Exported') . date('Ymd'), $type))
	$audit->log(false, $uidParam);
else
{
	$audit->log(true, $uidParam);
	$log->asDump('end ' . $modulename);
}
