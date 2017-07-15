<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='DCM4CHEE'</tt>. */
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

		$sql = 'SELECT "d1" AS period, COUNT(*) AS recordscount FROM study WHERE study_datetime >= CURDATE()' .
			' UNION ' .
			'SELECT "d3" AS period, COUNT(*) AS recordscount FROM study WHERE study_datetime >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)' .
			' UNION ' .
			'SELECT "w1" AS period, COUNT(*) AS recordscount FROM study WHERE study_datetime >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)' .
			' UNION ' .
			'SELECT "m1" AS period, COUNT(*) AS recordscount FROM study WHERE study_datetime >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)' .
			' UNION ' .
			'SELECT "y1" AS period, COUNT(*) AS recordscount FROM study WHERE study_datetime >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)' .
			' UNION ' .
			'SELECT "any" AS period, COUNT(*) AS recordscount FROM study';
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

			if ($criteriaName == 'patientid')
			{
				$where .= ' AND ';
				$where .= "LOWER(pat_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'patientname')
			{
				$where .= ' AND ';
				$where .= "LOWER(pat_name) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'id')
			{
				$where .= ' AND ';
				$where .= "LOWER(study_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'accessionnum')
			{
				$where .= ' AND ';
				$where .= "LOWER(accession_no) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'description')
			{
				$where .= ' AND ';
				$where .= "LOWER(study_desc) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'referringphysician')
			{
				$where .= ' AND ';
				$where .= "LOWER(ref_physician) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == 'readingphysician')
			{
				$audit->log(false, $auditMsg);
				return array('count' => 0, 'error' => '[DCM4CHEE] Searches by Reading Physician not supported');
			}
		}

		/* dates */
		if ($fromDate != '')
		{
			$fromDate = $authDB->sqlEscapeString(str_replace('.', '-', $fromDate));
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "from $fromDate";

			$where .= "study.study_datetime>='$fromDate'";
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString(str_replace('.', '-', $toDate));
			$where .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			$toTime = '23:59:59.999';
			if (($dbms != 'MSSQL') && ($dbms != 'SQLSRV'))
				$toTime .= '999';	/* MS server doesn't support and accept microseconds */
			$where .= "study.study_datetime<='$toDate $toTime'";
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
				$limit_suf = " LIMIT $listLimit";
		}

		if (isset($_SESSION[$authDB->sessionHeader.'notesExsist']) &&
			   $_SESSION[$authDB->sessionHeader.'notesExsist'])
			$sql = 'SELECT ' . $limit_pre . 'study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, study.mods_in_study AS modality,' .
				' study_desc, study_datetime, accession_no, ref_physician, studynotes.pk IS NOT NULL AS notes FROM study' .
				' LEFT JOIN studynotes ON study.pk=studynotes.study_fk, patient, series WHERE patient.pk=study.patient_fk' .
				" AND series.study_fk=study.pk AND (modality IS NULL OR (modality!='PR' AND modality!='KO'))$where" .
				' GROUP BY study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, study.mods_in_study, study_desc,' .
				" study_datetime, accession_no, ref_physician, studynotes.pk IS NOT NULL ORDER BY study_datetime DESC$limit_suf";
			/* !!!TODO: MSSQL fails on 'IS NULL'; PostgreSQL requires studynotes.pk in GROUP BY,
				however then the study is repeated for each attached report
			 */
		else
			$sql = 'SELECT ' . $limit_pre . 'study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, study.mods_in_study AS modality,' .
				' study_desc, study_datetime, accession_no, ref_physician FROM study, patient, series' .
				' WHERE patient.pk=study.patient_fk AND series.study_fk=study.pk' .
				" AND (modality IS NULL OR (modality!='PR' AND modality!='KO'))$where" .
				' GROUP BY study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, study.mods_in_study, study_desc,' .
				" study_datetime, accession_no, ref_physician ORDER BY study_datetime DESC$limit_suf";

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
			$return[$i]['uid'] = (string) $row['pk'];
			$return[$i]['id'] = $cs->utf8Encode((string) $row['study_id']);
			$return[$i]['patientid'] = $cs->utf8Encode((string) $row['pat_id']);
			$return[$i]['patientname'] = $cs->utf8Encode(trim(str_replace('^', ' ',
				(string) $row['pat_name'])));
			$return[$i]['patientbirthdate'] = (string) $row['pat_birthdate'];
			$return[$i]['modality'] = (string) $row['modality'];
			$return[$i]['description'] = $cs->utf8Encode((string) $row['study_desc']);
			$return[$i]['date'] = '';
			$return[$i]['time'] = '';

			$notes = 2;		// 2 - no notes table, 1 - notes exist, 0 - notes empty
			if ($dbms == 'POSTGRESQL')
			{
				/* well-known problem with those pseudo-booleans 't' and 'f' */
				if (isset($row['notes']))
					if ($row['notes'] == 't')
						$notes = 1;
					else
						if ($row['notes'] == 'f')
							$notes = 0;
			}
			else
				if (isset($row['notes']))
					$notes = ((bool) $row['notes']) ? 1 : 0;

			$return[$i]['notes'] = $notes;
			$return[$i]['datetime'] = (string) $row['study_datetime'];
			$return[$i]['reviewed'] = '';
			$return[$i]['accessionnum'] = (string) $row['accession_no'];
			$return[$i]['referringphysician'] = $cs->utf8Encode((string) $row['ref_physician']);
			$return[$i]['readingphysician'] = '';
			$return[$i]['sourceae'] = '';
			$return[$i]['received'] = '';
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
