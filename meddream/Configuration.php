<?php
/*
	Original name: Configuration.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified access to contents of config.php (via an array)
 */

namespace Softneta\MedDream\Core;

require_once 'autoload.php';

/** @brief Unified access to contents of config.php.

	Loaded contents are accessible as an array. This form is very convenient
	not only for various parts of MedDream, but also for unit testing.

	Furthermore, due to a single place where config.php is handled, it's easier
	to change the file name or even its format.

	An intended pattern is to dedicate the instance of this class to a particular
	configuration file:
@code
	$configTop = new Configuration();
	$configTop->load();
	$configPacs0 = new Configuration($configTop->data['pacs0']);
	$configPacs0->load();
@endcode
 * @todo Read $data item via function with default value
 */
class Configuration
{
	/** @brief Full path to the currently loaded configuration file */
	protected $configFilePath;

	/** @brief Name (with extension) of the currently loaded configuration file */
	protected $configFileName;

	/** @brief Currently loaded data

		Every variable @c $var loaded from configuration file is accessible here
		as <tt>$data[$var]</tt>.

		If @link load() @endlink has not been called, this is a string.
	 */
	public $data;


	/** @brief A simple constructor that ensures default values

		@param $configFilePath Override path to the file. @c null (default) or empty string is
		                       replaced with 'config.php' in the directory of Configuration.php.
	 */
	public function __construct($configFilePath = null)
	{
		if (is_null($configFilePath) || !strlen($configFilePath))
			$this->configFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
		else
			$this->configFilePath = $configFilePath;
		$this->configFileName = basename($this->configFilePath);

		$this->unload();
	}


	/** @brief Just a getter for @link $configFileName @endlink. */
	public function getFileName()
	{
		return $this->configFileName;
	}


	/** @brief Load configuration file into @link $data @endlink.

		@retval string Error message if not empty. In case of error, @link $data @endlink
		               remains an empty array.
	 */
	public function load()
	{
		$this->unload();

		clearstatcache(false, $this->configFilePath);

		/* some validation */
		if (!file_exists($this->configFilePath))
			return "file {$this->configFileName} does not exist\n(full path: '{$this->configFilePath}')";
		if (!self::endsWithPhpClosingTags(file_get_contents($this->configFilePath)))
			return "file {$this->configFileName} has an invalid ending, please make sure it ends with \"?>\" without any empty lines after";

		ob_start();
		include($this->configFilePath);
		$this->unexpectedOutput = ob_get_clean();
		if (strlen($this->unexpectedOutput))
			return "unexpected output while parsing {$this->configFilePath}: \"'" .
				addcslashes(substr($this->unexpectedOutput, 0, 50), "\000..\037\\") .
				'"';

		/* collect variables from config.php

			Must explicitly discard parameters, superglobals, etc.
		 */
		$loaded_config_contents = get_defined_vars();
		$cleaned_config_contents = array();
		foreach ($loaded_config_contents as $k => $v)
			if (!in_array($k, array('argv', 'argc', 'GLOBALS', '_POST', '_GET', '_COOKIE', '_FILES', '_SERVER')))
				$cleaned_config_contents[$k] = $v;
		$this->data = $cleaned_config_contents;

		return '';
	}


	/** @brief Discard @link $data @endlink when not needed.

		Might be useful after loading a large file, in order to conserve resources.

		@note $data is set to a string, therefore indexing errors will act as a
		      reminder that load() has not been called.
	 */
	public function unload()
	{
		$this->data = 'not loaded';
	}


	/** @brief Setter for @link $data @endlink.

		For unit tests and advanced uses.
	 */
	public function setData($data)
	{
		$this->data = $data;
	}


	/** @brief Verifies if last characters contain a correct PHP closing tag

		A common user error is multiple whitespace/newline after those tags. This
		redundant data is output unexpectedly and can corrupt network protocols,
		lead to "headers already sent" warnings, etc.

		The user can similarly add newlines etc before the starting tag. However
		this is a lot easier to notice, even when using a dumb text editor. load()
		catches it differently, just in case.

		@param $content Contents of a PHP file (or at least a few final characters)
	 */
	static function endsWithPhpClosingTags($content)
	{
		$validFileEndings = array("?>\r\n", "?>\n", "?>");
		foreach ($validFileEndings as $ending)
		{
			$content = substr($content, -strlen($ending));
			if ($content === $ending)
				return true;
		}
		return false;
	}
}
