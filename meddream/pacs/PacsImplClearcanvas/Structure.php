<?php

namespace Softneta\MedDream\Core\Pacs\Clearcanvas;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='ClearCanvas'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		require_once __DIR__ . '/clearcanvas.php';

		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		$ids = explode('*', $instanceUid);
		if (count($ids) < 2)
		{
			$err = 'wrong parameter format';
			$this->log->asErr($err);
			return array('error' => $err);
		}
		$pa = ClearCanvas_fetch_instance($this->authDB, $ids[1], $ids[0]);
		if (is_null($pa))
		{
			$error = 'ClearCanvas_fetch_instance() failed, see log';
			$this->log->asErr($error);
			return array('error' => $error);
		}

		$return = array('error' => '', 'uid' => $ids[0], 'path' => $pa['path'],
			'xfersyntax' => $pa['xfersyntax'], 'sopclass' => '',
			'bitsstored' => $pa['bitsstored']);
		if ($includePatient)
		{
			/* these are not supported yet but we must obey StructureIface */
			$return['patientid'] = '';
			$return['firstname'] = '';
			$return['lastname'] = '';
			$return['fullname'] = '';
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function seriesGetMetadata($seriesUid)
	{
		require_once __DIR__ . '/clearcanvas.php';

		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$series = ClearCanvas_fetch_series($authDB, $seriesUid);
		if (is_null($series))
			return array('error' => 'ClearCanvas_fetch_series() failed, see log');
		$series['error'] = '';

		$log->asDump('returning: ', $series);
		$log->asDump('end ' . __METHOD__);
		return $series;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		require_once __DIR__ . '/clearcanvas.php';

		$return = array();
		$return['count'] = 0;

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$return['error'] = 'not authenticated';
			return $return;
		}

		$cs = $this->cs;

		$return['error'] = '';

		/* we'll need filesystem paths, which are stored in the database */
		if (!ClearCanvas_collect_storage($authDB, $filesystems, $partitions))
		{
			$return['error'] = 'ClearCanvas_collect_storage() failed, see log';
			return $return;
		}

		$patientid = '';
		$sourceae = '';
		$studydate = '';
		$studytime = '';
		$lastname = '';
		$firstname = '';

		$sql = 'SELECT CONVERT(varchar(50), ServerPartitionGUID) AS serverpartn, ' .
				'CONVERT(varchar(50), StudyStorageGUID) AS studystor, ' .
				'CONVERT(varchar(50), GUID) AS studyid, ' .
				'CONVERT(varchar(50), PatientGUID) AS patientid, ' .
				'CONVERT(varchar(10), StudyDate, 102) AS studydat ' .
			'FROM Study ' .
			"WHERE StudyInstanceUid='" . $authDB->sqlEscapeString($studyUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (3), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = $row["patientid"];
			$study_uuid = $row["studyid"];
			$partn_uuid = $row["serverpartn"];
			$studystor_uuid = $row["studystor"];
			$studydate = (string) $row["studydat"];
		}
		else
		{
			$this->log->asErr('no such study');
			$return['error'] = 'No such study';
			return $return;
		}
		$authDB->free($rs);
		$patient_inst = $patientid;

		$sql = 'SELECT PatientsName, PatientId, CONVERT(varchar(50), FilesystemGUID) AS filesysid, StudyFolder ' .
			'FROM Patient, FilesystemStudyStorage ' .
			"WHERE Patient.GUID='" . $authDB->sqlEscapeString($patientid) .
				"' AND StudyStorageGUID='" . $authDB->sqlEscapeString($studystor_uuid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (4), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$lastname = '';
			$firstname = $this->shared->buildPersonName($row['PatientsName']);
			$patient_inst = $row['PatientId'];
			$study_dir = $row['StudyFolder'];
			$fsys_uuid = $row['filesysid'];
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

		$sql = 'SELECT SeriesInstanceUid,SeriesDescription,Modality ' .
			'FROM Series ' .
			"WHERE StudyGUID='" . $authDB->sqlEscapeString($study_uuid) . "' " .
				(!$disableFilter ? "AND Modality!='KO' AND Modality!='PR' " : ' ') .
			'ORDER BY SeriesNumber';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (5), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$allSeries = array();
		while ($row1 = $authDB->fetchAssoc($rs))
			$allSeries[] = $row1;
		$authDB->free($rs);

		$i = 0;
		$inttype = 'INT';

		foreach ($allSeries as $row1)
		{
			$this->log->asDump('result: ', $row1);

			$modality = (string) $row1["Modality"];
			$seriesUID = $row1["SeriesInstanceUid"];
			$description = $row1["SeriesDescription"];

			/* enough data so far
				Series UIDs and descriptions are also present in the study XML
				file, so there is some temptation to use it instead, however:
				1) it is more difficult to extract the description from there
				2) in XML the sort criterion is absent and who knows whether the
				   series are (always) sorted
			 */
			$return['count']++;
			$return[$i]['id'] = $seriesUID;
			$return[$i]['description'] = $cs->utf8Encode($description);
			$return[$i]['modality'] = $modality;
			$i++;
		}

		/* ClearCanvas keeps instance list in a certain XML file */
		if (!ClearCanvas_fetch_study($filesystems[$fsys_uuid] . "\\" .
			$partitions[$partn_uuid] . "\\$study_dir", $studyUid, $return))
		{
			$return["error"] = 'ClearCanvas_fetch_study() failed, see log';
			return $return;
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
}
