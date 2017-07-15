<?php
/*
	Original name: data.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Sends out a study structure (encoded in a binary format) for Flash
 */

require_once __DIR__ . '/autoload.php';

error_reporting(0);
ignore_user_abort(true);
set_time_limit(0);

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

$DD = false;

$auditAction = '';
$auditObject = '';
$auditSuccess = true;

$backend = new Backend(array('Structure', 'Preload'));
$authDB = $backend->authDB;
$constants = new Constants();

$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump('begin ' . $modulename);

$retrieveEntireStudy = $backend->pacsConfig->getRetrieveEntireStudy();


function file_extension($filename)
{
	$path_info = pathinfo($filename);

	$extension = "";
	if (isset($path_info['extension']))
		$extension = $path_info['extension'];

	return $extension;
}


function writeData($out, $type, $data)
{
	global $log;

	fwrite($out, pack("C", $type));
	$len = strlen($data);
	if ($len > 255)
	{
		$log->asWarn("writeData: record of type $type too long ($len), truncated");
		$len = 255;
	}
	fwrite($out, pack("C", $len));
	fwrite($out, $data, $len);
}


function writeStudyList($out, $study)
{
	global $cached;

	if ($cached != 1)
	{
		writeData($out, 0x00, (string) $study["uid"]);
		writeData($out, 0x20, (string) $study["patientid"]);
		writeData($out, 0x21, (string) $study["lastname"]);
		writeData($out, 0x22, (string) $study["firstname"]);
		writeData($out, 0x30, (string) $study["notes"]);
		writeData($out, 0x31, (string) $study["sourceae"]);
		if (isset($study["studydate"]))		/* Study::getImageData() doesn't provide this */
			writeData($out, 0x32, (string) $study["studydate"]);
	}

	for ($s = 0; $s < $study["count"]; $s++)
	{
		if ($cached != 1)
		{
			writeData($out, 0x40, (string) $study[$s]["id"]);
			writeData($out, 0x41, (string) $study[$s]["description"]);
			if (isset($study[$s]["modality"]))
				writeData($out, 0x42, (string) $study[$s]["modality"]);
		}

		for ($i = 0; $i < $study[$s]["count"]; $i++)
		{
			writeData($out, 0x60, (string) $study[$s][$i]["id"]);
			if ($cached != 1)
				writeData($out, 0x61, (string) $study[$s][$i]["numframes"]);
		}
	}

	fwrite($out, pack("C", 0xF0));
	@ob_flush();
	flush();
}


function thumbnailFromJpeg($path, $thumbnailSize)
{
	global $log;

	$r = '0';

	if (@file_exists($path))
	{
		/* avoid calling a missing function with @ -- a failure will be impossible to diagnose
		   without modifying the code
		 */
		if (!function_exists('imagecreatefromjpeg'))
			return '2';

		$img1 = @imagecreatefromjpeg($path);
		if ($img1 === false)
			$r = '2';
		else
		{
			/* calculate resulting dimensions: $thumbnailSize is the longer one */
			$sx = imagesx($img1);
			$sy = imagesy($img1);
			if ($sx > $sy)
			{
				$dx = $thumbnailSize;
				$dy = intval(($dx * $sy) / $sx);
			}
			else
			{
				$dy = $thumbnailSize;
				$dx = intval(($dy * $sx) / $sy);
			}
			$log->asDump("resizing [$sx; $sy] to [$dx; $dy]" );

			$img2 = @imagecreatetruecolor($dx, $dy);
			if ($img2 === false)
				$r = '3';
			else
			{
				if (@imagecopyresized($img2, $img1, 0, 0, 0, 0, $dx, $dy, $sx, $sy))
				{
					ob_start();
					if (@imagejpeg($img2, NULL, 90))
						$r = ob_get_contents();
					else
						$r = '5';

					ob_end_clean();
				}
				else
					return '4';

				@imagedestroy($img2);
			}

			@imagedestroy($img1);
		}
	}
	else
		$r = '1';

	return $r;
}


