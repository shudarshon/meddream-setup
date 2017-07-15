<?php
/*
	Original name: Study.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Provides study structure with all related metadata. There are variants
		of it for a single series and a single image.

		Another set of functions implement study forwarding to another DICOM AE.

		A separate function indicates whether study notes exist for a given
		study.

		Universal server-side data reader for the Info Labels function.
 */

namespace Softneta\MedDream\Core;


if (!strlen(session_id()))
	@session_start();


/** @brief Functions related to a single study, mostly legacy ones. */
class Study
{
	protected $log;
	protected $backend = null;


	function __construct($backend = null)
	{
		require_once('autoload.php');

		$this->log = new Logging();

		$this->backend = $backend;
	}


	/** @brief Return a new or existing instance of Backend.

		@param array   $parts           Names of PACS parts that will be initialized
		@param boolean $withConnection  Is a DB connection required?

		If the underlying AuthDB must be connected to the DB, then will request the
		connection once more.
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


	/** @brief Wrapper for Pacs\StructureIface::studyGetMetadata(). */
	function getStudyList($studyUID, $disableFilter = false, $fromCache = false)
	{
		$backend = $this->getBackend(array('Structure'));
		return $backend->pacsStructure->studyGetMetadata($studyUID, $disableFilter, $fromCache);
	}


	/** @brief Wrapper for Pacs\StructureIface::studyGetMetadataBySeries(). */
	function getSeriesList($seriesList)
	{
		$backend = $this->getBackend(array('Structure'));
		return $backend->pacsStructure->studyGetMetadataBySeries($seriesList);
	}


	/** @brief Wrapper for Pacs\StructureIface::studyGetMetadataByImage(). */
	function getImageList($imageList, $fromCache = false)
	{
		$backend = $this->getBackend(array('Structure'));
		return $backend->pacsStructure->studyGetMetadataByImage($imageList, $fromCache);
	}


