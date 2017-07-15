<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	private function seriesIsVideoQuality($seriesdescription)
	{
		if ($seriesdescription == NULL)
			return false;

		$parts = explode(':', $seriesdescription);
		if (count($parts) != 2)
			return false;
		//first item is not empty and other one is a digit
		if (($parts[0] != '') && is_numeric($parts[1]))
			return $parts;

		return false;
	}


	/**
	 * make exclude list of series uid, which are video qualities
	 * if there is no original series for this quality - it will not be added to
	 * ecxlude list
	 *
	 * @param array $allSeries
	 * @return array
	 */
	private function excludeVideoQualitySeries($allSeries)
	{
		$excludequalityseries = array();
		$originals = array();
		$notoriginals = array();
		foreach ($allSeries as $row1)
		{
			$seriesUID = (string) $row1[$this->commonData['F_SERIES_UUID']];
			$description = (string) $row1['description'];
			$seriesnuber = (string) $row1[$this->commonData['F_SERIES_SERIESNUMBER']];

			$quality = $this->seriesIsVideoQuality($this->cs->utf8Encode($description));
			if ($quality === false)
				$originals[$seriesnuber] = $seriesUID;
			else
			{
				if (!isset($notoriginals[$quality[1]]))
					$notoriginals[$quality[1]] = array();

				$notoriginals[$quality[1]][] = $seriesUID;
			}
		}
		//$this->log->asDump('originals: ', $originals);
		//$this->log->asDump('not originals: ', $notoriginals);

		if (count($notoriginals) > 0)
		{
			$seriesindexs = array_keys($notoriginals);
			foreach ($seriesindexs as $seriesnuber)
			{
				if (!isset($originals[$seriesnuber]))
				{
					$originals[$seriesnuber] = $notoriginals[$seriesnuber][0];
					unset($notoriginals[$seriesnuber][0]);
				}
				$excludequalityseries = array_merge($excludequalityseries,
					array_values($notoriginals[$seriesnuber]));
			}

			unset($notoriginals);
		}
		unset($qualitysierieslist);

		return $excludequalityseries;
	}


	/**
	 * collect some data about image, study, and series
	 *
	 * @param string $imageuid
	 * @return array
	 */
	private function getImageStudySeriesData($imageuid)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$authDB = $this->authDB;

		$sql = 'SELECT instance, studyuid, series.' . $this->commonData['F_SERIES_UUID'] .
			', series.description, series.' . $this->commonData['F_SERIES_SERIESNUMBER'] .
			' FROM image, series' .
			' WHERE image.seriesuid = series.' . $this->commonData['F_SERIES_UUID'] .
				' AND image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
				$authDB->sqlEscapeString($imageuid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => "[Structure] Database error (1), see logs");
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);

		$return = array();
		if ($row)
		{
			$this->log->asDump('result: ', $row);

			$return['instance'] = (string) $row['instance'];
			$return['studyuid'] = (string) $row['studyuid'];
			$return['seriesuid'] = (string) $row[$this->commonData['F_SERIES_UUID']];
			$return['seriesnumb'] = (string) $row[$this->commonData['F_SERIES_SERIESNUMBER']];
			$return['seriesdescription'] = (string) $row['description'];
			$return['error'] = '';
		}
		else
		{
			$err = "Image not found: '$imageuid'";
			$this->log->asErr($err);
			$return['error'] = $err;
			return $return;
		}

		if (isset($return['studyuid']))
			if ($return['studyuid'] == '')
				$return['error'] = "Study not found for image '$imageuid'";

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->instanceGetMetadata($instanceUid, $includePatient);
		}

		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		if ($includePatient)
			$sql = 'SELECT path, xfersyntax, sopclass, bitsstored, origid, lastname, firstname' .
				' FROM image' .
				' LEFT JOIN series ON series.' . $this->commonData['F_SERIES_UUID'] . '=image.seriesuid' .
				' LEFT JOIN study ON study.' . $this->commonData['F_STUDY_UUID'] . '=series.studyuid' .
				' LEFT JOIN patient ON patient.origid=study.patientid' .
				' WHERE image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
					$authDB->sqlEscapeString($instanceUid) . "'";
		else
			$sql = 'SELECT path, xfersyntax, sopclass, bitsstored' .
				' FROM image' .
				' WHERE ' . $this->commonData['F_IMAGE_UUID'] . "='" .
					$authDB->sqlEscapeString($instanceUid) . "'";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => "[Structure] Database error (2), see logs");
		}

		$return = array();
		if ($row = $authDB->fetchAssoc($rs))
		{
			$return['error'] = '';
			$return['uid'] = $instanceUid;

			$log->asDump('result: ', $row);

			$return['path'] = $this->fp->toLocal($row['path']);
			$return['xfersyntax'] = $row['xfersyntax'];
			$return['bitsstored'] = $row['bitsstored'];
			$return['sopclass'] = $row['sopclass'];

			if ($includePatient)
			{
				$return['patientid'] = $this->cs->utf8Encode($row['origid']);
				$return['firstname'] = $this->cs->utf8Encode($row['firstname']);
				$return['lastname'] = $this->cs->utf8Encode($row['lastname']);
				$return['fullname'] = $this->shared->buildPersonName($return['lastname'],
					$return['firstname']);
			}
		}
		else
			$return['error'] = "record not found for instance '$instanceUid'";
		$authDB->free($rs);

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function instanceGetStudy($instanceUid)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->instanceGetStudy($instanceUid);
		}

		$log = $this->log;
		$authDB = $this->authDB;
		$dbms = $authDB->getDbms();

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$sql = 'SELECT studyuid' .
			' FROM series' .
			' LEFT JOIN image ON image.seriesuid=series.' . $this->commonData['F_SERIES_UUID'] .
			' WHERE image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
				$authDB->sqlEscapeString($instanceUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Structure] Database error (3), see logs');
		}

		$return = array('error' => '');
		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		if ($row)
			$return['studyuid'] = $row['studyuid'];
		else
		{
			$log->asWarn(__METHOD__ . ': study not found for instance ' . var_export($instanceUid, true));
			$return['studyuid'] = null;
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function seriesGetMetadata($seriesUid)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->seriesGetMetadata($seriesUid);
		}

		$log = $this->log;
		$authDB = $this->authDB;
		$dbms = $this->commonData['dbms'];

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$sql = 'SELECT i.path, i.xfersyntax, i.bitsstored, p.firstname, p.lastname' .
			' FROM image i' .
			' LEFT JOIN series se ON se.' . $this->commonData['F_SERIES_UUID'] . ' = i.seriesuid' .
			' LEFT JOIN study st ON st.' . $this->commonData['F_STUDY_UUID'] . ' = se.studyuid' .
			' LEFT JOIN patient p ON p.origid = st.patientid' .
			" WHERE i.seriesuid='" . $authDB->sqlEscapeString($seriesUid) . "'";
		$sql1 = '';
		foreach ($this->commonData['sop_class_blacklist'] as $sc)
		{
			if (strlen($sql1))
				$sql1 .= ' AND ';
			$sql1 .= "sopclass != '$sc'";
		}
		if (strlen($sql1))
			$sql .= " AND (sopclass IS NULL OR ($sql1))";
		$sql .= ' ORDER BY instance';
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Structure] Database error (4), see logs');
		}

		$firstName = '';
		$lastName = '';
		$fullName = '';
		$series = array('error' => '');
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$log->asDump("result #$i: ", $row);

			$firstName = $this->cs->utf8Encode($row['firstname']);
			$lastName = $this->cs->utf8Encode($row['lastname']);
			$fullName = $this->shared->buildPersonName($lastName, $firstName);

			$path = $this->fp->toLocal($row['path']);

			$img = array();
			$img["path"] = $path;
			$img['xfersyntax'] = $row['xfersyntax'];
			$img['bitsstored'] = $row['bitsstored'];
			$series["image-" . sprintf("%06d", $i++)] = $img;
		}
		$series['count'] = $i;
		if (!$i)
			$series['error'] = 'No such series';
		else
		{
			$series['firstname'] = $firstName;
			$series['lastname'] = $lastName;
			$series['fullname'] = $fullName;
		}

		$authDB->free($rs);

		$log->asDump('returning: ', $series);
		$log->asDump('end ' . __METHOD__);
		return $series;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->studyGetMetadata($studyUid, $disableFilter, $fromCache);
		}

		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		$cs = $this->cs;

		$return['error'] = '';

		$patientid = '';
		$sourceae = '';
		$studydate = '';
		$studytime = '';
		$lastname = '';
		$firstname = '';

		$sql = 'SELECT patientid, sourceae, ' . $this->commonData['F_STUDY_DATE'] . ', ' .
				$this->commonData['F_STUDY_TIME'] .
			' FROM ' . $this->commonData['F_TBL_NAME_STUDY'] .
			' WHERE ' . $this->commonData['F_STUDY_UUID'] . "='" .
				$authDB->sqlEscapeString($studyUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (5), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = (string) $row['patientid'];
			$sourceae = (string) $row['sourceae'];
			$studydate = (string) $row[$this->commonData['F_STUDY_DATE']];
			$studytime = (string) $row[$this->commonData['F_STUDY_TIME']];
		}
		else
		{
			$this->log->asErr('no such study');
			$return['error'] = 'No such study';
			return $return;
		}
		$authDB->free($rs);

		if (Constants::FOR_WORKSTATION)
			$sql = 'SELECT lastname, firstname' .
				' FROM patient' .
				" WHERE origid='" . $authDB->sqlEscapeString($patientid) .
					"' AND studyuid='" . $authDB->sqlEscapeString($studyUid) . "'";
		else
			$sql = 'SELECT lastname, firstname' .
				' FROM ' . $this->commonData['F_TBL_NAME_PATIENT'] .
				" WHERE origid='" . $authDB->sqlEscapeString($patientid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (6), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$lastname = (string) $row['lastname'];
			$firstname = (string) $row['firstname'];
		}
		$authDB->free($rs);

		$return['lastname'] = $cs->utf8Encode($lastname);
		$return['firstname'] = $cs->utf8Encode($firstname);
		$return['uid'] = $studyUid;
		$return['patientid'] = $cs->utf8Encode($patientid);
		$return['sourceae'] = $cs->utf8Encode($sourceae);
		$return['studydate'] = $studydate;
		$return['studytime'] = $studytime;

		$notes = $this->studyHasReport($studyUid);
		$return['notes'] = $notes['notes'];

		if ($disableFilter)
			$filter = '';
		else
			$filter = " AND (modality IS NULL OR (modality != 'KO' AND modality != 'PR'))" .
				" AND (LOWER(description) != '" . Constants::PR_SERIES_DESC . "' OR description IS NULL) ";
		$sql = 'SELECT ' . $this->commonData['F_SERIES_UUID'] . ', description, modality, ' .
				$this->commonData['F_SERIES_SERIESNUMBER'] .
			' FROM series' .
			" WHERE studyuid='" . $authDB->sqlEscapeString($studyUid) . "'$filter" .
			' ORDER BY ' . $this->commonData['F_SERIES_SERIESNUMBER'];
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (7), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$allSeries = array();
		while ($row1 = $authDB->fetchAssoc($rs))
			$allSeries[] = $row1;
		$authDB->free($rs);

		if (!$disableFilter)
		{
			$excludequalityseries = $this->excludeVideoQualitySeries($allSeries);
			$this->log->asDump('exclude lower quality video series: ', $excludequalityseries);
		}
		else
			$excludequalityseries = array();

		$i = 0;
		foreach ($allSeries as $row1)
		{
			$this->log->asDump('result: ', $row1);

			$modality = (string) $row1['modality'];
			$seriesUID = (string) $row1[$this->commonData['F_SERIES_UUID']];
			$description = (string) $row1['description'];

			//skip video quality by series description
			if (in_array($seriesUID, $excludequalityseries))
			{
				$this->log->asDump("excluded video series '$seriesUID'");
				continue;
			}

			$sql = 'SELECT ' . $this->commonData['F_IMAGE_UUID'] . ', numframes, path,' .
					' xfersyntax, bitsstored, sopclass' .
				' FROM image' .
				" WHERE seriesuid='" . $authDB->sqlEscapeString($seriesUID) . "'";
			if (!$disableFilter)
			{
				$sql1 = '';
				foreach ($this->commonData['sop_class_blacklist'] as $sc)
				{
					if (strlen($sql1))
						$sql1 .= ' AND ';
					$sql1 .= "sopclass != '$sc'";
				}

				if (strlen($sql1))
					$sql .= " AND (sopclass IS NULL OR ($sql1))";
			}
			$sql .= ' ORDER BY instance';
			$this->log->asDump('$sql = ', $sql);

			$rs2 = $authDB->query($sql);
			if (!$rs2)
			{
				$return['error'] = '[Structure] Database error (8), see logs';
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return $return;
			}

			$j = 0;
			$return[$i]['count'] = 0;
			while ($row2 = $authDB->fetchAssoc($rs2))
			{
				$this->log->asDump('result: ', $row2);

				$return[$i]['count']++;
				$return[$i][$j]['id'] = (string) $row2[$this->commonData['F_IMAGE_UUID']];
				$return[$i][$j]['numframes'] = (string) $row2['numframes'];
				$return[$i][$j]['path'] = $this->fp->toLocal((string) $row2['path']);
				$return[$i][$j]['xfersyntax'] = (string) $row2['xfersyntax'];
				$return[$i][$j]['bitsstored'] = (string) $row2['bitsstored'];
				$return[$i][$j]['sopclass'] = (string) $row2['sopclass'];
				$j++;
			}

			/* mark video files with a magic value .numframes = -99 */
			for ($p = 0; $p < $j; $p++)
			{
				$ts = $return[$i][$p]['xfersyntax'];
				if (($ts == '1.2.840.10008.1.2.4.100') || ($ts == '1.2.840.10008.1.2.4.103') ||
						($ts == '1.2.840.10008.1.2.4.102') || ($ts == 'MP4'))
					$return[$i][$p]['numframes'] = '-99';
			}

			/* avoid empty series (images might be filtered out by SOP Class etc) */
			if (!$j)
				unset($return[$i]);
			else
			{
				$return['count']++;
				$return[$i]['id'] = $seriesUID;
				$return[$i]['description'] = $cs->utf8Encode($description);
				$return[$i]['modality'] = $modality;
				$i++;
			}

			$authDB->free($rs2);
		}

		if ($return['count'] > 0)
			$return['error'] = '';
		else
			if (empty($return['error']))
				$return['error'] = "No images to display\n(some might have been skipped)";

		/* update the "reviewed" flag; failure will be only logged */
		$user = $authDB->getAuthUser();
		$sql = 'UPDATE ' . $this->commonData['F_TBL_NAME_STUDY'] .
			" SET reviewed='" . $authDB->sqlEscapeString($user) .
			"' WHERE " . $this->commonData['F_STUDY_UUID'] . "='" .
				$authDB->sqlEscapeString($studyUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs3 = $authDB->query($sql);
		if (!$rs3)
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
		else
			$this->log->asDump('number of reviewed record(s): ', $authDB->getAffectedRows($rs3));

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->studyGetMetadataBySeries($seriesUids, $disableFilter, $fromCache);
		}

		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUids, ')');

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

		$return['error'] = '';
		$cs = $this->cs;

		$tbl = $this->commonData['F_TBL_NAME_STUDY'];
		$sql = "SELECT $tbl." . $this->commonData['F_STUDY_UUID'] . ' AS studyuid,' .
				' series.' . $this->commonData['F_SERIES_UUID'] . ' AS seriesuid, series.' .
				$this->commonData['F_SERIES_SERIESNUMBER'] . ', series.modality, image.' .
				$this->commonData['F_IMAGE_UUID'] . ' AS imageuid, image.instance,' .
				' series.description, image.numframes, image.path, image.xfersyntax,' .
				' image.bitsstored' .
			" FROM image, series, $tbl" .
			' WHERE image.seriesuid = series.' . $this->commonData['F_SERIES_UUID'] .
				' AND image.seriesuid = series.' . $this->commonData['F_SERIES_UUID'] .
				" AND series.studyuid = $tbl." . $this->commonData['F_STUDY_UUID'] .
				' AND (series.' . $this->commonData['F_SERIES_UUID'] . " = '" .
					$authDB->sqlEscapeString($seriesUids[0]) . "'";
		for ($i = 1; $i < sizeof($seriesUids); $i++)
			$sql .= ' OR series.' . $this->commonData['F_SERIES_UUID'] . " = '" .
				$authDB->sqlEscapeString($seriesUids[$i]) . "'";
		$sql .= ')';
		$sql1 = '';
		foreach ($this->commonData['sop_class_blacklist'] as $sc)
		{
			if (strlen($sql1))
				$sql1 .= ' AND ';
			$sql1 .= "sopclass != '$sc'";
		}
		if (strlen($sql1))
			$sql .= " AND (sopclass IS NULL OR ($sql1))";
		$sql .= " ORDER BY $tbl." . $this->commonData['F_STUDY_UUID'] . ', series.' .
			$this->commonData['F_SERIES_SERIESNUMBER'] . ', image.instance';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (9), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$studyUID =  '';
		$seriesUID =  '';
		$description =  '';
		$imageUID = '';

		$modality = '';
		$seriesUIDwas = '';
		$seriesCount = 0;
		$imageCount = 0;
		$return['count'] = $seriesCount;
		$instance_fk = -1;
		$duplicate = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$studyUID = (string) $row['studyuid'];
			$seriesUID = (string) $row['seriesuid'];
			$description = (string) $row['description'];
			$modality = (string) $row['modality'];
			$imageUID = (string) $row['imageuid'];
			$numframes = (string) $row['numframes'];
			$path = (string) $row['path'];
			$xfersyntax = (string) $row['xfersyntax'];
			$bitsstored = (string) $row['bitsstored'];

			if ($seriesUID != $seriesUIDwas)
			{
				$seriesCount++;
				$return[$seriesCount - 1] = array();
				$return[$seriesCount - 1]['id'] = $seriesUID;
				$return[$seriesCount - 1]['description'] = $cs->utf8Encode($description);
				$return[$seriesCount - 1]['modality'] = $modality;
				$return['count'] = $seriesCount;
				$seriesUIDwas = $seriesUID;
				$imageCount = 0;
			}
			$imageCount++;
			$return[$seriesCount - 1][$imageCount - 1] = array();
			$return[$seriesCount - 1][$imageCount - 1]['id'] = $imageUID;
			$return[$seriesCount - 1][$imageCount - 1]['path'] = $this->fp->toLocal($path);
			$return[$seriesCount - 1][$imageCount - 1]['bitsstored'] = $bitsstored;
			$return[$seriesCount - 1][$imageCount - 1]['xfersyntax'] = $xfersyntax;
			if (($xfersyntax == '1.2.840.10008.1.2.4.100') || ($xfersyntax == '1.2.840.10008.1.2.4.103') ||
					($xfersyntax == '1.2.840.10008.1.2.4.102') || ($xfersyntax == 'MP4'))
				$numframes ='-99';		/* shortcut for SWS: mark video by a magic value of .numframes */
			$return[$seriesCount - 1][$imageCount - 1]['numframes'] = $numframes;

			$return[$seriesCount - 1]['count'] = $imageCount;
		}
		$authDB->free($rs);

		if (!$return['count'])
		{
			$return['error'] = 'Series not found';
			return $return;
		}

		$patientid = '';
		$lastname = '';
		$firstname = '';
		$sourceae = '';
		$studydate = '';

		$sql = 'SELECT patientid, sourceae, ' . $this->commonData['F_STUDY_DATE'] .
			" FROM $tbl" .
			' WHERE ' . $this->commonData['F_STUDY_UUID'] . " = '" .
				$authDB->sqlEscapeString($studyUID) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (10), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = (string) $row['patientid'];
			$sourceae = (string) $row['sourceae'];
			$studydate = (string) $row[$this->commonData['F_STUDY_DATE']];
		}
		$authDB->free($rs);

		if (Constants::FOR_WORKSTATION)
			$sql = 'SELECT lastname, firstname' .
				' FROM patient' .
				" WHERE origid='" . $authDB->sqlEscapeString($patientid) .
					"' AND studyuid='" . $authDB->sqlEscapeString($studyUID) . "'";
		else
			$sql = 'SELECT lastname, firstname' .
				' FROM ' . $this->commonData['F_TBL_NAME_PATIENT'] .
				" WHERE origid = '" . $authDB->sqlEscapeString($patientid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (11), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$lastname = (string) $row['lastname'];
			$firstname = (string) $row['firstname'];
		}
		$authDB->free($rs);

		$return['uid'] = $studyUID;
		$return['patientid'] = $cs->utf8Encode($patientid);
		$return['lastname'] = $cs->utf8Encode($lastname);
		$return['firstname'] = $cs->utf8Encode($firstname);
		$return['sourceae'] = $cs->utf8Encode($sourceae);
		$return['studydate'] = $cs->utf8Encode($studydate);

		$notes = $this->studyHasReport($studyUID);
		$return['notes'] = $notes['notes'];

		$return['error'] = '';

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->studyGetMetadataByImage($imageUids, $disableFilter, $fromCache);
		}

		$return = array('count' => 0, 'error' => 'not authenticated');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		$cs = $this->cs;

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

		$sql = 'SELECT study.' . $this->commonData['F_STUDY_UUID'] . ' AS studyuid,' .
				' series.' . $this->commonData['F_SERIES_UUID'] . ' AS seriesuid,' .
				' modality, series.' . $this->commonData['F_SERIES_SERIESNUMBER'] .
				', series.description, image.' . $this->commonData['F_IMAGE_UUID'] .
				' AS imageuid, image.instance, image.numframes, image.path, image.xfersyntax,' .
				' image.bitsstored' .
			' FROM image, series, study' .
			' WHERE image.seriesuid=series.' . $this->commonData['F_SERIES_UUID'] .
				' AND image.seriesuid=series.' . $this->commonData['F_SERIES_UUID'] .
				' AND series.studyuid=study.' . $this->commonData['F_STUDY_UUID'] .
				' AND (image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
					$authDB->sqlEscapeString($imageUids[0]) . "'";
		for ($i = 1; $i < sizeof($imageUids); $i++)
			$sql .= ' OR image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
				$authDB->sqlEscapeString($imageUids[$i]) . "'";
		$sql .= ') ORDER BY study.' . $this->commonData['F_STUDY_UUID'] . ', series.' .
			$this->commonData['F_SERIES_SERIESNUMBER'] . ', image.instance';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (12), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$studyUID =  '';
		$seriesUID =  '';
		$description =  '';
		$imageUID = '';

		$seriesUIDwas = '';
		$seriesCount = 0;
		$return['count'] = $seriesCount;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$studyUID = (string) $row['studyuid'];
			$seriesUID = (string) $row['seriesuid'];
			$modality = $row['modality'];
			$description = (string) $row['description'];
			$imageUID = (string) $row['imageuid'];
			$numframes = (string) $row['numframes'];
			$path = (string) $row['path'];
			$xfersyntax = (string) $row['xfersyntax'];
			$bitsstored = (string) $row['bitsstored'];

			if ($seriesUID != $seriesUIDwas)
			{
				$seriesCount++;
				$return[$seriesCount - 1] = array();
				$return[$seriesCount - 1]['id'] = $seriesUID;
				$return[$seriesCount - 1]['modality'] = $modality;
				$return[$seriesCount - 1]['description'] = $cs->utf8Encode($description);
				$return['count'] = $seriesCount;
				$seriesUIDwas = $seriesUID;
				$imageCount = 0;
			}
			$imageCount++;
			$return[$seriesCount - 1][$imageCount - 1] = array();
			$return[$seriesCount - 1][$imageCount - 1]['id'] = $imageUID;
			$return[$seriesCount - 1][$imageCount - 1]['path'] = $this->fp->toLocal($path);
			$return[$seriesCount - 1][$imageCount - 1]['xfersyntax'] = $xfersyntax;
			if (($xfersyntax == '1.2.840.10008.1.2.4.100') || ($xfersyntax == '1.2.840.10008.1.2.4.103') ||
					($xfersyntax == '1.2.840.10008.1.2.4.102') || ($xfersyntax == 'MP4'))
				$numframes = '-99';		/* shortcut for SWS: mark video by a magic value of .numframes */
			$return[$seriesCount - 1][$imageCount - 1]['numframes'] = $numframes;
			$return[$seriesCount - 1][$imageCount - 1]['bitsstored'] = $bitsstored;
			$return[$seriesCount - 1]['count'] = $imageCount;
		}
		$authDB->free($rs);

		if ($return['count'] < 1)
		{
			$return['error'] = 'Image not found';
			return $return;
		}

		$patientid = '';
		$lastname = '';
		$firstname = '';
		$sourceae = '';

		$sql = 'SELECT patientid, sourceae FROM study WHERE ' . $this->commonData['F_STUDY_UUID'] .
			"='" . $authDB->sqlEscapeString($studyUID) . "'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (13), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$patientid = (string) $row['patientid'];
			$sourceae = (string) $row['sourceae'];
		}

		if (Constants::FOR_WORKSTATION)
			$sql = "SELECT lastname, firstname FROM patient WHERE origid='" .
				$authDB->sqlEscapeString($patientid) . "' AND studyuid='" .
				$authDB->sqlEscapeString($studyUID) . "'";
		else
			$sql = "SELECT lastname, firstname FROM patient WHERE origid='" .
				$authDB->sqlEscapeString($patientid) . "'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (14), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$lastname = (string) $row['lastname'];
			$firstname = (string) $row['firstname'];
		}
		$authDB->free($rs);

		$return['uid'] = $studyUID;
		$return['patientid'] = $cs->utf8Encode($patientid);
		$return['lastname'] = $cs->utf8Encode($lastname);
		$return['firstname'] = $cs->utf8Encode($firstname);
		$return['sourceae'] = $cs->utf8Encode($sourceae);

		$notes = $this->studyHasReport($studyUID);
		$return['notes'] = $notes['notes'];

		$return['error'] = '';
		return $return;
	}


	public function studyListSeries($studyUid)
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->studyListSeries($studyUid);
		}

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

		$sql = 'SELECT ' . $this->commonData['F_SERIES_UUID'] .
			' FROM series' .
			" WHERE studyuid='" . $authDB->sqlEscapeString($studyUid) .
				"' AND (modality IS NULL OR (modality!='KO' AND modality!='PR'))" .
			' ORDER BY ' . $this->commonData['F_SERIES_SERIESNUMBER'];
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (15), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$count = 0;
		while ($row = $authDB->fetchNum($rs))
		{
			$this->log->asDump('result: ', $row);

			$return[$count++] = (string) $row[0];
		}
		$return['count'] = $count;

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function studyHasReport($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$return = array();
		$return['error'] = '';
		$return['notes'] = 2;		/* unknown or can't detect */

		$authDB = $this->authDB;

		$sql = 'SELECT ' . $this->commonData['F_STUDY_UUID'] .
			' FROM studynotes' .
			' WHERE ' . $this->commonData['F_STUDY_UUID'] . "='" .
				$authDB->sqlEscapeString($studyUid) .
			"' LIMIT 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (16), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");

			return $return;
		}

		$r = $authDB->fetchNum($rs);
		$authDB->free($rs);
		$this->log->asDump('result: ', $r);

		$return['notes'] = (int) is_array($r);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function collectRelatedVideoQualities($imageUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $imageUid, ')');

		$return = array('error' => 'not authenticated', 'quality' => array());

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		if (trim($imageUid) == '')
		{
			$return['error'] = 'required parameter(s) are missing';
			$this->log->asErr($return['error']);
			return $return;
		}
		$return['error'] = '';

		//get info about image
		$return = $this->getImageStudySeriesData($imageUid);
		if ($return['error'] != '')
			return $return;
		$studyuid = $authDB->sqlEscapeString($return['studyuid']);
		$currentseriesuid = $authDB->sqlEscapeString($return['seriesuid']);
		$currentseriesdescription = $return['seriesdescription'];
		$instance = $return['instance'];
		$currentseriesnumb = $return['seriesnumb'];

		$originalqualityname = 'Original';
		$originalquality = $this->seriesIsVideoQuality($currentseriesdescription);
		if ($originalquality !== false)
		{
			//not original - set original series number
			$originalqualityname = $originalquality[0];
			$currentseriesnumb = $originalquality[1];
		}

		//clear
		unset($return['studyuid']);
		unset($return['seriesuid']);
		unset($return['seriesdescription']);
		unset($return['seriesnumb']);
		unset($return['instance']);

		$return['quality'] = array();

		// collect other series from the same study
		$sql = 'SELECT ' . $this->commonData['F_SERIES_UUID'] . ', description' .
			' FROM series' .
			" WHERE studyuid='$studyuid' AND " . $this->commonData['F_SERIES_UUID'] .
				" != '$currentseriesuid'" .
			' ORDER BY ' . $this->commonData['F_SERIES_SERIESNUMBER'];
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Structure] Database error (17), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		$allSeries = array();
		while ($row1 = $authDB->fetchAssoc($rs))
			$allSeries[] = $row1;
		$authDB->free($rs);
		if (count($allSeries) == 0)
		{
			/* no sense to continue if no other series was found */
			$this->log->asDump('return: ', $return);
			return $return;
		}

		$i = 0;
		foreach ($allSeries as $row1)
		{
			$seriesuid = '';
			$description = '';
			$this->log->asDump('result/1: ', $row1);

			$description = $row1['description'];
			$seriesuid = $row1[$this->commonData['F_SERIES_UUID']];

			//skip video quality by series description
			$quality = $this->seriesIsVideoQuality($description);
			$this->log->asDump('quality: ', $quality);

			if ($quality === false)
				continue;

			//series number is the same as in series description - for the same series
			if ((int)$quality[1] != (int)$currentseriesnumb)
			{
				$this->log->asDump('not for this series');
				continue;
			}

			//select images from other series with correct instance
			$sql = 'SELECT ' . $this->commonData['F_IMAGE_UUID'] .
			 		' FROM image' .
				" WHERE seriesuid='" . $authDB->sqlEscapeString($seriesuid) .
					"' AND instance='". $authDB->sqlEscapeString($instance) . "'";
			$this->log->asDump('$sql = ', $sql);

			$rs2 = $authDB->query($sql);
			if (!$rs2)
			{
				$return['error'] = '[Structure] Database error (18), see logs';
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return $return;
			}

			while ($row2 = $authDB->fetchAssoc($rs2))
			{
				$this->log->asDump('result/2: ', $row2);
				if (trim($row2[$this->commonData['F_IMAGE_UUID']]) == '')
					continue;

				$return['quality'][] = array('quality' => $quality[0],
					'imageid' => $row2[$this->commonData['F_IMAGE_UUID']]);
			}
			$authDB->free($rs2);
		}

		//add original or also with quality
		if (!empty($return['quality']) ||
			($originalqualityname != 'Original'))
		{
			array_unshift($return['quality'],
				array('quality' => $originalqualityname, 'imageid' => $imageUid));
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}
}
