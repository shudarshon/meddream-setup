<?php
/*
	Original name: Jobs.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		An amfPHP wrapper for ../Jobs.php
 */
namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';
use Softneta\MedDream\Core\Jobs as CoreJobs;

if ((function_exists('session_status') 
  && session_status() !== PHP_SESSION_ACTIVE) || !session_id()) 
  @session_start();

class Jobs extends CoreJobs
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
		(
			"addJob" => array(
				"description" => "add new job",
				"access" => "remote")
		);
	}


	/** @brief Add a new job.

		@param array $data - array('jobName'=>'..some identify', other data);
		@return array - array('error'=>'...',
		                      'jobId'=>'..',
		                      'jobName'=>'..some identify');
	 */
	public function addJob($data)
	{
		/* downgrade to amfPHP < 2.0 */
		if (is_object($data))
			$data = get_object_vars($data);
		
		return parent::addJob($data);
	}
}
?>
