<?php
/*
	Original name: flv.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ..\flv.php
 */
namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';
include_once __DIR__ . '/../flv.php';


class Flv extends \flv
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
		(
			"load" => array(
				"description" => "Set up a conversion and poll its status",
				"access" => "remote")
		);
	}
	
	public function load($uid, $type = 'flv')
	{
		return parent::load($uid, $type);
	}
}
