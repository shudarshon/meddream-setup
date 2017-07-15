<?php
/*
	Original name: saveImage.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Support for the Save Images dialog in the single image mode
 */

use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;
use Softneta\MedDream\Core\PathUtils;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

function updateType(&$type)
{
	//remove dot
	$type = str_replace('.', '', $type);
	if ($type == '.jpg')
		$type = 'jpeg';
}

function getTimestamp()
{
	list($usec, $sec) = explode(" ", microtime());
	list($int, $dec) = explode(".", $usec);
	$dec = date("YmdHis").sprintf("%06d", (int)($dec / 100));
	return $dec;
}

function setVideo($clientid,$filename,$path,$type,$speed=64)
{
	global $log;
	$tmp = __DIR__.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
	$headerFile = meddream_convert2($clientid, dirname(__FILE__), $path, $tmp, 0);
	if (is_array($headerFile))
		$headerFile = $headerFile['result'];

	if (file_exists($headerFile))
	{
		$matches = array();
		preg_match("/<pixelOffset>(.*?)<\/pixelOffset><pixelSize>(.*?)<\/pixelSize>/", file_get_contents($headerFile), $matches);
		@unlink($headerFile);

		$pixelOffset = $matches[1];
		$size = $matches[2];

		$log->asDump("mp4 tmp: '$path', $pixelOffset, $size");

		header("Content-length: " . $size);
		header("Cache-Control: cache, must-revalidate");
		header("Pragma: public");
		header("Content-disposition: file; filename=$filename");
		header('Content-Type: video/'.$type);

		$fp = fopen($path, "rb");
		fseek($fp, $pixelOffset);
		$readSize = round($speed*1024);

		while (!feof($fp) && !connection_aborted() )
		{
			echo fread($fp, $readSize);
			flush();
			if (SLOW_DOWN)
				usleep(SLOW_DOWN);
		}
		fclose($fp);
	}
}

function saveJPG($path, $xfersyntax, $bitsstored, $filename, $type = '.jpg')
{
	global $authDB, $smooth, $log;

	if ($xfersyntax == '')
	{
		$xfersyntax = meddream_extract_transfer_syntax(__DIR__, $path);
		if (!strlen($xfersyntax) || ($xfersyntax[0] == '*'))
		{
			$log->asErr('meddream_extract_transfer_syntax: ' . var_export($xfersyntax, true));
			$xfersyntax = '';
		}
	}
	if ($bitsstored == '')	/* extension will fail when parsing parameters */
		$bitsstored = 8;	/* value is irrelevant for a long time, probably since v3 */

	$quality = 100;			/* CONFIG: decrease this for smaller JPEGs */
	$useJPG = true;			/* CONFIG: change this to `false` for legacy behavior */
	if ($type == '.tiff')
	{
		if (!function_exists('imagecreatefromstring'))
		{
			$type = '.jpg';
			$log->asErr('GD2 extension is missing, TIFF format unsupported, using JPEG');
		}
		else
			$useJPG = false;
	}
	if (!defined('MEDDREAM_THUMBNAIL_JPG'))		/* older extension doesn't parse excess arguments */
	{
		$log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
		exit('processing failed, see logs');
	}
	if ($useJPG)
		$flags = $quality | MEDDREAM_THUMBNAIL_JPG | MEDDREAM_THUMBNAIL_JPG444;
	else
		$flags = 0;

	$thumbnail = str_replace("\\", "/", dirname(__FILE__)) . "/temp/image-" . getTimestamp() . ".tmp";
	$basedir = __DIR__;
	$thumbnailSize = 0;
	$log->asDump('meddream_thumbnail(', $path, ', ', $thumbnail, ', ', $basedir, ', ', $thumbnailSize,
		', ', $xfersyntax, ', ', $bitsstored, ', 0, 0, ', $smooth, ', ', $flags, ')');
	$r = meddream_thumbnail($path, $thumbnail, $basedir, $thumbnailSize, $xfersyntax,
		$bitsstored, 0, 0, $smooth, $flags);
	$log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

	if (strlen($r) < 1)
		exit('processing failed, see logs');
	if ($r[0] == 'E')
	{
		$r = substr($r, 5);
		if (file_exists($r))
			copy($r, $thumbnail);
	}
	elseif ($r[0] == '2')
	{
		/* verify once more as the GD2 format is still possible

		   Possible reasons are bugs in code before meddream_thumbnail (especially
		   if it was manipulated to force the legacy format), and even wrong version
		   of mdc.exe (for images that need it)
		 */
		if (!function_exists('imagecreatefromstring'))
		{
			$log->asErr('internal: GD2 extension is missing');
			exit('processing failed, see logs');
		}

		$r = substr($r, 5);
		$r = imagecreatefromstring($r);

		/* convert to TIFF if requested */
		if ($type == '.tiff')
		{
			include_once('convertImage.php');

			imagepng($r, $thumbnail);

			$newfile = convertImage::convertToType($thumbnail, $type);
			if ($newfile == '')
			{
				if (convertImage::$error != '')
					$log->asErr("conversion error: '" . convertImage::$error . "'");
				$log->asDump('converter output: ', convertImage::$out);

				$log->asWarn('falling back to JPEG format');
				$type = '.jpg';
				imagejpeg($r, $thumbnail, $quality);
			}
			else
			{
				@unlink($thumbnail);
				$thumbnail = $newfile;
			}
		}
		else
			imagejpeg($r, $thumbnail, $quality);

		imagedestroy($r);
	}
	elseif ($r[0] == 'J')
	{
		/* the decision about this format was made before the meddream_thumbnail call,
		   no further processing is needed
		 */
		$r = substr($r, 5);
		file_put_contents($thumbnail, $r);
	}
	else
		exit('processing failed, see logs');

	$size = filesize($thumbnail);

	header('Pragma: public');
	header('Cache-Control: cache, must-revalidate');
	header("Content-Length: $size");
	header('Content-Type: image/' . updateType($type));
	header('Content-disposition: attachment;filename="'.$filename.'"');
	echo file_get_contents($thumbnail);
	flush();

	@unlink($thumbnail);
}

