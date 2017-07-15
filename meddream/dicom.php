<?php
/*
	Original name: dicom.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>
		nm <nerijus.marcius@softneta.com>

	Description:
		Sends out a DICOM file to the client side. For most PACSes, it's enough to
		fetch an instance path from database and read a particular file.
 */

define('LEGACY_PATH_DEPTH', 4);
	/* Nonzero value duplicates behavior of preparation scripts that do not fully support
	   nonzero Backend::$preparedFilesNestingDepth and instead of that simply map
	   PacsOne's "Hierarchical" format to the "Flat" format.
	 */

error_reporting(0);
set_time_limit(0);
	/* With very large multiframe files, even meddream_convert2() might trigger the time
	   limit. Better to adjust the limit here instead of just before sending data to the
	   client.
	 */
if (!strlen(session_id()))
	@session_start();
ignore_user_abort(true);

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

require_once __DIR__ . '/autoload.php';

$log = new Logging();
$method = basename(__FILE__);
$log->asDump('begin ' . $method);
$warningLength = 0; 


function exitWithError($message = '')
{
	global $audit;
	global $imageID;
	global $log;

	header('Pragma: public');
	header('Expires: Sat, 26 Jul 2017 05:00:00 GMT');
	header("Cache-Control: maxage=1000000");
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: filename="error.txt"');
	$audit->log(false, $imageID);
	$len = strlen($message);
	if ($len > 0)
	{
		$log->asErr($message);
		$len = min($len, 255);
		echo pack('C', $len);
		echo substr($message, 0, $len);
	}
	exit;
}

function addWarning($message = '')
{
	global $log, $warningLength;
	$len = strlen($message);
	if ($len > 0)
	{
		$log->asWarn($message);
		$len = min($len, 255);
		$warningLength += $len + 1;
		echo pack('C', $len);
		echo substr($message, 0, $len);
	}
}

function file_extension($filename)
{
	$path_info = pathinfo($filename);

	$extension = "";
	if (isset($path_info['extension']))
		$extension = $path_info['extension'];

	return $extension;
}

function extract_date_as_legacy_subdir($full_path)
{
	$normalized = str_replace('\\', '/', $full_path);
	$parts_all = explode('/', $normalized);
	if (count($parts_all) < LEGACY_PATH_DEPTH)
		return '';
	$parts_needed = array();
	$idx_from = count($parts_all) - LEGACY_PATH_DEPTH;
	while ($idx_from < count($parts_all))
		$parts_needed[] = $parts_all[$idx_from++];

	/* verify if this is a valid date

		The year is allowed to be larger by one, just in case.
	 */
	if (($parts_needed[0] < 2000) || ($parts_needed[0] > (idate('Y') + 1)))
		return '';
	if (($parts_needed[1] < 1) || ($parts_needed[1] > 12))
		return '';
	if (($parts_needed[2] < 1) || ($parts_needed[2] > 31))
		return '';

	/* calculate weekday */
	$ts = mktime(0, 0, 0, $parts_needed[1], $parts_needed[2], $parts_needed[0]);
	$dow = idate('w', $ts);
	$wd = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
	$parts_needed[3] = $wd[$dow];

	return join('-', $parts_needed);
}

