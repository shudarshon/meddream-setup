<?php

namespace Softneta\MedDream\Core\QueryRetrieve;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\CharacterSet;


/** @brief Includes blocking functions only (DCM4CHEE2 Toolkit).

	Data is transferred via standard output from the DcmQr tool.
 */
class QrToolkit extends QrAbstract
{
	/** @brief Directory where the DcmQR shell wrapper exists, with trailing directory separator */
	protected $toolDir;

	/** @brief Full path to the DcmQR shell wrapper */
	protected $toolPath;

	/** @brief Name of "null" device to which standard output is redirected */
	protected $nullDevice;

	protected $cache;           /**< @brief An instance of QrCache */


	/** @brief Provide values for $toolDir, $toolPath, $nullDevice. */
	protected function configureTool($opSys)
	{
		$dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dcm4che' . DIRECTORY_SEPARATOR .
			'bin' . DIRECTORY_SEPARATOR;

		/* deal with embedded spaces */
		$exe = $dir;
		if (strpos($exe, ' '))		/* int(0) is not FALSE but we'll ignore spaces at beginning, too */
		{
			if ($exe[0] != '"')
				$exe = '"' . $exe;
			if ($exe[strlen($exe) - 1] == '"')
				$exe = substr($exe, 0, strlen($exe) - 1);

			$suff = '"';
		}
		else
			$suff = '';

		/* even executable names differ sometimes */
		if ($opSys == 'WINNT')
		{
			$exe = 'echo . && ' . $exe . 'dcmqr.bat';
				/* bug #40988 (before PHP 5.3.0): the underlying system() can't execute
				   a command with more than 2 double quotes; an additional harmless
				   command apparently helps
				 */
			$dev = 'NUL';
		}
		else
		{
			$exe .= 'dcmqr';
			$dev = '/dev/null';
		}
		$exe .= $suff;

		return array($dir, $exe, $dev);
	}


	/** @brief Assign some fields of @p $src to a certain subarray of @p $dst

		Various $src's must be grouped by $src['seriesuid']. Difficulty: the latter
		is a string and the mentioned subarrays must be indexed by number. For this
		mapping we'll use an externally initialized $keys_uniq.
	 */
	protected function structuredAssign(&$dst, $src, &$keys_uniq)
	{
		/* (0008,0052) must be 'IMAGE'. The PACS might fall back to SERIES just
		   after establishing the association, despite the option -I at dcmqr,
		   and switch to IMAGE for a sub-request. The first response at SERIES
		   level contains only UIDs and this parser becomes a bit confused.
		 */
		if (!empty($src['qlevel']))
		{
			if (strcasecmp($src['qlevel'], 'IMAGE'))
				return;
		}
		else
			/*
			 * Clearcanvas does not send qlevel attribute when its value should
			   be other than 'IMAGE'. Assume that presence of SOP Instance UID
			   indicates the IMAGE level.
			 */
			if (empty($src['imageid']))
				return;

		/* our image identifier shall be combined with series/study IDs

			Many MedDream modules are provided only with image identifier.
			dcm4che_getimage_WADO() actually manages to retrieve an image
			from DCM4CHEE given empty series and study identifiers. But
			other PACSes are not so generous. For example, dcmrcv (the
			engine of the "DICOM" pseudo-PACS) requires all three search
			criteria.

			Of course, those additional IDs are to be discarded where appropriate.
		 */
		$allids = @$src['imageid'] . '*' . $src['seriesid'] . '*' . $src['studyid'];

		/* initialize fields that relate to the study only */
		if (!$dst['count'])
		{
			$dst['uid'] = (string) $src['studyid'];
			$dst['studydate'] = (string) @$src['studydate'];
			$dst['studytime'] = (string) @$src['studytime'];
			$dst['patientid'] = (string) @$src['patientid'];
			$dst['lastname'] = '';
			$dst['firstname'] = (string) @$src['patientname'];
		}

		/* perhaps a new series must be added */
		$i = array_search($src['seriesid'], $keys_uniq);
		if ($i === FALSE)
		{
			$i = $dst['count']++;	/* ready to use because of 0-based indices */
			$keys_uniq[] = $src['seriesid'];
			$dst[$i] = array('count' => 0, 'id' => $src['seriesid'] . '*' . $src['studyid'],
				'description' => (string) @$src['description'], 'modality' => (string) @$src['modality'],
				'seriesno' => (string) @$src['seriesno']);
		}

		/* finally, we simply augment the selected series */
		$dst[$i]['count']++;
		$dst[$i][] = array('id' => $allids, 'numframes' => (string) @$src['numframes'],
			'xfersyntax' => '', 'sopclass' => (string) @$src['sopclass'],
			'bitsstored' => (string) @$src['bitsstored'], 'instanceno' => (string) @$src['instanceno'],
			'acqno' => (string) @$src['acqno'], 'path' => '');
	}


