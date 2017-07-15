<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='dcm4chee-arc'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	public function getStudyCounts()
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->getStudyCounts();
		}

		$this->log->asDump('begin ' . __METHOD__);
		$dbms = $this->commonData['dbms'];

		$return = array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);

		if (!$this->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		switch ($dbms)
		{
		case 'MYSQL':
			$sql = 'SELECT "d1" AS period, COUNT(*) AS recordscount' .
					' FROM study' .
					' WHERE study_date >= CURDATE()' .
				' UNION' .
				' SELECT "d3" AS period, COUNT(*) AS recordscount' .
					' FROM study' .
					' WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)' .
				' UNION' .
				' SELECT "w1" AS period, COUNT(*) AS recordscount' .
					' FROM study' .
					' WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)' .
				' UNION' .
				' SELECT "m1" AS period, COUNT(*) AS recordscount' .
					' FROM study' .
					' WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)' .
				' UNION' .
				' SELECT "y1" AS period, COUNT(*) AS recordscount' .
					' FROM study' .
					' WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)' .
				' UNION' .
				' SELECT "any" AS period, COUNT(*) AS recordscount' .
					' FROM study';
			break;

		case 'OCI8':
			$sql = "SELECT 'd1' AS period, COUNT(*) AS recordscount" .
					' FROM study ' .
					" WHERE study_date >= TO_CHAR(SYSDATE, 'YYYYMMDD')" .
				' UNION' .
				" SELECT 'd3' AS period, COUNT(*) AS recordscount" .
					' FROM study' .
					" WHERE study_date >= TO_CHAR(SYSDATE - INTERVAL '2' DAY, 'YYYYMMDD')" .
				' UNION' .
				" SELECT 'w1' AS period, COUNT(*) AS recordscount" .
					' FROM study' .
					" WHERE study_date >= TO_CHAR(SYSDATE - INTERVAL '6' DAY, 'YYYYMMDD')" .
				' UNION' .
				" SELECT 'm1' AS period, COUNT(*) AS recordscount" .
					' FROM study' .
					" WHERE study_date >= TO_CHAR(SYSDATE - INTERVAL '1' MONTH, 'YYYYMMDD')" .
				' UNION' .
				" SELECT 'y1' AS period, COUNT(*) AS recordscount" .
					' FROM study' .
					" WHERE study_date >= TO_CHAR(SYSDATE - INTERVAL '1' YEAR, 'YYYYMMDD')" .
				' UNION ' .
				" SELECT 'any' AS period, COUNT(*) AS recordscount" .
					' FROM study';
		}
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
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax);
		}

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
			$where .= " AND patient.pat_id='$patientID'";
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
				$where .= "LOWER(pat_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "patientname")
			{
				$where .= " AND ";
				$where .= "LOWER(pat_name) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "id")
			{
				$where .= " AND ";
				$where .= "LOWER(study_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "accessionnum")
			{
				$where .= " AND ";
				$where .= "LOWER(accession_no) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "description")
			{
				$where .= " AND ";
				$where .= "LOWER(study_desc) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "referringphysician")
			{
				$where .= " AND ";
				$where .= "LOWER(ref_physician) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "readingphysician")
			{
				$audit->log(false, $auditMsg);
				return array('count' => 0, 'error' => '[DCM4CHEE-ARC] Searches by Reading Physician not supported');
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

			/* defined in the database as varchar(255), separators will interfere */
			$where .= "study.study_date>='" . str_replace(".", "", $fromDate) . "'";
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString($toDate);
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			/* must remove separators again */
			$where .= "study.study_date<='" . str_replace(".", "", $toDate) . "'";
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
			if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV'))
				$limit_pre = "TOP($listLimit) ";
			else
				if ($dbms != "OCI8")
					$limit_suf = " LIMIT $listLimit";
		}

		if (isset($_SESSION[$authDB->sessionHeader.'notesExsist']) &&
			$_SESSION[$authDB->sessionHeader.'notesExsist'])
		{
			if ($dbms == "OCI8")
				$studynotesExpr = 'NVL2(studynotes.pk, 1, 0)';
			else
				$studynotesExpr = 'studynotes.pk IS NOT NULL';
				/* in Oracle 11g, IS NULL is accepted only in the WHERE clause but not in the SELECT list.
				   Didn't find any official explanation. --tb
				 */
			$sql = 'SELECT ' . $limit_pre . 'study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, mods_in_study,' .
				' study_desc, study_date, study_time, accession_no, ref_physician, src_aet,' .
				" $studynotesExpr AS notes" .
				' FROM study LEFT JOIN studynotes ON study.pk=studynotes.study_fk, patient, series' .
				' WHERE patient.pk=study.patient_fk AND series.study_fk=study.pk' .
				" AND (series.modality IS NULL OR (series.modality!='PR' AND series.modality!='KO'))$where" .
				' GROUP BY study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, mods_in_study, study_desc,' .
				" study_date, study_time, accession_no, ref_physician, src_aet, $studynotesExpr" .
				" ORDER BY study_date DESC, study_time DESC, src_aet$limit_suf";
		}
		else
			$sql = 'SELECT ' . $limit_pre . 'study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, mods_in_study,' .
				' study_desc, study_date, study_time, accession_no, ref_physician, src_aet FROM study, patient, series' .
				' WHERE patient.pk=study.patient_fk AND series.study_fk=study.pk' .
				" AND (series.modality IS NULL OR (series.modality!='PR' AND series.modality!='KO'))$where" .
				' GROUP BY study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, mods_in_study, study_desc,' .
				" study_date, study_time, accession_no, ref_physician, src_aet ORDER BY study_date DESC, study_time DESC, src_aet$limit_suf";
		if ($listLimit && ($dbms == "OCI8"))
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= $listLimit";

		$this->log->asDump('$sql = ', $sql);
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Search] Database error, see logs';
			$return['count'] = 0;
			$this->log->asErr('query failed: ' . $authDB->getError());
			$audit->log(false, $auditMsg);
			return $return;
		}

		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump("#$i: \$row = ", $row);

			$return[$i]["uid"] = (string) $row["pk"];
			$return[$i]["id"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["study_id"]));
			$return[$i]["patientid"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["pat_id"]));
			$return[$i]["patientname"] = $cs->utf8Encode(trim(str_replace("^", " ",
				$this->shared->cleanDbString((string) $row["pat_name"]))));
			$return[$i]["patientbirthdate"] = $this->shared->cleanDbString((string) $row["pat_birthdate"]);
			$return[$i]["modality"] = $this->shared->cleanDbString($row["mods_in_study"]);
			$return[$i]["description"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["study_desc"]));
			$return[$i]["date"] = $this->shared->cleanDbString((string) $row["study_date"]);
			$return[$i]["time"] = $this->shared->cleanDbString((string) $row["study_time"]);

			$notes = 2;		// 2 - no notes table, 1 - notes exist, 0 - notes empty
			if ($dbms == "POSTGRESQL")
			{
				/* well-known problem with those pseudo-booleans 't' and 'f' */
				if (isset($row["notes"]))
					if ($row["notes"] == 't')
						$notes = 1;
					else
						if ($row["notes"] == 'f')
							$notes = 0;
			}
			else
				if (isset($row["notes"]))
					$notes = ((bool) $row["notes"]) ? 1 : 0;

			$return[$i]["notes"] = $notes;
			$return[$i]["datetime"] = trim($return[$i]["date"] . " " . $return[$i]["time"]);
			$return[$i]["reviewed"] = "";
			$return[$i]["accessionnum"] = $this->shared->cleanDbString((string) $row["accession_no"]);
			$return[$i]["referringphysician"] = $cs->utf8Encode($this->shared->cleanDbString(trim(str_replace("^", " ",
				(string) $row["ref_physician"]))));
			$return[$i]["readingphysician"] = "";
			$return[$i]["sourceae"] = $row["src_aet"];
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
