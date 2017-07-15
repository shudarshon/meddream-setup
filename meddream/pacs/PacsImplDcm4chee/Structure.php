<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='DCM4CHEE'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	private function seriesIsVideoQuality($seriesdescription)
	{
		if ($seriesdescription == NULL)
			return false;

		$parts = explode(':', $seriesdescription);
		if (count($parts) != 2)
			return false;
		//first item is not empty and oteh is digit
		if (($parts[0] != '') && is_numeric($parts[1]))
			return $parts;

		return false;
	}


	/**
	 * make exclude list of series uid, which are video qualities
	 * if there is no original series for this quality - it will not be added to
	 * ecxlude list
	 *
	 * @param class $authDB
	 * @param array $allSeries
	 * @return array
	 */
	private function excludeVideoQualitySeries($authDB, $allSeries)
	{
		$excludequalityseries = array();
		$originals = array();
		$notoriginals = array();
		foreach ($allSeries as $row1)
		{
			$seriesUID = (string) $row1["pk"];
			$description = (string) $row1["series_desc"];
			$seriesnuber = (string) $row1['series_no'];

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

		$sql = 'SELECT series.study_fk AS studyuid, series.pk, files.instance_fk,' .
			' instance.inst_no, series.series_desc, series.series_no' .
			' FROM instance, files, series' .
			' WHERE files.pk=' . $authDB->sqlEscapeString($imageuid) .
				' AND files.instance_fk=instance.pk' .
				' AND instance.series_fk=series.pk ';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => "Database error (1), see logs");
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);

		$return = array();
		if ($row)
		{
			$this->log->asDump('result: ', $row);

			$return['studyuid'] = (string) $row['studyuid'];
			$return['instance'] = (string) $row['inst_no'];
			$return['seriesuid'] = (string) $row['pk'];
			$return['seriesnumb'] = (string) $row['series_no'];
			$return['seriesdescription'] = (string) $row['series_desc'];
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


	private function mapRowKeys($row, $inputKey, $outputKey)
	{
		$return = array('error' => '', $outputKey => null);

		if (isset($row[$inputKey]))
			$return[$outputKey] = $row[$inputKey];

		return $return;
	}


	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		if ($includePatient)
			$sql = 'SELECT f.filepath, fs.dirpath, f.file_tsuid, i.sop_cuid, p.pat_name AS fullname' .
				' FROM files f' .
				' LEFT JOIN filesystem fs ON fs.pk = f.filesystem_fk' .
				' LEFT JOIN instance i ON i.pk = f.instance_fk' .
				' LEFT JOIN series se ON se.pk = i.series_fk' .
				' LEFT JOIN study st ON st.pk = se.study_fk' .
				' LEFT JOIN patient p ON p.pk = st.patient_fk' .
				' WHERE f.pk=' . $authDB->sqlEscapeString($instanceUid);
		else
			$sql = 'SELECT filepath, dirpath, file_tsuid, sop_cuid' .
				' FROM files' .
				' LEFT JOIN filesystem ON filesystem.pk = files.filesystem_fk' .
				' LEFT JOIN instance ON instance.pk = files.instance_fk' .
				' WHERE files.pk=' . $authDB->sqlEscapeString($instanceUid);
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => "Database error (2), see logs");
		}

		$return = array();
		if ($row = $authDB->fetchAssoc($rs))
		{
			$return['error'] = '';
			$return['uid'] = $instanceUid;

			$log->asDump('result: ', $row);

			$path = (string) $row["dirpath"] . DIRECTORY_SEPARATOR . $row["filepath"];
			if (($path[0] != "/") && ($path[0] != "\\") && ($path[1] != ":"))
				$path = $this->commonData['archive_dir_prefix'] . $path;
			$return['path'] = $this->fp->toLocal($path);

			$return['xfersyntax'] = (string) $row['file_tsuid'];
			$return['bitsstored'] = '8';
			$return['sopclass'] = $row['sop_cuid'];
			if ($includePatient)
			{
				$return['patientid'] = '';
				$return['fullname'] = $this->cs->utf8Encode($row['fullname']);
				$return['firstname'] = '';
				$return['lastname'] = '';
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
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$limit_pre = '';
		$limit_suf = '';
		if (($authDB->getDbms() == 'MSSQL') || ($authDB->getDbms() == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			$limit_suf = ' LIMIT 1';
		$sql = "SELECT$limit_pre series.study_fk " .
			'FROM series' .
				' LEFT JOIN instance ON instance.series_fk=series.pk' .
				' LEFT JOIN files ON files.instance_fk=instance.pk' .
			' WHERE files.pk=' . $authDB->sqlEscapeString($instanceUid) . $limit_suf;
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (3), see logs');
		}

		$return = array('error' => '');
		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		if ($row)
			$return['studyuid'] = $row['study_fk'];
		else
		{
			$log->asWarn(__METHOD__ . ': study not found for instance ' . var_export($instanceUid, true));
			$return['studyuid'] = null;
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function instanceUidToKey($instanceUid)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$u = trim($instanceUid);
		if ($u == '')
		{
			$return = array('error' => '', 'imagepk' => $u);
			$log->asDump('returning: ', $return);
			return $return;
		}

		$limit_pre = '';
		$limit_suf = '';
		if (($authDB->getDbms() == 'MSSQL') || ($authDB->getDbms() == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			$limit_suf = ' LIMIT 1';
		$sql = "SELECT$limit_pre files.pk" .
			' FROM files' .
				' LEFT JOIN instance ON instance.pk=files.instance_fk ' .
			"WHERE instance.sop_iuid='" . $authDB->sqlEscapeString($instanceUid) . "'$limit_suf";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (4), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		$this->log->asDump('result: ', $row);

		$return = $this->mapRowKeys($row, 'pk', 'imagepk');
		$this->log->asDump('$return = ', $return);

		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function instanceKeyToUid($instanceKey)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceKey, ')');

		$u = trim($instanceKey);
		if ($u == '')
		{
			$return = array('error' => '', 'imageuid' => $u);
			$log->asDump('returning: ', $return);
			return $return;
		}

		$limit_pre = '';
		$limit_suf = '';
		if (($authDB->getDbms() == 'MSSQL') || ($authDB->getDbms() == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			$limit_suf = ' LIMIT 1';
		$sql = "SELECT$limit_pre instance.sop_iuid" .
			' FROM instance' .
				' LEFT JOIN files ON files.instance_fk=instance.pk' .
			' WHERE files.pk=' . $authDB->sqlEscapeString($instanceKey) . $limit_suf;
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (5), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		$this->log->asDump('result: ', $row);

		$return = $this->mapRowKeys($row, 'sop_iuid', 'imageuid');
		$this->log->asDump('$return = ', $return);

		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function seriesGetMetadata($seriesUid)
	{
		$log = $this->log;
		$authDB = $this->authDB;
		$dbms = $this->commonData['dbms'];

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV') || ($dbms == 'POSTGRESQL'))
			$inttype = 'INT';
		else
			$inttype = 'UNSIGNED';
		$sql = 'SELECT f.instance_fk, f.pk, f.filepath, fs.dirpath, f.file_tsuid, p.pat_name AS fullname' .
			' FROM instance i' .
			' LEFT JOIN files f ON f.instance_fk = i.pk' .
			' LEFT JOIN filesystem fs ON fs.pk = f.filesystem_fk' .
			' LEFT JOIN series se ON se.pk = i.series_fk' .
			' LEFT JOIN study st ON st.pk = se.study_fk' .
			' LEFT JOIN patient p ON p.pk = st.patient_fk' .
			' WHERE i.series_fk=' . $authDB->sqlEscapeString($seriesUid) .
			" ORDER BY CAST(i.inst_no AS $inttype), i.content_datetime, f.pk DESC";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (6), see logs');
		}

		$fullName = '';
		$series = array('error' => '');
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$log->asDump("result #$i: ", $row);

			$path = (string) $row['dirpath'] . DIRECTORY_SEPARATOR . $row['filepath'];
			if (($path[0] != "/") && ($path[0] != "\\") && ($path[1] != ":"))
				$path = $this->commonData['archive_dir_prefix'] . $path;

			$fullName = $row['fullname'];

			$img = array();
			$img['path'] = $this->fp->toLocal($path);
			$img['xfersyntax'] = (string) $row['file_tsuid'];
			$img['bitsstored'] = 8;
			$series['image-' . sprintf('%06d', $i++)] = $img;
		}
		$authDB->free($rs);

		$series['count'] = $i;
		if (!$i)
			return array('error' => 'No such series');
		$series['firstname'] = '';
		$series['lastname'] = '';
		$series['fullname'] = $fullName;

		$log->asDump('returning: ', $series);
		$log->asDump('end ' . __METHOD__);
		return $series;
	}


	public function seriesUidToKey($seriesUid)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$u = trim($seriesUid);
		if ($u == '')
		{
			$return = array('error' => '', 'seriespk' => $u);
			$log->asDump('$return = ', $return);
			return $return;
		}

		$limit_pre = '';
		$limit_suf = '';
		if (($authDB->getDbms() == 'MSSQL') || ($authDB->getDbms() == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			$limit_suf = ' LIMIT 1';
		$sql = "SELECT$limit_pre pk FROM series" .
			" WHERE series_iuid='" . $authDB->sqlEscapeString($seriesUid) . "'$limit_suf";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (7), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		$this->log->asDump('result: ', $row);

		$return = $this->mapRowKeys($row, 'pk', 'seriespk');
		$this->log->asDump('$return = ', $return);

		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$dbms = $this->commonData['dbms'];
		$return = array();
		$return['count'] = 0;
		$return['error'] = '';

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		$cs = $this->cs;

		$return['error'] = '';

		$patientid = '';
		$sourceae = '';
		$studydate = '';
		$studytime = '';
		$lastname = '';
		$firstname = '';

		$sql = 'SELECT patient_fk,study_datetime FROM study WHERE pk=' . $authDB->sqlEscapeString($studyUid);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (8), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = (string) $row['patient_fk'];
			$datetime = explode(' ', (string) $row['study_datetime']);
			$studydate = $datetime[0];
			$studytime = '';
			if (count($datetime) > 1)	/* no spaces for MSSQL, it prefers ISO 8601 */
				$studytime = $datetime[1];
		}
		else
		{
			$this->log->asErr('no such study');
			$return['error'] = 'No such study';
			return $return;
		}
		$authDB->free($rs);
		$patient_inst = $patientid;

		$sql = 'SELECT pat_name, pat_id FROM patient WHERE pk=' . $authDB->sqlEscapeString($patientid);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (9), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$lastname = '';
			$firstname = trim(str_replace('^', ' ',(string) $row['pat_name']));
			$patient_inst = $row['pat_id'];
		}
		$authDB->free($rs);

		$return['lastname'] = $cs->utf8Encode($lastname);
		$return['firstname'] = $cs->utf8Encode($firstname);
		$return['uid'] = $studyUid;
		$return['patientid'] = $cs->utf8Encode($patient_inst);
		$return['sourceae'] = $cs->utf8Encode($sourceae);
		$return['studydate'] = $studydate;
		$return['studytime'] = $studytime;

		$notes = $this->studyHasReport($studyUid);
		$return['notes'] = $notes['notes'];

		$sql = 'SELECT pk,series_desc,series_iuid,modality,series_no ' .
			'FROM series ' .
			'WHERE study_fk=' . $authDB->sqlEscapeString($studyUid) . ' ' .
				($disableFilter ? ' ' :
					"AND (modality IS NULL OR (modality!='KO' AND modality!='PR'))" .
						" AND (LOWER(series_desc)!='presentation state' OR series_desc IS NULL) "
				) .
				' ORDER BY series_no';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (10), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$allSeries = array();
		while ($row1 = $authDB->fetchAssoc($rs))
			$allSeries[] = $row1;
		$authDB->free($rs);

		$i = 0;

		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV'))
			$inttype = 'INT';
		else
			$inttype = 'UNSIGNED';

		if (!$disableFilter)
		{
			$excludequalityseries = $this->excludeVideoQualitySeries($authDB, $allSeries);
			$this->log->asDump('exclude lower quality video series: ', $excludequalityseries);
		}
		else
			$excludequalityseries = array();

		foreach ($allSeries as $row1)
		{
			$this->log->asDump('result: ', $row1);

			$modality = (string) $row1['modality'];
			$seriesUID = (string) $row1['pk'];
			$description = (string) $row1['series_desc'];

			//skip video quality by series description
			if (in_array($seriesUID, $excludequalityseries))
			{
				$this->log->asDump("excluded video series '$seriesUID'");
				continue;
			}

			$sql = 'SELECT files.instance_fk,files.pk,files.filepath,filesystem.dirpath,files.file_tsuid,' .
				'instance.sop_cuid,instance.sop_iuid ' .
				'FROM instance, files, filesystem WHERE files.instance_fk=instance.pk AND' .
				' files.filesystem_fk=filesystem.pk AND instance.series_fk=' . $authDB->sqlEscapeString($seriesUID) .
				" ORDER BY CAST(instance.inst_no AS $inttype), instance.content_datetime, files.pk DESC";
			$this->log->asDump('$sql = ', $sql);

			$rs2 = $authDB->query($sql);
			if (!$rs2)
			{
				$return['error'] = 'Database error (11), see logs';
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return $return;
			}

			$j = 0;
			$return[$i]['count'] = 0;
			$instance_fk = -1;
			while ($row2 = $authDB->fetchAssoc($rs2))
			{
				$this->log->asDump('result: ', $row2);

				if ($instance_fk == $row2['instance_fk'])
					continue;		/* sometimes the `files` table contains duplicates */
				$instance_fk = $row2['instance_fk'];

				$return[$i]['count']++;
				$return[$i][$j]['id'] = (string) $row2['pk'];
				$return[$i][$j]['numframes'] = 0;

				$path = (string) $row2['dirpath'] . DIRECTORY_SEPARATOR . $row2['filepath'];
				if (($path[0] != '/') && ($path[0] != '\\') && ($path[1] != ':'))
					$path = $this->commonData['archive_dir_prefix'] . $path;
				$return[$i][$j]['path'] = $this->fp->toLocal($path);

				$return[$i][$j]['xfersyntax'] = (string) $row2['file_tsuid'];
				$return[$i][$j]['bitsstored'] = '8';
				$return[$i][$j]['sopclass'] = (string)$row2['sop_cuid'];
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

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false)
	{
		$return = array();
		$return['count'] = 0;
		$return['error'] = 'reconnect';

		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUids, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		$cs = $this->cs;

		$return['error'] = '';

		if (!is_array($seriesUids))
		{
			$return['error'] = "Error: wrong types of parameters";
			return $return;
		}
		if (sizeof($seriesUids) == 0)
		{
			$return['error'] = "Error: mandatory parameters missing";
			return $return;
		}

		$sql = 'SELECT study.pk AS studyuid,series.pk AS seriesuid,series.series_no,files.pk AS imageuid,' .
			'files.instance_fk,series.series_desc,series.modality,files.filepath,filesystem.dirpath,' .
			'files.file_tsuid' .
			' FROM instance,files,filesystem,series,study' .
			' WHERE files.instance_fk=instance.pk' .
			' AND files.filesystem_fk=filesystem.pk' .
			' AND instance.series_fk=series.pk' .
			' AND series.study_fk=study.pk' .
			' AND (series.pk=' . $authDB->sqlEscapeString($seriesUids[0]);
		for ($i = 1; $i < sizeof($seriesUids); $i++)
			$sql .= ' OR series.pk=' . $authDB->sqlEscapeString($seriesUids[$i]);
		$sql .= ') ORDER BY study.pk,series.series_no,files.instance_fk';
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

			$duplicate = $instance_fk == $row['instance_fk'];
			if ($duplicate)
				continue;

			$instance_fk = $row['instance_fk'];

			$studyUID = (string) $row['studyuid'];
			$seriesUID = (string) $row['seriesuid'];
			$description = (string) $row['series_desc'];
			$modality = (string) $row['modality'];
			$imageUID = (string) $row['imageuid'];
			$numframes = '0';

			$path = (string) $row['dirpath'] . DIRECTORY_SEPARATOR . $row['filepath'];
			if (($path[0] != '/') && ($path[0] != '\\') && ($path[1] != ':'))
				$path = $this->commonData['archive_dir_prefix'] . $path;

			$xfersyntax = (string) $row['file_tsuid'];
			$bitsstored = '8';

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
				$numframes = '-99';		/* shortcut for SWS: mark video by a magic value of .numframes */
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

		$sql = 'SELECT patient_fk as patientid,ext_retr_aet,study_datetime FROM study WHERE pk=' .
			$authDB->sqlEscapeString($studyUID);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (13), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = (string) $row['patientid'];
			$sourceae = (string) $row['ext_retr_aet'];
			$datetime = explode(' ', (string) $row['study_datetime']);
			$studydate = $datetime[0];
		}

		$sql = 'SELECT pat_name,pat_id FROM patient WHERE pk=' . $authDB->sqlEscapeString($patientid);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (14), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = $row['pat_id'];
			$names = explode('^', (string) $row['pat_name']);
			$lastname = $names[0];
			$firstname = $names[1];
		}

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

		$sql = 'SELECT pk FROM series WHERE study_fk=' . $authDB->sqlEscapeString($studyUid) .
			" AND (modality IS NULL OR (modality!='KO' AND modality!='PR')) ORDER BY series_no";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (15), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$count = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$return[$count++] = (string) $row['pk'];
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
		$dbms = $this->commonData['dbms'];

		$limit_pre = '';
		$limit_suf = '';
		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			$limit_suf = ' LIMIT 1';

		if (isset($_SESSION[$authDB->sessionHeader.'notesExsist']) &&
			$_SESSION[$authDB->sessionHeader.'notesExsist'])
		{
			$sql = "SELECT$limit_pre study_fk FROM studynotes WHERE study_fk=" .
				$authDB->sqlEscapeString($studyUid) . $limit_suf;
		}
		else
		{
			$return['notes'] = 2;
			return $return;
		}
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (16), see logs';
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

		$return = array();
		$return['error'] = 'not authenticated';
		$return['quality'] = array();

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
		{
			$this->log->asErr($return['error']);
			return $return;
		}

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
		$sql = '';

		// collect other series from the same study
		$sql = 'SELECT pk, series_desc'
			. ' FROM series'
			. " WHERE study_fk=$studyuid AND pk!=$currentseriesuid"
			. ' ORDER BY series_no';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (17), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		/* ODBC doesn't support multiple cursors over the same connection so
		   we must read all series into array before proceeding with queries
		   to images. There should be no memory problems as number of series
		   is usually moderate.
		 */
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

			$description = $row1['series_desc'];
			$seriesuid = $row1['pk'];

			//skip video quality by series description
			$quality = $this->seriesIsVideoQuality($description);
			$this->log->asDump('quality:', $quality);

			if ($quality === false)
				continue;

			//series number is the same as in series description - for the same series
			if ((int)$quality[1] != (int)$currentseriesnumb)
			{
				$this->log->asDump('not for this series');
				continue;
			}

			//select images from other series with correct instance
			$sql = 'SELECT files.pk as imageuid'
				. ' FROM instance, files'
				. ' WHERE files.instance_fk=instance.pk'
					. ' AND instance.series_fk=' . $authDB->sqlEscapeString($seriesuid)
					. ' AND instance.inst_no=' . $authDB->sqlEscapeString($instance);
			$this->log->asDump('$sql = ', $sql);

			$rs2 = $authDB->query($sql);
			if (!$rs2)
			{
				$return['error'] = 'Database error (18), see logs';
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return $return;
			}

			while ($row2 = $authDB->fetchAssoc($rs2))
			{
				$this->log->asDump("result/2: ", $row2);
				if (trim($row2['imageuid']) == '')
					continue;

				$return['quality'][] = array('quality' => $quality[0],
					'imageid' => $row2['imageuid']);
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