function updateHeaderData($out, $study)
{
	global $log;

	$arrdata = array();
	for ($s = 0; $s < $study["count"]; $s++)
	{
		writeData($out, 0x40, (string) $study[$s]["id"]);
		writeData($out, 0x42, (string) $study[$s]["modality"]);

		for ($i = 0; $i < $study[$s]["count"]; $i++)
		{
			$update = !$i || ($study[$s][$i]["xfersyntax"] != "1.2.840.10008.1.2.4.103");

			if ($update)
			{
				$arrdata = meddream_extract_meta(dirname(__DIR__), $study[$s][$i]["path"], 0);
				$log->asDump('$arrdata: ', $arrdata);
			}

			if (!$i)
				writeData($out, 0x41, (string) $arrdata["seriesdesc"]);

			writeData($out, 0x60, (string) $study[$s][$i]["id"]);

			if ($update && isset($arrdata['numframes']))
				if ($study[$s][$i]["xfersyntax"] != "1.2.840.10008.1.2.4.103")
					$study[$s][$i]["numframes"] = $arrdata['numframes'];

			writeData($out, 0x61, (string) $study[$s][$i]["numframes"]);
			writeData($out, 0x70, (string) $study[$s][$i]["path"]);
			writeData($out, 0x80, (string) $study[$s][$i]["xfersyntax"]);

			$arrdata = array();
		}
	}
	fwrite($out, pack("C", 0xF0));
	@ob_flush();
	flush();
}


