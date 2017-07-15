<?php

/** @brief Communication with the PACS Gateway.

	The upcoming Java-based PACS Gateway will gradually replace existing
	PHP-based support for various PACSes.
*/
namespace Softneta\MedDream\Core\PacsGateway;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;


/** @brief Wrapper of Gateway's REST interfaces. */
class PacsGw
{
	protected $hc;          /**< @brief Instance of HttpClient */
	protected $log;         /**< @brief Instance of Logging */
	protected $cs;          /**< @brief Instance of CharacterSet */
	protected $fp;          /**< @brief Instance of ForeignPath */
	protected $authDB;      /**< @brief Instance of AuthDB */


	/** @codeCoverageIgnore */
	public function __construct($baseUrl, Logging $logger, CharacterSet $cs, ForeignPath $fp,
		AuthDB $authDb = null, HttpClient $hc = null)
		/* nullable parameters are for unit tests that won't call dependant methods from here */
	{
		$this->log = $logger;
		$this->cs = $cs;
		$this->fp = $fp;
		$this->authDB = $authDb;

		if (is_null($hc))
		{
			$hc = new HttpClient($baseUrl, $logger);
		}
		$this->hc = $hc;
	}


	/** @brief See StructureIface::instanceGetMetadata(). $fromCache is for $pacs='DICOM'. */
	public function instanceGetMetadata($instanceUid, $includePatient = false, $fromCache = false)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ', ', $fromCache, ')');

		$return = array('error' => '');

		/* grab a result array from the service */
		$url = "instance/metadata?instancePkUid=$instanceUid";
		if ($includePatient)
		{
			$url .= '&includePatient=true';
		}
		if ($fromCache)
		{
			$url .= '&fromCache=true';
		}
		$str = $this->hc->request($url);
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (1), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (2), see logs');
		}

		$return = array('error' => '');

		if (array_key_exists('error', $arr))
		{
			$return['error'] = (string) $arr['error'];
		}

		if (!strlen($return['error']))
		{
			$return['path'] = $this->fp->toLocal((string) $arr['path']);
			$return['xfersyntax'] = (string) $arr['transferSyntax'];
			$return['sopclass'] = (string) $arr['sopClass'];
			$return['bitsstored'] = (string) $arr['bitsStored'];

			if ($includePatient)
			{
				$pat = &$arr['patient'];

				$return['patientid'] = (string) $pat['id'];
				$return['firstname'] = (string) $pat['firstName'];
				$return['lastname'] = (string) $pat['lastName'];
				$return['fullname'] = (string) $pat['fullName'];
			}

			$return['uid'] = (string) $arr['id'];
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::instanceUidToKey(). */
	public function instanceUidToKey($instanceUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$u = trim($instanceUid);
		if ($u == '')
		{
			$return = array('error' => '', 'imagepk' => $u);
			$this->log->asDump('returning: ', $return);
			return $return;
		}

		/* grab a result array from the service */
		$str = $this->hc->request("instance/pk?instanceUid=$instanceUid");
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (3), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (4), see logs');
		}

		$return = array();

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		else
		{
			$return['error'] = '';
		}

		if (!strlen($return['error']))
		{
			if (array_key_exists('imagePk', $arr))
			{
				$return['imagepk'] = $arr['imagePk'];
			}
			else
			{
				$return['imagepk'] = '';
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::instanceKeyToUid(). */
	public function instanceKeyToUid($instanceKey)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceKey, ')');

		$u = trim($instanceKey);
		if ($u == '')
		{
			$return = array('error' => '', 'imageuid' => $u);
			$this->log->asDump('returning: ', $return);
			return $return;
		}

		/* grab a result array from the service */
		$str = $this->hc->request("instance/iuid?fileRefPkInstanceUid=$instanceKey");
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (5), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (6), see logs');
		}

		$return = array();

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		else
		{
			$return['error'] = '';
		}

		if (!strlen($return['error']))
		{
			if (array_key_exists('imageUid', $arr))
			{
				$return['imageuid'] = $arr['imageUid'];
			}
			else
			{
				$return['imageuid'] = '';
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::instanceGetStudy(). */
	public function instanceGetStudy($instanceUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		/* grab a result array from the service */
		$str = $this->hc->request("instance/study/pk?instanceUidFileRefPK=$instanceUid");
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (7), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (8), see logs');
		}

		$return = array();

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		else
		{
			$return['error'] = '';
		}

		if (!strlen($return['error']))
		{
			if (array_key_exists('studyUid', $arr))
			{
				$return['studyuid'] = $arr['studyUid'];
			}
			else
			{
				$return['studyuid'] = '';
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::seriesGetMetadata(). Optional parameters are for $pacs='DICOM'. */
	public function seriesGetMetadata($seriesUid, $forDicom = false, $fromCache = false)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ', ', $fromCache, ')');

		/* grab a result array from the service */
		$url = "series/metadata?seriesPkUid=$seriesUid";
		if ($fromCache)
		{
			$url .= '&fromCache=true';
		}
		$str = $this->hc->request($url);
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (9), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (10), see logs');
		}

		$return = array('error' => '');
		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}

		if (!strlen($return['error']))
		{
			$return['count'] = $arr['count'];

			$pat = &$arr['patient'];
			$return['firstname'] = (string) $pat['firstName'];
			$return['lastname'] = (string) $pat['lastName'];
			$return['fullname'] = (string) $pat['fullName'];

			$instances = &$arr['instances'];
			for ($i = 0; $i < $return['count']; $i++)
			{
				$key = sprintf('image-%06d', $i);

				$return[$key]['path'] = $this->fp->toLocal((string) $instances[$i]['path']);
				$return[$key]['xfersyntax'] = (string) $instances[$i]['transferSyntax'];
				$return[$key]['bitsstored'] = (string) $instances[$i]['bitsStored'];
				if ($forDicom)
				{
					$id = @$instances[$i]['id'];
					if (!is_null($id))
					{
						$ids = explode('*', $id);
						$return[$key]['object'] = $ids[0];
						if (count($ids) > 1)
						{
							$return[$key]['series'] = $ids[1];
						}
						else
						{
							$return[$key]['series'] = '';
						}
						if (count($ids) > 2)
						{
							$return[$key]['study'] = $ids[2];
						}
						else
						{
							$return[$key]['study'] = '';
						}
					}
				}
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::seriesUidToKey(). */
	public function seriesUidToKey($seriesUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$u = trim($seriesUid);
		if ($u == '')
		{
			$return = array('error' => '', 'seriespk' => $u);
			$this->log->asDump('$return = ', $return);
			return $return;
		}

		/* grab a result array from the service */
		$str = $this->hc->request("series/pk?seriesUid=$seriesUid");
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (11), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (12), see logs');
		}

		$return = array();

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		else
		{
			$return['error'] = '';
		}

		if (!strlen($return['error']))
		{
			if (array_key_exists('seriesPk', $arr))
			{
				$return['seriespk'] = $arr['seriesPk'];
			}
			else
			{
				$return['seriespk'] = '';
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::studyGetMetadata(). */
	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		/* grab a result array from the service */
		$url = "study/metadata?uid=$studyUid";
		if ($disableFilter)
			$url .= '&disableFilter=true';
		if ($fromCache)
			$url .= '&fromCache=true';
		$str = $this->hc->request($url);
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (13), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (14), see logs');
		}

		$return = array('error' => '');

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		if (!strlen($return['error']))
		{
			$return['uid'] = (string) $arr['uid'];

			$pat = &$arr['patient'];
			$return['firstname'] = (string) $pat['firstName'];
			$return['lastname'] = (string) $pat['lastName'];
			$return['patientid'] = (string) $pat['id'];

			$return['sourceae'] = (string) $arr['sourceAE'];
			$return['studydate'] = (string) $arr['studyDate'];
			$return['studytime'] = (string) $arr['studyTime'];
			$return['notes'] = $arr['notes'];
			$return['count'] = $arr['count'];

			for ($i = 0; $i < $return['count']; $i++)
			{
				$series = &$arr['series'][$i];

				$return[$i]['id'] = (string) $series['id'];
				$return[$i]['description'] = (string) $series['description'];
				$return[$i]['modality'] = (string) $series['modality'];
				$return[$i]['count'] = $series['count'];

				for ($j = 0; $j < $return[$i]['count']; $j++)
				{
					$instance = &$series['instances'][$j];

					$return[$i][$j]['id'] = (string) $instance['id'];
					$return[$i][$j]['numframes'] = (string) $instance['numFrames'];
					$return[$i][$j]['path'] = $this->fp->toLocal((string) $instance['path']);
					$return[$i][$j]['xfersyntax'] = (string) $instance['transferSyntax'];
					$return[$i][$j]['bitsstored'] = (string) $instance['bitsStored'];
					$return[$i][$j]['sopclass'] = (string) $instance['sopClass'];
				}
			}

			if (!$fromCache && !$return['count'])
				$return['error'] = "No images to display\n(some might have been skipped)";
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::studyGetMetadataBySeries(). */
	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false)
	{
		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUids, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		if (!is_array($seriesUids))
		{
			$return['error'] = 'wrong types of parameters';
			$this->log->asErr($return['error']);
			return $return;
		}
		if (sizeof($seriesUids) == 0)
		{
			$return['error'] = 'mandatory parameters missing';
			$this->log->asErr($return['error']);
			return $return;
		}

		/* grab a result array from the service */
		$url = 'study/metadata?uid=' . join(';', $seriesUids) . '&type=2';
		if ($disableFilter)
			$url .= '&disableFilter=true';
		$str = $this->hc->request($url);
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (15), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (16), see logs');
		}

		$return = array('error' => '');

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		if (!strlen($return['error']))
		{
			$return['uid'] = (string) $arr['uid'];

			$pat = &$arr['patient'];
			$return['firstname'] = $pat['firstName'];
			$return['lastname'] = $pat['lastName'];
			$return['patientid'] = $pat['id'];

			$return['sourceae'] = (string) $arr['sourceAE'];
			$return['studydate'] = $arr['studyDate'];
			$return['studytime'] = $arr['studyTime'];
			$return['notes'] = $arr['notes'];
			$return['count'] = $arr['count'];

			for ($i = 0; $i < $return['count']; $i++)
			{
				$series = &$arr['series'][$i];

				$return[$i]['id'] = (string) $series['id'];
				$return[$i]['description'] = $series['description'];
				$return[$i]['modality'] = $series['modality'];
				$return[$i]['count'] = $series['count'];

				for ($j = 0; $j < $return[$i]['count']; $j++)
				{
					$instance = &$series['instances'][$j];

					$return[$i][$j]['id'] = (string) $instance['id'];
					$return[$i][$j]['numframes'] = $instance['numFrames'];
					$return[$i][$j]['path'] = $this->fp->toLocal($instance['path']);
					$return[$i][$j]['xfersyntax'] = (string) $instance['transferSyntax'];
					$return[$i][$j]['bitsstored'] = $instance['bitsStored'];
					$return[$i][$j]['sopclass'] = $instance['sopClass'];
				}
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::studyGetMetadataByImage(). */
	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $imageUids, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		if (!is_array($imageUids))
		{
			$return['error'] = 'wrong types of parameters';
			$this->log->asErr($return['error']);
			return $return;
		}
		if (sizeof($imageUids) == 0)
		{
			$return['error'] = 'mandatory parameters missing';
			$this->log->asErr($return['error']);
			return $return;
		}

		/* grab a result array from the service */
		$url = 'study/metadata?uid=' . join(';', $imageUids) . '&type=3';
		if ($disableFilter)
			$url .= '&disableFilter=true';
		$str = $this->hc->request($url);
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (17), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (18), see logs');
		}

		$return = array('error' => '');

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		if (!strlen($return['error']))
		{
			$return['uid'] = (string) $arr['uid'];

			$pat = &$arr['patient'];
			$return['firstname'] = $pat['firstName'];
			$return['lastname'] = $pat['lastName'];
			$return['patientid'] = $pat['id'];

			$return['sourceae'] = (string) $arr['sourceAE'];
			$return['studydate'] = $arr['studyDate'];
			$return['studytime'] = $arr['studyTime'];
			$return['notes'] = $arr['notes'];
			$return['count'] = $arr['count'];

			for ($i = 0; $i < $return['count']; $i++)
			{
				$series = &$arr['series'][$i];

				$return[$i]['id'] = (string) $series['id'];
				$return[$i]['description'] = $series['description'];
				$return[$i]['modality'] = $series['modality'];
				$return[$i]['count'] = $series['count'];

				for ($j = 0; $j < $return[$i]['count']; $j++)
				{
					$instance = &$series['instances'][$j];

					$return[$i][$j]['id'] = (string) $instance['id'];
					$return[$i][$j]['numframes'] = $instance['numFrames'];
					$return[$i][$j]['path'] = $this->fp->toLocal($instance['path']);
					$return[$i][$j]['xfersyntax'] = (string) $instance['transferSyntax'];
					$return[$i][$j]['bitsstored'] = $instance['bitsStored'];
					$return[$i][$j]['sopclass'] = $instance['sopClass'];
				}
			}
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See StructureIface::studyListSeries(). */
	public function studyListSeries($studyUid)
	{
		$return = array();
		$return['count'] = 0;
		$return['error'] = '';

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$return['error'] = $err;
			return $return;
		}

		/* grab a result array from the service */
		$str = $this->hc->request("study/series/pks?studyPkUid=$studyUid");
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (19), see logs');
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (20), see logs');
		}

		$return = array();

		if (array_key_exists('error', $arr))
		{
			$return['error'] = $arr['error'];
		}
		else
		{
			$return['error'] = '';
		}

		if (!strlen($return['error']))
		{
			$count = 0;
			$return['count'] = $count;		/* reserve a certain position in the array */
			if (array_key_exists('uids', $arr))
			{
				foreach ($arr['uids'] as $k => $v)
				{
					/* finish in case of non-contiguous numbering */
					if ($k != $count)
					{
						break;
					}

					$return[$count] = (string) $arr['uids'][$count];
					$count++;
				}
			}
			$return['count'] = $count;
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See SearchIface::getStudyCounts(). */
	public function getStudyCounts()
	{
		$this->log->asDump('begin ' . __METHOD__);

		$defaultRsp = array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $defaultRsp;
		}

		$str = $this->hc->request('study/counts');

		if ($str === false)
			return $defaultRsp;
		$return = @json_decode($str, true);
		if (!is_array($return))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return $defaultRsp;
		}

		if (!isset($return['d1']))
			$return['d1'] = 0;
		if (!isset($return['d3']))
			$return['d3'] = 0;
		if (!isset($return['w1']))
			$return['w1'] = 0;
		if (!isset($return['m1']))
			$return['m1'] = 0;
		if (!isset($return['y1']))
			$return['y1'] = 0;
		if (!isset($return['any']))
			$return['any'] = 0;

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See SearchIface::findStudies(). */
	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $actions, ', ', $searchCriteria, ', ',
			$fromDate, ', ', $toDate, ', ', $mod, ', ', $listMax, ')');

		$audit = new Audit('SEARCH');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false);
			return array('error' => 'not authenticated');
		}

		$auditMsg = '';
		$parameters = array("count=$listMax");
		$cs = $this->cs;

		/* convert objects to arrays

			Objects come from Flash since amfPHP 2.0. HTML due to some reason also
			passes a JSON-encoded object instead of an array.
		 */
		if (is_object($actions))
		{
			$actions = get_object_vars($actions);
		}
		for ($i = 0; $i < count($searchCriteria); $i++)
			if (is_object($searchCriteria[$i]))
			{
				$searchCriteria[$i] = get_object_vars($searchCriteria[$i]);
			}

		/* different behavior when searching for a patient via HIS integration */
		$patientFromAction = false;
		$patient_id = '';
		if ($actions && (strtoupper($actions['action'])=="SHOW") &&
				(strtoupper($actions['option']) == "PATIENT") &&
				((int) sizeof((array) $actions['entry']) > 0))
		{
			$patientFromAction = true;
			$patient_id = $cs->utf8Decode($actions['entry'][0]);
			$parameters[] = 'patientId=' . urlencode($patient_id);
			$parameters[] = "exactPatientId=true";
			$auditMsg .= "patientid '$patient_id'";
		}

		/* convert $searchCriteria to separate variables */
		for ($i = 0; $i < count($searchCriteria); $i++)
		{
			$criterionName = strtolower($searchCriteria[$i]['name']);
			$criterionText = trim($cs->utf8Decode($searchCriteria[$i]['text']));

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "$criterionName '$criterionText'";

			switch ($criterionName)
			{
			case 'patientid':
				if ($patientFromAction)
					$this->log->asWarn("Patient ID '$patient_id' already specified, ignoring another one: '$criterionText'");
				else
					$parameters[] = 'patientId=' . urlencode($criterionText);
				break;

			case 'patientname':
				$parameters[] = 'patientName=' . urlencode($criterionText);
				break;

			case 'id':
				$parameters[] = 'studyId=' . urlencode($criterionText);
				break;

			case 'accessionnum':
				$parameters[] = 'accessionNumber=' . urlencode($criterionText);
				break;

			case 'description':
				$parameters[] = 'studyDescription=' . urlencode($criterionText);
				break;

			case 'sourceae':
				$parameters[] = 'srcAET=' . urlencode($criterionText);
				break;

			case 'referringphysician':
				$parameters[] = 'referringPhysicianName=' . urlencode($criterionText);
				break;

			case 'readingphysician':
				$parameters[] = 'readingPhysicianName=' . urlencode($criterionText);
				break;

			default:
				$this->log->asErr("unrecognized search criterion '$criterionName'='$criterionText'");
				$audit->log(false, $auditMsg);
				return array('count' => 0, 'error' => "[GW] Searches by '$criterionName' not supported");
			}
		}

		if (strlen($fromDate))
		{
			$fromDate = str_replace('.', '', $fromDate);	/* delimiters are not needed */

			if (!empty($auditMsg))
			{
				$auditMsg .= ', ';
			}
			$auditMsg .= "from $fromDate";

			$parameters[] = 'dateFrom=' . urlencode($fromDate);
		}

		if (strlen($toDate))
		{
			$toDate = str_replace('.', '', $toDate);	/* delimiters are not needed */

			if (!empty($auditMsg))
			{
				$auditMsg .= ', ';
			}
			$auditMsg .= "to $toDate";

			$parameters[] = 'dateTo=' . urlencode($toDate);
		}

		$modAll = true;
		$modList = array();
		for ($i = 0; $i < count($mod); $i++)
		{
			/* check the 'custom' attribute first so that 'selected' becomes optional */
			if (isset($mod[$i]['custom']) || $mod[$i]['selected'])
			{
				$modList[] = urlencode($mod[$i]['name']);
			}
			else
			{
				$modAll = false;
			}

			if (isset($mod[$i]['custom']))
			{
				$modAll = false;
			}
		}
		$this->log->asDump('$modAll = ', $modAll);
		if (!$modAll && count($modList))
		{
			if (strlen($auditMsg))
			{
				$auditMsg .= ', ';
			}
			$auditMsg .= 'modality ' . implode('/', $modList);

			$parameters[] = 'modalities=' . implode(',', $modList);
		}

		/* map our parameters to request parameters */
		$url = 'study/search';
		if (count($parameters))
			$url .= '?' . implode('&', $parameters);

		/* grab a result array from the service */
		$str = $this->hc->request($url);
		if ($str === false)
		{
			$audit->log(false, $auditMsg);
			return array('count' => 0, 'error' => '[GW] Endpoint failed (21), see logs');
		}
		$arr = @json_decode($str, true);

		/* map output elements to our output elements */
		if (!is_array($arr))
		{
			$audit->log(false, $auditMsg);
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('count' => 0, 'error' => '[GW] Endpoint failed (22), see logs');
		}
		$return = array('count' => 0, 'error' => '');
		if (array_key_exists('error', $arr))
			$return['error'] = $arr['error'];
		if (!$return['error'])
			foreach ($arr as $result)
			{
				$out = array();

				$out['uid'] = (string) $result['uid'];
				$out['id'] = (string) $result['id'];
				$out['patientid'] = (string) $result['patientId'];
				$out['patientname'] = (string) $result['patientName'];
				$out['patientbirthdate'] = (string) $result['patientBirthDate'];
				if (array_key_exists('patientSex', $result))
					$out['patientsex'] = (string) $result['patientSex'];
				else
					$out['patientsex'] = '';
				$out['modality'] = (string) $result['modality'];
				$out['description'] = (string) $result['description'];
				$out['date'] = (string) $result['date'];
				$out['time'] = (string) $result['time'];
				$out['datetime'] = trim($out['date'] . ' ' . $out['time']);
				$out['notes'] = (int) $result['notes'];
				$out['reviewed'] = (string) $result['reviewed'];
				$out['accessionnum'] = (string) $result['accessionNum'];
				$out['referringphysician'] = (string) $result['referringPhysician'];
				$out['readingphysician'] = (string) $result['readingPhysician'];
				$out['sourceae'] = (string) $result['sourceAE'];
				$out['received'] = (string) $result['received'];

				$return[$return['count']++] = $out;
			}

		/* provide an error message in addition to validation by external.php

			Shall be visible only during initial search, where the only criterion is
			Patient ID passed through the action.
		 */
		if (!$return['count'] && $patientFromAction && !count($searchCriteria))
			$return['error'] = "Patient '$patient_id' not found";

		/* clean up */
		if (strlen($return['error']))
			$audit->log(false, $auditMsg);
		else
			$audit->log($return['count'] . ' result(s)', $auditMsg);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief See ForwardIface::collectDestinationAes(). */
	public function collectDestinationAes()
	{
		$this->log->asDump('begin ' . __METHOD__);

		$str = $this->hc->request('forward/peers');
		if ($str === false)
			return array('error' => '[GW] Endpoint failed (23), see logs');

		$return = @json_decode($str, true);
		if (!is_array($return))
		{
			$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($str, true));
			return array('error' => '[GW] Endpoint failed (24), see logs');
		}

		if (!count($return))	/* empty array indicates "not implemented" but we indicate this differently */
			$return = null;
		else
			$this->log->asWarn(__METHOD__ . ': remap not implemented; received ' . var_export($return, true));

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