function get_alternative_if_exists($path, $alt_dir, $smoothing_is_on, $depth)
{
	if ($alt_dir == '')
		return '';

	global $backend;

	/* some conditioning */
	$alt_dir = str_replace("\\", "/", $alt_dir);
	if ($alt_dir[strlen($alt_dir) - 1] != '/')
		$alt_dir .= '/';

	/* timestamp for $path */
	if (!@file_exists($path))
		return '';		/* avoid a warning from filemtime() */
	$tm = filemtime($path);

	$alt_dir_hier = $alt_dir;
	if (!$depth)
	{
		/* extract a date-based subdirectory from original path */
		$subdir = preg_replace("/.*(\d\d\d\d)-(\d\d)-(\d\d)-([A-Z][A-Z][A-Z]).*/", "$1-$2-$3-$4", $path, -1, $count);
		if (is_null($subdir) || !$count)
		{
			$subdir = extract_date_as_legacy_subdir($path);
			if (strlen($subdir))
				$alt_dir_hier .= $subdir . '/';
		}
		else
			$alt_dir_hier .= $subdir . '/';

		/* is the alternative available? */
		$success = true;
		if ($smoothing_is_on)
			$prepared_suff = $backend->preparedFilesSufSmooth;
		else
			$prepared_suff = $backend->preparedFilesSuf;
		$path_alt = $alt_dir_hier . basename($path) . $prepared_suff;
		if (!@file_exists($path_alt))
		{
			/* try without date-based subdirectory: converter script might produce files there */
			if (!strlen($subdir))	/* already did that -- no subdirectory in the path */
				$success = false;
			else
			{
				$path_alt = $alt_dir . basename($path) . $prepared_suff;
				if (!@file_exists($path_alt))
					$success = false;
			}
		}

		/* if it isn't, and $authDB->prepared_files_substitute is enabled,
		   then try with the opposite of $smoothing_is_on
		 */
		if (!$success && $backend->preparedFilesSubstitute)
		{
			$success = true;
			if ($smoothing_is_on)
				$prepared_suff = $backend->preparedFilesSuf;
			else
				$prepared_suff = $backend->preparedFilesSufSmooth;
			$path_alt = $alt_dir_hier . basename($path) . $prepared_suff;
			if (!@file_exists($path_alt))
			{
				/* try without date-based subdirectory: converter script might produce files there */
				if (!strlen($subdir))	/* already did that -- no subdirectory in the path */
					$success = false;
				else
				{
					$path_alt = $alt_dir . basename($path) . $prepared_suff;
					if (!@file_exists($path_alt))
						$success = false;
				}
			}
		}
	}
	else
	{
		/* some more conditioning */
		$path = str_replace("\\", "/", $path);

		/* extract the specified number of components from the end */
		$comp = explode('/', $path);
		if (!strlen($comp[0]) && !strlen($comp[1]))
			$limit = 4 + $depth;	/* minimum: "\\server\share\STUDY\SERIES\INSTANCE" */
		else
			$limit = 2 + $depth;	/* minimum: "DISK:\DIR\STUDY\SERIES\INSTANCE"; don't allow lots of entries in the root directory */
		if (count($comp) < $limit)
			return '';				/* path too short */
		while (count($comp) > $depth)
			array_shift($comp);
		$rel_path = join('/', $comp);
		$alt_dir_hier .= $rel_path;

		/* is the alternative available? */
		$success = true;
		if ($smoothing_is_on)
			$prepared_suff = $backend->preparedFilesSufSmooth;
		else
			$prepared_suff = $backend->preparedFilesSuf;
		$path_alt = $alt_dir_hier . $prepared_suff;

		if (!@file_exists($path_alt))
		{
			/* try without study/series subdirectories: converter script might produce files there */
			$path_alt = $alt_dir . $comp[$depth - 1] . $prepared_suff;
			if (!@file_exists($path_alt))
				$success = false;
		}

		/* if it isn't, and $authDB->prepared_files_substitute is enabled,
		   then try with the opposite of $smoothing_is_on
		 */
		if (!$success && $backend->preparedFilesSubstitute)
		{
			$success = true;
			if ($smoothing_is_on)
				$prepared_suff = $backend->preparedFilesSuf;
			else
				$prepared_suff = $backend->preparedFilesSufSmooth;
			$path_alt = $alt_dir_hier . $prepared_suff;
			if (!@file_exists($path_alt))
			{
				/* try without date-based subdirectory: converter script might produce files there */
				$path_alt = $alt_dir . $comp[$depth - 1] . $prepared_suff;
				if (!@file_exists($path_alt))
					$success = false;
			}
		}
	}

	/* timestamp for the alternative */
	if (!$success)
		return '';
	$tm_alt = filemtime($path_alt);

	/* is the alternative up to date? */
	if ($tm_alt < $tm)
		return '';
	return $path_alt;
}


