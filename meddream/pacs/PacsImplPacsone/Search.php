<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;
use Softneta\MedDream\Core\Pacs\PacsShared as GenericPacsShared;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;

require_once __DIR__ . '/PacsOneUser.php';


/** @brief Implementation of SearchIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	/** @brief Disable modality aggregation.

		With aggregation (set to false), all modalities from the study will be reported.
		This, however, exhibits more load to the database and therefore slower responses.

		Without aggregation (set to true), modality is almost randomly taken from some
		series of the study.
	 */
	const NO_MODALITY_AGGREGATE = true;


	protected $pu;          /**< @brief Instance of PacsOneUser */


	/** @brief Makes sure the local instance of PacsOneUser is created */
	public function __construct(Logging $logger, AuthDB $authDb, Configuration $cfg, CharacterSet $cs,
		ForeignPath $fp, PacsGw $gw, QR $qr, GenericPacsShared $shared)
	{
		parent::__construct($logger, $authDb, $cfg, $cs, $fp, $gw, $qr, $shared);
		$this->pu = new PacsOneUser($logger, $authDb);
	}


	/** @brief Makes sure the local instance of PacsOneUser is initialized */
	public function importCommonData($data)
	{
		$e = $this->pu->importCommonData($data);
		if ($e)
			return $e;
		return parent::importCommonData($data);
	}


	public function getStudyCounts()
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
		{
			return $this->gw->getStudyCounts();
		}

		$this->log->asDump('begin ' . __METHOD__);

		$return = array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);

		if (!$this->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return $return;
		}

		$col = $this->commonData['F_STUDY_DATE'];
		$sql = 'SELECT "d1" AS period, COUNT(*) AS recordscount' .
				" FROM study WHERE $col >= CURDATE()" .
			' UNION ' .
			'SELECT "d3" AS period, COUNT(*) AS recordscount' .
				" FROM study WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)" .
			' UNION ' .
			'SELECT "w1" AS period, COUNT(*) AS recordscount' .
				" FROM study WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)" .
			' UNION ' .
			'SELECT "m1" AS period, COUNT(*) AS recordscount' .
				" FROM study WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)" .
			' UNION ' .
			'SELECT "y1" AS period, COUNT(*) AS recordscount' .
				" FROM study WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)" .
			' UNION ' .
			'SELECT "any" AS period, COUNT(*) AS recordscount FROM study';
				/* DAY, MONTH, YEAR supported in MySQL 3.2.3+ -- effectively all versions */
		$this->log->asDump('$sql = ', $sql);

		$rs = $this->authDB->query($sql);
		if (!$rs)
			$this->log->asErr("query failed: " . $this->authDB->getError());
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
			$where .= ' AND (';

			$patientID = $authDB->sqlEscapeString($cs->utf8Decode($actions['entry'][0]));
			$col = $this->commonData['F_TBL_NAME_PATIENT'];

			$where .= "$col.origid='$patientID' OR $col.origid LIKE '$patientID" . '[%' . "'";
				/* After coercion, ID ::= new_id, '[', orig_id, ']'; */

			/* probably used by PacsOne's applet.php if multiple patients selected? */
			for ($i = 1; $i < sizeof($actions['entry']); $i++)
			{
				$patientID = $authDB->sqlEscapeString($cs->utf8Decode($actions['entry'][$i]));
				$where .= " OR patient.origid='$patientID'";
			}
			$where .= ')';
		}

		/* likely required for PacsOne's applet.php and multiple study selection */
		if ($actions && (strtoupper($actions['action']) == 'SHOW') &&
			(strtoupper($actions['option']) == 'STUDY') &&
			((int) sizeof((array) $actions['entry']) > 0))
		{
			$studyUID = $authDB->sqlEscapeString($actions['entry'][0]);

			$tbl = $this->commonData['F_TBL_NAME_STUDY'];
			$col = $this->commonData['F_STUDY_UUID'];

			$where .= " AND ($tbl.$col='$studyUID'";

			for ($i = 1; $i < sizeof($actions['entry']); $i++)
			{
				$studyUID = $authDB->sqlEscapeString($actions['entry'][$i]);

				$where .= " OR $tbl.$col='$studyUID'";
			}
			$where .= ")";
		}

		/* PacsOne, a user without 'viewprivate' privilege: studies with
		   .private=1 are to be viewed only by a corresponding Referring
		   Physician or Reading Physician
		 */
		if (!$actions && !$this->pu->hasPrivilege('view'))
		{
			$firstName = $authDB->sqlEscapeString($this->pu->firstName());
			$lastName = $authDB->sqlEscapeString($this->pu->lastName());

			$where .= ' AND (' . $this->commonData['F_TBL_NAME_STUDY'] . '.private=0';
			$where .= " OR referringphysician LIKE '%$firstName%$lastName%'";
			$where .= " OR referringphysician LIKE '%$lastName%$firstName%'";
			$where .= " OR readingphysician LIKE '%$firstName%$lastName%'";
			$where .= " OR readingphysician LIKE '%$lastName%$firstName%')";
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

			if ($criteriaName == "patientname")
			{
				$where .= " AND ";
				if ($dbms == 'SQLITE3')
					$where .= "LOWER(patient.lastname || patient.firstname) LIKE LOWER('%$criteriaText%')";
				else
				{
					$tbl = $this->commonData['F_TBL_NAME_PATIENT'];
					$where .= "LOWER(CONCAT($tbl.lastname, ' ', $tbl.firstname)) " .
						"LIKE LOWER('%$criteriaText%')";
						/* CONCAT is safe here as those two columns are "NOT NULL" */
				}
			}
			else if ($criteriaName == "ris_scheduled_user_id")
				$where .= " AND ($criteriaName = $criteriaText)";
			else if ($criteriaName == "repo_approved_datetime")
				$where .= " AND ($criteriaName $criteriaText)";
			else if ($criteriaName == "repo_ready_review")
				$where .= " AND ($criteriaName = $criteriaText) AND repo_approved_datetime IS NULL" .
					" AND (repo_conclusion IS NULL OR repo_conclusion = '')";
			else
			{
				$tbl = $this->commonData['F_TBL_NAME_STUDY'];
				$criteriaName = "$tbl.$criteriaName";
				$where .= " AND LOWER($criteriaName) LIKE LOWER('%$criteriaText%')";
			}
		}

		/* dates */
		if ($fromDate != '')
		{
			$fromDate = $authDB->sqlEscapeString($fromDate);

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "from $fromDate";

			$tbl = $this->commonData['F_TBL_NAME_STUDY'];
			$col = $this->commonData['F_STUDY_DATE'];
			$where .= ' AND ';
			if ($dbms == 'SQLITE3')
				$where .= "strftime('%Y.%m.%d', study.$col) >= '$fromDate'";
			else
			{
				$fromDate = str_replace('.', '-', $fromDate);	/* for correct matching */
				$where .= "$tbl.$col >= '$fromDate'";
			}
		}

		if ($toDate != '')
		{
			$toDate = $authDB->sqlEscapeString($toDate);

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "to $toDate";

			$tbl = $this->commonData['F_TBL_NAME_STUDY'];
			$col = $this->commonData['F_STUDY_DATE'];
			$where .= ' AND ';
			if ($dbms == 'SQLITE3')
				$where .= "strftime('%Y.%m.%d', study.$col) <= '$toDate'";
			else
			{
				$toDate = str_replace('.', '-', $toDate);	/* for correct matching */
				$where .= "$tbl.$col <= '$toDate'";
			}
		}

		/* modality checkboxes */
		$modAll = true;
		$modSelected = false;
		for ($i = 0; $i < sizeof($mod); $i++)
		{
			$mod[$i]['name'] = $authDB->sqlEscapeString($mod[$i]['name']);
			$mod[$i]['selected'] = (bool) $mod[$i]['selected'];
			
			if (!$mod[$i]['selected'] || isset($mod[$i]['custom'])) 
				$modAll = false;
		}
		
		$this->log->asDump('$modAll = ', $modAll);
		if (!$modAll)
		{
			$auditMsgMod = '';

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
					$where .= "modality='$modality'";
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
		if ($modAll || !$modSelected)
			$where .= " AND (series.modality IS NULL OR (series.modality <> 'KO' AND series.modality <> 'PR'))";

		/* must limit the number of studies returned */
		$listMax = (int) $listMax;
		$listLimit = 0;			/* a different approach for OCI8 */
		$limit_suf = '';
		if ($listMax)
		{
			$listLimit = $listMax + 1;
			$limit_suf = " LIMIT $listLimit";
		}

		/* build the final query */
		if (Constants::FOR_RIS)
			$sql = 'SELECT rispatient.lastname, rispatient.middlename, rispatient.firstname,' .
					' rispatient.ris_last_name, rispatient.ris_middle_name, rispatient.ris_first_name,' .
					' rispatient.birthdate, rispatient.sex, rispatient.ris_pnt_id, rispatient.ris_pesel,' .
					' series.modality,' .
					' riscaorder.caor_id,' .
					' report.repo_id,' .
					' risstudy.id, risstudy.patientid,' .
					' risstudy.description, risstudy.' . $this->commonData['F_STUDY_DATE'] .
					', risstudy.' . $this->commonData['F_STUDY_TIME'] . ',' .
					' risstudy.' . $this->commonData['F_STUDY_UUID'] . ', risstudy.reviewed,' .
					' risstudy.accessionnum, risstudy.referringphysician, risstudy.readingphysician,' .
					' risstudy.sourceae, risstudy.received, risstudy.ris_urgent,' .
					' ris_scheduled_user_id, ris_referring_inst_id, ris_second_referring_inst_id,' .
					' ris_exam_description, ris_memo, ris_submitted_for_review,' .
					' studynotes.id IS NOT NULL AS notes' .
				' FROM risstudy ' .
				' LEFT JOIN studynotes ON risstudy.uuid=studynotes.uuid' .
				' LEFT JOIN (SELECT * FROM risreport WHERE risreport.repo_del_datetime IS NULL) AS report' .
					 ' ON risstudy.uuid=report.study_uuid' .
				' LEFT JOIN riscaorder ON risstudy.uuid=riscaorder.caor_uuid,' .
					' rispatient, series' .
				' WHERE rispatient.origid = risstudy.patientid' .
					' AND risstudy.ris_del_datetime IS NULL' .
					' AND risstudy.' . $this->commonData['F_STUDY_UUID'] . "=series.studyuid$where" .
				' GROUP BY risstudy.' . $this->commonData['F_STUDY_UUID'] .
				' ORDER BY risstudy.' . $this->commonData['F_STUDY_DATE'] . ' DESC, risstudy.' .
					$this->commonData['F_STUDY_TIME'] . "DESC $limit_suf";
		elseif (Constants::FOR_WORKSTATION)
			$sql = 'SELECT study.id, study.patientid, patient.lastname, patient.firstname,' .
					' patient.birthdate, patient.sex, series.modality, study.description, study.' .
					$this->commonData['F_STUDY_DATE'] . ', study.' . $this->commonData['F_STUDY_TIME'] .
					', study.' . $this->commonData['F_STUDY_UUID'] . ', study.reviewed, study.accessionnum,' .
					' study.referringphysician, study.readingphysician, study.sourceae, study.received,' .
					' studynotes.id IS NOT NULL AS notes' .
				' FROM study' .
				' LEFT JOIN studynotes ON study.' . $this->commonData['F_STUDY_UUID'] . '=studynotes.' .
					$this->commonData['F_STUDYNOTES_UUID'] . ', patient, series' .
				' WHERE patient.origid=study.patientid AND patient.studyuid=study.' . $this->commonData['F_STUDY_UUID'] .
					' AND study.' . $this->commonData['F_STUDY_UUID'] . "=series.studyuid$where" .
				' GROUP BY study.' . $this->commonData['F_STUDY_UUID'] .
				' ORDER BY study.' . $this->commonData['F_STUDY_DATE'] . ' DESC, study.' .
					$this->commonData['F_STUDY_TIME'] . " DESC$limit_suf";
		elseif (self::NO_MODALITY_AGGREGATE)
			$sql =  'SELECT study.id, study.patientid, patient.lastname, patient.firstname,' .
					' patient.birthdate, patient.sex, series.modality, study.description, study.' .
					$this->commonData['F_STUDY_DATE'] . ', study.' . $this->commonData['F_STUDY_TIME'] .
					', study.' . $this->commonData['F_STUDY_UUID'] . ', study.reviewed, study.accessionnum,' .
					' study.referringphysician, study.readingphysician, study.sourceae, study.received,' .
					' studynotes.id IS NOT NULL AS notes' .
				' FROM study' .
				' LEFT JOIN studynotes ON study.' . $this->commonData['F_STUDY_UUID'] . '=studynotes.' .
					$this->commonData['F_STUDYNOTES_UUID'] .
				' LEFT JOIN patient ON study.patientid=patient.origid, series' .
				' WHERE study.' . $this->commonData['F_STUDY_UUID'] . '=series.studyuid' .
					$where .
				' GROUP BY study.' . $this->commonData['F_STUDY_UUID'] .
				' ORDER BY ' . $this->commonData['F_STUDY_DATE'] . ' DESC, ' .
					$this->commonData['F_STUDY_TIME'] . " DESC$limit_suf";
		else
			$sql =  'SELECT id, patientid, lastname, firstname, birthdate, sex,' .
					" GROUP_CONCAT(DISTINCT modality ORDER BY modality SEPARATOR '\\\\') AS modality, description," .
					$this->commonData['F_STUDY_DATE'] . ', ' . $this->commonData['F_STUDY_TIME'] . ', ' .
					$this->commonData['F_STUDY_UUID'] . ', reviewed, accessionnum, referringphysician,' .
					' readingphysician, sourceae, received, notes' .
				' FROM (' .
					'SELECT study.id, study.patientid, patient.lastname, patient.firstname,' .
						' patient.birthdate, patient.sex, series.modality, study.description, study.' .
						$this->commonData['F_STUDY_DATE'] . ', study.' . $this->commonData['F_STUDY_TIME'] .
						', study.' . $this->commonData['F_STUDY_UUID'] . ', study.reviewed, study.accessionnum,' .
						' study.referringphysician, study.readingphysician, study.sourceae, study.received,' .
						' studynotes.id IS NOT NULL AS notes' .
					' FROM study' .
					' LEFT JOIN studynotes ON study.' . $this->commonData['F_STUDY_UUID'] . '=studynotes.' .
						$this->commonData['F_STUDYNOTES_UUID'] .
					' LEFT JOIN patient ON study.patientid=patient.origid, series' .
					' WHERE study.' . $this->commonData['F_STUDY_UUID'] . '=series.studyuid' .
						$where .
					' GROUP BY study.' . $this->commonData['F_STUDY_UUID'] . ', studynotes.id IS NOT NULL, modality' .
				') AS tmp' .
				' GROUP BY id, patientid, lastname, firstname, birthdate, sex, description, ' .
					$this->commonData['F_STUDY_DATE'] . ', ' . $this->commonData['F_STUDY_TIME'] . ', ' .
					$this->commonData['F_STUDY_UUID'] . ', reviewed, accessionnum, referringphysician,' .
					' readingphysician, sourceae, received, notes' .
				' ORDER BY ' . $this->commonData['F_STUDY_DATE'] . ' DESC, ' .
					$this->commonData['F_STUDY_TIME'] . " DESC$limit_suf";

		$this->log->asDump('$sql = ', $sql);
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error, see logs';
			$return['count'] = 0;
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, $auditMsg);
			return $return;
		}

		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump("#$i: \$row = ", $row);

			$return[$i]['uid'] = (string) $row[$this->commonData['F_STUDY_UUID']];
			$return[$i]['id'] = $cs->utf8Encode((string) $row['id']);
			$return[$i]['patientid'] = $cs->utf8Encode((string) $row['patientid']);
			$return[$i]['patientname'] = $cs->utf8Encode($this->shared->buildPersonName($row['lastname'],
				$row['firstname']));
			$return[$i]['patientbirthdate'] = (string) $row['birthdate'];
			$return[$i]['patientsex'] = (string) $row['sex'];
			$return[$i]['modality'] = (string) $row['modality'];
			$return[$i]['description'] = $cs->utf8Encode((string) $row['description']);
			$return[$i]['date'] = (string) $row[$this->commonData['F_STUDY_DATE']];
			$return[$i]['time'] = (string) $row[$this->commonData['F_STUDY_TIME']];
			$return[$i]['notes'] = ((bool) $row['notes']) ? 1 : 0;
			$return[$i]['datetime'] = trim($return[$i]['date'] . ' ' . $return[$i]['time']);
			$return[$i]['reviewed'] = (string) $row['reviewed'];
			$return[$i]['accessionnum'] = (string) $row['accessionnum'];
			$return[$i]['referringphysician'] = $cs->utf8Encode($this->shared->cleanDbString((string) $row['referringphysician']));
			$return[$i]['readingphysician'] = $cs->utf8Encode($this->shared->cleanDbString((string) $row['readingphysician']));
			$return[$i]['sourceae'] = (string) $row['sourceae'];
			$return[$i]['received'] = (string) $row['received'];
			if (Constants::FOR_RIS)
			{
				$return[$i]['uuid'] = (string) $row[$this->commonData['F_STUDY_UUID']];
				$return[$i]['ris_pnt_id'] = (string) $row['ris_pnt_id'];
				$return[$i]['lastname'] = $cs->utf8Encode((string) $row['lastname']);
				$return[$i]['middlename'] = $cs->utf8Encode((string) $row['middlename']);
				$return[$i]['firstname'] = $cs->utf8Encode((string) $row['firstname']);
				$return[$i]['ris_first_name'] = $row['ris_first_name'];
				$return[$i]['ris_middle_name'] = $row['ris_middle_name'];
				$return[$i]['ris_last_name'] = $row['ris_last_name'];
				$return[$i]['ris_urgent'] = (bool) $row['ris_urgent'];
				$return[$i]['ris_scheduled_user_id'] = $row['ris_scheduled_user_id'];
				$return[$i]['ris_referring_inst_id'] = $row['ris_referring_inst_id'];
				$return[$i]['ris_second_referring_inst_id'] = $row['ris_second_referring_inst_id'];
				//$return[$i]['ris_exam_description'] = $row['ris_exam_description'];
				$return[$i]['ris_memo'] = $row['ris_memo'];
				$return[$i]['ris_pesel'] = $row['ris_pesel'];
				$return[$i]['repo_id'] = $row['repo_id'];
				$return[$i]['caor_id'] = $row['caor_id'];
				$return[$i]['ris_submitted_for_review'] = $row['ris_submitted_for_review'];
			}
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
