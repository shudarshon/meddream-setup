<?php
/*
	Original name: DicomTags.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ..\DicomTags.php
 */
namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\DicomTags as Tags;


class DicomTags extends Tags
{
	public function __construct($authDb = null)
	{
		parent::__construct();

		$this->methodTable = array
		(
			"getTags" => array(
				"description" => "get DICOM tags",
				"access" => "remote")
		);
	}

	public function getRootDir()
	{
		return dirname(__DIR__) . DIRECTORY_SEPARATOR;
	}


	/**
	 * get tag list from dicom header
	 * 
	 * @param string $uid - image uid
	 * @return array
	 */
	public function getTags($uid)
	{
		return parent::getTags($uid);
	}
}
