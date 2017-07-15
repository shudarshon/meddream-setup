<?php
/*
	Original name: study.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		An amfPHP wrapper for ..\Study.php
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\Study as CoreStudy;

class Study extends CoreStudy
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
		(
			"getStudyList" => array(
				"description" => "",
				"access" => "remote"),
			"getSeriesList" => array(
				"description" => "",
				"access" => "remote"),
			"getImageList" => array(
				"description" => "",
				"access" => "remote"),
			"forward" => array(
				"description" => "",
				"access" => "remote"),
			"forwardStatus" => array(
				"description" => "",
				"access" => "remote"),
			"getForwardAEList" => array(
				"description" => "",
				"access" => "remote"),
			"getNotes" => array(
				"description" => "",
				"access" => "remote"),
			"getImageQualityList" => array(
				"description" => "",
				"access" => "remote"),
			"getInfoLabels" => array(
				"description" => "",
				"access" => "remote")
		);
	}


	/* provides a study data structure for data.php

		Valid data:
			array('error' = '', 'lastname' => ?, 'firstname' => ?, 'uid' => ?, 'patientid' => ?,
				'notes' => ?, 'sourceae' => ?, 'studydate' => ?,
				'count' => ?,
				0 => array('id' => ?, 'description' => ?, 'modality' => ?,
						'count' => ?,
						0 => array('id' => ?, 'numframes' => ?, 'path' => ?, 'xfersyntax' => ?,
							'bitsstored' => ?),
						1 => ...)
				1 => ...)

		Failure:
			$return['error'] (string) is not empty
	 */
	public function getStudyList($studyUID, $disableFilter = false, $fromCache = false)
	{
		return parent::getStudyList($studyUID, $disableFilter, $fromCache);
	}


	public function getSeriesList($seriesList)
	{
		return parent::getSeriesList($seriesList);
	}

	public function getImageList($imageList, $fromCache = false)
	{
		return parent::getImageList($imageList, $fromCache);
	}

	public function forward($studyUID, $sendToAE = '', $path = '')
	{
		$dir = getcwd();
		chdir(dirname(__DIR__));
		
		$result = parent::forward($studyUID, $sendToAE, $path);
		chdir($dir);
		
		return $result;
	}

	/*
		$dbjob: DcmSnd is used even with PacsOne

		return values:
			'' -- job failed, display a non-specific error message box
			'success' -- display the string below the button and finish
			anything else -- display it below the button and continue
	 */
	public function forwardStatus($id, $dbjob = 1)
	{
		$dir = getcwd();
		chdir(dirname(__DIR__));
		
		$result = parent::forwardStatus($id, $dbjob);
		chdir($dir);
		
		return $result;
	}

	public function getForwardAEList()
	{
		return parent::getForwardAEList();
	}

	public function getNotes($studyUID)
	{
		return parent::getNotes($studyUID);
	}

	/**
	 * collect image quality file list
	 *
	 * @param type $imageuid
	 * @return array
	 */
	public function getImageQualityList($imageuid)
	{
		return parent::getImageQualityList($imageuid);
	}
	
	public function getInfoLabels($instanceUid)
	{
		return parent::getInfoLabels($instanceUid);
	}
}