function writeThumbnail($clientid, $out, $study, $s, $i, $thumbnailSize)
{
	global $backend;
	global $constants;
	global $log;

	/* SR files aren't supported and must be skipped; UI will call
	   sr::getHtml() for these later
	 */
	if (isset($study[$s]['modality']))
		if ($study[$s]['modality'] == 'SR')
			return;

	$basedir = $backend->pacsConfig->getWriteableRoot();
	$tempPath = $basedir . 'temp' . DIRECTORY_SEPARATOR;
	if (!file_exists($tempPath))
		mkdir($tempPath);

	$xfersyntax = $study[$s][$i]["xfersyntax"];
	$bitsstored = $study[$s][$i]["bitsstored"];
	if ($bitsstored == "") $bitsstored = 8;

	if ($thumbnailSize > 150)
		$thumbnailSize = 150;
	if ($thumbnailSize < 50)
		$thumbnailSize = 50;
	if ($study[$s]["count"] == 1)
		$thumbnailSize = 150;

	/* in some PACSes the Image UID is combined with other UIDs, and the delimiter
	   is a character not allowed in file names; must leave only the true UID
	   for the name of a cached thumbnail. In case of FileSystem, it might even
	   contain path components. Neither PacsPreload nor PacsStructure so far
	   contains a dedicated method, so will clean it here.
	 */
	$uuid = $study[$s][$i]['id'];
	$len = strpos($uuid, '*');
	if ($len !== false)
		$uuid = substr($uuid, 0, $len);
	$uuid = str_replace(array('\\', '/'), '_', $uuid);

	$thumbnail = $tempPath.$uuid.".thumbnail-".$thumbnailSize.".jpg";
	$path = str_replace("\\", "/", $study[$s][$i]['path']);

	$pathJPG = $path.".thumbnail-".$thumbnailSize.".jpg";
	if (!Constants::DL_REGENERATE)
	{
		$need_new = !file_exists($pathJPG);
		if (!$need_new)
		{
			$r = file_get_contents($pathJPG);
			$log->asInfo("cached thumbnail: '$pathJPG'");
		}
	}
	else
		$need_new = true;

	if ($need_new)
	{
		if (!file_exists($thumbnail))
		{
			$r = '';
			$fi = $backend->pacsPreload->fetchInstance($study[$s][$i]['id'], $study[$s]['id'], $study['uid']);
			if (is_string($fi))
				$path = $fi;
			else
				if (!is_null($fi))
					/* simulate an error from meddream_thumbnail, which is in form "*ERR:<numeric code>".
					   md-swf displays the part after "*ERR:" (with additional formatting) in a tooltip.
					 */
					$r = '*ERR:cache failure';

			if (!strlen($r))
			{
				if ($xfersyntax == 'jpg')
				{
					$log->asDump("thumbnailFromJpeg('$path', $thumbnailSize)");
					$r = thumbnailFromJpeg($path, $thumbnailSize);
					if (strlen($r) == 1)		/* $r is the error location */
					{
						$log->asDump('thumbnailFromJpeg: ', $r);
						$r = '';
					}
				}
				else
					if (!defined('MEDDREAM_THUMBNAIL_JPG'))
					{
						$r = '*ERR:incorrect php_meddream version';
						$log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
					}
					else
					{
						if (empty($xfersyntax))
						{
							$xfersyntax = meddream_extract_transfer_syntax(dirname(__DIR__), $path);
							if (!strlen($xfersyntax) || ($xfersyntax[0] == '*'))
							{
								$log->asErr('meddream_extract_transfer_syntax: ' . var_export($xfersyntax, true));
								$xfersyntax = '';
							}
						}
						$flags = 90 | MEDDREAM_THUMBNAIL_JPG;
						$log->asDump('meddream_thumbnail(', $path, ', ', $thumbnail, ', ', $basedir, ', ', $thumbnailSize,
							', ', $xfersyntax, ', ', $bitsstored, ', 0, 0, ', $backend->enableSmoothing, ', ', $flags, ')');
						$r = meddream_thumbnail($path, $thumbnail, $basedir, $thumbnailSize, $xfersyntax,
							$bitsstored, 0, 0, $backend->enableSmoothing, $flags);
						$log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

						if (strlen($r) > 0)
						{
							if ($r[0] == "E")			/* path to an already existing thumbnail */
							{
								$r = substr($r, 5);
								if (file_exists($r))
									$r = file_get_contents($r);
								else
									$r = "";
							}
							else
								if ($r[0] == "2")		/* GD2 data */
								{
									$r = substr($r, 5);
									if (function_exists('imagecreatefromstring'))
									{
										$obj = imagecreatefromstring($r);
										ob_start();
										imagejpeg($obj, NULL, 90);
										$r = ob_get_contents();
										ob_end_clean();
										imagedestroy($obj);
									}
									else
									{
										$r = '*ERR:no GD2 support';
										$log->asErr('GD2 extension is missing');
									}
								}
								else
									if ($r[0] == 'J')	/* a ready to use JPEG */
										$r = substr($r, 5);
								/* otherwise return as is; for example, it could be '?PDF' -- indication
								   to display a PDF icon, or '*ERR:<error code>'
								 */
						}
						else
							$log->asErr("meddream_thumbnail failed on '$path'");
					}

				/* remove the source file that might be created by fetchInstance() */
				$backend->pacsPreload->removeFetchedFile($path);
			}
		}
		else
			$r = file_get_contents($thumbnail);

		if (Constants::DL_REGENERATE)
			if (@file_put_contents($pathJPG, $r))
				$log->asInfo("updated '$pathJPG'");
			else
				$log->asWarn("failed to update '$pathJPG'");
	}

	$uuid = $study[$s][$i]["id"];	/* the "cleaned up" version above isn't suitable */
	fwrite($out, pack("C", 0xF1));
	fwrite($out, pack("C", strlen($uuid)));
	fwrite($out, $uuid, strlen($uuid));
	fwrite($out, pack("N", strlen($r)));
	fwrite($out, $r, strlen($r));
	@ob_flush();
	flush();
}


function getfileType($path)
{
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$type = finfo_file($finfo, $path);
	finfo_close($finfo);
	$contentType = explode("/",$type);
	if (sizeof($contentType) == 2)
		return $contentType[1];
	return "";
}


