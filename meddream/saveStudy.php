<?php
/*
	Original name: saveStudy.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Support for the Save Images dialog in the study mode
 */

session_start();

require_once(__DIR__ . '/autoload.php');

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;
use Softneta\MedDream\Core\PathUtils;

define('SLOW_DOWN', 0);			/* for SWS: 10000 */
set_time_limit(0);
ini_set('memory_limit', '1024M');

$audit = new Audit('SAVE STUDY');

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
	$log->asErr('required parameters are missing');
	$audit->log(false);
	exit;
}
if (!$backend->authDB->isAuthenticated())
{
	$log->asErr('not authenticated');
	$audit->log(false, $uidParam);
	exit;
}

/***** internal functions and classes >>>> *****/

require_once 'ZipFile.php';

function addFile(&$zipfile, $uid, &$img, $tmp, $clientid, $studydir, $ext = ".dcm")
{
	if ($studydir != '')
		$studydir .= '/';
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

	$img['ext'] = $ext;
	$img['name'] = $studydir. $img['name'];
	if (connection_aborted())
		return;
	$zipfile->set_file($img);
}


function addFileDCM(&$zipfile, $uid, &$img, $studydir, $ext, $clientid)
{
	if ($studydir != '')
		$studydir .= '/';
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
	$img['name'] = $studydir. $img['name'];
	$img['ext'] = $ext;

	$zipfile->set_file($img);
}


function zipFiles(&$backend, &$files, $filename, $ext = ".dcm")
{
	global $smooth, $log;

	if (count($files) == 0)
	{
		echo 'Study contains no images';		/* the zip file will contain this text */
		return false;
	}

	error_reporting(E_ERROR);

	set_time_limit(0);

	$zipfile = new zipfile();
	$zipfile->smooth = $smooth;
	$zipfile->slowDown = SLOW_DOWN;

	$tmpdir = __DIR__.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
	$tempname = $tmpdir.'study_'.rand(100, 10000).'.zip';

	$clientid = '';
	if (isset($_SESSION['clientIdMD']))
		$clientid = $_SESSION['clientIdMD'];

	$log->asDump('$_SESSION ' , $_SESSION);

	if ($clientid != '')
	{
		$_SESSION[$clientid] = array();
		$_SESSION[$clientid]['total'] = sizeof($files);
		$_SESSION[$clientid]['completed'] = 0;
		$_SESSION[$clientid]['action'] = 0; //0 - preparing, 1 - extracting, 2 - adding to zip
	}
	$errors = $backend->pacsPreload->fetchAndSortStudies($files);
	if ($errors != '')
	{
		echo $errors;
		return false;
	}

	$multiStudy = count($files) > 1;
	foreach ($files as $studyDir => $study)
	{
		foreach ($study as $seriesDir => $series)
		{
			$dir = $multiStudy ? "$studyDir/$seriesDir" : $seriesDir;
			foreach ($series as $uid => $img)
			{
				$img['path'] = str_replace('\\', '/', $img['path']);

				if (($ext == '.dcm') ||
						((strtolower($img["xfersyntax"]) == 'jpg') && ($ext != '.dcm') && ($ext != '.tiff')))
					addFileDCM($zipfile, $uid, $img, $dir, $ext, $clientid);
				else
					addFile($zipfile, $uid, $img, $tmpdir, $clientid, $dir, $ext);
			}
		}
	}
	$speed = 64;
	$size = $zipfile->create_zip($tempname, $speed);

	if (!file_exists($tempname))
		return false;
	if (connection_aborted())
		return false;

	/* remove the source files that might be created by fetchAndSortStudies() */
	foreach ($files as $study)
		foreach ($study as $series)
			foreach ($series as $image)
				$backend->pacsPreload->removeFetchedFile($image['path']);

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


$studies = array();

$name = null;
$uidArray = explode(';', $uidParam);
$multiStudy = count($uidArray) > 1;
foreach ($uidArray as $n => $uid)
{
	if ($backend->pacsConfig->getRetrieveEntireStudy())
	{
		$retrieve = new RetrieveStudy(new Study(), $log);
		$err = $retrieve->verifyAndFetch($uid);
		if ($err)
		{
			$audit->log(false, $uidParam);
			exit($err);
		}
	}
	$study = array();

	/* PacsStructure provides suitable data, we only need to reorganize it into a different format */
	$studyList = $backend->pacsStructure->studyGetMetadata($uid, true, $backend->pacsConfig->getRetrieveEntireStudy());
	if (strlen($studyList['error']))
	{
		$audit->log(false, $uidParam);
		exit($studyList['error']);
	}
	for ($k = 0; $k < $studyList['count']; $k++)
	{
		if (!$multiStudy && $name == null)
			$name = PathUtils::getName($studyList);

		$files = array();
		for ($i = 0; $i < $studyList[$k]['count']; $i++)
		{
			$img = array();

			/* DICOM/WADO configurations don't have paths at this moment, need UIDs instead */
			$img["study"] = $studyList["uid"];

			$uid = $studyList[$k]["id"];
			$uids = explode('*', $uid);
			$img["series"] = $uids[0];

			$uid = $studyList[$k][$i]["id"];
			$uids = explode('*', $uid);
			$img["object"] = $uids[0];

			$img["path"] = @$studyList[$k][$i]["path"];
			$img["xfersyntax"] = $studyList[$k][$i]["xfersyntax"];
			$img["bitsstored"] = $studyList[$k][$i]["bitsstored"];

			if ($img["bitsstored"] == '')
				$img["bitsstored"] = 8;

			$files["image-".sprintf("%06d", $i)] = $img;
		}
		$study["series-".sprintf("%06d", $k)] = $files;
	}
	$studies['study-' . sprintf('%06d', $n)] = $study;
}

$name = PathUtils::escapeFileName($name);

session_write_close();
$r = zipFiles($backend, $studies, ($name ?: 'Exported') . date('Ymd'), $type);
$audit->log($r, $uidParam);
if (!$r)
	$log->asDump('end ' . $modulename);