	/** @brief Initializes @link $toolPath @endlink and @link $cache @endlink. */
	public function __construct(Logging $log, CharacterSet $cs, $retrieveEntireStudy,
		$remoteConnectionString, $localConnectionString, $localAet, $wadoAddr)
	{
		parent::__construct($log, $cs, $retrieveEntireStudy, $remoteConnectionString,
			$localConnectionString, $localAet, $wadoAddr);

		list($this->toolDir, $this->toolPath, $this->nullDevice) = $this->configureTool(PHP_OS);
		$this->cache = new QrCache($log);
	}


	public function findStudies($patientId, $patientName, $studyId, $accNum, $studyDesc, $refPhys,
		$dateFrom, $dateTo, $modality)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $patientId, ', ', $patientName, ', ', $studyId,
			', ', $accNum, ', ', $studyDesc, ', ', $refPhys, ', ', $dateFrom, ', ', $dateTo,
			', ', $modality, ')');

		$cs = $this->cs;

		/* build the command line
			in order to retrieve some IDs, they must be requested with -r; but then it
			is an error to use them with -q.
		 */
		$arg = '-L ' . escapeshellarg($this->localAet) . ' ' . escapeshellarg($this->remoteListener) . ' ';
		if ($patientId != '')
			$arg .= '-qPatientID=' . escapeshellarg("*$patientId*") . ' ';
		else
			$arg .= '-r PatientID ';
		if ($patientName != '')
			$arg .= '-qPatientName=' . escapeshellarg("*$patientName*") . ' ';
		else
			$arg .= '-r PatientName ';
		if ($studyId != '')
			$arg .= '-qStudyID=' . escapeshellarg("*$studyId*") . ' ';
		else
			$arg .= '-r StudyID ';
		if ($accNum != '')
			$arg .= '-qAccessionNumber=' . escapeshellarg("*$accNum*") . ' ';
		else
			$arg .= '-r AccessionNumber ';
		if ($studyDesc != '')
			$arg .= '-qStudyDescription=' . escapeshellarg("*$studyDesc*") . ' ';
		else
			$arg .= '-r StudyDescription ';
		if ($refPhys != '')
			$arg .= '-qReferringPhysicianName=' . escapeshellarg("*$refPhys*") . ' ';
		else
			$arg .= '-r ReferringPhysicianName ';
		$datearg = '';
		if ($dateFrom != '')
			$datearg .= '-qStudyDate=' . $dateFrom;
		else
			if ($dateTo != '')
				$datearg .= '-qStudyDate=19011213';		/* minimum of time_t (negative) */
		if ($dateTo != '')
			$datearg .= '-' . $dateTo;
		else
			if ($dateFrom != '')
				$datearg .= '-20380119';				/* maximum of time_t (positive) */
		if (strlen($datearg))
			$arg .= escapeshellarg($datearg) . ' ';
		if ($modality != '')
			$arg .= '-qModalitiesInStudy=' . escapeshellarg($modality) . ' ';
		else
			$arg .= '-r ModalitiesInStudy ';

		/* run the command and parse results */
		session_write_close();		/* against bug #44942 if multiple users are using this function */
		$cmd = $this->toolPath . ' ' . $arg . ' 2>&1';
		$log->asDump("popen('" . $cmd . "', 'r')");
		$out = popen($cmd, 'r');
		if ($out === FALSE)
		{
			$log->asErr("failed to run dcmqr as '$cmd'");
			return array('count' => 0, 'error' => '[WADO/DICOM] popen() failed???');
		}
		$rtns = array('count' => 0, 'error' => '');
		$rsp = array();
		$inside_rsp = 0;
		$first_line_read = 0;
		$errbuf = '';
		$count = 0;
		while (!feof($out))
		{
			$line = trim(fgets($out, 1024));

			/* If the first string begins with "HH:MM:SS,SSS INFO", it indicates
			   that connection was initiated successfully. If it contains "Server
			   listening on port", this is also normal while using -L with hostname
			   or port. Otherwise we may have an error message in similar form,
			   or a free-form error message if dcmqr didn't like the command line.
			   Both must be captured.
			 */
			if ($first_line_read == 2)
			{
				/* capture everything into error buffer */
				$errbuf .= $line . "\n";
				continue;
			}
			else
				if (!$first_line_read)
				{
					if ($line != '.')	/* skip output of that additional harmless command */
					{
						if (preg_match('/^\d+:\d+:\d+.\d+ INFO /', $line) ||
								strpos($line, ' Server listening on port '))
							$first_line_read = 1;
						else
						{
							$errbuf .= $line . "\n";
							$first_line_read = 2;
						}
					}
					continue;
				}

			/* responses begin with a certain string, end with an empty string */
			if (!strlen($line))
			{
				/* time to flush results */
				if (count($rsp))
				{
					$log->asDump('end of infoblock, merging');

					$rsp['patientbirthdate'] = '';
					$rsp['patientsex'] = '';
					$rsp['notes'] = 2;
					$rsp['datetime'] = trim($rsp['date'] . ' ' . $rsp['time']);
					$rsp['reviewed'] = '';
					$rsp['readingphysician'] = '';
					$rsp['sourceae'] = '';
					$rsp['received'] = '';
					$rtns[] = $rsp;
					$count++;
					$rsp = array();
				}
				$inside_rsp = 0;
				continue;
			}
			else
				if (strpos($line, "INFO   - Query Response #"))		/* int(0) impossible to return */
				{
					$inside_rsp = 1;
					continue;
				}
				else
					if (preg_match('/^\d+:\d+:\d+.\d+ ERROR /', $line))	/* catch any error reports */
					{
						$errbuf .= $line . "\n";
						$first_line_read = 2;		/* include remaining text */
						continue;
					}
			if (!$inside_rsp)
				continue;

			/* only DICOM dump is remaining now */
			$log->asDump('$line = ', $line);
			$cnt = 0;
			$attr_value = preg_replace("/\(0008,0020\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['date'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0030\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['time'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0050\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['accessionnum'] = $cs->utf8Encode($attr_value);
				continue;
			}
			$attr_value = preg_replace("/\(0008,0061\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['modality'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0090\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['referringphysician'] = $cs->utf8Encode(trim(str_replace('^', ' ', $attr_value)));
				continue;
			}
			$attr_value = preg_replace("/\(0008,1030\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['description'] = $cs->utf8Encode($attr_value);
				continue;
			}
			$attr_value = preg_replace("/\(0010,0010\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['patientname'] = $cs->utf8Encode(trim(str_replace('^', ' ', $attr_value)));
				continue;
			}
			$attr_value = preg_replace("/\(0010,0020\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['patientid'] = $cs->utf8Encode($attr_value);
				continue;
			}
			$attr_value = preg_replace("/\(0020,000D\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['uid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0010\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['id'] = $cs->utf8Encode($attr_value);
				continue;
			}
				/* anything that follows (unrecognized entries) is ignored */
		}
		pclose($out);
		if (count($rsp))	/* try to flush again, in case that empty string wasn't found somehow */
		{
			$rsp['patientbirthdate'] = '';
			$rsp['notes'] = 2;
			$rsp['datetime'] = trim($rsp['date'] . ' ' . $rsp['time']);
			$rsp['reviewed'] = '';
			$rsp['readingphysician'] = '';
			$rsp['sourceae'] = '';
			$rsp['received'] = '';
			$rtns[] = $rsp;
			$count++;
		}
		$rtns['count'] = $count;

		/* truncate error messages a bit */
		if ($errbuf != '')
		{
			$log->asErr('dcmqr failure: ' . trim($errbuf));
			$rtns['error'] = "[WADO/DICOM] dcmqr failed, see log";
		}

		$log->asDump('$rtns = ', $rtns);
		$log->asDump('end ' . __METHOD__);

		return $rtns;
	}


	/** @brief Implementation of QrBasicIface::seriesGetMetadata().

		@bug We are requesting Bits Allocated instead of Bits Stored (the latter isn't supported
			 anyway by DCM4CHEE 2.x), as otherwise md-html silently refuses to load the image:
			 it requires a supported value of Bits Stored too early (in the study structure).
	 */
	public function seriesGetMetadata($seriesUid)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$parts = explode('*', $seriesUid);

		if ($this->retrieveEntireStudy)
		{
			if (count($parts) != 2)
			{
				$err = 'Series UID has ' . count($parts) . ' component(s)';
				$this->log->asErr($err);
				return array('error' => $err);
			}

			$rtns = $this->cache->seriesGetMetadata($parts[0], $parts[1]);

			$log->asDump('$rtns = ', $rtns);
			$log->asDump('end ' . __METHOD__);
			return $rtns;
		}
		$seriesUid = $parts[0];

		/* build the command line */
		$arg = '-L ' . escapeshellarg($this->localAet) . ' ' . escapeshellarg($this->remoteListener) .
			' -qSeriesInstanceUID=' . escapeshellarg($seriesUid) .
			' -I' .