$constants = new Constants();
$backend = new Backend(array('Structure', 'Preload'));
if (!$backend->authDB->isAuthenticated())
{
	$err = 'not authenticated';
	$log->asErr($err);
	exitWithError($err);
}

$fileFormat = "";
if (isset($_REQUEST['format']))
	$fileFormat = urldecode($_REQUEST['format']);

$imageID = "";
if (isset($_REQUEST['imageid']))
	$imageID = urldecode($_REQUEST['imageid']);
if (isset($_REQUEST['uid']))
	$imageID = urldecode($_REQUEST['uid']);
$clientid = "";
if (isset($_REQUEST['clientid']))
	$clientid = urldecode($_REQUEST['clientid']);
$pixelOffset = -1;
if (isset($_REQUEST['o']))
	$pixelOffset = intval(urldecode($_REQUEST['o']));
$pixelSize = 0;
if (isset($_REQUEST['s']))
	$pixelSize = intval(urldecode($_REQUEST['s']));
$pluginChoice = array();
if (isset($_REQUEST['plugin']))
	$pluginChoice = $_REQUEST['plugin'];
$smooth = $backend->enableSmoothing;
if (isset($_REQUEST['smooth']))
	$smooth = intval(urldecode($_REQUEST['smooth']));

$log->asDump("params: imageid='$imageID', offset=$pixelOffset, size=$pixelSize, clientid='$clientid'," .
	" smooth=$smooth, plugin=", $pluginChoice);

$encapsedDoc = false;

$audit = new Audit('VIEW IMAGE');
$auditSuccess = true;

$st = $backend->pacsStructure->instanceGetMetadata($imageID);
if (strlen($st['error']))
	exitWithError($st['error']);
$sopclass = $st['sopclass'];
$path = $st['path'];

$tempPath = $backend->pacsConfig->getWriteableRoot();
if (is_null($tempPath))
	exit_with_error('getWriteableRoot failed, see logs');
$tempPath .= 'temp/';
$tempPath = str_replace('\\', '/', $tempPath);		/* needed during removal of temporary file */
if (!file_exists($tempPath) && !mkdir($tempPath))
{
	$log->asErr("unavailable: " . $tempPath);
	exitWithError("failed to create Temp Path");
}

