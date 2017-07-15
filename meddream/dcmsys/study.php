<?php
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;

class DcmsysStudy
{
	private $db_host;
	private $db_user;
	private $log;
	private $root_dir;
	private $storageSinglePrm;
	private $storageMultiPrm;


	public function __construct($host, $user, Logging $log, $rootdir)
	{
		$this->db_host = $host;
		$this->db_user = $user;
		$this->log = $log;
		$this->root_dir = $rootdir;
		if (isset($_REQUEST['storage']))
		{
			$this->storageSinglePrm = '?storage=' . $_REQUEST['storage'].'&dataset=meddream';
			$this->storageMultiPrm = '&storage=' . $_REQUEST['storage'].'&dataset=meddream';
		}
		else
		{
			$this->storageSinglePrm = '?dataset=meddream';
			$this->storageMultiPrm = '?dataset=meddream';
		}
	}


	public function getStudyMetadata($studyUid)
	{
		$rsp = $this->wadors_get_study_data($studyUid);
		$wadoRsStudyMetaData = json_decode($rsp, true);
		if (isset($tmp['error']))
			return $rsp;
		unset($rsp);

		$study = array();

		//add series data to study
		foreach ($wadoRsStudyMetaData as $instance)
		{
			$tmpSeries['id'] = $this->std_get_tag('0020000E', $instance, false, true);

			if (is_null($tmpSeries['id']))
			{
				$studyId = $this->std_get_tag('0020000D', $instance);
				$this->log->asWarn("(dcmsys/study.php) ignoring series of $studyId due to missing SOP Instance UID in: " .
					var_export($instance, true));
				continue;
			}

			$exist = false;
			foreach ($study as $series)
			{
				if ($tmpSeries['id'] === $series['id'])
				{
					$exist = true;
					break;
				}
			}
			if (!$exist)
			{
				$tmpSeries['id'] = $this->std_get_tag('0020000E', $instance, false, true);
				$tmpSeries['description'] = $this->std_get_tag('0008103E', $instance);
				$tmpSeries['modality'] = $this->std_get_tag('00080060', $instance);
				$tmpSeries['studyId'] = $this->std_get_tag('0020000D', $instance);
				$tmpSeries['no'] = intval($this->std_get_tag('00200011', $instance)); //SeriesNumber
				$tmpSeries['count'] = 0;
				if ($tmpSeries['modality'] != 'PR' && $tmpSeries['modality']  != 'KO')
				{
					array_push($study, $tmpSeries);
				}
				unset($tmpSeries);
			}
		}
		$study = $this->records_sort($study, 'no');


		//add instances data series
		for ($i = 0, $seriesCount = count($study); $i < $seriesCount; $i++)
		{
			$tmpSeries = array();
			foreach ($wadoRsStudyMetaData as $instance)
			{
				$seriesId = $this->std_get_tag('0020000E', $instance, false, true);
				if ($seriesId === $study[$i]['id'])
				{
					$tmpInstance = array();

					$tmpInstance['id'] = $this->std_get_tag('00080018', $instance);
					if (is_null($tmpInstance['id']))
					{
						$this->log->asWarn("(dcmsys/study.php) ignoring object of $seriesId due to missing SOP Instance UID in: " .
							var_export($instance, true));
						continue;
					}

					$tmpInstance['bitsStored'] = intval($this->std_get_tag('00280101', $instance));
					$nf = $this->std_get_tag('00280008', $instance);
					$tmpInstance['numFrames'] = $nf == '' ? 1 : intval($nf);
					$tmpInstance['path'] = '';
					$tmpInstance['no'] = intval($this->std_get_tag('00200013', $instance)); //InstanceNumber

					$tmpInstance['patientName'] = $this->std_get_tag('00100010', $instance, true);
					$tmpInstance['patientId'] = $this->std_get_tag('00100020', $instance);
					$tmpInstance['studyDate'] = $this->std_date_from_dicom($this->std_get_tag('00080020', $instance));
					$tmpInstance['studyTime'] = $this->std_time_from_dicom($this->std_get_tag('00080030', $instance));
					$tmpInstance['studyId'] = $this->std_get_tag('0020000D', $instance);
					$tmpInstance['rows'] = intval($this->std_get_tag('00280010', $instance));
					$tmpInstance['columns'] = intval($this->std_get_tag('00280011', $instance));

					$tmpInstance['minPixelValue'] = 0;
					$tmpInstance['maxPixelValue'] = 256; //todo kaip gauti tikra reiksme?
					$tmpInstance['slope'] = 1;
					$tmpInstance['intercept'] = 1;
					$wc = $this->std_get_tag('00281050', $instance);
					if (is_array($wc))
						$wc = $wc[0];
					$tmpInstance['windowCenter'] = intval($wc);
					$wl = $this->std_get_tag('00281051', $instance);
					if (is_array($wl))
						$wl = $wl[0];
					$tmpInstance['windowWidth'] = intval($wl);
					$ps = $this->std_get_tag('00280030', $instance);
					if (is_array($ps))
					{
						$tmpInstance['pixelSpacingX'] = floatval($ps[1]);
						$tmpInstance['pixelSpacingY'] = floatval($ps[0]);
						$tmpInstance['pixelSpacing'] = array(floatval($ps[0]), floatval($ps[1]));
					}
					else
					{
						$tmpInstance['pixelSpacingX'] = 0.0;
						$tmpInstance['pixelSpacingY'] = 0.0;
						$tmpInstance['pixelSpacing'] = array(0.0, 0.0);
					}
					$tmpInstance['xferSyntax'] = $this->std_get_tag('00020010', $instance);
					$tmpInstance['bitsAllocated'] = intval($this->std_get_tag('00280100', $instance));
					$tmpInstance['frameTime'] = floatval($this->std_get_tag('00181063', $instance));
					$tmpInstance['sliceThickness'] = 1; //todo find tag number
					$io = $this->std_get_tag('00200037', $instance);
					if (is_array($io))
						$tmpInstance['imageOrientation'] = array_map('floatval', $io);
					else
						$tmpInstance['imageOrientation'] = '';
					$ip = $this->std_get_tag('00200032', $instance);
					if (is_array($ip))
						$tmpInstance['imagePosition'] = array_map('floatval', $ip);
					else
						$tmpInstance['imagePosition'] = '';

					$tmpInstance['load'] = true;
					$tmpInstance['vpMode'] = $tmpInstance['numFrames'] == 1 ? 1 : 2;
					array_push($tmpSeries/*$study[$i]*/, $tmpInstance);
					$study[$i]['count']++;
					unset($tmpInstance);
				}
			}
			$tmpSeries = $this->records_sort($tmpSeries, 'no');
			for ($j = 0, $instancesCount = count($tmpSeries); $j < $instancesCount; $j++)
			{
				array_push($study[$i], $tmpSeries[$j]);
			}
			unset($tmpSeries);

		}

		$study['count'] = count($study);
		$study['error'] = '';
		$study['firstName'] = '';
		$study['lastName'] = $this->std_get_tag('00100010', $wadoRsStudyMetaData[0], true);
		$study['notes'] = 2;
		$study['patientId'] = $this->std_get_tag('00100020', $wadoRsStudyMetaData[0]);
		$study['sourceAE'] = '';
		$study['studyDate'] = $this->std_date_from_dicom($this->std_get_tag('00080020', $wadoRsStudyMetaData[0]));
		$study['studyTime'] = $this->std_time_from_dicom($this->std_get_tag('00080030', $wadoRsStudyMetaData[0]));
		$study['uid'] = $this->std_get_tag('0020000D', $wadoRsStudyMetaData[0]);
		unset($wadoRsStudyMetaData);

		$this->log->asDump('(dcmsys/study.php) $study = ',  $study);
		return json_encode($study);
	}


