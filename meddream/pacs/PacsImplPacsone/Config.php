<?php

/** @brief Implementation for <tt>$pacs='PacsOne'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public $pacsoneVersion = '6.2.1';   /**< @brief Auto-detected version of PacsOne */
	public $mdpacsDir = '';             /**< @brief (MW/OW/etc) Used when (un)installing the mdPACS service */

	/** @name Version-dependant names of PacsOne's database fields */
	/**@{*/
	public $F_STUDY_UUID = 'uuid';
	public $F_STUDY_DATE = 'studydate';
	public $F_STUDY_TIME = 'studytime';
	public $F_SERIES_UUID = 'uuid';
	public $F_SERIES_SERIESNUMBER = 'seriesnumber';
	public $F_IMAGE_UUID = 'uuid';
	public $F_DBJOB_USERNAME = 'username';
	public $F_DBJOB_CLASS = 'class';
	public $F_DBJOB_UUID = 'uuid';
	public $F_STUDYNOTES_UUID = 'uuid';
	public $F_STUDYNOTES_USERNAME = 'username';
	public $F_STUDYNOTES_TOTALSIZE = 'totalsize';
	public $F_ATTACHMENT_UUID = 'uuid';
	public $F_ATTACHMENT_TOTALSIZE = 'totalsize';
	public $F_EXPORT_UUID = 'uuid';
	public $F_EXPORT_CLASS = 'class';
	public $F_PRIVILEGE_USERNAME = 'username';
	public $F_PRIVILEGE_VIEWPRIVATE = 'viewprivate';
	public $F_PRIVILEGE_MODIFYDATA = 'modifydata';
	public $F_TBL_NAME_PATIENT = 'patient';
	public $F_TBL_NAME_STUDY = 'study';
	public $F_PATIENT_IDEOGRAPHIC = '';
	public $F_PATIENT_PHONETIC = '';
	/**@}*/


	/** @brief Extract @c $VERSION from PacsOne's sharedData.php.

		The default location is <tt>..\\sharedData.php</tt> (MedDream is installed
		under <tt>PacsOne\\php\\meddream</tt>).

		However, if MedDream is installed elsewhere, then <tt>$login_form_db</tt>
		points to PacsOne's installation directory (*.ini files with database names
		are expected there). In that case the location is changed to
		<tt>$login_form_db\\php\\sharedData.php</tt>.
	 */
	private function getPacsOneVersion()
	{
		if ($this->constants->FDL || Constants::FOR_WORKSTATION)
			return $this->pacsoneVersion;

		$dir = '';

		/* $login_form_db might override the location */
		if (trim($this->loginFormDb) != '')
			$dir = $this->loginFormDb . 'php';
		else
			$dir = dirname(dirname(dirname(__DIR__)));

		$sharedDataFile = $dir . DIRECTORY_SEPARATOR . 'sharedData.php';
		$versionStr = '';

		if (!@file_exists($sharedDataFile))
			$this->log->asErr("missing file '$sharedDataFile'");
		else
		{
			$str = @file_get_contents($sharedDataFile);
			if ($str === false)
				$this->log->asErr("unreadable file '$sharedDataFile'");
			else
				if (!strlen($str))
					$this->log->asErr("empty file '$sharedDataFile'");
				else
				{
					$versionKey = '$VERSION';
					$startPos = stripos($str, $versionKey) + strlen($versionKey);
					$endPos = stripos($str, ";", $startPos);
					$versionStr = substr($str, $startPos, $endPos - $startPos);
					$versionStr = str_replace('=', '', $versionStr);
					$versionStr = str_replace('"', '', $versionStr);
					$versionStr = str_replace(' ', '', $versionStr);
					$versionStr = str_replace("'", '', $versionStr);
				}
		}
		if ($versionStr != '')
			$this->pacsoneVersion = $versionStr;
		else
			$this->pacsoneVersion = null;
		$this->log->asDump('detected PacsOne version: ', $this->pacsoneVersion);
	}


	/** @brief Update <tt>$this-F_*</tt> according to the version of PacsOne

		Field names in the PacsOne's database sometimes change or are added. We shall
		adjust to a large range of versions.
	 */
	private function getDBFields()
	{
		if (!$this->isOracle())
		{
			$this->F_STUDY_UUID = 'uid';
			$this->F_STUDY_DATE = 'date';
			$this->F_STUDY_TIME = 'time';
			$this->F_SERIES_UUID = 'uid';
			$this->F_SERIES_SERIESNUMBER = 'number';
			$this->F_IMAGE_UUID = 'uid';
			$this->F_DBJOB_USERNAME = 'user';
			$this->F_DBJOB_CLASS = 'level';
			$this->F_DBJOB_UUID = 'uid';
			$this->F_STUDYNOTES_UUID = 'uid';
			$this->F_STUDYNOTES_USERNAME = 'user';
			$this->F_STUDYNOTES_TOTALSIZE = 'size';
			$this->F_ATTACHMENT_UUID = 'uid';
			$this->F_ATTACHMENT_TOTALSIZE = 'size';
			$this->F_EXPORT_UUID = 'uid';
			$this->F_EXPORT_CLASS = 'level';
			$this->F_PRIVILEGE_USERNAME = 'user';
			$this->F_PRIVILEGE_VIEWPRIVATE = 'view';
			$this->F_PRIVILEGE_MODIFYDATA = 'modify';
		}
		if (Constants::FOR_RIS)
		{
			$this->F_TBL_NAME_PATIENT = 'rispatient';
			$this->F_TBL_NAME_STUDY = 'risstudy';
		}

		if ($this->isGreaterThan624())
		{
			$this->F_PATIENT_IDEOGRAPHIC = 'ideographic';
			$this->F_PATIENT_PHONETIC = 'phonetic';
		}
	}


	/** @brief @c true if PacsOne supports Oracle under Windows.

		<b>This is a synonym for version 6.2.1+.</b> Besides Oracle support (which is
		still hardly important for MedDream), it introduced a significant change in
		column names of the database.
	 */
	private function isOracle()
	{
		//version not found - return true
		if (is_null($this->pacsoneVersion))
			return true;

		if ($this->pacsoneVersion < '6.2.1')
			return false;

		return true;
	}


	/** @brief @c true if PacsOne's web interface uses @c mcrypt() for the login data.

		Older versions do not, in which case we are also turning the encryption off
		so that MedDream is still able to use PacsOne's login data from the session.
		This kind of SSO is important for our integration into PacsOne's web interface
		via applet.php.
	 */
	private function isEncrypted()
	{
		//version not found - return true
		if (is_null($this->pacsoneVersion))
			return true;

		if ($this->pacsoneVersion < '6.1.3')
			return false;
		return true;
	}


	/** @brief @c true if PacsOne's version is <b>not less than</b> 6.2.4. */
	private function isGreaterThan624()
	{
		return $this->pacsoneVersion >= '6.2.4';
	}


	private static function getDbNameFromIniFile($inifile)
	{
		$file = @file($inifile);
		if ($file === false)
			return '';
		foreach ($file as $line)
		{
			$tokens = preg_split('/[\s=]+/', $line);
			if ((count($tokens) > 1) && !strcasecmp($tokens[0], 'Database'))
				return $tokens[1];
		}
		return '';
	}


	public function exportCommonData($what = null)
	{
		return array_merge(parent::exportCommonData($what),
			array(
				'pacsoneVersion' => $this->pacsoneVersion,
				'F_STUDY_UUID' => $this->F_STUDY_UUID,
				'F_STUDY_DATE' => $this->F_STUDY_DATE,
				'F_STUDY_TIME' => $this->F_STUDY_TIME,
				'F_SERIES_UUID' => $this->F_SERIES_UUID,
				'F_SERIES_SERIESNUMBER' => $this->F_SERIES_SERIESNUMBER,
				'F_IMAGE_UUID' => $this->F_IMAGE_UUID,
				'F_DBJOB_USERNAME' => $this->F_DBJOB_USERNAME,
				'F_DBJOB_CLASS' => $this->F_DBJOB_CLASS,
				'F_DBJOB_UUID' => $this->F_DBJOB_UUID,
				'F_STUDYNOTES_UUID' => $this->F_STUDYNOTES_UUID,
				'F_STUDYNOTES_USERNAME' => $this->F_STUDYNOTES_USERNAME,
				'F_STUDYNOTES_TOTALSIZE' => $this->F_STUDYNOTES_TOTALSIZE,
				'F_ATTACHMENT_UUID' => $this->F_ATTACHMENT_UUID,
				'F_ATTACHMENT_TOTALSIZE' => $this->F_ATTACHMENT_TOTALSIZE,
				'F_EXPORT_UUID' => $this->F_EXPORT_UUID,
				'F_EXPORT_CLASS' => $this->F_EXPORT_CLASS,
				'F_PRIVILEGE_USERNAME' => $this->F_PRIVILEGE_USERNAME,
				'F_PRIVILEGE_VIEWPRIVATE' => $this->F_PRIVILEGE_VIEWPRIVATE,
				'F_PRIVILEGE_MODIFYDATA' => $this->F_PRIVILEGE_MODIFYDATA,
				'F_TBL_NAME_PATIENT' => $this->F_TBL_NAME_PATIENT,
				'F_TBL_NAME_STUDY' => $this->F_TBL_NAME_STUDY,
				'F_PATIENT_IDEOGRAPHIC' => $this->F_PATIENT_IDEOGRAPHIC,
				'F_PATIENT_PHONETIC' => $this->F_PATIENT_PHONETIC
			));
	}


	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* validate $dbms (already imported by parent) */
		if (Constants::FOR_WORKSTATION)
		{
			if (($this->dbms != 'MYSQL') && ($this->dbms != 'SQLITE3'))
				return __METHOD__ . ': Unsupported value of $dbms "' . $this->dbms .
					'" in config.php. Allowed are "MySQL" and "SQLite3".';
		}
		else
			if ($this->dbms != 'MYSQL')
				return __METHOD__ . ': [PacsOne] Unsupported value of $dbms "' . $this->dbms .
				'" in config.php. Please use "MySQL".';

		/* validate $db_host

			Import and validation take place in AuthDB and below. However their messages
			are not for the end user -- they do not refer to config.php and parameter
			name there.
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db: in this PACS it's a directory for *.ini files.

			If present, must refer to an existing directory and will override
			location of *.ini files. A relative path (starting with '..') is
			relative to the directory of config.php.
		 */
		if (!empty($this->loginFormDb))
			if (!Constants::FOR_WORKSTATION)
			{
				$dir = $this->loginFormDb;
				if (substr($dir, 0, 2) == '..')
					$dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $dir;
				if ((substr($dir, -1, 1) != '/') && (substr($dir, -1, 1) != '\\'))
					$dir .= DIRECTORY_SEPARATOR;
				if (!is_dir($dir))
					return __METHOD__ . ': $login_form_db (config.php) is not a directory: "' . $this->loginFormDb . '"';

				/* until now we didn't modify $login_form_db directly: in the above
				   error message the user shall see the original unmodified value,
				   in order to minimize confusion
				 */
				$this->loginFormDb = $dir;
			}

		/* $archive_dir_prefix */
		if ($this->constants->FDL)
		{
			/* ensure a trailing path separator */
			$sl = strlen($this->archiveDirPrefix);
			if ($sl)
			{
				$lc = $this->archiveDirPrefix[$sl - 1];
				if (($lc != "/") && ($lc != "\\"))
					$this->archiveDirPrefix .= DIRECTORY_SEPARATOR;
			}
		}

		/* $mdpacs_dir */
		if (!$this->constants->FDL && (isset($_GET['sw']) || Constants::FOR_WORKSTATION))
		{
			if (isset($cnf['mdpacs_dir']))
				$this->mdpacsDir = $cnf['mdpacs_dir'];
		}

		/* these functions both depend on $this->loginFormDb, can't call them in the constructor */
		$this->getPacsOneVersion();
		$this->getDBFields();

		return '';
	}


	/* collect names from the files ../../{$AeTitle}.ini */
	public function getDatabaseNames()
	{
		if (!strlen($this->loginFormDb))
		{
			$dir = dirname(dirname(dirname(dirname(__DIR__)))) . '/';
			$src = 'default value';
		}
		else
		{
			$dir = $this->loginFormDb;
			$src = 'as per $login_form_db in config.php';
		}

		$dh = @opendir($dir);
		if (!$dh)
			return "opendir() failed on '$dir' ($src)";
			/* by the way, we didn't use is_dir($dir) as configure() already did that */

		$result = array();
		$database = null;		/* will be updated to non-null if at least one file is found */
		while (($file = @readdir($dh)) !== false)
		{
			$inifile = $dir . $file;
			if (!@is_dir($inifile))
			{
				$tokens = explode('.', $file);
				if (count($tokens) > 1)
				{
					$ext = array_pop($tokens);
					if (!strcasecmp($ext, 'ini'))
					{
						$database = $this->getDbNameFromIniFile($inifile);
						if (strlen($database))
							$result[] = $database;
					}
				}
			}
		}
		closedir($dh);

		if (!count($result))
			if (is_null($database))
				return "No file(s) $dir*.ini ($src) have been found";
			else
				return "File(s) $dir*.ini ($src) do not contain database definitions or are unreadable";

		return $result;
	}


	/** @brief Indicate whether encryption of login data is supported in this version of PacsOne web interface */
	public function canEncryptSession()
	{
		return $this->isEncrypted();
	}
}
