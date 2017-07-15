<?php
/*
	Original name: Plugin.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Builds a list of available plugins
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;


class Plugin
{
	private $log;
	protected $backend = null;

	public function __construct()
	{
		$this->log = new Logging();

		$this->methodTable = array
		(
			"getPluginList" => array(
				"description" => "plugin list",
				"access" => "remote"),
			"loadPlugin" => array(
				"description" => "load plugin",
				"access" => "remote")
		);
	}


	/**
	 * return new or existing Backend
	 * If the underlying AuthDB must be connected to the DB, then will request the connection once more.
	 *
	 * @global Backend $backend
	 * @param array $parts - Names of PACS parts that will be initialized
	 * @param boolean $withConnection - is a DB connection required?
	 * @return Backend
	 */
	function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	public function getPluginList()
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('Not authenticated');
			return "";
		}

		$plugins_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'plugin';
		$this->log->asDump("directory: '$plugins_dir'");

		if (file_exists($plugins_dir))
			$return = $this->readFiles($plugins_dir);
		else
			$return['error'] = '';

		$this->log->asDump('$return = ',$return);

		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function readFiles($directory)
	{
		$return['error'] = "Plugin directory doesn't exist";
		$return['count'] = 0;

		if (!file_exists($directory))
			return $return;

		$dirr = array();
		if ($handle = opendir($directory))
		{
			while (false !== ($entry = readdir($handle)))
			{
				if ($entry != "." && $entry != "..")
				{
					if (is_dir($directory . DIRECTORY_SEPARATOR . $entry))
					{
						$dirr[$entry] = array();
						$dirr[$entry]['name'] = $entry;
						$dirr[$entry]['xml'] = '';
						$item = $directory . DIRECTORY_SEPARATOR . $entry. DIRECTORY_SEPARATOR . $entry.'.xml';
						if (file_exists($item))
							$dirr[$entry]['xml'] = file_get_contents($item);
					}
				}
			}
			closedir($handle);
		}

		if (count($dirr) > 0)
		{
			$return['files'] = $dirr;
			$return['count'] = count($dirr);
			$return['error'] = '';
		}
		else
			$return['error'] = "";
		return $return;
	}


	public function loadPlugin($imageId)
	{
		$this->log->asDump('begin '.__METHOD__);

		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('Not authenticated');
			return "";
		}
		//NOT FINISHED
		//need to add methods, what to return
		$return['error'] = "";

		$this->log->asDump('$return = ',$return);

		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