	private function records_sort($records, $field)
	{
		$n = count($records);
		for ($k = 0; $k < $n - 1; $k++)
		{
			for ($i = 0; $i < $n - $k - 1; $i++)
			{
				if ($records[$i][$field] > $records[$i + 1][$field])
				{
					$temp = $records[$i];
					$records[$i] = $records[$i + 1];
					$records[$i + 1] = $temp;
				}
			}
		}
		return $records;
	}



	private function qidors_get_study_data($studyUID)
	{
		$url = $this->db_host . "router/qido-rs/studies?StudyInstanceUID=$studyUID" . $this->storageMultiPrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function qidors_list_study_series($studyUID)
	{
		$url = $this->db_host . "router/qido-rs/studies/$studyUID/series" . $this->storageSinglePrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function qidors_list_series_objects($studyUID, $seriesUID)
	{
		$url = $this->db_host . "router/qido-rs/studies/$studyUID/series/$seriesUID/instances" . $this->storageSinglePrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function wadors_get_study_data($studyUID)
	{
		$url = $this->db_host . "router/wado-rs/studies/$studyUID/metadata" . $this->storageSinglePrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function wadors_get_series_data($studyUid, $seriesUID)
	{
		$url = $this->db_host . "router/wado-rs/studies/$studyUid/series/$seriesUID/metadata" . $this->storageSinglePrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function wadors_get_object_data($studyUid, $seriesUID, $objectUID)
	{
		$url = $this->db_host . "router/wado-rs/studies/$studyUid/series/$seriesUID/instances/$objectUID/metadata" .
 		   	$this->storageSinglePrm;

		return $this->call_curl($url, __FUNCTION__);
	}


	private function call_curl($url, $caller)
	{
		$tm = microtime(true);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);		/* we don't have a client certificate */
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);			/* we are connecting to a trusted server (localhost) */

		if (isset($_COOKIE['suid']))
			curl_setopt($ch, CURLOPT_COOKIE, 'suid=' . $_COOKIE['suid']);
		else
		{
			$tmpfname =  $this->root_dir . '/log/cookie_' . $this->db_user . '.txt'; //todo padaryti kaip jpeg.php
			curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);   //set cookie to skip site ads
			curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		}

		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		$duration = sprintf('%.3f', microtime(true) - $tm);

		if ($httpcode === 200)
		{
			$msg = "success (in $duration s), data = " . var_export($result, true);
			$this->log->asDump("$caller on $url: $msg");
			return $result;
		}

		$msg = "HTTP request failed (in $duration s): code $httpcode, cURL error '$err'";
		$this->log->asErr("$caller on $url: $msg");
		return json_encode(array('error' => $msg));
	}


	private function std_get_tag($key, $arr, $is_PN_VR = false, $substitute_NULL = false, $def_value = '')
	{
		/* first sub-index */
		if(!is_array($arr))
			return $substitute_NULL ? NULL : $def_value;

		if (!array_key_exists($key, $arr))
			return $substitute_NULL ? NULL : $def_value;
		$arr1 = $arr[$key];
		if (is_null($arr1))		/* not encountered yet but who knows */
			return $substitute_NULL ? NULL : $def_value;

		/* sub-index 'Value' */
		if (!array_key_exists('Value', $arr1))
			return $substitute_NULL ? NULL : $def_value;
		$arr2 = $arr1['Value'];
		if (is_null($arr2))		/* an often-seen size optimization */
			return $substitute_NULL ? NULL : $def_value;

		/* sub-index 0 */
		if (!array_key_exists(0, $arr2))
			return $substitute_NULL ? NULL : $def_value;
		if (count($arr2) == 1)
		{
			$arr3 = $arr2[0];
		}
		else
		{
			$arr3 = $arr2;
		}

		/* in case of non-PN tags, that's all! */
		if (!$is_PN_VR)
			return $arr3;

		/* PN will additionally have a sub-index 'Alphabetic' (or, at the same level,
		   'Phonetic' and 'Ideographic'). Let's combine as much as possible into one
		   string.
		 */
		if (array_key_exists('Alphabetic', $arr3))
			$value = trim(str_replace('^', ' ', $arr3['Alphabetic']));
		else
			$value = '';
		if (array_key_exists('Phonetic', $arr3))
			$value .= ' (' . trim(str_replace('^', ' ', $arr3['Phonetic'])) . ')';
		if (array_key_exists('Ideographic', $arr3))
			$value .= ' (' . trim(str_replace('^', ' ', $arr3['Ideographic'])) . ')';
		return $value;
	}


	/* add date separators to a string of 8 digits (full DICOM-style date) */
	private function std_date_from_dicom($str)
	{
		if (strlen($str) == 8)
		{
			$final = preg_replace("/(\d\d\d\d)(\d\d)(\d\d)/", '$1-$2-$3', $str, -1, $num);
			if ($num === 1)			/* excludes NULL which indicates error */
				return $final;
		}
		return $str;
	}


	/* add time separators to a string of 6+ digits (DICOM-style time with
	   an optional fractional part which will be left intact)
	 */
	private function std_time_from_dicom($str)
	{
		if (strlen($str) >= 6)
		{
			$final = preg_replace("/(\d\d)(\d\d)(\d\d)(.*)/", '$1:$2:$3$4', $str, -1, $num);
			if ($num === 1)			/* excludes NULL which indicates error */
				return $final;
		}
		return $str;
	}
}


if (!strlen(session_id()))
	session_start();

ini_set('memory_limit', '512M');

$root_dir = dirname(__DIR__);
include_once("$root_dir/autoload.php");

$log = new Logging();

$backend = new Backend(array(), false);
$authDB = $backend->authDB;
if (!$authDB->isAuthenticated())
{
	$err = 'not authenticated';
	$log->asErr($err);
	return array('error' => $err);
}

$uid = $_REQUEST['studyUID'];

$st = new DcmsysStudy($authDB->dbHost, $authDB->getAuthUser(), $log, $root_dir);
$result = $st->getStudyMetadata($uid);
echo $result;
