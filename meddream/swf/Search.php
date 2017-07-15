<?php
/*
	Original name: search.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>
		al <audrius.liutkus@softneta.lt>

	Description:
		Provides data for the Search dialog
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';


class Search
{
	public function __construct($backend = null)
	{
		$this->log = new Logging();
		if (is_null($backend))
			$this->backend = new Backend(array('Search'));
		else
			$this->backend = $backend;

		$this->methodTable = array
		(
			"getList" => array(
				"description" => "Get list of Studies",
				"access" => "remote")
		);
	}


	/* provides data for the Search dialog (the Search button in
	   MedDream UI is pressed)

		Valid data:
			array('error' => '',
				'count' => ?,
				0 => array('uid' => ?, 'id' => ?, 'patientid' => ?, 'patientname' => ?,
					'patientbirthdate' => ?, 'patientsex' => ?, 'modality' => ?, 'description' => ?,
					'date' => ?, 'time' => ?, 'notes' => ?, 'datetime' => ?, 'reviewed' => ?,
					'accessionnum' => ?, 'referringphysician' => ?, 'readingphysician' => ?,
					'sourceae' => ?, 'received' => ?),
				1 => ... )

		Failure:
			$return['error'] (string) is shown in the UI
	 */
	public function getList($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$r = $this->backend->pacsSearch->findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax);

		$this->log->asDump('end ' . __METHOD__);
		return $r;
	}
}