	/** @brief Server-side implementation of Forward function: start a job.

		@param string $studyUID  Value of primary key in the images table (__not necessarily
		                         a SOP Instance UID__)
		@param string $sendToAE  Title of receiving AE
		@param string $path      Full path to a single file to send. If not empty, the
		                         basic implementation is used immediately.

		Calls Pacs\ForwardIface::createJob(). If the latter indicated that a more
		specific implementation is missing, then proceeds with a basic implementation.
	 */
	function forward($studyUID, $sendToAE = '', $path = '')
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUID, ', ', $sendToAE, ', ', $path, ')');

		$audit = new Audit('FORWARD SUBMIT');
		$auditSuccess = null;
		if (trim($path) == '')
			$auditDetails = "study '$studyUID', to '$sendToAE'";
		else
			$auditDetails = "file '$path', to local AE";

		$backend = $this->getBackend(array('Forward', 'Structure', 'Preload'));
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false, $auditDetails);
			return '';
		}

		/* We are sending our Presentation State (a single DICOM object). Must use DcmSnd
		   even with PACSes capable of forwarding as their forwarding mechanism is suitable
		   only for studies already in the database.
		*/
		if (!strlen(trim($path)))
		{
			$fwd = $backend->pacsForward->createJob($studyUID, $sendToAE);
			if (!is_null($fwd))
			{
				if (strlen($fwd['error']))
					return '';
				else
					return $fwd['id'];

				/* no need for audit logging, it was done in implementation of ForwardIface */
			}
		}
			/* otherwise continue with the common implementation */

		$id = (string) mt_rand();

		$dn = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		$fn_st = 'temp' . DIRECTORY_SEPARATOR . "status-$id.fwd";
		$fn_in = 'temp' . DIRECTORY_SEPARATOR . "in-$id.fwd";
		$fn_out = 'temp' . DIRECTORY_SEPARATOR . "out-$id.fwd";

		/* forward.in must contain paths to instance files */
		$job = '';
		$job_num = 0;

		if (trim($path) == '')
		{
			$studyUIDArray = explode(';', $studyUID);

			foreach ($studyUIDArray as $uid)
			{
				if ($backend->pacsConfig->getRetrieveEntireStudy())
				{
					$retrieve = new RetrieveStudy(new Study(), $this->log);
					$err = $retrieve->verifyAndFetch($uid);
					if ($err)
					{
						$audit->log(false, $auditDetails);
						return '';
					}
				}

				$sa = $backend->pacsStructure->studyGetMetadata($uid, false,
					$backend->pacsConfig->getRetrieveEntireStudy());
				if (strlen($sa['error']))
				{
					$audit->log(false, $auditDetails);
					return '';
				}

				$errors = $backend->pacsPreload->fetchAndSortStudy($sa);
				if ($errors != '')
				{
					$audit->log(false, $auditDetails);
					return '';
				}

				if (!array_key_exists('count', $sa))
				{
					$this->log->asErr('wrong format from studyGetMetadata/1');
					$audit->log(false, $auditDetails);
					return '';
				}
				for ($i = 0; $i < $sa['count']; $i++)
				{
					if (!array_key_exists('count', $sa[$i]))
					{
						$this->log->asErr('wrong format from studyGetMetadata/2');
						$audit->log(false, $auditDetails);
						return '';
					}
					for ($j = 0; $j < $sa[$i]['count']; $j++)
					{
						$job .= $sa[$i][$j]['path'] . PHP_EOL;
						$job_num++;
					}
				}
			}
		}
		else
		{
			if (!file_exists($path))
			{
				$this->log->asErr("file does not exist: '$path'");
				$audit->log(false, $auditDetails);
				return '';
			}
			$job .= $path . PHP_EOL;
			$job_num++;
		}

		if (!$job_num)
		{
			$this->log->asErr('study without any images: ' . $studyUID);
			$audit->log(false, $auditDetails);
			return '';
		}
		if (file_put_contents($dn . $fn_in, $job) === false)
		{
			$this->log->asErr("failed to update '$fn_in'");
			$audit->log(false, $auditDetails);
			return '';
		}
		if (file_put_contents($dn . $fn_out, "\n") === false)
		{
			$this->log->asErr("failed to create '$fn_out'");
			$audit->log(false, $auditDetails);
			return '';
			/* we're creating the file as an additional safeguard against collision
			   betweeen different users: then status checking has a better chance to
			   notice that number of files doesn't match, etc.
			 */
		}

		/* create the background task */
		$localAE = $backend->getPacsConfigPrm('local_aet');
		$forwardAETs = $backend->getPacsConfigPrm('forward_aets');
		if (is_null($localAE) || is_null($forwardAETs))
		{
			$this->log->asErr('PACS::getPacsConfigPrm() failed');
			$audit->log(false, $auditDetails);
			return '';
		}

		if (trim($path) != '')
		{
			/* get local AE Title

				A special format: the label must end with "- local".
			 */
			$sendToAE = '';
			foreach ($forwardAETs as $aetitleitem)
			{
				if (isset($aetitleitem['label']))
					if ($aetitleitem['label'] != '')
						if (substr($aetitleitem['label'], -7, 7) == '- local')
						{
							$sendToAE = $aetitleitem['data'];
							break;
						}
			}

			$auditDetails = "file '$path', to '$sendToAE'";

			if (trim($sendToAE) == '')
			{
				$this->log->asErr('failed to get local forward AET');
				$audit->log(false, $auditDetails);
				return '';
			}
		}

		$auditSuccess = "SUCCESS, job id $id";

		session_write_close();		/* against bug #44942 if multiple users are using forward */
		if (PHP_OS == 'WINNT')
		{
			$cmd = "start /B fwd.bat $fn_st $fn_in " .
				escapeshellcmd($sendToAE) . ' "' . $localAE . '" ' . $job_num . " >$fn_out 2>&1";

			$this->log->asDump("running '$cmd'");
			$fp = popen($cmd, 'r');
			pclose($fp);
			if ($fp === false)
			{
				$this->log->asErr("failed to run '$cmd'");
				$audit->log(false, $auditDetails);
				return '';
			}
		}
		else
		{
			$cmd = dirname(__FILE__) . "/fwd.sh $fn_st $fn_in " .
				escapeshellcmd($sendToAE) . ' "' . $localAE . '" ' . $job_num .
				"  >$fn_out 2>&1 &";
				/* without output redirection, '&' does not work */

			$this->log->asDump("running '$cmd'");
			$err1 = '';
			$err2 = '';
			try
			{
				exec($cmd, $err1);
			}
			catch (Exception $e)
			{
				$err2 = $e->getMessage();
				$this->log->asErr("failed to run fwd.sh: $err2");
				$audit->log(false, $auditDetails);
				return '';
			}
			if (!empty($err1))
			{
				$this->log->asWarn('from exec(): ' . implode("\n", $err1));
				$auditSuccess = false;
			}
		}

		$audit->log($auditSuccess, $auditDetails);
		$this->log->asDump('end ' . __METHOD__);

		return $id;
	}


	/** @brief Server-side implementation of Forward function: check status of a job.

		@param string $id     Job identifier from forward()
		@param int    $dbjob  If zero, proceed with the basic implementation immediately

		@retval ''         Job failed, GUI will display a non-specific error message box and stop
		@retval 'success'  Job finished, GUI will display the string below the button and stop
		@retval otherwise  GUI will display the text below the button and continue calling this function

		Calls Pacs\ForwardIface::getJobStatus(). If the latter indicated that a more
		specific implementation is missing, then proceeds with a basic implementation.
	 */
	function forwardStatus($id, $dbjob = 1)
	{
		$audit = new Audit('FORWARD STATUS');
		$auditStatus = false;

		$backend = $this->getBackend(array('Forward', 'Preload'), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$audit->log(false, $id);
			$this->log->asErr('not authenticated');
			return '';
		}

		if ($dbjob)
		{
			$st = $backend->pacsForward->getJobStatus($id);
			if (!is_null($st))
			{
				if (strlen($st['error']))
				{
					$this->log->asErr($lst['error']);
						/* attempt to catch an initialization error from PacsForward that isn't logged
						   elsewhere. Other errors will be duplicated but that's bearable.
						 */
					return '';
				}
				else
					return $st['status'];
			}
		}

		$dn = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR;
		$id1 = escapeshellcmd($id);
		$fn_st = "status-$id1.fwd";
		$fn_in = "in-$id1.fwd";
		$fn_out = "out-$id1.fwd";

		/* grab the status file and convert its contents to progress indicator */
		if (!file_exists($dn . $fn_st))
		{
			$this->log->asErr("'$fn_st' missing");
			$audit->log(false, $id);
			return '';
		}
		$fc = trim(file_get_contents($dn . $fn_st));
		if (!strlen($fc))
		{
			$this->log->asErr("forwarding failed, see '$fn_out'");
			$audit->log(false, $id);
			return '';
		}
		$progress = explode(' ', $fc);
		if (count($progress) != 2)
		{
			$this->log->asErr("contents of '$fn_st' invalid: '$fc' => " .
				var_export($progress, true));
			$audit->log(false, $id);
			return '';
		}
		$from = (int) $progress[0];
		$to = (int) $progress[1];
		if (!$to)
			$perc = 100;
		else
			$perc = floor($from * 100.0 / $to);

		/* build a progress indicator in a text form */
		if ($perc >= 100)
		{
			/* number of lines in $fn_out that refer to succeeded sessions,
			   must not be less than $to
			 */
			$fp = @fopen($dn . $fn_out, 'r');
			if (!$fp)
			{
				$this->log->asErr("failed to open '$fn_out'");
				$status = '';
			}
			else
			{
				$found = 0;
				while (!feof($fp))
				{
					$ln = fgets($fp);
					if (strpos($ln, 'Sent 1 objects (=') !== false)
						$found++;
				}
				fclose($fp);

				if ($found >= $to)
				{
					$this->log->asInfo("forwarded $found/$to file(s)");
					$auditStatus = true;
					$status = 'success';

					/* let's remove files for PACSes in which they are not immediately available
					   and temporary in nature. It's safe as other PACSes simply do not implement
					   removeFetchedFile().
					 */
					$content = file_get_contents($dn . $fn_in);
					if ($content === false)
						$this->log->asErr("failed to read the job file '$fn_in'");
					else
					{
						$paths = explode("\n", $content);
						foreach ($paths as $rawPath)
						{
							$cleanPath = trim($rawPath);
							if (strlen($cleanPath))
								$backend->pacsPreload->removeFetchedFile($cleanPath);
						}
					}

					try		/* sometimes we have exceptions instead of E_WARNING */
					{
						@unlink($dn . $fn_st);
						@unlink($dn . $fn_in);
						@unlink($dn . $fn_out);
					}
					catch (Exception $e)
					{
					}
				}
				else
				{
					$this->log->asErr("forwarded $found file(s) instead of $to");
					$status = 'failed';
				}
			}
		}
		else
		{
			$status = ((string) $perc) . ' %';
			$auditStatus = $status;
		}

		$audit->log($auditStatus, $id);
		return $status;
	}


	/** @brief Wrapper for Pacs\ForwardIface::collectDestinationAes(). */
	function getForwardAEList()
	{
		$return = array();
		$return['count'] = 0;
		$return['error'] = 'reconnect';

		$backend = $this->getBackend(array('Forward'), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		$lst = $backend->pacsForward->collectDestinationAes();
		if (strlen($lst['error']))
			$return['error'] = 'getForwardAEList() failed, see logs';
		else
			$return = $lst;

		return $return;
	}


	/** @brief Wrapper for Pacs\StructureIface::studyHasReport(). */
	function getNotes($studyUID)
	{
		$backend = $this->getBackend(array('Structure'));
		return $backend->pacsStructure->studyHasReport($studyUID);
	}


	/** @brief Return a cleaned-up tag value.

		@param DicomTags $tagClass  Instance of DicomTags
		@param Backend   $backend   Instance of Backend
		@param string    $charSet   Character set to encode from
		@param array     $tag       A single tag from, for example, DicomTags::getTag()
	 */
	private function getTagData($tagClass, $backend, $charSet, $tag)
	{
		$data = '';
		if (isset($tag['data']) && !is_null($tag['data']))
		{
			if (is_array($tag['data']))
				$data = implode('/', $tag['data']);
			else
				$data = $tag['data'];
			if (!empty($tag['vr']))
				$data = $tagClass->formatTagValue($tag['vr'], $data);
			$data = $backend->cs->encodeWithCharset($charSet, $data);
		}
		return $data;
	}


	/** @brief Build a value for a single InfoLabels element.

		@param Backend   $backend   Instance of Backend
		@param DicomTags $tagClass  Instance of DicomTags
		@param array     $item      Specification string of the InfoLabels element
		@param array     $tags      All tags from DicomTags::getTagsList() etc
		@param string    $charSet   Character set to decode from
	 */
	private function getLabelFromTags($backend, $tagClass, $item, $tags, $charSet)
	{
		$label = '';
		if (!empty($item['items']))
		{
			foreach ($item['items'] as $labelTag)
			{
				switch ($labelTag['tag'])
				{
					case '(0008,0104)':
						/* customization for some old customer:

							extract a few entries from under (0008,2218) Anatomic Region Sequence and
							(0008,2218) Primary Anatomic Region Sequence, then combine into a multiline
							string
						*/
						$tagsTmp1 = $tagClass->getSequence($tags, 8, 8728);
						$tagsTmp2 = $tagClass->getSequence($tags, 8, 8744);
						$tagsTmp = array($tagsTmp1, $tagsTmp2);
						unset($tagsTmp1);
						unset($tagsTmp2);

						$lines = array();
						foreach ($tagsTmp as $sequence)
						{
							$tagsTmp1 = $tagClass->getTagItems($sequence, 8, 256);
							$tagsTmp2 = $tagClass->getTagItems($sequence, 8, 258);
							$tagsTmp3 = $tagClass->getTagItems($sequence, 8, 260);
							$count = count($tagsTmp3);

							for ($i = 0; $i < $count; $i++)
							{
								$values = array();
								$values[] = $this->getTagData($tagClass, $backend, $charSet, $tagsTmp1[$i]);
								$values[] = $this->getTagData($tagClass, $backend, $charSet, $tagsTmp2[$i]);
								$values[] = $this->getTagData($tagClass, $backend, $charSet, $tagsTmp3[$i]);
								$lines[] = implode(' ', $values);
								unset($values);
							}
						}
						$data = implode("\n", $lines);
						unset($lines);
						unset($tagsTmp);
						break;

					default:
						$tag = $tagClass->getTag($tags, $labelTag['group'], $labelTag['element']);
						$data = $this->getTagData($tagClass, $backend, $charSet, $tag);
						break;
				}

				$item['label'] = str_replace($labelTag['tag'], $data, $item['label']);
				unset($data);
			}
		}
		if(!empty($item['label']))
			$label = $item['label'];
		return $label;
	}


	/** @brief Build the entire data array for the InfoLabels function.

		@param string $instanceUid  Value of primary key in the images table (__not necessarily
		                            a SOP Instance UID__)
	 */
	public function getInfoLabels($instanceUid)
	{
		$return = array('error' => '',
			'labels' => array(),
			'instanceUid' => $instanceUid);

		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$backend = $this->getBackend(array('Structure', 'Preload'));	/* Preload: for DicomTags */
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);
			return $return;
		}
		$tagClass = new DicomTags($backend);
		$tags = $tagClass->getTagsList($instanceUid);
		if ($tags['error'] != '')
		{
			$return['error'] = $tags['error'];
			return $return;
		}

		$system = new System($backend);
		$labels = $system->getLabelSettings();
		unset($system);
		if ($labels['error'] != '')
		{
			$return['error'] = $labels['error'];
			return $return;
		}

		if (!empty($labels['labels']) && !empty($tags['tags']))
		{
			$tag = $tagClass->getTag($tags['tags'], 8, 5);
			$charSet = '';
			if (isset($tag['data']) && !is_null($tag['data']))
				$charSet = $tag['data'];

			$left = array();
			if (!empty($labels['labels']['left']))
				foreach ($labels['labels']['left'] as $item)
					$left[] = $this->getLabelFromTags($backend, $tagClass, $item, $tags['tags'], $charSet);
			if (!empty($left))
				$return['labels']['left'] = $left;

			$right = array();
			if (!empty($labels['labels']['right']))
				foreach ($labels['labels']['right'] as $item)
					$right[] = $this->getLabelFromTags($backend, $tagClass, $item, $tags['tags'], $charSet);
						if (!empty($right))
				$return['labels']['right'] = $right;

			unset($left);
			unset($right);
		}
		unset($labels);
		unset($tags);
		unset($tagClass);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
