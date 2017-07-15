<?php

namespace Softneta\MedDream\Core\Pacs\Wado;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='WADO'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	/** @brief Implementation of SearchIface::getStudyCounts().

		This %PACS won't ever implement the function, so this stub is here
		just to silence the warning from the default implementation.
	 */
	public function getStudyCounts()
	{
		return array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);
	}


	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $actions, ', ', $searchCriteria, ', ',
			$fromDate, ', ', $toDate, ', ', $mod, ', ', $listMax, ')');
		$dbms = $this->commonData['dbms'];

		$audit = new Audit('SEARCH');

		$authDB = $this->authDB;
		if (!$this->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false);
			return array('error' => 'not authenticated');
		}

		$return = array('error' => '');
		$auditMsg = '';
		$cs = $this->cs;

		$patient_id = '';
		$patient_name = '';
		$study_id = '';
		$acc_num = '';
		$study_desc = '';
		$ref_phys = '';

		/* convert objects to arrays

			Objects come from Flash since amfPHP 2.0. HTML due to some reason also
			passes a JSON-encoded object instead of an array.
		 */
		if (is_object($actions))
			$actions = get_object_vars($actions);
		for ($i = 0; $i < count($searchCriteria); $i++)
			if (is_object($searchCriteria[$i]))
				$searchCriteria[$i] = get_object_vars($searchCriteria[$i]);

		/* different behavior when searching for a patient via HIS integration */
		$patientFromAction = false;
		if ($actions && (strtoupper($actions['action'])=="SHOW") &&
				(strtoupper($actions['option']) == "PATIENT") &&
				((int) sizeof((array) $actions['entry']) > 0))
		{
			$patient_id = $cs->utf8Decode($actions['entry'][0]);
			$auditMsg .= "patientid '$patient_id'";
			$patientFromAction = true;
		}

		/* convert dates to DICOM format

			The '.' separator is hardcoded in the Flash viewer.
		 */
		$fromDate = str_replace('.', '', $fromDate);
		$toDate = str_replace('.', '', $toDate);

		/* convert $searchCriteria to separate variables */
		for ($i = 0; $i < count($searchCriteria); $i++)
		{
			$criteriaName = strtolower($searchCriteria[$i]['name']);
			$criteriaText = trim($this->cs->utf8Decode($searchCriteria[$i]['text']));

			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "$criteriaName '$criteriaText'";

			if ($criteriaName == "patientid")
			{
				if ($patientFromAction)
					$this->log->asWarn("Patient ID '$patient_id' already specified, ignoring another one: '$criteriaText'");
				else
					$patient_id = $criteriaText;
				continue;
			}
			if ($criteriaName == "patientname")
			{
				$patient_name = $criteriaText;
				continue;
			}
			if ($criteriaName == "id")
			{
				$study_id = $criteriaText;
				continue;
			}
			if ($criteriaName == "accessionnum")
			{
				$acc_num = $criteriaText;
				continue;
			}
			if ($criteriaName == "description")
			{
				$study_desc = $criteriaText;
				continue;
			}
			if ($criteriaName == "referringphysician")
			{
				$ref_phys = $criteriaText;
				continue;
			}
			if ($criteriaName == "readingphysician")
			{
				$errorMessage = '[WADO] Searches by Reading Physician are not supported';
				$audit->log(false, $auditMsg);
				$this->log->asErr($errorMessage);
				return array('count' => 0, 'error' => $errorMessage);
			}
			if ($criteriaName == "sourceae")
			{
				$errorMessage = '[WADO] Searches by Source AE Title are not supported';
				$audit->log(false, $auditMsg);
				$this->log->asErr($errorMessage);
				return array('count' => 0, 'error' => $errorMessage);
			}
		}

		$modList = array();
		for ($i = 0; $i < count($mod); $i++)
			if ($mod[$i]['selected'])
				$modList[] = $mod[$i]['name'];
		$modalities = implode("\\", $modList);
		if (strlen($modalities))
		{
			if (strlen($auditMsg))
				$auditMsg .= ', ';
			$auditMsg .= "modality $modalities";
		}
		if (!$this->commonData['multiple_modality_search'] && (count($modList) > 1))
		{
			$audit->log(false, $auditMsg);
			return array('count' => 0, 'error' => '[WADO] Please select only one modality');
		}

		foreach (array('from' => $fromDate, 'to' => $toDate) as $key => $value)
			if (!empty($value))
			{
				if (!empty($auditMsg))
					$auditMsg .= ', ';

				$auditMsg .= "$key $value";
			}
		$return = $this->qr->findStudies($patient_id, $patient_name, $study_id,
			$acc_num, $study_desc, $ref_phys, $fromDate, $toDate, $modalities);
		if (strlen($return['error']))
			$audit->log(false, $auditMsg);
		else
		{
			$audit->log($return['count'] . ' result(s)', $auditMsg);

			/* provide an error message in addition to validation by external.php

				Shall be visible only during initial search, where the only criterion is
				Patient ID passed through the action.
			 */
			if (!$return['count'] && $patientFromAction && !count($searchCriteria))
				$return['error'] = "Patient '$patient_id' not found";
		}

		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