//			' -r 00080020 -r 00080060 -r 0008103e -r 0020000e -r 00020010 -r 00020012 -r 00020013' .
			' -r 00280008 -r 00280100 -r 00020012 -r 00020013';
/* TODO: in case of HIS integration via Series UID,
   additional attributes (commented out above) are important; however
   then Study UID is also required among keys as otherwise DCM4CHEE 2.x
   returns *all studies* with an incorrect Series UID (set to the key
   value everywhere).

   Normally we don't offer this kind of integration. Consequently this
   function is called only from managePrint.php that is happy with a
   bare bunch of UIDs returned at the moment.
*/

		/* run the command and parse results */
		session_write_close();		/* against bug #44942 if multiple users are using this function */
		$cmd = $this->toolPath . ' ' . $arg . ' 2>&1';
		$log->asDump("popen('" . $cmd . "', 'r')");
		$out = popen($cmd, 'r');
		if ($out === FALSE)
		{
			$log->asErr("failed to run dcmqr as '$cmd'");
			return array('count' => 0, 'error' => '[WADO/DICOM] popen() failed???');
		}
		$rtns = array('count' => 0, 'error' => '');
		$rsp = array();
		$series_uids = array();
		$inside_rsp = 0;
		$first_line_read = 0;
		$errbuf = '';
		$count = 0;
		while (!feof($out))
		{
			$line = trim(fgets($out, 1024));

			/* If the first string begins with "HH:MM:SS,SSS INFO", it indicates
			   that connection was initiated successfully. If it contains "Server
			   listening on port", this is also normal while using -L with hostname
			   or port. Otherwise we may have an error message in similar form,
			   or a free-form error message if dcmqr didn't like the command line.
			   Both must be captured.
			 */
			if ($first_line_read == 2)
			{
				/* capture everything into error buffer */
				$errbuf .= $line . "\n";
				continue;
			}
			else
				if (!$first_line_read)
				{
					if ($line != '.')	/* skip output of that additional harmless command */
					{
						if (preg_match('/^\d+:\d+:\d+.\d+ INFO /', $line) ||
								strpos($line, ' Server listening on port '))
							$first_line_read = 1;
						else
						{
							$errbuf .= $line . "\n";
							$first_line_read = 2;
						}
					}
					continue;
				}

			/* responses begin with a certain string, end with an empty string */
			if (!strlen($line))
			{
				/* time to flush results */
				if (count($rsp))
				{
					$log->asDump('end of infoblock, attempting to merge');
					$rsp['path'] = '';
						/* TODO: need something like structuredAssign() that ensures presence of mandatory
						   elements (they can be empty)
						 */
					$rtns[] = $rsp;
					$count++;
					$rsp = array();
				}
				$inside_rsp = 0;
				continue;
			}
			else
				if (preg_match("/INFO   - Query Response #\d+ for Query Request #/", $line))
				{
					$inside_rsp = 1;
					continue;
				}
				else
					if (preg_match('/^\d+:\d+:\d+.\d+ ERROR /', $line))	/* catch any error reports */
					{
						$errbuf .= $line . "\n";
						$first_line_read = 2;		/* include remaining text */
						continue;
					}
			if (!$inside_rsp)
				continue;

			/* only DICOM dump is remaining now */
/* TODO: how about query level 'IMAGE' like in studyGetMetadata()? */
			$log->asDump('$line = ', $line);
			$cnt = 0;
			$attr_value = preg_replace("/\(0008,0018\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['imageid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,000D\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['studyid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,000E\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['seriesid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0012\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['acqno'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0013\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['instanceno'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0028,0100\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['bitsstored'] = (int) $attr_value;
				continue;
			}
				/* anything that follows (unrecognized entries) is ignored */
		}
		pclose($out);
		if (count($rsp))	/* try to flush again, in case that empty string wasn't found somehow */
		{
			$rsp['path'] = '';
			$rtns[] = $rsp;
			$count++;
		}
		$rtns['count'] = $count;

		/* add values that are unsupported but still expected */
		$rtns['firstname'] = '';
		$rtns['lastname'] = '';
		$rtns['fullname'] = '';
		$rtns['notes'] = 2;
		$rtns['sourceae'] = '';

		if ($errbuf != '')
		{
			/* truncate error messages a bit */
			$rtns['error'] = "[WADO/DICOM] dcmqr failed, see log";
			$log->asErr("dcmqr failure: '" . trim($errbuf) . "'");
		}
		else
			$this->sortSeries($rtns);

		$log->asDump('$rtns = ', $rtns);
		$log->asDump('end ' . __METHOD__);
		return $rtns;
	}


	/** @brief Implementation of QrBasicIface::studyGetMetadata().

		@bug We are requesting Bits Allocated instead of Bits Stored (the latter isn't supported
			 anyway by DCM4CHEE 2.x), as otherwise md-html silently refuses to load the image:
			 it requires a supported value of Bits Stored too early (in the study structure).
	 */
	public function studyGetMetadata($studyUid, $fromCache = false)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $fromCache, ')');

		if ($fromCache)
		{
			$rtns = $this->cache->studyGetMetadata($studyUid);

			$log->asDump('$rtns = ', $rtns);
			$log->asDump('end ' . __METHOD__);
			return $rtns;
		}

		$cs = $this->cs;

		/* build the command line */
		$arg = '-L ' . escapeshellarg($this->localAet) . ' ' . escapeshellarg($this->remoteListener) .
			' -qStudyInstanceUID=' . escapeshellarg($studyUid) .
			' -I -r PatientID -r PatientName -r 00080016 -r 00080020 -r 00080030 -r 00080060 -r 0008103e -r 00020010' .
			' -r 0020000e -r 00200011 -r 00200012 -r 00200013 -r 00280008 -r 00280100';

		/* run the command and parse results */
		session_write_close();		/* against bug #44942 if multiple users are using this function */
		$cmd = $this->toolPath . ' ' . $arg . ' 2>&1';
		$log->asDump("popen('$cmd', 'r')");
		$out = popen($cmd, 'r');
		if ($out === FALSE)
		{
			$log->asErr("failed to run dcmqr as '$cmd'");
			return array('count' => 0, 'error' => '[WADO/DICOM] popen() failed???');
		}
		$rtns = array('count' => 0, 'error' => '');
		$rsp = array();
		$series_uids = array();
		$inside_rsp = 0;
		$first_line_read = 0;
		$errbuf = '';
		while (!feof($out))
		{
			$line = trim(fgets($out, 1024));

			/* If the first string begins with "HH:MM:SS,SSS INFO", it indicates
			   that connection was initiated successfully. If it contains "Server
			   listening on port", this is also normal while using -L with hostname
			   or port. Otherwise we may have an error message in similar form,
			   or a free-form error message if dcmqr didn't like the command line.
			   Both must be captured.
			 */
			if ($first_line_read == 2)
			{
				/* capture everything into error buffer */
				$errbuf .= $line . "\n";
				continue;
			}
			else
				if (!$first_line_read)
				{
					if ($line != '.')	/* skip output of that additional harmless command */
					{
						if (preg_match('/^\d+:\d+:\d+.\d+ INFO /', $line) ||
								strpos($line, ' Server listening on port '))
							$first_line_read = 1;
						else
						{
							$errbuf .= $line . "\n";
							$first_line_read = 2;
						}
					}
					continue;
				}

			/* responses begin with a certain string, end with an empty string */
			if (!strlen($line))
			{
				/* time to flush results */
				if (count($rsp))
				{
					$log->asDump('end of infoblock, attempting to merge');
					$this->structuredAssign($rtns, $rsp, $series_uids);
					$rsp = array();
				}
				$inside_rsp = 0;
				continue;
			}
			else
				if (preg_match("/INFO   - Query Response #\d+/", $line))
				{
					$inside_rsp = 1;
					continue;
				}
				else
					if (preg_match('/^\d+:\d+:\d+.\d+ ERROR /', $line))	/* catch any error reports */
					{
						$errbuf .= $line . "\n";
						$first_line_read = 2;		/* include remaining text */
						continue;
					}
			if (!$inside_rsp)
				continue;

			/* only DICOM dump is remaining now */
			$log->asDump('$line = ', $line);
			$cnt = 0;
			$attr_value = preg_replace("/\(0008,0016\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['sopclass'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0018\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['imageid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0020\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['studydate'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0030\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['studytime'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0052\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['qlevel'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,0060\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['modality'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0008,103E\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['description'] = $cs->utf8Encode($attr_value);
				continue;
			}
			$attr_value = preg_replace("/\(0010,0010\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['patientname'] = $cs->utf8Encode(trim(str_replace('^', ' ', $attr_value)));
				continue;
			}
			$attr_value = preg_replace("/\(0010,0020\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['patientid'] = $cs->utf8Encode($attr_value);
				continue;
			}
			$attr_value = preg_replace("/\(0020,000D\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['studyid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,000E\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['seriesid'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0011\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['seriesno'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0012\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['acqno'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0020,0013\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['instanceno'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0028,0008\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['numframes'] = $attr_value;
				continue;
			}
			$attr_value = preg_replace("/\(0028,0100\) .* \#\d+ \[(.*)\].*/", "$1", $line, -1, $cnt);
			if ($cnt)
			{
				$rsp['bitsstored'] = $attr_value;
				continue;
			}
				/* anything that follows (unrecognized entries) is ignored */
		}
		if (count($rsp))	/* try to flush again, in case that empty string wasn't found somehow */
			$this->structuredAssign($rtns, $rsp, $series_uids);
		pclose($out);

		/* add values that are unsupported but still expected */
		$rtns['notes'] = 2;
		$rtns['sourceae'] = '';

		if ($errbuf != '')
		{
			/* truncate error messages a bit */
			$rtns['error'] = "[WADO/DICOM] dcmqr failed, see log";
			$log->asErr("dcmqr failure: '" . trim($errbuf) . "'");
		}
		else
			$this->sortStudy($rtns);
		if (!$rtns['count'] && !$rtns['error'])
			$rtns['error'] = 'Study is missing or empty';

		$log->asDump('$rtns = ', $rtns);
		$log->asDump('end ' . __METHOD__);
		return $rtns;
	}


	function fetchImage($imageUid, $seriesUid, $studyUid)
	{
		set_time_limit(0);
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $imageUid, ', ', $seriesUid, ', ',
			$studyUid, ')');

		$rtn = array('path' => '', 'error' => '');

		/* support for RetrieveEntireStudy introduces a different cache */
		if ($this->retrieveEntireStudy)
		{
			$rtn = $this->cache->getObjectPath($imageUid, $seriesUid, $studyUid);

			if (!strlen($rtn['error']))
			{
				$path = $rtn['path'];
				if (@!file_exists($path))
					$rtn['error'] = "cache miss: '$path'";
			}

			$log->asDump('$rtn = ', $rtn);
			$log->asDump('end ' . __METHOD__);
			return $rtn;
		}

		/* perhaps already in the cache? */
		$r = meddream_dcmrcv_find($imageUid, $this->toolDir, $this->localListener, dirname(__DIR__));
		if (!empty($r) && ($r[0] != '*'))
		{
			$log->asInfo("already cached: '$imageUid'");
			$rtn['path'] = substr($r, 1);

			$log->asDump('$rtn = ', $rtn);
			$log->asDump('end ' . __METHOD__);
			return $rtn;
		}

		/* build the command line */
		$dra = explode('@', $this->localListener);
		$arg = '-L ' . escapeshellarg($this->localAet) . ' ' . escapeshellarg($this->remoteListener) .
			' -qSOPInstanceUID=' . escapeshellarg($imageUid) .
			' -qSeriesInstanceUID=' . escapeshellarg($seriesUid) .
			' -qStudyInstanceUID=' . escapeshellarg($studyUid) .
			' -I -cmove ' . $dra[0];

		/* run the command and parse results */
		session_write_close();		/* against bug #44942 if multiple users are using this function */
		$cmd = $this->toolPath . ' ' . $arg . ' 2>&1';
		$log->asDump("popen('" . $cmd . "', 'r')");
		$out = popen($cmd, 'r');
		if ($out === FALSE)
		{
			$log->asErr("failed to run dcmqr as '$cmd'");
			return array('path' => '', 'error' => '[DICOM] popen() failed???');
		}
		$parser_mode = 0;
			/* 0	skip lines that consist of a single ".", detect whether
					dcmqr has started successfully
			   1	there was an error; collect all further lines without parsing
			   2	searching for results of C-FIND
			   3	searching for results of C-MOVE
			 */
		$errbuf = '';
		while (!feof($out))
		{
			$line = trim(fgets($out, 1024));

			/* If the first string begins with "HH:MM:SS,SSS INFO", it indicates
			   that connection was initiated successfully. However we'll capture
			   all output for later diagnostic.

			   Otherwise we may have an error message in similar form, or a
			   free-form error message if dcmqr didn't like the command line.
			   This does not require any further parsing.
			 */
			if ($parser_mode)
			{
				/* capture everything into error buffer */
				$errbuf .= $line . "\n";
				if ($parser_mode == 1)
					continue;
			}
			else
			{
				if ($line != '.')	/* skip output of that additional harmless command */
				{
					if (preg_match('/^\d+:\d+:\d+.\d+ INFO /', $line))
						$parser_mode = 2;
					else
						$parser_mode = 1;
				}
				continue;
			}

			/* this time we'll catch errors and a couple of result strings */
			if (preg_match('/^\d+:\d+:\d+.\d+ ERROR /', $line))		/* error reports */
			{
				/* finish collecting output in $errbuf */
				$parser_mode = 1;
				continue;
			}
			$cnt = 0;
			if ($parser_mode == 2)
			{
				/* did the C-FIND command succeed? */
				$parsed = preg_replace("/^\d+:\d+:\d+.\d+ INFO   - Received (\d+) matching .*/", "$1", $line, -1, $cnt);
				if ($cnt)
				{
					/* do not continue if the object wasn't found */
					if ($parsed == '0')
					{
						$rtn['error'] = '[DICOM] object not found/1';
						$log->asErr("object not found: '$imageUid'");
						$log->asDump('dcmqr reports: ', trim($errbuf));
						return $rtn;
					}
					else
					{
						/* will check for a different command from now on */
						$errbuf = '';
						$parser_mode = 3;
					}
				}
			}
			else
				if ($parser_mode == 3)
				{
					/* did the C-MOVE command succeed? */
					$parsed = preg_replace("/^\d+:\d+:\d+.\d+ INFO   - Retrieved (\d+) objects .*/", "$1", $line, -1, $cnt);
					if ($cnt)
					{
						/* do not continue if the object wasn't retrieved */
						if ($parsed == '0')
						{
							$rtn['error'] = '[DICOM] dcmqr failed, see log';
							$log->asErr("object wasn't retrieved: '$imageUid'");
							$log->asDump('dcmqr reports: ', trim($errbuf));
							return $rtn;
						}
						else
						{
							$errbuf = '';
							break;		/* further output is irrelevant */
						}
					}
				}
		}
		pclose($out);

		/* a couple of `return` statements above should ensure that here we have
		   a successfully retrieved DICOM object; but what if the corresponding
		   strings weren't recognized?
		 */
		if ($errbuf != '')
		{
			$rtn['error'] = "[DICOM] dcmqr failed, see log";
			$log->asErr("unrecognized dcmqr output($parser_mode): '" . trim($errbuf) . "'");
			return $rtn;
		}

		/* search for the newly received object in cache

				dcmrcv should have already received the file as dcmqr reports
				status of a finished transaction. Still, our monitor thread
				might get too little CPU time; that's the only reason for retries.
		 */
		for ($i = 0; $i < 300; $i++)
		{
			$r = meddream_dcmrcv_find($imageUid, $this->toolDir, $this->localListener, dirname(__DIR__));
			if (!empty($r))
				break;
			usleep(100000);
		}

		/* we can simply fail, or get an error message */
		if (empty($r))
		{
			$rtn['error'] = '[DICOM] object not found/2';
			$log->asErr("object not in cache: '$imageUid'");
			return $rtn;
		}
		if ($r[0] == '*')
		{
			$rtn['error'] = '[DICOM] error from cache, see log';
			$log->asErr("cache reports: '" . substr($r, 1) . "'");
			return $rtn;
		}
		$rtn['path'] = substr($r, 1);

		$log->asDump('$rtn = ', $rtn);
		$log->asDump('end ' . __METHOD__);

		return $rtn;
	}


	function fetchImageWado($imageUid, $seriesUid, $studyUid)
	{
		set_time_limit(0);
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $imageUid, ', ', $seriesUid, ', ',
			$studyUid, ')');

		$rtn = array('path' => '', 'error' => '');

		/* temporary file is needed; because tempnam() has issues, let's do our own */
		$tries = 10;
		do
		{
			if (!(--$tries))
				break;
			$ofn = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR .
				date('YmdHis') . sprintf('%07u', mt_rand(0, 9999999)) . '.tmp.dcm';
			$ofh = @fopen($ofn, 'x');		/* fails on existing files! */
			} while ($ofh === FALSE);
		if ($tries < 1)
		{
			$rtn['error'] = "still not unique: '$ofn'";
			$log->asErr($rtn['error']);
			return $rtn;
		}

		/* open the remote resource */
		$ifn = $this->wadoAddr . '?requestType=WADO&studyUID=' . urlencode($studyUid) .
			'&seriesUID=' . urlencode($seriesUid) . '&objectUID=' . urlencode($imageUid) .
			'&contentType=application%2Fdicom';
		$log->asInfo("downloading from '$ifn'");
		$ifh = @fopen($ifn, 'r');
		if ($ifh === FALSE)
		{
			fclose($ofh);
			@unlink($ofn);
			$rtn['error'] = "unreadable: '$ifn'";
			$log->asErr($rtn['error']);
			return $rtn;
		}

		/* copy to temporary file */
		$size = 0;
		while (!feof($ifh))
			$size += fwrite($ofh, fread($ifh, 8192));
		fclose($ofh);
		fclose($ifh);
		$rtn['path'] = $ofn;
		$log->asInfo("saved to '$ofn', $size byte(s)");

		$log->asDump('$rtn = ', $rtn);
		$log->asDump('end ' . __METHOD__);
		return $rtn;
	}
}
