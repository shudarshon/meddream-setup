<?php
/*
	Original name: export.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Support for the Export dialog:
		1. make a copy of the study and include a DICOMDIR viewer (PacsOne does
		   that by itself);
		2. "archive" said copy to an .ISO file
		3. remove the copy and other temporary files when the dialog closes

 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;

class export
{
	protected $log;
	protected $backend = null;


	public function __construct($backend = null)
	{
		require_once('autoload.php');
		$this->log = new Logging();
		$this->backend = $backend;
	}


	/**
	 * return new or existing instance of Backend
	 * If the underlying AuthDB must be connected to the DB, then will request the connection once more.
	 *
	 * @param array $parts - Names of PACS parts that will be initialized
	 * @param boolean $withConnection - is a DB connection required?
	 * @return Backend
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	private function getTimestamp()
	{
		list($usec, $sec) = explode(" ", microtime());
		list($int, $dec) = explode(".", $usec);
		$dec = date("YmdHis").sprintf("%06d", (int)($dec / 100));
		return $dec;
	}


	public function media($studyInstanceUID, $size = '650')
	{
		set_time_limit(0);

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyInstanceUID, ', ', $size, ')');

		$return = array('error' => 'reconnect', 'id' => '', 'timestamp' => '');

		try
		{
			$audit = new Audit('EXPORT');

			$return['error'] = 'error';

			$backend = $this->getBackend(array('Export', 'Structure', 'Preload'), false);
			if (!$backend->authDB->isAuthenticated())
			{
				$this->log->asErr('not authenticated');
				$audit->log(false, $studyInstanceUID);
				return $return;
			}

			$timestamp = $this->getTimestamp();

			$exportDir = dirname(__FILE__) . "/temp/$timestamp.export.tmp";
			$exportDir = str_replace("\\", "/", $exportDir);
			$exportDir = str_replace("//", "/", $exportDir);
			$this->log->asDump('$exportDir: ', $exportDir);

			if (@!file_exists($exportDir) && @!mkdir($exportDir))
			{
				$return['error'] = "Failed to create Sub-Directory: $exportDir";
				$this->log->asErr($return['error']);
				$audit->log(false, $studyInstanceUID);
				return $return;
			}

			$exp = $backend->pacsExport->createJob($studyInstanceUID, '', $size, $exportDir);
			if (is_null($exp))
			{
				$err = $backend->authDB->reconnect();
				if (strlen($err))
				{
					$return['error'] = 'not connected';
					$this->log->asErr($return['error']);
					return $return;
				}

				if ($backend->pacsConfig->getRetrieveEntireStudy())
				{
					$retrieve = new RetrieveStudy(new Study(), $this->log);
					$err = $retrieve->verifyAndFetch($studyInstanceUID);
					if ($err)
					{
						$audit->log(false, $studyInstanceUID);
						$return['error'] = $err;
						return $return;
					}
				}

				$return['error'] = $this->manageExport($studyInstanceUID, $exportDir, $backend);
				if (strlen($return['error']))
				{
					$this->log->asDump('manageExport: ', $return['error']);
					$audit->log(false, $studyInstanceUID);
					return $return;
				}

				$return['error'] = '';
				$return['timestamp'] = (string) $timestamp;
				$return['id'] = 'submitted';
			}
			else
				if (strlen($exp['error']))
				{
					$this->log->asErr($exp['error']);
					$audit->log(false, $studyInstanceUID);
					return $return;
				}
				else
				{
					$return['error'] = '';
					$return['timestamp'] = (string) $timestamp;
					$return['id'] = (string) $exp['id'];
				}

			$audit->log(true, $studyInstanceUID);
		}
		catch (Exception $e)
		{
			$return["error"] = "Unhandled exception '" . $e->getMessage() .
				"' (" . var_export($e->getCode(), true) . ') in ' .
				$e->getFile() . ':' . $e->getLine();
		}
		$this->log->asDump('returning: ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	function status($id)
	{
		$this->log->asDump('begin '.__METHOD__);
		$return = array();
		$return['error'] = 'reconnect';
		$return['status'] = '';

		$backend = $this->getBackend(array('Export'), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		$st = $backend->pacsExport->getJobStatus($id);
		if (is_null($st))
		{
			$return['error'] = '';
			$return['status'] = 'success';
		}
		else
			if (strlen($st['error']))
			{
				$return['error'] = $st['error'];
				$this->log->asErr($st['error']);
				return $return;
			}
			else
				$return = $st;

		$this->log->asDump('$return: ', $return);
		$this->log->asDump('end '.__METHOD__);
		return $return;
	}


	/* export the last report into a file (our own text format)

		The returned text is treated as an error message and displayed in
		a pop-up window.
	 */
	function notes($timestamp, $studyUID)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array('Report'));
		if (!$backend->authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			return $err;
		}

		set_time_limit(0);

		$timestamp = str_replace("..", "", $timestamp);	/* against path traversal attacks */
		$timestamp = str_replace(".", "", $timestamp);
		$timestamp = str_replace("\\", "", $timestamp);
		$timestamp = str_replace("/", "", $timestamp);

		$exportDir = dirname(__FILE__)."/temp/$timestamp.export.tmp";
		$exportDir = str_replace("\\", "/", $exportDir);
		$exportDir = str_replace("//", "/", $exportDir);

		if (!file_exists($exportDir))
		{
			$error = 'Export Dir not found: ' . $exportDir;
			$this->log->asErr($error);
			return $error;
		}
		$notes = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\r\n";
		$notes .= "<notes>\r\n";

		$n = 1;
		$studyUIDArray = explode(';', $studyUID);
		foreach ($studyUIDArray as $uid)
		{
			$rep = $backend->pacsReport->getLastReport($uid);
			if (strlen($rep['error']))
				return '';
			if (is_null($rep['notes']))		/* notes absent (no matter they are supported or not) */
			{
				$this->log->asDump('end '.__METHOD__);
				return '';
			}

			$notes .= "\t<note>\r\n";
			$notes .= "\t\t<uid>{$uid}</uid>\r\n";
				/* TODO: dcm4chee (any version): $uid is just a primary key, must convert to UID */
			$notes .= "\t\t<file>NOTE{$n}</file>\r\n";
			$notes .= "\t</note>\r\n";

			/* save our report to a plain text file "STUDYNOTES/NOTE{$i} in every
			   volume. Multiple files if >1 study was exported.
			 */
			$i = 1;
			$volDir = $exportDir."/VOL{$i}";
			while (file_exists($volDir))
			{
				$studyNotesDir = "{$volDir}/STUDYNOTES";
				if (@!file_exists($studyNotesDir) && (@!mkdir($studyNotesDir)))
				{
					$error = "Failed to create Sub-Directory: ".$studyNotesDir;
					$this->log->asErr($error);
					return $error;
				}
				$noteFileName = "{$studyNotesDir}/NOTE{$n}";

				$f = fopen($noteFileName, 'w+');
				fwrite($f, $rep['notes']);
				fclose($f);

				$i++;
				$volDir = $exportDir."/VOL{$i}";
			}
			$n++;
		}
		$notes .= "</notes>\r\n";

		/* create an "index" file STUDYNOTES/NOTES (XML format) */
		$i = 1;
		$volDir = $exportDir."/VOL$i";
		while (file_exists($volDir))
		{
			$studyNotesDir = "$volDir/STUDYNOTES";
			if (@!file_exists($studyNotesDir) && (@!mkdir($studyNotesDir)))
			{
				$error = "Failed to create Sub-Directory: ".$studyNotesDir;
				$this->log->asErr($error);
				return $error;
			}
			$notesFileName = "$studyNotesDir/NOTES";

			$f = fopen($notesFileName, 'w+');
			fwrite($f, $notes);
			fclose($f);

			$i++;
			$volDir = $exportDir."/VOL$i";
		}

		$this->log->asDump('end '.__METHOD__);

		return '';
	}


	function vol($timestamp, $mediaLabel)
	{
		set_time_limit(0);

		$this->log->asDump('begin ' . __METHOD__ . '(', $timestamp, ', ', $mediaLabel, ')');
		$return = array();
		$return["error"] = "reconnect";
		$return["vol"] = array();
		$return["vol"]["count"] = 0;

		$backend = $this->getBackend(array('Export'), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		try
		{
			$return["error"] = "";

			$mediaLabel = trim($mediaLabel);

			$timestamp = str_replace("..", "", $timestamp);
			$timestamp = str_replace(".", "", $timestamp);
			$timestamp = str_replace("\\", "", $timestamp);
			$timestamp = str_replace("/", "", $timestamp);

			$exportDir = dirname(__FILE__) . "/temp/$timestamp.export.tmp";
			$exportDir = str_replace("\\", "/", $exportDir);
			$exportDir = str_replace("//", "/", $exportDir);

			$this->log->asDump('$exportDir: ' . $exportDir);

			if (!file_exists($exportDir))
			{
				$return["error"] = "Export: Failed to read ExportDir";
				$this->log->asErr($return["error"] );
				return $return;
			}

			$i = 1;
			$volDir = "$exportDir/VOL$i";
			$isoFileName = "$exportDir/VOL$i.ISO";
			$burnFileName = "$exportDir/VOL$i.burn";
			$iso = "temp/$timestamp.export.tmp/VOL$i.ISO";
			$burn = "temp/$timestamp.export.tmp/VOL$i.burn";

			session_write_close();		/* the loop below might take lots of time */

			$vrf = $backend->pacsExport->verifyJobResults($exportDir);
			if (!is_null($vrf))
				if (strlen($vrf))
				{
					$return['error'] = $vrf;
					return $return;
				}

			while (file_exists($volDir))
			{
				$return["vol"]["count"] = $i;
				$return["vol"][$i - 1] = array();
				$return["vol"][$i - 1]["name"] = "VOL$i";
				$return["vol"][$i - 1]["iso"] = $iso;
				$return["vol"][$i - 1]["burn"] = $burn;

				$currentLoc = dirname(__FILE__);
				$currentLoc = str_replace("\\", "/", $currentLoc);
				$currentLoc = str_replace("//", "/", $currentLoc);
				$dicomdir = $currentLoc . "/DICOMDIR";

				if ((file_exists($dicomdir)) && (!file_exists($volDir . "/autorun.inf")))
					$dicomdirStr = " \"$dicomdir\"";
				else
					$dicomdirStr =  "";

				$currentLoc = str_replace("/", "\\", $currentLoc);
				if (PHP_OS == "WINNT")
					$exe = "CMD /C \"\"".$currentLoc."\\cdrtools\\mkisofs.exe\" -D -l -m '#*' -m '*~' -m '*.core' -r -J -V \"VOL$i\" -o \"$isoFileName\" \"$volDir\"".$dicomdirStr."\"  2>&1";
				else
					$exe = "mkisofs -D -J -l -m \"#*\" -m \"*~\" -m \"*.core\" -r -V \"VOL$i\" -o \"$isoFileName\" \"$volDir\"".$dicomdirStr."  2>&1";

				$errors = '';
				$this->log->asDump('$exe = ', $exe);
				try
				{
					exec($exe, $err);			//try execute command line
				}
				catch (Exception $e)
				{
					$errors = 'mkisofs failed: ' . $e->getMessage();
					$this->log->asErr($errors);
				}

				if (file_exists($isoFileName))
					copy($isoFileName, $burnFileName);
				else
				{
					$errors = "can't start mkisofs: " . implode("\n", $err);
					$this->log->asErr($errors);
				}
				if (strlen($errors))
				{
					$return = array('error' => $errors);
					break;
				}

				$i++;
				$volDir = "$exportDir/VOL$i";
				$isoFileName = "$exportDir/VOL$i.ISO";
				$burnFileName = "$exportDir/VOL$i.burn";
				$iso = "temp/$timestamp.export.tmp/VOL$i.ISO";
				$burn = "temp/$timestamp.export.tmp/VOL$i.burn";
			}
		}
		catch (Exception $e)
		{
			$return = array('error' => "Unhandled exception '" . $e->getMessage() .
				"' (" . var_export($e->getCode(), true) . ') in ' .
				$e->getFile() . ':' . $e->getLine());
		}

		$this->log->asDump('return: ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	function delete_directory($dirname)
	{
		if (!@is_dir($dirname))
		{
			$this->log->asWarn('Directory does not exist :' . $dirname);
			return false;
		}
		$dir_handle = opendir($dirname);
		if (!$dir_handle)
		{
			$this->log->asWarn('Can not open directory for delete: ' . $dirname);
			return false;
		}
		while ($file = readdir($dir_handle))
		{
			if ($file != "." && $file != "..")
			{
				if (!@is_dir($dirname . '/' . $file))
				{
					try
					{
						@unlink($dirname . '/' . $file);
					}
					catch (Exception $e)
					{
						$this->log->asWarn($e->getMessage());
					}
				}
				else
					$this->delete_directory($dirname . '/' . $file);
			}
		}
		closedir($dir_handle);

		try
		{
			@rmdir($dirname);
		}
		catch (Exception $e)
		{
		}

		return true;
	}


	function deleteTemp($timestamp)
	{
		$this->log->asDump('begin ' . __METHOD__);

		set_time_limit(0);

		$timestamp = str_replace("..", "", $timestamp);
		$timestamp = str_replace(".", "", $timestamp);
		$timestamp = str_replace("\\", "", $timestamp);
		$timestamp = str_replace("/", "", $timestamp);

		$exportDir = dirname(__FILE__)."/temp/$timestamp.export.tmp";
		$exportDir = str_replace("\\", "/", $exportDir);
		$exportDir = str_replace("//", "/", $exportDir);
		$this->log->asDump('$exportDir: ', $exportDir);

		session_write_close();		/* just in case, again */

		$this->delete_directory($exportDir);
		$this->log->asDump("'$exportDir' deleted? ", !file_exists($exportDir));

		$this->log->asDump('end ' . __METHOD__);
		return $exportDir;
	}


	/*
	*	try get studies array data
	*	try create DICOMDIR file
	*	input
	*		$studyInstanceUID  	- studies uid
	*		$exportDir  		- temp exporting directorie
	*	return
	*		string 	 - error or ''
	*/
	function manageExport($studyInstanceUID, $exportDir, $backend)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$patients = array();
		$studyInstanceUIDArray = explode(';', $studyInstanceUID);
		foreach ($studyInstanceUIDArray as $uid)
		{
			$studyList = $backend->pacsStructure->studyGetMetadata($uid, true,
				$backend->pacsConfig->getRetrieveEntireStudy());

			if ($studyList['error']!='')						//bad connection
			{
				$this->delete_directory($exportDir);
				$error = "Failed to connect";
				$this->log->asErr($error);
				return $error;
			}
			if ($studyList['count']==0)							//no series
			{
				$this->delete_directory($exportDir);
				$error = "No series in this study";
				$this->log->asErr($error);
				return $error;
			}

			if (($error = $this->make($studyList, $exportDir, $backend, $patients)) != '')
			{
				$this->log->asErr($error);
				return $error;
			}
		}
		$this->log->asDump('end ' . __METHOD__);

		return '';
	}

	/**
	 * @return array Available volume types
	 */
	public function getVolumeSizes()
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array('Export'), false);	/* no database connection, we need only ::$pacs */

		/* a particular piece of text must be translated */
		$tr = $backend->tr;
		$err = $tr->load();
		if (strlen($err))
			$this->log->asWarn(__METHOD__ . ": problem with translations: $err");
		$mediaSize = $tr->translate('export\Unlimited',
			'Unlimited (a single volume)');

		$sizes = array(
			'data' => array(
				array(
					'id' => 'unlimited',
					'type' => 'volume',
					'attributes' => array(
						'name' => $mediaSize,
						'size' => '2147483647',
					),
				),
			),
			'meta' => array(
				'default' => 'unlimited'
			),
		);

		$otherSizes = $backend->pacsExport->getAdditionalVolumeSizes();
		if (!is_array($otherSizes))
			$this->log->asErr('PacsExport::getAdditionalVolumeSizes: ' . $backend->pacsExport->getInitializationError());
		else
		{
			$sizes['data'] = array_merge($sizes['data'], $otherSizes['data']);
			$sizes['meta']['default'] = $otherSizes['default'];
		}

		$this->log->asDump(__METHOD__ . ': returning ', $sizes);
		$this->log->asDump('end ' . __METHOD__);

		return $sizes;
	}


	private function compareSeries($a, $b)
	{
		/* our arrays contain an additional level with numeric keys.
		   Luckily, the key 0 should always exist, so we can use it.
		 */
		if (isset($a[0]))
			$aa = $a[0];
		else
			return 0;
		if (isset($b[0]))
			$bb = $b[0];
		else
			return 0;

		/* move unset values to the end */
		if (isset($aa['seriesno']))
		{
			if (!isset($bb['seriesno']))
				return -1;
			/* both values are now safe for indexing */

			/* move null values to the end */
			if (!is_null($aa['seriesno']))
			{
				if (is_null($bb['seriesno']))
					return -2;
				/* both values are now safe for logic */

				/* the main logic */
				return ((int) $aa['seriesno']) - ((int) $bb['seriesno']);
			}
			else
				if (!is_null($bb['seriesno']))
					return 2;
				else
					return 0;
		}
		else
			if (isset($bb['seriesno']))
				return 1;
			else
				return 0;		/* both unset */
	}


	/* sort $study in-place */
	private function sortStudy(&$study)
	{
		/* usort() needs numerically-indexed arrays though ours are indexed in both
			fashions. The easiest way is to sort a copy. Two separate levels will be
			needed due to the two-dimensional nature.

			Afterwards the items are updated by indexing them in the same fashion
			(via 0-based numbers), therefore the original may keep unsorted values
			up to that point.
		 */
		$series = array();
		for ($i = 0; $i < $study['count']; $i++)
		{
			$ser = $study[$i];

			$instances = array();
			for ($j = 0; $j < $ser['count']; $j++)
				$instances[] = $ser[$j];

			usort($instances, 'dcm4che_compare_instances');

			$j = 0;
			foreach ($instances as $ins)
				$ser[$j++] = $ins;

			$series[] = $ser;
		}

		usort($series, 'export::compareSeries');

		$i = 0;
		foreach ($series as $s)
			$study[$i++] = $s;
	}


	/**
	 *	try create necessery directories and copy images in it
	 *	try create DICOMDIR file
	 *	input
	 *		$studyList  - studies array data
	 *		$exportDir  - temp exporting directorie
	 *		$patients   - a list of patients that was found in previous studies
	 *	return
	 *		string 	 - error or ''
	 */
	private function make($studyList, $exportDir, $backend, &$patients = array())
	{
		set_time_limit(0);
		$this->log->asDump('begin ' . __METHOD__);

		try {
			//try make directories /vol1/PAT{$n}/STU{$n}
			$dir = $this->makeStudDir($exportDir, $studyList['patientid'], $patients);
		} catch(Exception $e) {                  //if encounters error creating directories
			$error = $e->getMessage();
			self::delete_directory($exportDir);  //delete created directories
			$this->log->asErr($error);
			return $error;
		}

		/* in DICOM, WADO and similar modes, images might still be unavailable. The
		   study structure might also be not in the correct order, and this order is
		   used by setDirsAndFiles() to name the files and directories.
		 */
		$errors = $backend->pacsPreload->fetchAndSortStudy($studyList);
		if ($errors != '')
			return $errors;

		/* make a copy into a directory tree of certain structure */
		$errors = '';
		for ($i = 0; $i < $studyList['count']; $i++)		//try create series and images
		{
			$error = $this->setDirsAndFiles($backend, $studyList[$i], $dir, ($i+1));
			if ($error != '')
				$errors .= ' ' . $error;
		}

		/**
		 * create dicomdir
		 */
		if (empty($errors))
		{
			$input = $exportDir . DIRECTORY_SEPARATOR . 'VOL1';
			$output = $exportDir . DIRECTORY_SEPARATOR . 'VOL1' . DIRECTORY_SEPARATOR . 'DICOMDIR';

			$dcm4cheBin = __DIR__ . DIRECTORY_SEPARATOR . 'dcm4che' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR;

			if (PHP_OS == "WINNT")
				$cmd = "CMD /C \" \"" . $dcm4cheBin . "dcmdir.bat\" -c \"$output\"  \"$input\" \" 2>&1";
			else
				$cmd = $dcm4cheBin . "dcmdir -c \"$output\"  \"$input\" 2>&1";

			$this->log->asDump('$cmd = ', $cmd);

			$err = array();
			session_write_close();
			try
			{
				exec($cmd, $err);			//try execute command line
			}
			catch (Exception $e)
			{
				$errors = 'dcmdir failed: ' . $e->getMessage();
				$this->log->asErr($errors);
			}

			if (!file_exists($output))
			{
				$errors = "can't start dcmdir: " . implode("\n", $err);
				$this->log->asErr($errors);
			}
			else
				/* In some cases (for example, *.v2 files in Conquest) DcmDir crashes
				   after writing some data to the file so the above check doesn't log
				   its output for diagnostic. Therefore we'll log it blindly. In case
				   of success, the amount of text is moderate (2 lines total in DcmDir
				   v2, ~1 line per image in DcmDir v3) and doesn't interfere.
				 */
				if (count($err))
					$this->log->asDump('output from dcmdir: ', $err);
		}
		else
			$this->log->asErr($errors);

		$this->log->asDump('end ' . __METHOD__);

		return $errors;
	}


	/**
	 *	create necessery directories /vol1 /PAT1 /STU1
	 *	input
	 *		$exportDir  - temp exporting directorie
	 *		$patientId  - id of the patient whos study is being exported
	 *		$patients   - a list of patients that was found in previous studies
	 *	return
	 *		string      - created directory path
	 */
	private function makeStudDir($exportDir, $patientId, &$patients = array())
	{
		$this->log->asDump('begin ' . __METHOD__);

		$dir = "$exportDir/VOL1";
		$this->createSubDirectory($dir);  //try create /VOL1

		if (isset($patients[$patientId]))
			$patients[$patientId]['studies']++;
		else
			$patients[$patientId] = array(
				'id' => count($patients) + 1,
				'studies' => 1,
			);

		$dir .= '/PAT' . $patients[$patientId]['id'];
		$this->createSubDirectory($dir);  //try create /PAT{$n}

		$dir .= '/STU' . $patients[$patientId]['studies'];
		$this->createSubDirectory($dir);  //try create /STU{$n}

		$this->log->asDump('$return: ', $dir);
		$this->log->asDump('end ' . __METHOD__);

		return $dir;
	}


	private function createSubDirectory($dir)
	{
		if (@!file_exists($dir) && @!mkdir($dir))
			throw new Exception("Failed to create Sub-Directory: {$dir}");
	}


	/**
	 * update $img with missing attributes
	 *
	 * @param array $img
	 */
	private function updateWithDicomAttributes(&$img)
	{
		if (($img['xfersyntax'] == '') && !empty($img['path']))
		{
			$meta = meddream_extract_meta(__DIR__, $img['path'], 0);
			if (!$meta['error'])
			{
				if (isset($meta['xfersyntax']))
					$img['xfersyntax'] = $meta['xfersyntax'];
				if (isset($meta['sernum']))
					$img['seriesno'] = $meta['sernum'];
				if (isset($meta['instancenum']))
					$img['instanceno'] = $meta['instancenum'];
			}
			else
				$this->log->asErr('meddream_extract_meta: ' . var_export($meta, true));
		}
		if ($img['bitsstored'] == '')
			$img['bitsstored'] = 8;		/* irrelevant for a long time, probably since v3 */
	}


	/*
	*	create series directory and copy images in it
	*	input
	*		$backend  - instance of Backend
	*		$series  - series array
	*		$dir 	 - destination directory
	*		$index 	 - series index
	*	return
	*		string 	 - error or ''
	*/
	private function setDirsAndFiles($backend, &$series, $dir, $index)
	{
		$this->log->asDump('begin ' . __METHOD__);

		if ($series['count'] == 0)							//if series contains images
			return 'No images in series ' . $index;

		$dir = $dir . '/SER' . $index;
		if (@!file_exists($dir) && @!mkdir($dir)) 			//make series directory
			return "Failed to create Sub-Directory: ".$dir;

		$error = '';
		$count = 0;
		for ($i = 0; $i < $series['count']; $i++)			//begin copying images
		{
			if (!$this->stream_copy($series[$i]['path'], $dir . '/IMA' . ($i+1)))
				$error .= 'Failed: ' . $series[$i]['path'] . "\n";
			else
			{
				$count++;

				/* also remove the source file that might be created by instanceGetMetadata() */
				$backend->pacsPreload->removeFetchedFile($series[$i]['path']);
			}
		}

		if ($count == 0)									//if no image copied
			$this->delete_directory($dir);

		$this->log->asDump('end ' . __METHOD__);

		return $error;
	}


	/*
	*	copy files
	*	input
	*		$src  - source file
	*		$dest - destination file
	*	return
	*		boolean - if file exsists?true:false
	*/
	private function stream_copy($src, $dest)
	{
		$this->log->asDump('end ' . __METHOD__);
		$this->log->asDump("try copy '$src' to '$dest'");

		if (!file_exists($src))								//source file exists
			return false;

		copy($src, $dest);

		$this->log->asDump('end ' . __METHOD__);
		return file_exists($dest);
	}
}

?>