$predefinedDICOM = '';
if ($pixelOffset >= 0)
{
	$dicomFile = $path;
	if (!$pixelOffset)
		$pixelSize = filesize($dicomFile);
}
else
{
	if ((strtoupper(file_extension($path)) == 'MP4') ||
		(strtoupper(file_extension($path)) == '_SFNTV'))
	{
		/* approx. 500 bytes, each tag entered separately to ease further modifications */
		$predefinedDICOM = pack('x128') .
			'DICM' .
			pack('H*', '02000000554C0400E0000000') .
			pack('H*', '020001004F420000020000000001') .
			pack('H*', '0200100055491200312E322E3834302E31303030382E312E3220') .
			pack('H*', '020002014F420000AC000000') .
				'<?xml version="1.0"?><privateInformation><ts>1.2.840.10008.1.2.4.103' .
				'</ts><monochrome>0</monochrome><pixelOffset>0</pixelOffset>' .
				'<pixelSize>0</pixelSize></privateInformation>' .
			pack('H*', '080005000A00000049534F5F495220313030') .
			pack('H*', '28000200020000000300') .
			pack('H*', '280004000400000052474220') .
			pack('H*', '28000600020000000000') .
			pack('H*', '28000800020000003000') .
			pack('H*', '28000001020000000800') .
			pack('H*', '28000101020000000800') .
			pack('H*', '28000201020000000700') .
			pack('H*', '28000301020000000000') .
			pack('H*', '28001021020000003031');

		$pixelSize = strlen($predefinedDICOM);

		$log->asInfo("$pixelSize byte(s) of predefined header for '$path'");
	}
	else
		if ((strtoupper(file_extension($path)) == 'JPG') ||
			(strtoupper(file_extension($path)) == '_SFNTI'))
		{
			/* obtain image dimensions; unfortunately, getimagesizefromstring() is PHP 5.4+ */
			$sizeInfo = @getimagesize($path);
			if ($sizeInfo === false)
			{
				$jpgW = 0;
				$jpgH = 0;
			}
			else
			{
				$jpgW = $sizeInfo[0];
				$jpgH = $sizeInfo[1];
			}

			/* get raw JPG w/ padding */
			$rawJpgData = @file_get_contents($path);
			$rawJpgSize = strlen($rawJpgData);
			if ($rawJpgSize % 2)
			{
				$rawJpgSize++;
				$rawJpgData .= pack('x1');
			}

			$patName = $st['fullname'];
			if (strlen($patName) % 2)
				$patName .= ' ';
			$patId = $st['patientid'];
			if (strlen($patId) % 2)
				$patId .= ' ';

			/* make a minimalistic DICOM file */
			$predefinedDICOM = pack('x128') .
				'DICM' .
				pack('H*', '02000000554C04002C000000') .
				pack('H*', '020001004F420000020000000001') .
				pack('H*', '0200100055491600312E322E3834302E31303030382E312E322E342E3530') .
				pack('H*', '0800050043530A0049534F5F495220313030');

			if (trim($patName) != '')
				$predefinedDICOM .= pack('H*', '10001000504E') . pack('v', strlen($patName)) . $patName;

			if (trim($patId) != '')
				$predefinedDICOM .= pack('H*', '100020004C4F') . pack('v', strlen($patId)) . $patId;

			$predefinedDICOM .=
				pack('H*', '28000200555302000300') .
				pack('H*', '280004004353040052474220') .
				pack('H*', '28000600555302000000') .
				pack('H*', '28000800495302003000') .
				pack('H*', '2800100055530200') . pack('v', $jpgH) .
				pack('H*', '2800110055530200') . pack('v', $jpgW) .
				pack('H*', '28000001555302000800') .
				pack('H*', '28000101555302000800') .
				pack('H*', '28000201555302000700') .
				pack('H*', '28000301555302000000') .
				pack('H*', '28001021435302003031') .
				pack('H*', 'E07F10004F420000FFFFFFFF') .
				pack('H*', 'FEFF00E000000000') .
				pack('H*', 'FEFF00E0') . pack('V', $rawJpgSize) .
				$rawJpgData;
			$predefinedDICOM .= pack('H*', 'FEFFDDE000000000');

			$pixelSize = strlen($predefinedDICOM);
			$log->asInfo("$pixelSize byte(s) of constructed DICOM for '$path'");
		}
		else
		{
			$path_final = '';

			/* BEGIN SILPOL

				PacsOne-like paths must still be tried first
			 * /
			if ($authDB->prepared_files_nesting_depth)
				foreach ($authDB->prepared_files_dir as $pfd)
				{
					$path_final = get_alternative_if_exists($path, $pfd, $smooth, 0);
					if (strlen($path_final))
						break;
				}
			if (!strlen($path_final))
			/ *	 ^^^ HACK: for the next foreach()!
			   END SILPOL */

			/* try all directories from $prepared_files_dir (config.php) */
			foreach ($backend->preparedFilesDir as $pfd)
			{
				$path_final = get_alternative_if_exists($path, $pfd, $smooth,
					$backend->preparedFilesNestingDepth);
				if (strlen($path_final))
					break;
			}

			if (strlen($path_final))
			{
				$dicomFile = $path_final;
				$log->asInfo("already prepared: '$path_final'");
			}
			else
			{
				$pathMD = $path . ".md";
				if (!Constants::DL_REGENERATE)
				{
					$need_convert = !file_exists($pathMD);

					if (!$need_convert)
					{
						$dicomFile = $pathMD;
						$log->asInfo("already converted: '$pathMD'");
					}
				}
				else
					$need_convert = true;

				if ($need_convert)
				{
					$pluginStr = '';
					if (count($pluginChoice))
						$pluginStr = $pluginChoice['name'] . '|' . $pluginChoice['function'] .
							'|' . $pluginChoice['args'];

					$log->asDump("meddream_convert2('$clientid','$path')");
					$mc = meddream_convert2($clientid, dirname(__FILE__), $path, $tempPath,
						$smooth, $pluginStr);
					if (is_array($mc))
					{
						$dicomFile = $mc['result'];
						$encapsedDoc = $mc['encapdoc'];
					}
					else	/* 'Error' (Win32 exception) or 'error' (PHP, related to argument parsing) */
						$dicomFile = $mc;
					if (strlen($dicomFile))
					{
						$log->asDump('meddream_convert2: ', $mc);
						if (array_key_exists('warning', $mc))
							addWarning($mc['warning']);
						if (Constants::DL_REGENERATE)
							if (copy($dicomFile, $pathMD))
								$log->asInfo("updated '$pathMD'");
							else
								$log->asWarn("failed to update '$pathMD'");
					}
					else
					{
						$log->asWarn("meddream_convert2: failed on '$path' (uid = '$imageID'): " . var_export($mc, true));
						exitWithError("Failed to read data");
					}
				}
			}

			$pixelSize = filesize($dicomFile);
		}
}
session_write_close();
$fileName = '';
$fileExtWithDot = '';
$pa = pathinfo($dicomFile);
if (isset($pa['filename']))
	$fileName = $pa['filename'];
