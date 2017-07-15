<?php

namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='%Conquest'</tt>. */
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

		if ($dbms != 'MYSQL')
		{
			$this->log->asWarn(__METHOD__ . ': not implemented for DBMS "' . $dbms . '"');
			return $return;
		}

		$sql = 'SELECT "d1" AS period, COUNT(*) AS recordscount FROM DICOMstudies WHERE StudyDate >= CURDATE()' .
			' UNION ' .
			'SELECT "d3" AS period, COUNT(*) AS recordscount FROM DICOMstudies WHERE StudyDate >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)' .
			' UNION ' .
			'SELECT "w1" AS period, COUNT(*) AS recordscount FROM DICOMstudies WHERE StudyDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)' .
			' UNION ' .
			'SELECT "m1" AS period, COUNT(*) AS recordscount FROM DICOMstudies WHERE StudyDate >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)' .
			' UNION ' .
			'SELECT "y1" AS period, COUNT(*) AS recordscount FROM DICOMstudies WHERE StudyDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)' .
			' UNION ' .
			'SELECT "any" AS period, COUNT(*) AS recordscount FROM DICOMstudies';
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
			$where .= " AND DICOMPatients.PatientID='$patientID'";
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
					$where .= " AND ";
					$where .= "LOWER(DICOMPatients.PatientID) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "patientname")
				{
					$where .= " AND ";
					$where .= "LOWER(DICOMPatients.PatientNam) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "id")
				{
					$where .= " AND ";
					$where .= "LOWER(DICOMStudies.StudyID) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "accessionnum")
				{
					$where .= " AND ";
					$where .= "LOWER(AccessionN) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "description")
				{
					$where .= " AND ";
					$where .= "LOWER(StudyDescr) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "referringphysician")
				{
					$where .= " AND ";
					$where .= "LOWER(ReferPhysi) LIKE LOWER('%$criteriaText%')";
					continue;
				}
				if ($criteriaName == "readingphysician")
				{
					$audit->log(false, $auditMsg);
					return array('count' => 0, 'error' => '[Conquest] Searches by Reading Physician not supported');
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

			$where .= "DICOMStudies.StudyDate>='" . str_replace(".", "", $fromDate) . "'";
				/* str_replace(): defined in the database as char(8), separators will interfere in some cases */
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString($toDate);
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			$where .= "DICOMStudies.StudyDate<='" . str_replace(".", "", $toDate) . "'";
				/* str_replace(): must remove separators again */
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
		$listLimit = 0;
		$limit_suf = '';
		if ($listMax)
		{
			$listLimit = $listMax + 1;
			$limit_suf = " LIMIT $listLimit";
		}

		$sql = 'SELECT DICOMStudies.StudyInsta, DICOMStudies.StudyID, DICOMPatients.PatientID,' .
				' DICOMPatients.PatientNam, DICOMPatients.PatientBir, StudyModal, StudyDescr, StudyDate,' .
				' StudyTime, AccessionN, ReferPhysi FROM DICOMPatients, DICOMStudies, DICOMSeries' .
			' WHERE DICOMPatients.PatientID=DICOMStudies.PatientID AND DICOMSeries.StudyInsta=DICOMStudies.StudyInsta' .
				" AND (Modality IS NULL OR (Modality!='PR' AND Modality!='KO'))" . $where .
			' GROUP BY DICOMStudies.StudyInsta, DICOMStudies.StudyID, DICOMPatients.PatientID, DICOMPatients.PatientNam,' .
				' DICOMPatients.PatientBir, StudyModal, StudyDescr, StudyDate, StudyTime, AccessionN, ReferPhysi' .
			" ORDER BY DICOMStudies.StudyDate DESC, DICOMStudies.StudyTime DESC, StudyModal$limit_suf";

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
				$return[$i]["uid"] = (string) $row["StudyInsta"];
				$return[$i]["id"] = $cs->utf8Encode((string) $row["StudyID"]);
				$return[$i]["patientid"] = $cs->utf8Encode((string) $row["PatientID"]);
				$return[$i]["patientname"] = $cs->utf8Encode(trim(str_replace("^", " ",
					(string) $row["PatientNam"])));
				$return[$i]["patientbirthdate"] = (string) $row["PatientBir"];
				$return[$i]["modality"] = (string) $row["StudyModal"];
				$return[$i]["description"] = $cs->utf8Encode((string) $row["StudyDescr"]);
				$return[$i]["date"] = (string) $row["StudyDate"];
				$return[$i]["time"] = (string) $row["StudyTime"];
				$return[$i]["notes"] = 2;
				$return[$i]["datetime"] = $return[$i]["date"]." ".$return[$i]["time"];
				$return[$i]["reviewed"] = "";
				$return[$i]["accessionnum"] = (string) $row["AccessionN"];
				$return[$i]["referringphysician"] = $cs->utf8Encode((string) $row["ReferPhysi"]);
				$return[$i]["readingphysician"] = "";
				$return[$i]["sourceae"] = "";
				$return[$i]["received"] = "";
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
