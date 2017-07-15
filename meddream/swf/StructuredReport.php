<?php
/*
	Original name: SR.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ..\SR.php
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\SR;


class StructuredReport extends SR
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
			(
			"getHtml" => array(
				"description" => "convert SR to HTML",
				"access" => "remote")
		);
	}


	/* converts a SR file referenced by $imageId, to a HTML document

		Valid data:
			array('error' => '', 'html' => ?)

		Failure:
			$return['error'] (string) not empty, displayed in the UI
	 */

	public function getHtml($imageId)
	{
		return parent::getHtml($imageId);
	}
}
