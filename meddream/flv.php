<?php
/*
	Original name: flv.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>

	Description:
		Converts a DICOM-encapsulated MPEG file to a .flv movie for playing in
		Flash. Uses flv.(bat|sh) as a lower-level engine that runs in the background.
 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

class flv
{
	/* The "subfile" protocol was added to FFmpeg on 2014-03-08
		(commit b28c37156782e563341e1bccc1a15dfbc01a569f). If you are on Linux and
		can't update /usr/bin/ffmpeg, then simply change this to false and MedDream
		will call FFmpeg using the legacy syntax.

		The legacy syntax means that FFmpeg itself attempts to skip the DICOM header
		when searching for start of video data. The detection fails with some DICOM
		files, which is the reason behind this dependency.
	 */
	const USE_FFMPEG_SUBFILE = true;

	protected $log;
	protected $backend;

	function __construct($backend = null)
	{
		require_once('autoload.php');
		$this->log = new Logging();

		if (is_null($backend))
			$this->backend = new Backend(array('Structure'));
		else
			$this->backend = $backend;
	}


	function load($uid, $type = 'flv')
	{
		$this->log->asDump('begin ' . __METHOD__);

		$audit = new Audit('FLV');

		if ($this->backend->pacs == 'FILESYSTEM')
		{
			$audit->log(false, $uid);
			$this->log->asErr('[FileSystem] Conversion to FLV not supported');
			return 'OK';
				/* flv.php itself works and creates the .flv where appropriate, due to
				   adjusted $uid from instanceGetMetadata(). But, as soon as we return
				   'OK', meddream.swf blindly generates a direct URL to /temp/$uid.flv
				   using the original value of $uid. Because the value often contains
				   subdirectory references, the file won't be found.

					SOLUTION 1: getflv.php like the one planned for remote preparation of MPEG2

					SOLUTION 2: a different protocol -- the response shall be "OK:$resultPath"
				 */
		}
		elseif ($this->backend->pacs == 'WADO')
		{
			$audit->log(false, $uid);
			$this->log->asErr('[WADO] Conversion to FLV not supported');
			return 'OK';
				/* in this mode a new $path would be downloaded on every call. Basically
				   caching like in $pacs='DICOM' will solve this.
				 */
		}

		if (!$this->backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false, $uid);
			return 'OK';
		}

		$tempPath = $this->backend->pacsConfig->getWriteableRoot();
		if (is_null($tempPath))
		{
			$audit->log(false, $uid);
			return 'OK';
		}
		$tempPath .= 'temp' . DIRECTORY_SEPARATOR;

		$st = $this->backend->pacsStructure->instanceGetMetadata($uid);
		if (strlen($st['error']))
		{
			$audit->log(false, $uid);
			return 'OK';
		}
		$path = $st['path'];
		$uid = $st['uid'];	/* will be used in a file name */

		/* some paranoia to avoid overwriting of wrong files

			Normally, UIDs with those characters won't be accepted by the PACS
			and therefore won't be found in the database by PacsStructure.
		 */
		$uid = str_replace("..", "", $uid);
		$uid = str_replace("\\", "", $uid);
		$uid = str_replace("/", "", $uid);
		$uid = str_replace("\"", "", $uid);
		$uid = str_replace("'", "", $uid);

		$toolOut = "$tempPath$uid.out";
		$flvTMP = $tempPath.$uid.'.tmp.'.$type;
		$flvCOPY = $path.'.'.$type;
		$flv = $tempPath.$uid.'.'.$type;

		if (PHP_OS == 'WINNT')
		{
			$flvREN = $uid.'.'.$type;
				/* the "ren" command doesn't accept a path name for destination file */

			$toolOut = str_replace("/", "\\", $toolOut);
			$path = str_replace("/", "\\", $path);
			$flvTMP = str_replace("/", "\\", $flvTMP);
			$flvREN = str_replace("/", "\\", $flvREN);
			$flvCOPY = str_replace("/", "\\", $flvCOPY);
			$flv = str_replace("/", "\\", $flv);
		}
		else
		{
			$flvREN = "$tempPath$uid." . $type;
				/* however here we're using "mv", which will move the file across
				   directories and even partitions if destination file has no
				   path and current directory is different (which of course is).
				 */

			$toolOut = str_replace("\\", "/", $toolOut);
			$path = str_replace("\\", "/", $path);
			$flvTMP = str_replace("\\", "/", $flvTMP);
			$flvREN = str_replace("\\", "/", $flvREN);
			$flvCOPY = str_replace("\\", "/", $flvCOPY);
			$flv = str_replace("\\", "/", $flv);
		}

		session_write_close();

		if (file_exists($flv))
		{
			@unlink($toolOut);
			$this->log->asInfo("done with '$flv'");
			$this->log->asDump('end ' . __METHOD__);
			$audit->log(true, $uid);
			return "OK";
		}

		/**
		 * command line takes $options as %5 %6 %7 %8 parameters
		 * if you will add more options - need add more parameters to cmd
		 */
		if (strtolower(trim($type)) == 'mp4')
			$options = '-vcodec libx264 -preset medium';
		else
			$options = '-ar 22050 -qscale 0.2';

		/* let's use FFmpeg seeking ability for files which format isn't otherwise detected
		 */
		if (self::USE_FFMPEG_SUBFILE)
		{
			$newPath = '';

			/* it needs valid offsets into file */
			$meta = meddream_extract_meta(__DIR__, $path, 0);
			if ($meta['error'])
				$this->log->asErr('meddream_extract_meta: ' . var_export($meta, true));
			else
				if (!isset($meta['pixel_locations'][0]['offset']) ||
						!isset($meta['pixel_locations'][0]['size']))
					$this->log->asErr('offset and/or size missing: ' . var_export($meta, true));
				else
				{
					$start = $meta['pixel_locations'][0]['offset'];
					$end = $meta['pixel_locations'][0]['size'];
					$end += $start;

					$newPath = "subfile,,start,$start,end,$end,,:$path";
				}

			if (strlen($newPath))
				$path = $newPath;
			else
				$this->log->asWarn('seeking into DICOM file not possible, FFmpeg will do the old-style conversion');
		}

		$this->log->asInfo('about to create: ' . $flv);
		if (!file_exists($flvTMP))
		{
			if (PHP_OS == 'WINNT')
			{
				$exe = 'start /b CMD /C ""' . dirname(__FILE__) . "\\flv.bat\" \"$path\" \"$flvTMP\" \"$flvREN\" \"$flvCOPY\" $options" .
					"  >\"$toolOut\" 2>&1\"";

				$this->log->asDump("running '$exe'");
				$fp = popen($exe, 'r');
				pclose($fp);
				if ($fp === false)
				{
					$this->log->asErr("failed to run '$exe'");
					$audit->log(false, $uid);
					return 'OK';	/* so far the only way to inhibit further calls */
				}
			}
			else
			{
				$exe = '"' . dirname(__FILE__) . "/flv.sh\" \"$path\" \"$flvTMP\" \"$flvREN\" \"$flvCOPY\" $options" .
					"  </dev/null >\"$toolOut\" 2>&1 &";
					/* stdin: otherwise the child remains stopped in the background
						see http://stackoverflow.com/q/17621798/4279
					 */
				$this->log->asDump("running '$exe'");

				$err1 = '';
				$err2 = '';
				try
				{
					exec($exe, $err1);
				}
				catch (Exception $e)
				{
					$audit->log(false, $uid);
					$err2 = $e->getMessage();
					$this->log->asErr("exception: $err2");
					return 'OK';	/* so far the only way to inhibit further calls */
				}
				if (!empty($err1))
					$this->log->asWarn(__METHOD__ . ': from exec(): ' . implode("\n", $err1));
			}
		}

		$audit->log('IN PROGRESS', $uid);
		$this->log->asDump(__METHOD__ . ': in progress');

		return "BUSY";
	}
}
?>
