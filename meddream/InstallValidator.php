<?php
/*
	Original name: InstallValidator.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Global actions of installation check
 */

namespace Softneta\MedDream\Core;

require_once __DIR__ . '/autoload.php';


/** @brief Checks for common installation problems. */
class InstallValidator
{
	private $translation = null;        /* An instance of Translation */


	/** @brief Initialize the class.

		@param Translation $translation
	 */
	public function __construct($translation = null)
	{
		$this->translation = $translation;
	}


	/** @brief Obtain an instance of Translation (create/initialize if necessary). */
	public function getTranslation()
	{
		if (is_null($this->translation))
		{
			$this->translation = new Translation();
			$this->translation->configure();
			$this->translation->load();
		}
		return $this->translation;
	}


	/** @brief Return messages for non-writeable directories.

		@return array Elements are individual message strings
	 */
	public function getNotWritableErrors()
	{
		$tr = $this->getTranslation();
		$messages = array();

		$r = $this->testWriteable(dirname(__FILE__));
		if ($r)
		{
			$messages[] = $tr->translate('login\testWriteable',
				'Error: the MedDream directory is not writeable (hint: {r})',
				array('{r}' => $r));
		}
		$r = $this->testWriteable(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp');
		if ($r)
		{
			$messages[] = $tr->translate('login\testWriteableTemp',
				"Error: the 'temp' subdirectory is not writeable (hint: {r})",
				array('{r}' => $r));
		}
		$r = $this->testWriteable(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'log');
		if ($r)
		{
			$messages[] = $tr->translate('login\testWriteableLog',
				"Error: the 'log' subdirectory is not writeable (hint: {r})",
				array('{r}' => $r));
		}

		return $messages;
	}


	/** @brief Return a message for a wrong date.timezone settin

		@return array Elements are individual message strings
	 */
	public function getTimeZoneErrors()
	{
		$tr = $this->getTranslation();
		$messages = array();

		if ($this->testTimezone())
			$messages[] = $tr->translate('login\isTimezoneError',
				'Error: date.timezone not specified in php.ini');

		return $messages;
	}


	/** @brief Return messages related to PHP extensions (missing, wrong version, etc).

		@param string $pacs        Name of the PACS (<tt>$pacs</tt> in config.php)
		@param string $withPacsGw  <tt>$pacs_gateway_addr</tt> in config.php is not empty

		@return array Elements are individual message strings
	 */
	public function getExtentionErrors($pacs = '', $withPacsGw = false)
	{
		$tr = $this->getTranslation();
		$messages = array();

		if (!extension_loaded('gd') || !function_exists('gd_info'))
		{
			$messages[] = $tr->translate('login\missingExt',
				'Error: PHP extension "{n}" not installed',
				array('{n}' => 'gd'));
		}
		if (!function_exists('meddream_version'))
		{
			$messages[] = $tr->translate('login\missingExtMeddream',
				'Error: php_meddream.dll not installed',
				array('php_meddream.dll' => $this->getMedDreamExtensionName()));
		}
		$extensions = array('xml', 'SimpleXML', 'json');
		if (($pacs == 'DCMSYS') || ($pacs == 'GW') || $withPacsGw)
			$extensions[] = 'curl';
		$missingExt = array();
		foreach ($extensions as $extension)
		{
			if (!extension_loaded($extension))
				$messages[] = $tr->translate('login\missingExt',
					'Error: PHP extension "{n}" not installed',
					array('{n}' => $extension));
		}

		return $messages;
	}


	/** @brief Return messages related to PHP version.

		@return array Elements are individual message strings
	 */
	public function getPhpVersionErrors()
	{
		$tr = $this->getTranslation();
		$messages = array();

		$sapi = php_sapi_name();
		if (strpos($sapi, 'cgi') !== false)
		{
			$messages[] = $tr->translate('login\unsupportedCgi',
				'Error: PHP is integrated into the Web server via the "{s}" SAPI. This mode is not supported.',
				array('{s}' => $sapi));
		}
		$phpVersion = phpversion();
		if (version_compare($phpVersion, '5.3.0', '<'))
		{
			$messages[] = $tr->translate('login\oldPhpVersion',
				'Error: MedDream requires PHP 5.3.0 or higher (current PHP is {v})',
				array('{v}' => $phpVersion));
		}

		return $messages;
	}


	/** @brief Return all possible messages at once.

		@param string $pacs        Name of the PACS (<tt>$pacs in config.php</tt>)
		@param string $withPacsGw  <tt>$pacs_gateway_addr</tt> in config.php is not empty

		@return array Elements are individual message strings
	 */
	public function getErrors($pacs = '', $withPacsGw = false)
	{
		$messages1 = $this->getPhpVersionErrors();
		$messages2 = $this->getExtentionErrors($pacs, $withPacsGw);
		$messages3 = $this->getTimeZoneErrors();
		$messages4 = $this->getNotWritableErrors();
		return array_merge($messages1, $messages2, $messages3, $messages4);
	}


	/** @brief Return all possible messages at once, as a string

		@param string $pacs        Name of the PACS (<tt>$pacs in config.php</tt>)
		@param string $withPacsGw  <tt>$pacs_gateway_addr</tt> in config.php is not empty
		@param string $endline     Line separator to join messages with

		@return string
	 */
	public function getErrorsAsString($pacs = '', $withPacsGw = false, $endline = "\n")
	{
		$messages = $this->getErrors($pacs, $withPacsGw);
		return implode($endline, $messages);
	}


	/** @brief Test if directory is writeable.

		@param string $dir  Name or path of the directory

		Attempts to create, fill and remove a temporary file.

		@retval 0  Success
		@retval 1  Create failed
		@retval 2  Write failed
		@retval 3  Remove failed
	 */
	public function testWriteable($dir)
	{
		$result = 0;

		$file = $dir;
		$lc = $file[strlen($file) - 1];
		if (($lc != '\\') && ($lc != '/'))
			$file .= DIRECTORY_SEPARATOR;
		$file .= 'meddream_write_test.tmp';

		$fh = @fopen($file, 'wb');
		if (!$fh)
			$result = 1;
		else
		{
			if (@fwrite($fh, '...', 2) != 2)
				$result = 2;
			fclose($fh);

			if (!@unlink($file))
				if (!$result)		/* do not overwrite existing hint */
					$result = 3;
		}
		return $result;
	}


	/** @brief Detect if date.timezone (php.ini) is not configured.

		@retval true  Not configured
	 */
	public function testTimezone()
	{
		if (function_exists('error_get_last'))
		{
			$errorlevel = error_reporting(0);
			$e1 = error_get_last();
			$d = date("Ymd");
			$e2 = error_get_last();
			error_reporting($errorlevel);
			return ($e1 != $e2);
		}
		else
		{
			$errorlevel = error_reporting(E_ALL);
			ob_start();
			$d = date("Ymd");
			$r = ob_get_contents();
			ob_end_clean();
			error_reporting($errorlevel);
			return (strlen($r) != 0);
		}
	}


	/** @brief Build the expected name of the md-php-ext binary.

		@return string <tt>"php$VER_meddream$ARCH.$EXT"</tt>

		<tt>$VER</tt>: "5.3", "5.4", "5.5", "5.6", ...

		<tt>$ARCH</tt>: "-x86_64" or "" (Linux); "-VC6", "-VC9" or "" (Windows)

		<tt>$EXT</tt>: "dll", "so"
	*/
	public function getMedDreamExtensionName()
	{
		ob_start();
		phpinfo(INFO_GENERAL);
		$phpInformation = ob_get_clean();
		$phpVersion = phpversion();
		$vParts = explode('.', $phpVersion);

		if (count($vParts) >= 2)
			$VER = $vParts[0] . '.' . $vParts[1];
		else
			$VER = '';

		$ARCH = '';
		if (strpos($phpInformation, 'Windows') !== false)
		{
			$EXT = 'dll';
			if ($VER == '5.3')
			{
				preg_match('/VC\d/', $phpInformation, $matches);
				if (!empty($matches[0]))
				{
					if (($matches[0] == 'VC6') || ($matches[0] == 'VC9'))
						$ARCH = '-' . $matches[0];
				}
			}

			/* recent builds include x64, try to extract this */
			if (strpos($phpInformation, "\nArchitecture => x64\n"))
				$ARCH .= '-x64';
		}
		else	/* assume Linux */
		{
			$EXT = 'so';
			if (strpos($phpInformation, 'x86_64') !== false)
				$ARCH = '-x86_64';
		}
		return 'php' . $VER . '_meddream' . $ARCH . '.' . $EXT;
	}
}