if (!strlen(session_id()))
	@session_start();

require_once(__DIR__ . '/autoload.php');
require_once('convertImage.php');

define('SLOW_DOWN', 0);			/* for SWS: 10000 */
set_time_limit(0);
ini_set('memory_limit', '1024M');

$modulename = basename(__FILE__);

$log = new Logging();
$log->asDump('begin ' . $modulename);

$audit = new Audit('SAVE IMAGE');	/* after session_start() for proper session ID tracking */

if (isset($_GET['uid']))
	$uid = urldecode($_GET['uid']);
if (!strlen($uid))
{
	$audit->log(false);
	$log->asWarn('nothing to do');
	exit;
}

$clientid = '';
if (isset($_SESSION['clientIdMD']))
	$clientid = $_SESSION['clientIdMD'];

$backend = new Backend(array('Structure', 'Preload'));
if (!$backend->authDB->isAuthenticated() || ($clientid == ''))
{
	$audit->log(false, $uid);
	$log->asErr('not authenticated');
	return false;
}

$type = '.jpg';
if (isset($_GET['type']))
	$type = urldecode($_GET['type']);

$smooth = $backend->enableSmoothing;
if (isset($_REQUEST['smooth']))
	$smooth = intval(urldecode($_REQUEST['smooth']));

$log->asDump('$uid = ', $uid, ', $type = ', $type, ', $smooth = ', $smooth);

if ($backend->pacsConfig->getRetrieveEntireStudy())
{
	$retrieve = new RetrieveStudy(new Study(), $log);
	$err = $retrieve->verifyAndFetch($uid);
	if ($err)
	{
		$audit->log(false, $uid);
		exit($err);
	}
}
$st = $backend->pacsStructure->instanceGetMetadata($uid, true);
if (strlen($st['error']))
{
	$audit->log(false, $uid);
	exit($st['error']);
}
$path = $st['path'];
$xfersyntax = $st['xfersyntax'];
$bitsstored = $st['bitsstored'];
$name = PathUtils::getName($st);

$convert = false;
$type = strtolower($type);
$speed = 64;

switch (strtolower($xfersyntax))
{
	case '1.2.840.10008.1.2.4.100':
		if ($type != '.dcm')
		{
			$convert = true;
			$type = '.mpg';
		}
		break;

	case '1.2.840.10008.1.2.4.103':
		if ($type != '.dcm')
		{
			$convert = true;
			$type = '.mp4';
		}
		break;

	case 'mp4':
		$convert = false;
		$type = '.mp4';
		break;

	default:
		if ($type != '.dcm')
			$convert = true;
		else
			$convert = false;
		break;
}

$name = PathUtils::escapeFileName($name);
$filename = ($name ?: 'Exported') . date('Ymd') . $type;

$log->asDump('$convert = ', $convert, ', $path = ', $path, ', $filename = ', $filename);
if (!$convert)
{
	$size = @filesize($path);
	$fp = @fopen($path, "rb");
	if (!$fp)
	{
		$audit->log(false, $uid);
		$log->asErr('unreadable: ' . var_export($path, true));
		exit('Failed to read the source file');
	}

	header('Pragma: public');
	header('Cache-Control: cache, must-revalidate');
	header("Content-Length: $size");
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');

	$readSize = round($speed*1024);

	while (!feof($fp) && !connection_aborted())
	{
		echo fread($fp, $readSize);
		flush();
		if (SLOW_DOWN)
			usleep(SLOW_DOWN);
	}
	fclose($fp);
}
else
{
	if (($type == '.mp4') || ($type == '.mpg'))
		setVideo($clientid, $filename, $path, $type, $speed);
	else
		if (($type == '.jpg') || ($type == '.tiff'))
			saveJPG($path, $xfersyntax, $bitsstored, $filename, $type);
}

/* also remove the source file that might be created by instanceGetMetadata() */
$backend->pacsPreload->removeFetchedFile($path);

$audit->log(true, $uid);

$log->asDump('end ' . $modulename);