function getNewThumbnail($path, $thumbnailSize, $xfersyntax, $bitsstored, $uuid, $out)
{
	$r = "";
	$type = "";
	if ($thumbnailSize > 150)
		$thumbnailSize = 150;
	if ($thumbnailSize < 50)
		$thumbnailSize = 50;

	$basedir = dirname(__DIR__);
	$tempPath = $basedir."/temp/";
	$thumbnail = $tempPath.$uuid.".thumbnail-".$thumbnailSize.".jpg";

	if ($xfersyntax == 'jpg')
	{
		$log->asDump("thumbnailFromJpeg('$path', $thumbnailSize)");
		$r = thumbnailFromJpeg($path, $thumbnailSize);
		if (strlen($r) == 1)		/* $r is the error location */
		{
			$log->asErr("thumbnailFromJpeg: $r");
			$r = '';
		}
	}
	else
	{
		$type = getfileType($path);

		$log->asInfo("file type: $type, $path");
		if (($type == 'dicom') || ($type == 'mp4'))
		{
			if ($bitsstored == "") $bitsstored = 8;

			$log->asDump("meddream_thumbnail('$path', '$thumbnail', $thumbnailSize, '$xfersyntax', $bitsstored)");
			$r = meddream_thumbnail($path, $thumbnail, $basedir, $thumbnailSize, $xfersyntax, $bitsstored, 0, 0, 0);
			$log->asDump("new meddream_thumbnail: ", substr($r, 0, 6));
		}

		if (strlen($r) > 0)
		{
			if ($r[0] != "2")
			{
				$r = substr($r, 5);
				if (file_exists($r))
					$r = file_get_contents($r);
				else
					$r = "";
			}
			else
			{
				$r = substr($r, 5);
				$r = imagecreatefromstring($r);
				ob_start();
				imagejpeg($r, NULL, 90);
				$r = ob_get_contents();
				ob_end_clean();
			}
		}
	}

	if (strlen($r) > 0)
	{
		fwrite($out, pack("C", 0xF1));
		fwrite($out, pack("C", strlen($uuid)));
		fwrite($out, $uuid, strlen($uuid));
		fwrite($out, pack("N", strlen($r)));
		fwrite($out, $r, strlen($r));
		@ob_flush();
		flush();
	}
}


function writeThumbnails($clientid, $out, $study, $thumbnailSize)
{
	global $auditSuccess;

	$mWas = 0;
	$mStart = 12;
	$mCount = $mStart + 1;

	for ($m = $mStart; $m <= $mCount; $m++)
	{
		for ($s = 0; $s < $study["count"]; $s++)
		{
			$imageCount = $study[$s]["count"];
			if ($imageCount > $mCount) $mCount = $imageCount;
			if ($m < $imageCount) $imageCount = $m;

			for ($i = $mWas; $i < $imageCount; $i++)
			{
				writeThumbnail($clientid, $out, $study, $s, $i, $thumbnailSize);
				if (connection_aborted() == 1)
				{
					$auditSuccess = false;
					return;
				}
			}
		}
		$mWas = $m;
	}
}


$studyUID = "";
$seriesUID = "";
$imageUID = "";
$clientid = "";
$thumbnailSize = 50;
$cached = 0;

if (isset($_GET['clientid']))
	$clientid = urldecode($_GET['clientid']);
else
	if (isset($_POST['clientid']))
		$clientid = $_POST['clientid'];

if (isset($_GET['study']))
	$studyUID = urldecode($_GET['study']);
else
if (isset($_POST['study']))
		$studyUID = $_POST['study'];
else
if (isset($_GET['series']))
	$seriesUID = urldecode($_GET['series']);
else
if (isset($_POST['series']))
	$seriesUID = $_POST['series'];
else
if (isset($_GET['image']))
	$imageUID = urldecode($_GET['image']);
else
if (isset($_POST['image']))
	$imageUID = $_POST['image'];

if (isset($_REQUEST['thumbnailSize']))
	$thumbnailSize = $_REQUEST['thumbnailSize'];
if (isset($_REQUEST['cached']))
	$cached = $_REQUEST['cached'];

$study = array();

$new = 0;
$path = "";
$xfersyntax = "";
$bitsstored = "";
if (isset($_POST['new'])) $new = $_POST['new'];
if (isset($_POST['path'])) $path = $_POST['path'];
if (isset($_POST['xfersyntax'])) $xfersyntax = $_POST['xfersyntax'];
if (isset($_POST['bitsstored'])) $bitsstored = $_POST['bitsstored'];

if (isset($_POST['studies']))
{
	$study = $_POST['studies'];
	$log->asDump('DICOMDIR: $study: ', $study);
	$DD = true;
}

