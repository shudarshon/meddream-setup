<?php

namespace Softneta\MedDream\Core\Pacs\Clearcanvas;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='ClearCanvas'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	public function getStudyCounts()
	{
		$this->log->asDump('begin ' . __METHOD__);
		$dbms = $this->commonData['dbms'];

		$return = array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);

		if (!$this->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		$today = 'DATEADD(dd, 0, DATEDIFF(dd, 0, GETDATE()))';	/* set the time part to 00:00:00 */
		$sql = "SELECT 'd1' AS period, COUNT(*) AS recordscount" .
				' FROM Study' .
				" WHERE StudyDate >= $today" .
			' UNION' .
			" SELECT 'd3' AS period, COUNT(*) AS recordscount" .
				' FROM Study' .
				" WHERE StudyDate >= DATEADD(day, -2, $today)" .
			' UNION' .
			" SELECT 'w1' AS period, COUNT(*) AS recordscount" .
				' FROM Study' .
				" WHERE StudyDate >= DATEADD(day, -6, $today)" .
			' UNION' .
			" SELECT 'm1' AS period, COUNT(*) AS recordscount" .
				' FROM Study' .
				" WHERE StudyDate >= DATEADD(month, -1, $today)" .
			' UNION' .
			" SELECT 'y1' AS period, COUNT(*) AS recordscount" .
				' FROM Study' .
				" WHERE StudyDate >= DATEADD(year, -1, $today)" .
			' UNION' .
			" SELECT 'any' AS period, COUNT(*) AS recordscount" .
				' FROM Study';
		$this->log->asDump('$sql = ', $sql);

		$rs = $this->authDB->query($sql);
		if (!$rs)
			$this->log->asErr('query failed: ' . $this->authDB->getError());
		else
		{
			while ($row = $this->authDB->fetchAssoc($rs))
			{
				$this->log->asDump('$row = ', $row);
				$return[$row['period']] = $row['recordscount'];
			}
			$this->authDB->free($rs);
		}

		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $actions, ', ', $searchCriteria, ', ',
			$fromDate, ', ', $toDate, ', ', $mod, ', ', $listMax, ')');
		$dbms = $this->commonData['dbms'];

		$audit = new Audit('SEARCH');

		if (!$this->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false);
			return array('error' => 'not authenticated');
		}

		$return = array('error' => '');
		$authDB = $this->authDB;
		$cs = $this->cs;

		$where = '';
		$limit = '';
		$sql = '';
		$auditMsg = '';

		/* convert objects to arrays

			Objects come from Flash since amfPHP 2.0. HTML due to some reason also
			passes a JSON-encoded object instead of an array.
		 */
		if (is_object($actions))
			$actions = get_object_vars($actions);
		for ($i = 0; $i < count($searchCriteria); $i++)
			if (is_object($searchCriteria[$i]))
				$searchCriteria[$i] = get_object_vars($searchCriteria[$i]);

		/* search for patient via HIS integration */
		if ($actions && (strtoupper($actions['action']) == 'SHOW') &&
			(strtoupper($actions['option']) == 'PATIENT') &&
			((int) sizeof((array) $actions['entry']) > 0))
		{
			$patientID = $authDB->sqlEscapeString($cs->utf8Decode($actions['entry'][0]));
			$auditMsg = "patientid '$patientID'";
			$where .= "AND Patient.PatientId='$patientID'";
		}

	/* search from the dialog */
		/* drop-down boxes or their equivalent in HTML search */
		for ($i = 0; $i < sizeof($searchCriteria); $i++)
		{
			$criteriaName = $authDB->sqlEscapeString($searchCriteria[$i]['name']);
			$criteriaText = trim($authDB->sqlEscapeString($cs->utf8Decode($searchCriteria[$i]['text'])));

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "$criteriaName '$criteriaText'";

			if ($criteriaName == "patientid")
			{
				$where .= " AND LOWER(Patient.PatientId) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "patientname")
			{
				$where .= " AND LOWER(Patient.PatientsName) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "id")
			{
				$where .= " AND LOWER(Study.StudyId) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "accessionnum")
			{
				$where .= " AND LOWER(AccessionNumber) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "description")
			{
				$where .= " AND LOWER(StudyDescription) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "referringphysician")
			{
				$where .= " AND LOWER(ReferringPhysiciansName) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "readingphysician")
			{
				$audit->log(false, $auditMsg);
				return array('count' => 0, 'error' => '[ClearCanvas] Searches by Reading Physician not supported');
			}
		}

		/* dates */
		if ($fromDate != '')
		{
			$fromDate = $authDB->sqlEscapeString($fromDate);
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "from $fromDate";

			/* defined in the database as varchar(8), separators will interfere */
			$where .= "Study.StudyDate>='" . str_replace(".", "", $fromDate) . "'";
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString($toDate);
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			/* must remove separators again */
			$where .= "Study.StudyDate<='" . str_replace(".", "", $toDate) . "'";
		}

		/* modality checkboxes */
		$modAll = true;
		for ($i = 0; $i < sizeof($mod); $i++)
		{
			$mod[$i]['name'] = $authDB->sqlEscapeString($mod[$i]['name']);
			$mod[$i]['selected'] = (bool) $mod[$i]['selected'];
			
			if (!$mod[$i]['selected'] || isset($mod[$i]['custom'])) 
				$modAll = false;
		}
		
		$this->log->asDump('$modAll = '.(int)$modAll);
		if (!$modAll)
		{
			$modColName = 'modality';

			$auditMsgMod = '';

			$modSelected = false;
			for ($i = 0; $i < sizeof($mod); $i++)
			{
				if ($mod[$i]['selected'])
				{
					if ($modSelected)
						$auditMsgMod .= '/';
					$auditMsgMod .= $mod[$i]['name'];

					if (!$modSelected)
						$where .= ' AND (';
					else
						$where .= ' OR ';

					$modSelected = true;
					$modality = $mod[$i]['name'];
					$where .= "$modColName='$modality'";
				}
			}
			if ($modSelected)
				$where .= ')';

			if (strlen($auditMsgMod))
			{
				if (strlen($auditMsg))
					$auditMsg .= ', ';
				$auditMsg .= "modality $auditMsgMod";
			}
		}

		/* must limit the number of studies returned */
		$listMax = (int) $listMax;
		$listLimit = 0;			/* a different approach for OCI8 */
		$limit_pre = '';
		$limit_suf = '';
		if ($listMax)
		{
			$listLimit = $listMax + 1;
			$limit_pre = " TOP($listLimit) ";
		}

		/* build the final query */
		if (strlen($where))
			$whereOuter = " WHERE $where";
		$sql = 'SELECT ' . $limit_pre . 'Study.StudyInstanceUid, StudyId, Patient.PatientId, Patient.PatientsName,' .
			'Modality, SourceApplicationEntityTitle, CONVERT(varchar, InsertTime, 121) AS received, StudyDescription,' .
			' StudyDate, StudyTime, AccessionNumber, ReferringPhysiciansName FROM Patient, Study, Series, StudyStorage' .
			' WHERE Patient.GUID=Study.PatientGUID AND Series.StudyGUID=Study.GUID' .
			" AND StudyStorage.GUID=Study.StudyStorageGUID AND Modality!='PR' AND Modality!='KO'" .
			$where . ' GROUP BY Study.StudyInstanceUid, StudyId, Patient.PatientId, Patient.PatientsName, Modality,' .
			' SourceApplicationEntityTitle, InsertTime, StudyDescription, StudyDate, StudyTime, AccessionNumber,' .
			" ReferringPhysiciansName ORDER BY Study.StudyDate DESC, Study.StudyTime DESC$limit_suf";

		$this->log->asDump('$sql = ', $sql);
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error, see logs';
			$return['count'] = 0;
			$this->log->asErr('query failed: ' . $authDB->getError());
			$audit->log(false, $auditMsg);
			return $return;
		}

		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump("#$i: \$row = ", $row);
			$return[$i]["uid"] = (string) $row["StudyInstanceUid"];
			$return[$i]["id"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["StudyId"]));
			$return[$i]["patientid"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["PatientId"]));
			$return[$i]["patientname"] = $cs->utf8Encode($this->shared->buildPersonName($row["PatientsName"]));
			$return[$i]["patientbirthdate"] = "";
			$return[$i]["modality"] = $this->shared->cleanDbString((string) $row["Modality"]);
			$return[$i]["description"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["StudyDescription"]));
			$return[$i]["date"] = $this->shared->cleanDbString((string) $row["StudyDate"]);
			$return[$i]["time"] = $this->shared->cleanDbString((string) $row["StudyTime"]);
			$return[$i]["notes"] = 2;
			$return[$i]["datetime"] = trim($return[$i]["date"] . " " . $return[$i]["time"]);
			$return[$i]["reviewed"] = "";
			$return[$i]["accessionnum"] = $this->shared->cleanDbString((string) $row["AccessionNumber"]);
			$return[$i]["referringphysician"] = $cs->utf8Encode($this->shared->buildPersonName((string)
				$row["ReferringPhysiciansName"]));
			$return[$i]["readingphysician"] = "";
			$return[$i]["sourceae"] = $this->shared->cleanDbString($row["SourceApplicationEntityTitle"]);
			$return[$i]["received"] = $row["received"];
			$i++;
		}
		$authDB->free($rs);

		$return['count'] = $i;
		$audit->log("$i result(s)", $auditMsg);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
