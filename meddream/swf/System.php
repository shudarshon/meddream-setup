<?php
/*
	Original name: System.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		A wrapper for ..\System.php
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\System as CoreSystem;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\AuthDB;


class System extends CoreSystem
{
	public function __construct()
	{
		parent::__construct();

		$this->methodTable = array
		(
			"license" => array(
				"description" => "License",
				"access" => "remote"),
			"connect" => array(
				"description" => "Connect",
				"access" => "remote"),
			"disconnect" => array(
				"description" => "Disconnect",
				"access" => "remote"),
			"refresh" => array(
				"description" => "Refresh",
				"access" => "remote"),
			"connections" => array(
				"description" => "Connections",
				"access" => "remote"),
			"saveSettings" => array(
				"description" => "Save Settings",
				"access" => "remote"),
			"packageExsist" => array(
				"description" => "Test URL for packages",
				"access" => "remote"),
			"saveStyle" => array(
				"description" => "save style sheets",
				"access" => "remote"),
			"register" => array(
				"description" => "register license",
				"access" => "remote"),
			"call3d" => array(
				"description" => "call remote 3D server",
				"access" => "remote"),
			"updateLanguage" => array(
				"description" => "set new language and return file",
				"access" => "remote")
		);
	}

	public function getRootDir()
	{
		return dirname(__DIR__) . DIRECTORY_SEPARATOR;
	}

	public function license($clientid)
	{
		return parent::license($clientid);
	}


	public function connect($clientid)
	{
		return parent::connect($clientid);
	}


	public function disconnect($clientid = '')
	{
		return parent::disconnect($clientid);
	}


	public function refresh($clientid = '', $uid = '')
	{
		return parent::refresh($clientid, $uid);
	}


	public function connections()
	{
		return parent::connections();
	}


	public function saveSettings($text)
	{
		return parent::saveSettings($text);
	}


	public function saveStyle($text)
	{
		$fileName = 'style.xml';

		$file = fopen($fileName, "w+");
		if ($file)
		{
			if (fwrite($file, $text) === FALSE)
			{
				fclose($file);
				return "Can't write to $fileName file";
			}
			fclose($file);
			return "";
		}
		return "Can't create $fileName file";
	}


	public function packageExsist($url, $version = "")
	{
		return parent::packageExsist($url, $version);
	}

	/* called after the "Register" button is pressed
		Returns either a string 'relogin' (Flash reacts to it automatically)
		or an error message.
	 */
	public function register($data)
	{
		return parent::register($data);
	}


	public function call3d($uid)
	{
		return parent::call3d($uid);
	}
	/**
	 * returns information about latest version
	 *
	 * @param array $data - array('version' => '4.07...',
	 *								'updateto' => '2016-11-12');
	 * @return array
	 */
	function latestVersion()
	{
		return parent::latestVersion();
	}
}