if (isset($pa['extension']))
	$fileExtWithDot = '.' . strtolower($pa['extension']);

if ($fileFormat == 'mp4')
{
	$stream = "";
	$buffer = 32 * 1024;
	$start = -1;
	$end = -1;
	$size = 0;

	if (!($stream = fopen($dicomFile, 'rb')))
	{
		$log->asErr("unreadable: '$dicomFile'");
		exitWithError('Could not open stream for reading');
	}

	ob_get_clean();
	header("Content-Type: video/mp4");
	header("Cache-Control: max-age=2592000, public");
	header("Expires: " . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
	header("Last-Modified: " . gmdate('D, d M Y H:i:s', @filemtime($dicomFile)) . ' GMT');
	$start = 0;

	$size = $pixelSize;
	$end = $size - 1;

	/*https://www.w3.org/TR/media-frags-recipes*/
	header("Accept-Ranges: bytes");

	if (isset($_SERVER['HTTP_RANGE']))
	{
		$tmp_start = $start;
		$tmp_end = $end;

		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		if (strpos($range, ',') !== false)
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		if ($range == '-')
			$tmp_start = $size - substr($range, 1);
		else
		{
			$range = explode('-', $range);
			$tmp_start = $range[0];

			$tmp_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $tmp_end;
		}
		$tmp_end = ($tmp_end > $end) ? $end : $tmp_end;
		if ($tmp_start > $tmp_end || $tmp_start > $size - 1 || $tmp_end >= $size)
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		$start = $tmp_start;
		$end = $tmp_end;
		$length = $end - $start + 1;

		header('HTTP/1.1 206 Partial Content');
		header("Content-Length: $length");
		header("Content-Range: bytes $start-$end/$size");
	}
	else
		header("Content-Length: " . ($size+$warningLength));

	$file_start = $start + $pixelOffset;
	$file_end = $end + $pixelOffset;
	fseek($stream, $file_start);

	$i = $file_start;
	set_time_limit(0);

	while (!feof($stream) && ($i <= $file_end))
	{
		$bytesToRead = $buffer;
		if (($i+$bytesToRead) > $file_end)
			$bytesToRead = $file_end - $i + 1;
		$data = fread($stream, $bytesToRead);
		echo $data;
		flush();
		$i += $bytesToRead;
	}
	fclose($stream);
}
else
{
	header('Pragma: public');
	header('Expires: Sat, 26 Jul 2017 05:00:00 GMT');
	header("Cache-Control: maxage=1000000");
	header("Content-Length: " . ($pixelSize + $warningLength));
	header('Content-Transfer-Encoding: binary');
	if ($fileExtWithDot == '.pdf')
	{
		/* likely PDF from meddream_convert2() */
		header('Content-Type: application/pdf');
		header('Content-Disposition: filename="' . $fileName . $fileExtWithDot . '"');
	}
	else
		if ($sopclass == '1.2.840.10008.5.1.4.1.1.104.1')
		{
			/* likely a prepared file with different extension; let's attempt to change
			   the extension, in order to provide a hint for some browsers
			 */
			if (!strcasecmp($fileExtWithDot, $backend->preparedFilesSuf) ||
				!strcasecmp($fileExtWithDot, $backend->preparedFilesSufSmooth))
			{
				header('Content-Type: application/pdf');
				$outName = "$fileName.pdf";
			}
			else
			{
				/* this SOP Class mandates PDFs, however the extension is neither 'pdf'
				   nor 'prep' / 'smooth'. Probably there is a reason for that, let's
				   force the download.
				 */
				header('Content-Type: application/octet-stream');
				$outName = "$fileName$fileExtWithDot";
			}

			header('Content-Disposition: filename="' . $outName . '"');
		}
		else
		{
			/* the rest: DICOM or strange encapsulated document, directly from
			   meddream_convert2() (randomized name, original extension) or prepared
			   (UID as name, one of two predefined extensions).

			   In fact, preparation scripts should ignore non-PDF embedded documents, as
			   an ordinary user won't be able to guess the format. Unless we utilize
			   the Fileinfo extension here, of course.
			 */
			header('Content-Type: application/octet-stream');

			/* Content Disposition is beneficial for any DICOM-ized data: the browser will
			   suggest to download the file, and use a proper name instead of "dicom.php".
			*/
			if ($encapsedDoc)
				header('Content-Disposition: filename="' . $fileName . $fileExtWithDot . '"');
				/* php_meddream always sets $encapsedDoc for a PDF SOP Class, however
				   $sopclass is different here. Logical for $backend->pacs that doesn't
				   support the SOP Class. The extension is not "pdf", too, likely due
				   to a wrong MIME Type tag.

				   With prepared files, $encapsedDoc is always unset so we'll still get
				   the "dicom.php" offer. This means no preparation for PACSes without
				   SOP Class support.
				 */
		}

	if (!empty($predefinedDICOM))
		print $predefinedDICOM;
	else
	{
		ignore_user_abort(true);

		if ($file = fopen($dicomFile, 'rb'))
		{
			
			$log->asDump("about to read: ", fstat($file));
			if ($pixelOffset > 0)
				fseek($file, $pixelOffset);

			while (!feof($file) && $pixelSize && !connection_aborted())
			{
				$bs = 32 * 1024;
				if ($bs > $pixelSize)
					$bs = $pixelSize;

				$buffer = fread($file, $bs);
				print $buffer;
				@ob_flush();
				flush();
				if (connection_aborted() == 1)
				{
					$auditSuccess = false;
					break;
				}
				$pixelSize -= strlen($buffer);
			}
			fclose($file);
		}
		else
		{
			$auditSuccess = false;
			$log->asErr("unreadable: '$dicomFile'");
		}

		/* Will remove only files just created by meddream_convert(). These are always under
		   $tempPath. Additionally, the function sometimes returns the same path if the file
		   does not need processing.
		 */
		if (($dicomFile != $path) && ((dirname($dicomFile) . '/') == $tempPath))
		{
			@unlink($dicomFile);
			$log->asDump("removed: '$dicomFile'");
		}

		/* also remove the source file that might be created by instanceGetMetadata() */
		$backend->pacsPreload->removeFetchedFile($path);
	}
}

$audit->log($auditSuccess, $imageID);
$log->asDump('end ' . $method);

exit;

?>
