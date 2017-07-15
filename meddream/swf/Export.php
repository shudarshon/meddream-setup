<?php
/*
	Original name: export.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ../export.php

 */
namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';
include_once __DIR__ . '/../export.php';


class Export extends \export
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
		(
			"media" => array(
				"description" => "Export Study to Media",
				"access" => "remote"),
			"status" => array(
				"description" => "Export Status",
				"access" => "remote"),
			"notes" => array(
				"description" => "Export Notes",
				"access" => "remote"),
			"vol" => array(
				"description" => "Get Export Data",
				"access" => "remote"),
			"deleteTemp" => array(
				"description" => "Delete Temp Dir",
				"access" => "remote"),
			"getVolumeSizes" => array(
				"description" => "Get Available Volume Types And Sizes",
				"access" => "remote")
		);
	}
	
	public function media($studyInstanceUID, $size = '650')
	{
		return parent::media($studyInstanceUID, $size);
	}
	
	public function status($id)
	{
		return parent::status($id);
	}
	
	public function notes($timestamp, $studyUID)
	{
		return parent::notes($timestamp, $studyUID);
	}
	
	public function vol($timestamp, $mediaLabel)
	{
		return parent::vol($timestamp, $mediaLabel);
	}
	
	public function deleteTemp($timestamp)
	{
		return parent::deleteTemp($timestamp);
	}
	
	public function getVolumeSizes()
	{
		return parent::getVolumeSizes();
	}
}
