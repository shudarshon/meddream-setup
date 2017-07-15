<?php

/** @brief Implementation for <tt>$pacs='%Conquest'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='%Conquest'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	const LINE_LEN_MAX = 1024;
		/**< @brief Maximum expected line length in getStorageDevices() */


	/** @brief Registry of Conquest storage devices

		An array which keys (in form @c MAG0 ... @c MAGn) point to corresponding
		base directories.
	 */
	public $storageDevices = array();


	/** @brief Read MAGDevice* configuration parameters from dicom.ini (Conquest)

		Updates $storageDevices.
	 */
	private function getStorageDevices()
	{
		$ini = $this->archiveDirPrefix . 'dicom.ini';

		$er = error_reporting(E_ALL);
		clearstatcache(false, $ini);
		if (!@is_file($ini))
			return "[Conquest] configuration file not found: $ini";

		/* warnings from parse_ini_file() are always visible to AMFPHP and
		   confuse the latter; therefore we'll need to parse the file ourselves
		 */
		$cfg = array();
		$file = @fopen($ini, "rt");
		if (!$file)
			return "[Conquest] unreadable: $ini";
		$linenum = 1;
		while (!feof($file))
		{
			/* if fgets() ever encounters a line longer than expected, the
			   data read is incomplete and we shall at least warn the user
			 */
			$line = fgets($file, self::LINE_LEN_MAX + 1);
			if (strlen($line) == self::LINE_LEN_MAX)
			{
				fclose($file);
				return "[Conquest] line #$linenum longer than " . self::LINE_LEN_MAX . ": $ini";
			}
			$linenum++;

			/* strip comments */
			$cmt = strpos($line, '#');
			if ($cmt === false)
				$line_nocmt = $line;
			else
				$line_nocmt = substr($line, 0, $cmt);
			$line_nocmt = trim($line_nocmt);
			if (!strlen($line_nocmt))
				continue;

			/* split into name and value */
			$nv = explode('=', $line_nocmt);
			if (count($nv) < 2)
				continue;
			$name = trim($nv[0]);
			$value = trim($nv[1]);

			/* preserve data that we look for */
			if (!strncmp($name, "MAGDevice", 9))		/* MAGDevices, MAGDeviceNNN, ... */
				$cfg[$name] = $value;
		}
		fclose($file);

		/* move to another array with different keys */
		$key = 'MAGDevices';
		if (!isset($cfg[$key]))
			return "[Conquest] parameter '$key' not found: $ini";
		$this->storageDevices = array();
		$count = (int) $cfg[$key];
		if ($count < 1)
			return "[Conquest] the parameter 'MAGDevices' ('" . $cfg['MAGDevices'] . "') must be at least 1: $ini";
		for ($i = 0; $i < $count; $i++)
		{
			/* sanity check */
			$key = "MAGDevice$i";
			if (!isset($cfg[$key]))
				return "[Conquest] 'MAGDevices' set to $count but '$key' not found: $ini";
			$dir = trim($cfg[$key]);
			if ($dir == "")
				return "[Conquest] '$key' is an empty string: $ini";

			/* sometimes paths are relative! */
			if (($dir[0] != '/') && ($dir[0] != '\\') && ($dir[1] != ':'))
			{
				if (($dir[0] == '.') && (($dir[1] == '/') || ($dir[1] == '\\')))
					$dir = substr($dir, 2);
				$dir = $this->archiveDirPrefix . $dir;
			}

			/* ensure a trailing separator */
			$ps = $dir[strlen($dir) - 1];
			if (($ps != "/") && ($ps != "\\"))
				$dir .= DIRECTORY_SEPARATOR;

			$this->storageDevices["MAG$i"] = $dir;
		}
		error_reporting($er);

		return '';
	}


	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* validate $dbms (already imported by parent) */
		if (($this->dbms != 'MYSQL') && ($this->dbms != 'SQLITE3'))
			return __METHOD__ . ": [Conquest] Unsupported value of \$dbms '" . $this->dbms ."' in config.php. " .
				'Allowed are "MySQL" and "SQLite3".';

		/* validate $db_host

			Import and validation take place in AuthDB and below. However their messages
			are not for the end user -- they do not refer to config.php and parameter
			name there.
		 */
		if ($this->dbms == 'MYSQL')
			if (empty($this->dbHost))
				return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db */
		if (empty($this->loginFormDb))
			return __METHOD__ . ': $login_form_db (config.php) is not set';

		/* import $archive_dir_prefix (also needed by getStorageDevices()) */
		$sl = strlen($this->archiveDirPrefix);
		if (!$sl)
			return __METHOD__ . ': $archive_dir_prefix (config.php) is not set';
		$lc = $this->archiveDirPrefix[$sl - 1];
		if (($lc != "/") && ($lc != "\\"))
			$this->archiveDirPrefix .= DIRECTORY_SEPARATOR;

		$err = $this->getStorageDevices();
		if (strlen($err))
			return $err;

		return '';
	}


	public function exportCommonData($what = null)
	{
		$cd = parent::exportCommonData($what);
		$cd['storage_devices'] = $this->storageDevices;
		return $cd;
	}


	public function getDatabaseNames()
	{
		if ($this->dbms == 'SQLITE3')
		{
			/* see getDatabaseOptions(): an alias is unusable without the
			   true value, so we'll return both -- but otherwise arrays
			   aren't allowed
			 */
			$lfd = explode('|', $this->loginFormDb);
			if (count($lfd) > 1)
				return array($lfd);
			else
				return array($this->loginFormDb);
		}
		else
			return array($this->loginFormDb);
	}


	public function supportsAuthentication()
	{
		return $this->dbms != 'SQLITE3';
	}
}