$log->asDump('DD=', $DD, '|new=', $new);
if ($new)
{
	$log->asDump("path=$path|xfersyntax=$xfersyntax|bitsstored=$bitsstored|imageUID=$imageUID");
	if (($path == "") || ($xfersyntax == ""))
		exit;
}
else
{
	$log->asDump("size=$thumbnailSize|cached=$cached|study=$studyUID|series=$seriesUID|image=$imageUID");
	if (($studyUID == "") && ($seriesUID == "") && ($imageUID == ""))
		exit;

	/* in this file, we trust a nonzero $cached blindly, though this has sense
	   only together with nonzero $retrieveEntireStudy
	 */
	if ($cached && !$retrieveEntireStudy)
	{
		$log->asErr('wrong parameter(s): nonzero $cached is not expected');
		exit;
	}

	/* with nonzero $retrieveEntireStudy, $imageUID doesn't mean HIS integration
	   by Image UID, and $studyUID is required in other two cases
	 */
	if ($retrieveEntireStudy &&
	    ((($cached == 2) && ($imageUID == "")) ||
	     (($cached != 2) && ($studyUID == ""))))
	{
		$log->asErr('wrong parameter(s)');
		exit;
	}
}

if (!$new && !$DD)
{
	if ($studyUID != "")
	{
		$study = $backend->pacsStructure->studyGetMetadata($studyUID, false, $cached == 1);
		if (!$cached)
		{
			$auditAction = 'OPEN STUDY';
			$auditObject = $studyUID;
		}
	}
	elseif ($seriesUID != "")
	{
		$seriesList = explode("|", $seriesUID);
		$study = $backend->pacsStructure->studyGetMetadataBySeries($seriesList, false,
			$cached == 1);
		$auditAction = 'OPEN SERIES';
		$auditObject = $seriesUID;
	}
	elseif ($imageUID != "")
	{
		$imageList = explode("|", $imageUID);
		$study = $backend->pacsStructure->studyGetMetadataByImage($imageList, false,
			$cached == 2);		/* NOTE: it's "2" on purpose, not "1" */
		if (!$cached)
		{
			$auditAction = 'OPEN IMAGE';
			$auditObject = $imageUID;
		}
	}
	else
		exit;
	$st = NULL;

	if ($retrieveEntireStudy)
	{
		/* output the number of images that will be returned */
		$ni = 0;
		for ($i = 0; $i < $study['count']; $i++)
			for ($j = 0; $j < $study[$i]['count']; $j++)
				$ni++;
		$log->asDump("returning $ni entries for \$cached = ", $cached);

/*
$tma = explode(' ', microtime());
$tm = date('H:i:s', $tma[1]) . substr($tma[0], 1, 7);
file_put_contents('data.lst', "$tm  $cached  $ni\n", FILE_APPEND);
//*/
	}

	$audit = new Audit($auditAction);
}

session_write_close();

header("Pragma: public");
header("Expires:  Sat, 26 Jul 1977 05:00:00 GMT");
header("Cache-Control: maxage=0");
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: binary');

ignore_user_abort(true);

$out = fopen("php://output", "w");
try
{
	if (!empty($study["error"]))
	{
		writeData($out, 0xFF, (string) $study["error"]);

		if (strlen($auditAction))
			$audit->log(false, $auditObject);
		$auditAction = '';	/* won't be reported once more */
	}
	else
		if (!$new)
		{
			if (!$DD)
			{
				if (!$retrieveEntireStudy ||
						($retrieveEntireStudy && ($cached != 2)))
						/* RetrieveEntireStudy: case #3 doesn't expect the study structure */
					writeStudyList($out, $study);
			}
			else
				updateHeaderData($out, $study);


			if (strlen($auditAction))
				$audit->log(true, $auditObject);
			$auditAction = '';

			if (!$retrieveEntireStudy ||
					($retrieveEntireStudy && ($cached == 2)))
					/* RetrieveEntireStudy: cases #1, #2 do not expect thumbnails */
				writeThumbnails($clientid, $out, $study, $thumbnailSize);
		}
		else
			getNewThumbnail($path, $thumbnailSize, $xfersyntax, $bitsstored, $imageUID, $out);
}
catch (Exception $e)
{
	$log->asErr('catch: ' . $e->getMessage());
}
fclose($out);

if (strlen($auditAction))
	$audit->log($auditSuccess, $auditObject);

$log->asDump('end ' . $modulename);
