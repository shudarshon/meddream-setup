<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc_5;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='dcm4chee-arc-5'</tt>. */
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
				' UNION ' .
				" SELECT 'm1' AS period, COUNT(*) AS recordscount" .
					' FROM study' .
					" WHERE study_date >= TO_CHAR(SYSDATE - INTERVAL '1' MONTH, 'YYYYMMDD')" .
				' UNION ' .
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

		$whereInner = '';
		$whereOuter= '';
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
			$whereInner .= " AND pat_id='$patientID'";
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
				$whereInner .= " AND ";
				$whereInner .= "LOWER(pat_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "patientname")
			{
				if (strlen($whereOuter))
					$whereOuter .= " AND ";
				if ($dbms == 'OCI8')
					$whereOuter .= "LOWER(pat_fn || ' ' || pat_gn) LIKE LOWER('%$criteriaText%')";
				else
					$whereOuter .= "LOWER(CONCAT(COALESCE(pat_fn, ''), ' ', COALESCE(pat_gn, '')))" .
						" LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "id")
			{
				$whereInner .= " AND ";
				$whereInner .= "LOWER(study_id) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "accessionnum")
			{
				$whereInner .= " AND ";
				$whereInner .= "LOWER(accession_no) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "description")
			{
				$whereInner .= " AND ";
				$whereInner .= "LOWER(study_desc) LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "referringphysician")
			{
				if (strlen($whereOuter))
					$whereOuter .= " AND ";
				if ($dbms == 'OCI8')
					$whereOuter .= "LOWER(ref_phys_fn || ' ' || ref_phys_gn) LIKE LOWER('%$criteriaText%')";
				else
					$whereOuter .= "LOWER(CONCAT(COALESCE(ref_phys_fn, ''), ' ', COALESCE(ref_phys_gn, '')))" .
						" LIKE LOWER('%$criteriaText%')";
				continue;
			}
			if ($criteriaName == "readingphysician")
			{
				$audit->log(false, $auditMsg);
				return array('count' => 0, 'error' => '[DCM4CHEE-ARC-5] Searches by Reading Physician not supported');
			}
		}

		/* dates */
		if ($fromDate != '')
		{
			$fromDate = $authDB->sqlEscapeString($fromDate);
			$whereInner .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "from $fromDate";

			/* defined in the database as varchar(255), separators will interfere */
			$whereInner .= "study.study_date>='" . str_replace(".", "", $fromDate) . "'";
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString($toDate);
			$whereInner .= ' AND ';

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			/* must remove separators again */
			$whereInner .= "study.study_date<='" . str_replace(".", "", $toDate) . "'";
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
						$whereInner .= ' AND (';
					else
						$whereInner .= ' OR ';

					$modSelected = true;
					$modality = $mod[$i]['name'];
					$whereInner .= "$modColName='$modality'";
				}
			}
			if ($modSelected)
				$whereInner .= ')';

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
				$limit_pre = " TOP($listLimit) ";
			else
				if ($dbms != "OCI8")
					$limit_suf = " LIMIT $listLimit";
		}

		/* build the final query */
		if (strlen($whereOuter))
			$whereOuter = " WHERE $whereOuter";
		if ($dbms == 'OCI8')
		{
			$modalitiesExpr = " LISTAGG(modality, '\\') WITHIN GROUP (ORDER BY modality)";
			$sourceAetsExpr = " LISTAGG(src_aet, '\\') WITHIN GROUP (ORDER BY src_aet)";
			$tableAlias1 = '';
			$tableAlias2 = '';
		}
		else
		{
			$modalitiesExpr = " GROUP_CONCAT(DISTINCT modality ORDER BY modality SEPARATOR '\\\\')";
			$sourceAetsExpr = " GROUP_CONCAT(DISTINCT src_aet ORDER BY src_aet SEPARATOR '\\\\')";
			$tableAlias1 = ' AS inner1';
			$tableAlias2 = ' AS inner2';
		}
		if (isset($_SESSION[$authDB->sessionHeader . 'notesExsist']) &&
			$_SESSION[$authDB->sessionHeader . 'notesExsist'])
		{
			if ($dbms == 'OCI8')
				$studynotesExpr = ', NVL2(studynotes.pk, 1, 0)';
					/* in Oracle 11g, IS NULL is accepted only in the WHERE clause but not in the SELECT list.
					   Didn't find any official explanation. --tb
					 */
			else
				$studynotesExpr = ', studynotes.pk IS NOT NULL';
			$studynotesExprAliased = "$studynotesExpr AS notes";
			$studynotesAliased = ', notes';
			$studynotesJoin = ' LEFT JOIN studynotes ON study.pk=studynotes.study_fk';
		}
		else
		{
			$studynotesExpr = '';
			$studynotesExprAliased = '';
			$studynotesAliased = '';
			$studynotesJoin = '';
		}
		$sql = "SELECT$limit_pre mods_in_study, study_pk, study_iuid, study_id, study_desc, study_date, study_time," .
				" created_time, accession_no,$sourceAetsExpr AS src_aets, pat_id, pat_birthdate, pat_fn, pat_gn," .
				" ref_phys_fn, ref_phys_gn$studynotesAliased" .
			' FROM (' .
				"SELECT$modalitiesExpr AS mods_in_study, study_pk, study_iuid," .
					' study_id, study_desc, study_date, study_time, created_time, accession_no, src_aet, pat_id,' .
					" pat_birthdate, pat_fn, pat_gn, ref_phys_fn, ref_phys_gn$studynotesAliased" .
				' FROM (' .
					'SELECT modality, study.pk AS study_pk, study_iuid, study_id, study_desc, study_date, study_time,' .
						' study.created_time, accession_no, src_aet, pat_id, pat_birthdate, pat_fn, pat_gn, ref_phys_fn,' .
						" ref_phys_gn$studynotesExprAliased" .
					' FROM series' .
					' LEFT JOIN study ON study.pk=series.study_fk' .
					$studynotesJoin .
					' LEFT JOIN patient ON patient.pk=study.patient_fk' .
					' LEFT JOIN patient_id ON patient_id.pk=patient.patient_id_fk' .
					' LEFT JOIN (SELECT pk AS pat_pk, family_name AS pat_fn, given_name AS pat_gn FROM person_name) tmp2' .
						' ON tmp2.pat_pk=patient.pat_name_fk' .
					' LEFT JOIN (SELECT pk AS ref_phys_pk, family_name AS ref_phys_fn, given_name AS ref_phys_gn FROM person_name) tmp3' .
						' ON tmp3.ref_phys_pk=study.ref_phys_name_fk' .
					" WHERE (modality IS NULL OR (modality!='PR' AND modality!='KO'))$whereInner" .
					' GROUP BY modality, study.pk, study_iuid, study_id, study_desc, study_date, study_time,' .
						'  study.created_time, accession_no, src_aet, pat_id, pat_birthdate, pat_fn, pat_gn, ref_phys_fn,' .
						' ref_phys_gn' .  $studynotesExpr .
				")$tableAlias1$whereOuter" .
				' GROUP BY study_pk, study_iuid, study_id, study_desc, study_date, study_time, created_time, accession_no,' .
					" src_aet, pat_id, pat_birthdate, pat_fn, pat_gn, ref_phys_fn, ref_phys_gn$studynotesAliased" .
				' ORDER BY study_date DESC, study_time DESC, study_pk DESC' .
			")$tableAlias2" .
			' GROUP BY mods_in_study, study_pk, study_iuid, study_id, study_desc, study_date, study_time, created_time,' .
				" accession_no, pat_id, pat_birthdate, pat_fn, pat_gn, ref_phys_fn, ref_phys_gn$studynotesAliased" .
			" ORDER BY study_date DESC, study_time DESC, study_pk DESC$limit_suf";
			/*
				The 1st SELECT aggregates `src_aet` which will differ if some series was uploaded from
				another device. (MedDream annotations can't be used for testing as modality 'PR' is
				specifically included in the 3rd SELECT.)

				The 2nd SELECT aggregates `modality` which often differs among series, and filters
				Patient Name and Referring Physician that are not directly available.

				The 3rd SELECT is the main "workhorse".

				Two deeper SELECTs are for joining with the same table, `person_name`, in two different
				contexts.
			 */
		if ($listLimit && ($dbms == "OCI8"))
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= $listLimit";

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

			$return[$i]['uid'] = (string) $row['study_pk'];
			$return[$i]['id'] = $cs->utf8Encode($this->shared->cleanDbString((string) $row['study_id']));
			$return[$i]['description'] = $cs->utf8Encode($this->shared->cleanDbString((string) $row['study_desc']));
			$return[$i]['date'] = $this->shared->cleanDbString((string) $row['study_date']);
			$return[$i]['time'] =  $this->shared->cleanDbString((string) $row['study_time']);
			$return[$i]['datetime'] = trim($return[$i]['date'] . ' ' . $return[$i]['time']);
			$return[$i]['accessionnum'] = $this->shared->cleanDbString((string) $row['accession_no']);
			$return[$i]['sourceae'] = $this->shared->cleanDbString($row['src_aets']);
			$return[$i]['received'] = $row['created_time'];
			$return[$i]['referringphysician'] = $cs->utf8Encode($this->shared->buildPersonName($row['ref_phys_fn'],
				$row['ref_phys_gn']));
			$return[$i]['readingphysician'] = '';
			$return[$i]['modality'] = $this->shared->cleanDbString($row['mods_in_study']);
			$return[$i]['patientid'] = $cs->utf8Encode($this->shared->cleanDbString((string) $row['pat_id']));
			$return[$i]['patientname'] = $cs->utf8Encode($this->shared->buildPersonName($row['pat_fn'],
				$row['pat_gn']));
			$return[$i]['patientbirthdate'] = $this->shared->cleanDbString((string) $row['pat_birthdate']);
			$return[$i]['reviewed'] = '';

			$notes = 2;		// 2 - no notes table, 1 - notes exist, 0 - notes empty
			if ($dbms == "POSTGRESQL")
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
