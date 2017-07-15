<?php
/*
	Original name: System.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Various functions, mostly licensing-related
 */

namespace Softneta\MedDream\Core;

if (!strlen(session_id()))
	@session_start();


/** @brief Miscellaneous "infrastructure" functions. */
class System
{
	protected $log;
	protected $constants;
	protected $cnf;
	protected $tr;
	protected $backend = null;
	protected $branding = null;	/**< @brief An instance of Branding */


	function __construct(Backend $backend = null, Translation $translator = null,
		Configuration $cnf = null, Logging $logger = null, Constants $const = null)
	{
		require_once('autoload.php');

		if (is_null($const))
		{
			$const = new Constants();
		}
		$this->constants = $const;

		if (is_null($logger))
		{
			$logger = new Logging();
		}
		$this->log = $logger;

		if (is_null($cnf))
		{
			$cnf = new Configuration();
			$error = $cnf->load();
			if (strlen($error))
				$this->log->asErr('$error in ' . __METHOD__ . ': ' . $error);
		}
		$this->cnf = $cnf;

		if (is_null($translator))
		{
			$translator = new Translation();
			$translator->configure();
		}
		$this->tr = $translator;

		$this->backend = $backend;
	}


	/** @brief Return new or existing instance of Backend.

		@param array $parts             Names of PACS parts that will be initialized
		@param boolean $withConnection  Is a DB connection required?

		If the underlying AuthDB must be connected to the DB, then will request the connection
		once more.
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	/** @brief Read an existing and a non-empty file, fail silently. */
	private function loadFile($fileName)
	{
		$str = "";
		if (@file_exists($fileName))
		{
			$file = fopen($fileName, "r");
			if ($file)
			{
				if (filesize($fileName) > 0)
					$str = fread($file, filesize($fileName));
				fclose($file);
			}
		}
		return $str;
	}


	/**
	 * get cookie path according meddream installation dir
	 *
	 * @param string $pathName - path of current url e.g /meddream/index.html
	 * @param string $delimiter - limiter, that need take all path until some string
	 * @return string
	 */
	public function getCookiePath($pathName, $delimiter = '')
	{
		if (($pathName == '') || ($pathName == '/'))
			return '/';

		$fullPath = explode('/', $pathName);

		$path = array('');
		$count = count($fullPath);

		for ($i = 0; $i < $count; $i++)
		{
			if (strlen($delimiter))
				if ($fullPath[$i] == $delimiter)
					break;

			if ($fullPath[$i] == '')
				continue;

			$tmp = explode('.', $fullPath[$i]);
			if (count($tmp) > 1)
				continue;

			$path[] = $fullPath[$i];
		}
		if ($path[count($path)-1] != '')
			$path[] = '';

		return implode('/', $path);
	}


	/** @brief Read the license file. */
	public function license($clientid)
	{
		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return "";
		}
		$this->log->asDump(__METHOD__ . ': ok');

		include __DIR__ . '/sharedData.php';
		if (Constants::FOR_WORKSTATION && isset($PRODUCT))
			$product = $PRODUCT;
		else
			$product = '';

		if (Constants::FOR_WORKSTATION)
			$file = "/$product.lic";
		else
			$file = "/meddream.lic";
		$licenseFile = dirname(__FILE__) . $file;

		if ($backend->pacs == 'DICOMDIR')
			return @meddream_license($clientid, $licenseFile);
		else
			return @meddream_license($clientid, $licenseFile, $product);
				/* @ inhibits a warning about a different number of parameters (when the
				   extension is very old). This warning causes a confusion in amfPHP, and
				   Flash is unable to display a warning about the version mismatch which
				   is very likely in this situation.

				   The warning itself is not important: an old extension accepts 2 parameters
				   while we offer 3, it won't even notice. The opposite situation where
				   we would benefit from that warning -- a newer extension requires more
				   parameters, _is_unable_to_detect_that_itself_, and meddream.version_override
				   still forces a correct version -- is very unlikely.

				   DICOMDIR above still uses an old extension, and that won't change soon,
				   so we can add an exception.
				 */
	}


	/** @brief Return list of supported languages.

		@return <tt>array('en', 'lt', ...)</tt>

		Basically a wrapper of Translation::supported() that uses an already present
		instance of Translation.
	 */
	function getSupportedLanguages()
	{
		$ignored = '';
		return $this->tr->supported($ignored);
	}


	/** @brief Return contents of the current translation file.

		Basically a wrapper of Translation::read() that ensures a silent failure
		(empty string is returned in that case).
	 */
	function loadLanguage()
	{
		$ignored = '';
		$content = $this->tr->read($ignored);
		if ($content === false)
			$content = '';
		return $content;
	}


	/**
	 * set new/default supported language and return content
	 *
	 * @param string $lang - lang code
	 * @return string - empty or language content
	 */
	public function updateLanguage($lang, &$authDbObj = null)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$return = array('error' => '', 'languages' => '');
		if (empty($lang))
			$lang = 'en';

		$supported = $this->getSupportedLanguages();
		if (in_array($lang, $supported))
		{
			/* the change will be taken into account after page refresh

				'swf' is for the case when we're called from swf\System.php; no harm for
				other callers.
			 */
			setcookie('userLanguage', $lang, 0, $this->getCookiePath($_SERVER['PHP_SELF'], 'swf'));
			/* ...but also immediately for Backend::$tr */
			$_COOKIE['userLanguage'] = $lang;

			$this->log->asDump('set new language ', $_COOKIE['userLanguage']);
			$return['languages'] = $this->loadLanguage();
		}
		else
		{
			$this->log->asErr("missing language '$lang', supported: " . var_export($supported, true));
			$return['error'] = 'language not found: "'.$lang.'"';
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Register the connection. */
	function connect($clientid)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$rootDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;

		include $rootDir . 'sharedData.php';

		/* last resort to upgrade if the installer failed to execute php.exe due to some reason */
		if (Constants::FOR_WORKSTATION && file_exists($rootDir . 'upgrade.php'))
			include_once($rootDir . 'upgrade.php');

		$return = array();
		$return[0] = 0;
		$return['error'] = 'reconnect';
		$return['reconnect'] = false;

		$backend = $this->getBackend(array('Auth'));	/* 'Auth': addPrivilegies() and onConnect() */
		if (!$backend->authDB->isAuthenticated() || empty($clientid))
		{
			$return['reconnect'] = true;
			return $return;
		}

		$accesscode = $this->getAccesCode($clientid);
		$php_meddream_version = function_exists('meddream_version') ? meddream_version() : '';

		$actions = null;
		if (isset($_SESSION['actions']) && !$this->constants->FDL)
		{
			$actions = $_SESSION['actions'];

			/* bug: ctrl+mouse click to open swf viewer
			 * will open search and destroy the session
			 * after that second tab opens viewer and can't find session
			 */
			if (!empty($_REQUEST['windowname']) &&
				($_REQUEST['windowname'] != 'MedDreamSearch'))
			{
				$this->log->asDump('unset action for window '.$_REQUEST['windowname']);
				unset($_SESSION['actions']);
			}
		}
		else
		{
			if (file_exists($rootDir . 'external.php'))
			{
				include_once($rootDir . 'external.php');
				externalActions($actions);
			}
		}
		$supported = $this->getSupportedLanguages();
		if (count($supported) > 0)
		{
			$languages = $this->loadLanguage();
		}
		else	/* old style: file is kept in the current directory and contains multiple languages */
			$languages = $this->loadFile($rootDir . 'languages.xml');

		$settings = $this->readSettingsXml();

		$style = $this->loadFile($rootDir . 'style.xml');

		$return = array();
		$return[0] = 0;
		$return['error'] = "";
		$return['accesscode'] = $accesscode;
		$return['php_meddream_version'] = $php_meddream_version;
		$return['php_meddream_api_version'] = function_exists('meddream_api_version') ? meddream_api_version() : '';
		$return['actions'] = $actions;
		$return['date'] = date("Ymd");
		$return['languages'] = $languages;
		$return['supportedLanguages'] = $supported;
		$return['settings'] = $settings;
		$return['style'] = $style;
		$return['home'] = '';		/* a URL for the "BUY NOW" button (visible if not empty) */
		$return['3d'] = $backend->m3dLink;
		$return['3d2'] = $backend->m3dLink2;
		$return['3d3'] = $backend->m3dLink3;
		$return['smooth'] = $backend->enableSmoothing;
		$return['debug'] = $this->log->isLoggingLevelEnabled(Logging::LEVEL_DEBUG);

		$this->addPrivilegies($return);
		$this->addQrConfig($return);
		$this->addBranding($return);
		$this->addToolLinks($return);
		$this->addShareConfig($return);

		$return['product'] = $PRODUCT;
		$return['version'] = $VERSION;
		$return['clientId'] = $clientid;

		if (isset($_SESSION['externaldessiondata']))
		{
			$return['externaldessiondata'] = $_SESSION['externaldessiondata'];
		}

		if (isset($_GET['sw']) || Constants::FOR_WORKSTATION)
		{
			$return['connections'] = $this->connections();
		}

		/* default values, probably updated by PacsAuth::onConnect() */
		$_SESSION[$backend->authDB->sessionHeader . 'notesExsist']  = false;
		$return['attachmentExist'] = false;

		$backend->pacsAuth->onConnect($return);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Update/create the "privileges" parameter array.

		The "privileges" array is stored among session variables. If it's missing,
		a new array is created and stored there. Otherwise the array read from session
		is used to update the by-ref variable.

		Also calls externalPrivileges() from external.php to make any customizable
		adjustments.

		Called from connect().
	 */
	public function addPrivilegies(&$config)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array('Auth'));

		$privileges = $backend->authDB->getPrivileges();
		if (empty($privileges) ||
			!array_key_exists('medreport', $privileges))
				/* this session was created by MedReport, must overwrite it */
		{
			$privileges = array();
			$privileges['username'] = $backend->authDB->getAuthUser();
			$privileges['root'] = $backend->pacsAuth->hasPrivilege('root');
			$privileges['view'] = $backend->pacsAuth->hasPrivilege('view');
			$privileges['forward'] = $backend->pacsAuth->hasPrivilege('forward');
			$privileges['export'] = $backend->pacsAuth->hasPrivilege('export');
			$privileges['upload'] = $backend->pacsAuth->hasPrivilege('upload');
			$privileges['share'] = $backend->pacsAuth->hasPrivilege('share');
			$privileges['medreport'] = $backend->authDB->medreportRootLink;
			$privileges['hisreport'] = $backend->hisReportLink;
			$privileges['reporttextrightalign'] = $backend->reportTextRightAlign;
			$privileges['useradministration'] = false;
			$privileges['closebutton'] = false;
			$privileges['imagejpeg'] = false;
			$privileges['closestudies'] = false;
			$privileges['saveimages'] = true;

			if (Constants::FOR_WORKSTATION)
				$privileges['usergroup'] = $backend->authDB->userGroup;

			$rootDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
			if (file_exists($rootDir . 'external.php'))
			{
				global $log;
				include_once($rootDir . 'external.php');
				$log = $this->log;		/* used by some versions of external.php */
				externalPrivileges($privileges);
			}

			$backend->authDB->setPrivileges($privileges);
		}

		$this->log->asDump('$privileges = ', $privileges);

		$config['privileges'] = $privileges;

		$this->log->asDump('end ' . __METHOD__);
	}


	/** @brief Add configuration for the external links (Flash: context menu, HTML: "Tools" button)

		@param array $config  The "privileges" parameter array, see connect()
	 */
	public function addToolLinks(&$config)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array('Auth'));
		$links = array();
		if (strlen($backend->m3dLink) > 0)
		{
			$links[] = '3D...|' . $backend->m3dLink . '||image,call3d';
		}
		if (strlen($backend->m3dLink2) > 0)
		{
			$links[] = 'MIP/MPR/3D...|' . $backend->m3dLink2 . '|_self|image';
		}
		if (strlen($backend->m3dLink3) > 0)
		{
			$links[] = $backend->m3dLink3;
		}

		$config['toolLinks'] = implode("\n", $links);
		$this->log->asDump('$toolLinks = ', $config['toolLinks']);
		$this->log->asDump('end ' . __METHOD__);
	}


	/** @brief Add configuration specific to <tt>$pacs='DICOM'</tt> (a.k.a. "QR").

		@param array $config  The "privileges" parameter array, see connect()

		Called from connect().
	 */
	public function addQrConfig(&$config)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array(), false);
		$config['qr'] = (int) $backend->pacsConfig->getRetrieveEntireStudy();
		if ($config['qr'] == 1)
		{
			$this->log->asDump('Takes qr parameters');

			$config['qr_repeat_send_timeout'] = 30;
				/* if prefetch.php has finished and THE SAME NUMBER of images (belonging
				   to the current study) is missing from cache for this amount of seconds,
				   then prefetch.php is started again
				 */
			if (!empty($this->cnf->data['qr_repeat_send_timeout']))
			{
				$config['qr_repeat_send_timeout'] = $this->cnf->data['qr_repeat_send_timeout'];
			}

			$config['qr_repeat_check_timeout'] = 300;
				/* after a study is complete for this number of seconds, its structure
				   will be queried _from_the_PACS_ again to detect possible changes
				 */
			if (!empty($this->cnf->data['qr_repeat_check_timeout']))
			{
				$config['qr_repeat_check_timeout'] = $this->cnf->data['qr_repeat_check_timeout'];
			}

			$config['qr_thumbnail_check_timeout'] = 0.5;
				/* how much seconds to wait before re-fetching the study structure
				   _from_the_cache_ if AT LEAST ONE image is found there
				 */
			if (!empty($this->cnf->data['qr_thumbnail_check_timeout']))
			{
				$config['qr_thumbnail_check_timeout'] = $this->cnf->data['qr_thumbnail_check_timeout'];
			}

			$config['qr_empty_thumbnail_check_timeout'] = 1;
				/* how much seconds to wait before re-fetching the study structure
				   _from_the_cache_ if NO IMAGES are found there
				 */
			if (!empty($this->cnf->data['qr_empty_thumbnail_check_timeout']))
			{
				$config['qr_empty_thumbnail_check_timeout'] = $this->cnf->data['qr_empty_thumbnail_check_timeout'];
			}
		}
		else
			$this->log->asDump('Qr not active');
		$this->log->asDump('end ' . __METHOD__);

	}


	/** @brief Add configuration for the Share to Dicom Library function.

		@param array $config  The "privileges" parameter array, see connect()
	 */
	public function addShareConfig(&$config)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$config['share'] = array();
		if (array_key_exists('dicomLibrarySender', $this->cnf->data))
		{
			$config['share']['dicomLibrarySender'] = $this->cnf->data['dicomLibrarySender'];
		}
		if (array_key_exists('dicomLibrarySubject', $this->cnf->data))
		{
			$config['share']['dicomLibrarySubject'] = $this->cnf->data['dicomLibrarySubject'];
		}
		if (array_key_exists('dicomLibraryEnabled', $this->cnf->data))
		{
			$config['share']['dicomLibraryEnabled'] = $this->cnf->data['dicomLibraryEnabled'];
		}
		else
		{
			$config['share']['dicomLibraryEnabled'] = false;
		}

		$this->log->asDump('end ' . __METHOD__);
	}


	/** @brief A wrapper to meddream_connect() with some caching in the session. */
	protected function getAccesCode(&$clientid)
	{
		$this->addWindow();

		$accesscode = '';

		if (!empty($_SESSION['clientIdMD']))
		{
			$clientid = $_SESSION['clientIdMD'];
			$accesscode = $_SESSION['accesscode'];
		}
		$_SESSION['clientIdMD'] = $clientid;

		/* if the user forgot to update the extension and it's very old, then this code will
		   crash due to a missing function. The extension version check is performed in the
		   frontend a bit later, unfortunately.
		 */
		if (!function_exists('meddream_is_connected'))
		{
			$this->log->asErr('php_meddream too old: missing function meddream_is_connected');
			$accesscode = '';
		}
		else
			if (!meddream_is_connected($clientid))
				$accesscode = meddream_connect($clientid);

		$_SESSION['accesscode'] = $accesscode;

		return $accesscode;
	}


	/** @brief Add a non-existing window to the session. */
	public function addWindow()
	{
		/* identify window and register */
		$windowname = 'unknown';
		if (!empty($_REQUEST['windowname']))
			$windowname = $_REQUEST['windowname'];

		$windows = array();
		if (!empty($_SESSION['windows']))
			$windows = $_SESSION['windows'];

		if (!in_array($windowname, $windows))
		{
			$windows[] = $windowname;
			$_SESSION['windows'] = $windows;

			$this->log->asDump('connected window ', $windowname);
		}
		else
			$this->log->asDump('window already connected: ', $windowname);
	}


	/** @brief Remove an existing window from the sesssion. */
	public function removeWindow()
	{
		$this->log->asDump('$_REQUEST: ', $_REQUEST);
		/* identify window and register */
		$windowname = 'unknown';
		if (!empty($_REQUEST['windowname']))
			$windowname = $_REQUEST['windowname'];

		$windows = array();
		if (!empty($_SESSION['windows']))
			$windows = $_SESSION['windows'];

		$windows = array_diff($windows, array($windowname));
		$_SESSION['windows'] = array_values($windows);

		$this->log->asDump('disconnected window ', $windowname);
		$this->log->asDump('left active windows: ', $_SESSION['windows']);
	}


	/** @brief De-register the connection. */
	public function disconnect($clientid = '')
	{
		$audit = new Audit('DISCONNECT');

		if (isset($_REQUEST['clientid']))
			$clientid = urldecode($_REQUEST['clientid']);

		if (isset($_SESSION['clientIdMD']))
		{
			$clientid = $_SESSION['clientIdMD'];
			unset($_SESSION['clientIdMD']);
			unset($_SESSION['windows']);
			unset($_SESSION['accesscode']);
		}

		if (function_exists('meddream_disconnect'))
			meddream_disconnect($clientid);
			/* function_exists() is needed in situations where a missing md-php-ext is
			   reported after login (for example, during HIS integration) and one wants
			   to use logoff.php manually
			 */
		$this->log->asDump('disconnected client ', $clientid);

		if (isset($_SESSION['authClientId']))
			unset($_SESSION['authClientId']);

		if ($clientid)
			$audit->log();

		return;
	}


	/** @brief A licensing heartbeat. */
	public function refresh($clientid = '', $uid = '')
	{
		/* for html */
		if (empty($clientid))
			$clientid = isset($_REQUEST['clientid']) ? $_REQUEST['clientid'] : '';

		if (empty($uid))
			$uid = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : '';

		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
			return "reconnect";

		return meddream_refresh($clientid, $uid);
	}


	/** @brief Return the number of existing connections. */
	public function connections()
	{
		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			return "";
		}

		return meddream_connections();
	}


	/** @brief Save frontend settings to a dedicated file.

		@param string text  JSON-formatted settings
	 */
	function saveSettings($text)
	{
		$audit = new Audit('CONFIGURE');

		/* OW/MW/etc might still use $text in XML format, compareSettings() would fail */
		if (isset($_GET['sw']))
			return 'Saving of XML-formatted data is not implemented';

		$fileName = $this->getSettingFilePath();

		$changes = $this->compareSettings(@file_get_contents($fileName), $text);

		$file = @fopen($fileName, "w+");
		if ($file)
		{
			$r = @fwrite($file, $text);
			fclose($file);
			if ($r === FALSE)
			{
				if (!count($changes))
					$audit->log(false);
				else
					foreach ($changes as $c)
						$audit->log(false, $c);

				return "Can't write to file $fileName";
			}

			if (!count($changes))
				$audit->log(true);
			else
				foreach ($changes as $c)
					$audit->log(true, $c);
			return '';
		}

		if (!count($changes))
			$audit->log(false);
		else
			foreach ($changes as $c)
				$audit->log(false, $c);
		return "Can't create file $fileName";
	}


	private function getSettingFilePath()
	{
		$rootDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		if (isset($_GET['sw']))
			return $rootDir . 'swsettings.json';
		else
			return $rootDir . 'settings.json';
	}


	/** @brief Read a settings file and return an XML string.

		@return string XML data (empty if failure)
	 */
	public function readSettingsXml()
	{
		$file = $this->getSettingFilePath();
		$dataXml = '';
		$data = $this->loadFile($file);
		if (!empty($data))
		{
			$dataArray = @json_decode($data, true);
			if (!is_null($dataArray))
			{
				$dataXml = $this->arrayToXmlString ($dataArray);
				$dataXml = "<settings>\n" . $dataXml . "\n</settings>";
				$dataXml = '<?xml version="1.0" encoding="utf-8" ?>' ."\n". $dataXml;
			}
		}
		unset($data);
		unset($dataArray);
		return $dataXml;
	}


	/** @brief Read a settings file and return an array.

		@return array Settings data (an empty array usually means failure)
	 */
	public function readSettingsJsonToArray()
	{
		$file = $this->getSettingFilePath();
		$data = $this->loadFile($file);
		$dataArray = array();
		if (!empty($data))
			$dataArray = @json_decode($data, true);
		if (is_null($dataArray))
			$dataArray = array();
		unset($data);
		return $dataArray;
	}


	/** @brief Compare two settings arrays.

		@param array $container  Name of container for @p $from and @p $to
		@param array $from       Initial contents
		@param array $to         Final contents

		@return array of strings: one string for every change found.

		Will call itself recursively for any sub-arrays.
	 */
	private function compareSettingArrays($context, &$from, &$to)
	{
		$changes = array();

		/* convert empty arrays to strings

			For a better look in some cases. Not harmful as empty containers are not
			utilized in the current settings.
		 */
		foreach ($from as $k => $vFrom)
			if (is_array($vFrom) && !count($vFrom))
				$from[$k] = '';
		foreach ($to as $k => $vTo)
			if (is_array($vTo) && !count($vTo))
				$to[$k] = '';

		foreach ($to as $k => $vTo)
		{
			/* ready-to-print type and value (probably we'll need to output them) */
			if (is_array($vTo))
			{
				$pvTo = '';
				$ptTo = '(array)';
			}
			else
			{
				$pvTo = var_export($vTo, true);
				$ptTo = '';
			}

			/* make sure $from can be indexed by $k */
			if (!array_key_exists($k, $from))
			{
				$changes[] = "added $context$k = $ptTo$pvTo";
			}
			else
			{
				/* ready-to-print type and value of $to */
				$vFrom = $from[$k];
				if (is_array($vFrom))
				{
					$pvFrom = '';
					$ptFrom = '(array)';
				}
				else
				{
					$pvFrom = var_export($vFrom, true);
					$ptFrom = '';
				}

				/* as indexing is now possible, we can examine the change (even recursively) */
				if (is_array($vFrom) && is_array($vTo))
				{
					/* a special setting: ordering is not controlled via GUI, and shall be ignored for clarity */
					if ("$context$k" == 'settings>html>defaultModalityList')
					{
						foreach ($vTo as $tTo)
						{
							$found = array_search($tTo, $vFrom);
							if ($found !== false)
								unset($vFrom[$found]);
							else
							{
								$changes[] = "added '$tTo' to $context$k";
							}
						}
						foreach ($vFrom as $tFrom)
						{
							$changes[] = "removed '$tFrom' from $context$k";
						}
					}
					else
						$changes = array_merge($changes, $this->compareSettingArrays("$context$k>", $from[$k], $to[$k]));
				}
				else
					if (is_array($vFrom) xor is_array($vTo))
					{
						$changes[] = "changed $context$k = $ptTo$pvTo";
					}
					else	/* both are scalars, substring comparison is possible */
					{
						if ($pvTo != $pvFrom)
						{
							/* another special setting: elements are kept in a semicolon-delimited string */
							if ("$context$k" == 'settings>toolbar')
							{
								$subFrom = explode(';', $vFrom);
								$subTo = explode(';', $vTo);

								$same = true;
								foreach ($subTo as $kk => $tTo)
								{
									$found = array_search($tTo, $subFrom);
									if ($found !== false)
										unset($subFrom[$found]);
									else
									{
										if ($kk)
										{
											$after = "after '" . $subTo[$kk - 1] . "'";
										}
										else
										{
											$after = 'at the beginning';
										}
										$changes[] = "added '$tTo' to $context$k, $after";
										$same = false;
									}
								}
								foreach ($subFrom as $tFrom)
								{
									$changes[] = "removed '$tFrom' from $context$k";
									$same = false;
								}

								/* if it looks identical without taking the order into account, then it's reordered.

									Won't implement reordering detection at the moment. It's difficult not only
									to implement, but also to understand the resulting log (unless there is just
									a couple of changes).
								 */
								if ($same)
									$changes[] = "reordered $context$k = $pvTo";
							}
							else
								$changes[] = "changed $context$k = $ptTo$pvTo";
						}
					}

				/* remove examined values from $from: any values remaining there after this loop
				   will be of "removed" status
				 */
				unset($from[$k]);
			}
		}

		/* is there any remainders? */
		foreach ($from as $k => $vFrom)
			$changes[] = "removed $context$k";

		return $changes;
	}


	/** @brief Compare two settings files available as strings.

		@param string $jsonFrom  Initial contents
		@param string $jsonTo    Final contents

		@return array of strings: one string for every change found.
	 */
	public function compareSettings($jsonFrom, $jsonTo)
	{
		if ($jsonFrom === false)
			return array('new settings file');

		$aFrom = @json_decode($jsonFrom, true);
		$aTo = @json_decode($jsonTo, true);

		if (is_null($aFrom))
			return array('wrong format of old settings data');

		if (is_null($aTo))
			return array('wrong format of new settings data');

		return $this->compareSettingArrays('settings>', $aFrom, $aTo);
	}


	/** @brief Verify presence of a remote component.

		@param string $url      URL to the resource containing the version
		@param string $version  The required version. If different, the component is
		                        considered missing.
	 */
	function packageExsist($url, $version = "")
	{
		$modulename = basename(__FILE__);
		$this->log->asDump("begin $modulename/" . __FUNCTION__ . "('$url')");

		if ($url != '')
		{
			try
			{
				$tmo = ini_set('default_socket_timeout', 2);
					/* default is 60 -- impractically high */
				$handle = @fopen($url, 'r');
				ini_set('default_socket_timeout', $tmo);
				if ($handle)
				{
					/* if $version is given, the remote version shall be high enough */
					if ($version != "")
					{
						$versionEx = fread($handle, 32);
						fclose($handle);
						return version_compare($version, $versionEx) !== 1;
					}

					/* otherwise we simply test for presence */
					fclose($handle);
					return true;
				}
				else
					return false;
			}
			catch (Exception $e) { return false; }
		}
		return false;
	}


	/** @brief Server-side implementation of the "Register" function.

		@param array $data  Installation attributes like hostname etc

		@retval 'relogin'  Success, the frontend shall react to this automatically
		@retval otherwise  An error message to be displayed in the frontend
	 */
	function register($data)
	{
		$audit = new Audit('REGISTER');

		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$audit->log(false);
			return 'Not authenticated';
		}

		/* downgrade to amfPHP < 2.0 */
		if (is_object($data))
			$data = get_object_vars($data);

		/* build a request URL */
		$list = '';
		if (count($data) > 0)
			foreach ($data as $key => $value)
				$list .= "&$key=" . urlencode($value);
		$this->log->asDump(Constants::HOME_URL . "/license/?m=1$list");

		/* download a new license file */
		$tmo = ini_set('default_socket_timeout', 15);	/* the default "60" is too large */
		$content = @file_get_contents(Constants::HOME_URL . "/license/?m=1$list");
		ini_set('default_socket_timeout', $tmo);
		$this->log->asDump($content);

		if ($content === false)
		{
			$audit->log(false);
			return "Licensing server doesn't respond.\n\nCheck your Internet connection.";
		}
		elseif (strpos($content, '<license>') === false)
		{
			$audit->log(false);
			return "Licensing server returned:\n\n$content";
		}
		elseif (strlen($content))
		{
			/* update the .lic file */
			include __DIR__ . '/sharedData.php';

			if (isset($PRODUCT))
				$product = $PRODUCT;
			else
				$product = '';

			if (Constants::FOR_WORKSTATION)
				$file = "/$product.lic";
			else
				$file = "/meddream.lic";
			$licenseFile = dirname(__FILE__) . $file;

			if (@file_put_contents($licenseFile, $content) === false)
			{
				$audit->log(false);
				return 'Failed to update license file';
			}
		}

		$audit->log(true);
		$this->log->asDump('end ' . __METHOD__);
		return 'relogin';			/* automatic relogin */
	}


	/** @brief Server-side implementation of the "3D ..." context menu function.

		@param string $uid  Series Instance UID

		Used with (older?) MedDream3D: passes the given UID and client's IP address
		to the server-side component on the remote server. The client-side component
		performs any remaining communication.
	 */
	function call3d($uid)
	{
		set_time_limit(0);

		$this->log->asDump('begin ' . __METHOD__);

		/* An instance of AuthDB is needed, and it's an opportunity to combat
		   some hacking attempts.
		 */
		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$err = "Not authenticated";
			$this->log->asErr($err);
			return $err;
		}

		/* Build the URL

			Backend::m3dLink does not necessarily include the URL scheme,
			host and port; default them to the current values.
		 */
		$url = $backend->m3dLink;
		if ($url[0] == '/')
		{
			$is_secure = !empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']);
			$port = $_SERVER['SERVER_PORT'];
			if ($is_secure)
			{
				$prefix = 'https://';
				if ($port == '443')
					$port = '';
			}
			else
			{
				$prefix = 'http://';
				if ($port == '80')
					$port = '';
			}
			$prefix .= 'localhost'; 		/* or perhaps $_SERVER['HTTP_HOST'] ? */
			if ($port != '')
				$prefix .= ':' . $port;

			$url = $prefix . $url;
		}
		$client = $_SERVER['REMOTE_ADDR'];
		$url .= "?session=3d&series=$uid&clientip=$client";
		$this->log->asDump('request: ', $url);

		/* the remaining job can take many minutes; ensure that refresh() isn't blocked */
		session_write_close();

		/* The first attempt. A success means that a page is redirected
		   to itself (document body is empty) in order to set up a session.
		 */
		$params = array('http' => array('timeout' => 10.0,
			'follow_location' => false));
		$ctx = stream_context_create($params);
			/* NOTE: follow_location is crucial and requires PHP 5.3.4+ */

		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp)
		{
			$err0 = error_get_last();
			$err = "Failed to open '$url' (1): " . $err0['message'];
			$this->log->asErr($err);
			return $err;
		}
		$hdr = $http_response_header;

		$response = @stream_get_contents($fp);
		$stop = false;
		if ($response === false)
		{
			$err0 = error_get_last();
			$response = "Failed to read from '$url' (1): " . $err0['message'];
			$stop = true;
		}
		@fclose($fp);
		if ($stop)
		{
			$this->log->asErr("returning: '$response'");
			return $response;
		}
		$this->log->asDump('headers/1: ', $hdr);

		/* The second attempt (if it's a redirect). Must do basically the
		   same but with an additional Cookie: header.
		 */
		if (strpos($hdr[0], '302') !== false)
		{
			/* extract some values from headers */
			$cookie1 = false;
			$cookie2 = false;
			$location = false;
			foreach ($hdr as $h)
			{
				if ($cookie1 === false)
				{
					$pos = strpos($h, 'PHPSESSID=');
					if ($pos !== false)
					{
						$c = substr($h, $pos);
						$tmp = explode(';', $c);
						$cookie1 = $tmp[0];
					}
				}
				if ($cookie2 === false)
				{
					$pos = strpos($h, 'sessionCookie=');
					if ($pos !== false)
					{
						$c = substr($h, $pos);
						$tmp = explode(';', $c);
						$cookie2 = $tmp[0];
					}
				}
				if ($location === false)
				{
					$pos = strpos($h, 'Location: ');
					if ($pos !== false)
						$location = substr($h, 10);
				}
			}
			if ($cookie1 === false)
			{
				$err = 'PHPSESSID cookie not found in a redirect';
				$this->log->asErr($err);
				return $err;
			}
			if ($cookie2 === false)
			{
				$err = 'sessionCookie cookie not found in a redirect';
				$this->log->asErr($err);
				return $err;
			}
			if ($location === false)
			{
				$err = 'Location: header not found in a redirect';
				$this->log->asErr($err);
				return $err;
			}

			$params = array('http' => array('timeout' => 120.0,
				'follow_location' => false,
				'header' => "Cookie: $cookie1; $cookie2\r\n"));
			$ctx = stream_context_create($params);

			$fp = @fopen($location, 'rb', false, $ctx);
			if (!$fp)
			{
				$err0 = error_get_last();
				$err = "Failed to open '$url' (2): " . $err0['message'];
				$this->log->asErr($err);
				return $err;
			}
			$this->log->asDump('headers/2: ', $http_response_header);

			/* cancellable read from stream */
			$response = '';
			while (!@feof($fp))
			{
				if (connection_aborted())
				{
					$this->log->asErr('user break');
					return '';
				}
				$rsp = @fgets($fp);
				if ($rsp !== false)
					$response .= trim($rsp);
			}
			@fclose($fp);
		}

		$this->log->asDump('returning: ', $response);
		$this->log->asDump('end ' . __METHOD__);
		return $response;
	}


	/** @brief Checks if a newer version of MedDream is available.

		@return array

		Elements of the returned array:

		<ul>
			<li><tt>'error'</tt> - (string) error message, empty if success
			<li><tt>'newversion'</tt> - (boolean) indicates a newer version
			<li><tt>'requiresnewlicense'</tt> - (boolean) indicates that current license does not
			        allow upgrade to the available newer version
			<li><tt>'root'</tt> - (boolean) indicates administrator privileges that might require
			        more detailed messages in the frontend
		</ul>
	 */
	function latestVersion()
	{
		$this->log->asDump('begin ' . __METHOD__);

		$return = array('error' => '', 'newversion' => false,
						'requiresnewlicense' => true,
						'root' => false);

		$backend = $this->getBackend(array('Auth'));
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';

			$this->log->asErr($return['error']);
			return $return;
		}

		$return['root'] = $backend->pacsAuth->hasPrivilege('root') == 1;

		$url = Constants::HOME_URL . "/files/meddreamviewer/LATEST";
		$this->log->asDump('querying latest MedDream version from ', $url);

		/* download a new license file */
		session_write_close();		/* waiting for timeout shall not block other requests */
		$tmo = ini_set('default_socket_timeout', 15);	/* the default "60" is too large */
		$content = @file_get_contents($url);
		ini_set('default_socket_timeout', $tmo);
		$this->log->asDump('downloaded: ', addcslashes($content, "\0..\37"));

		if ($content === false)
		{
			$return['error'] = "server doesn't respond.\n\nCheck your Internet connection.";

			$this->log->asErr($return['error']);
			return $return;
		}

		if (strlen($content) > 0)
		{
			/* canonicalize: we must be independent on delimiters */
			$content = str_replace(array("\r", "\n", "\t", "\v"), ' ', trim($content));
			$lengthNew = -1;
			do
			{
				$lengthOld = $lengthNew;
				$content = str_replace('  ', ' ', $content);
				$lengthNew = strlen($content);
			} while ($lengthNew && ($lengthNew != $lengthOld));
			$this->log->asDump('cleaned up: ', addcslashes($content, "\0..\37"));

			$parts = explode(' ', $content);
			$this->log->asDump('$parts: ', $parts);

			$version = '';
			$date = '';

			if (isset($parts[0]))
				$version = (string) $parts[0];

			if (isset($parts[1]))
				$date = (string) $parts[1];

			if ($version == '')
			{
				$this->log->asErr('no new version found');
				return $return;
			}

			$rootDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;

			/* obtain the current version

				Might decide that update is not needed regardless of remote version.
				This, however, is still done *after* fetching the remote version, as
				the cost of fetching is acceptable and the log record reminds everyone
				about which version is currently the official one.
			 */
			include($rootDir . 'sharedData.php');
			$currentversion = '';
			if (isset($VERSION))
				$currentversion = trim($VERSION);
			if ($currentversion == 'DEV')
			{
				$this->log->asInfo('assuming up-to-date for a development version');
				$return['requiresnewlicense'] = false;
				return $return;
			}

			$currentupdateto = '';

			$lic = $this->loadFile($rootDir . 'meddream.lic');
			preg_match_all("/updatesTo>(.*)<\/updatesTo/", $lic, $out);

			if (isset($out[1][0]))
				$currentupdateto = (string) $out[1][0];

			$return['newversion'] = version_compare($currentversion, $version, '<');
			$this->log->asDump('compare: "' . $currentversion . '" < "' . $version . '" ? ' .
				var_export($return['newversion'], true));

			$this->log->asDump('updatesTo: local ', $currentupdateto, ', required ', $date);
			if (!empty($currentupdateto) && !empty($date))
				$return['requiresnewlicense'] = $this->date1IsLowerOrEqualToDate2($currentupdateto, $date);
		}

		$this->log->asDump('return: ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Date comparison for latestVersion().

		@return boolean  @c true if first date is <= second date
	 */
	protected function date1IsLowerOrEqualToDate2($date1, $date2)
	{
		if ($date1 == '0000-00-00')
			return true;

		if ($date2 == '0000-00-00')
			return true;

		$date11 = strtotime($date1);
		$date12 = strtotime($date2);
		return ($date11 <= $date12);
	}


	/** @brief Default configuration template for the InfoLabels function. */
	public function defaultLabels()
	{
		return array(
			'left' => array(
						array(
							'label' => '(0020,0010)',
							'items' => array(
								array(
									'group' => 32,
									'element' => 16,
									'tag' => '(0020,0010)')
								)
							),
						array(),
						array(
							'label' => '(0028,0011) x (0028,0010)',
							'items' => array(
								array(
									'group' => 40,
									'element' => 17,
									'tag' => '(0028,0011)'),
								array(
									'group' => 40,
									'element' => 16,
									'tag' => '(0028,0010)')
								)
							),
						array(),
						array()
			),
			'right' => array(
						array(
							'label' => '(0010,0010)',
							'items' => array(
								array(
									'group' => 16,
									'element' => 16,
									'tag' => '(0010,0010)')
								)
							),
						array(
							'label' => '(0010,0020)',
							'items' => array(
								array(
									'group' => 16,
									'element' => 32,
									'tag' => '(0010,0020)')
								)
							),
						array(
							'label' => '(0008,103E)',
							'items' => array(
								array(
									'group' => 8,
									'element' => 4158,
									'tag' => '(0008,103E)')
								)
							),
						array(
							'label' => '(0008,0020) (0008,0030)',
							'items' => array(
								array(
									'group' => 8,
									'element' => 32,
									'tag' => '(0008,0020)'),
								array(
									'group' => 8,
									'element' => 48,
									'tag' => '(0008,0030)')
								)
							),
						array()
			)
		);
	}


	/** @brief Return the currently configured template for the InfoLabels function. */
	public function getLabelSettings()
	{
		$return = array('error' =>'', 'labels'=>array(
				'left' => array(),
				'right' => array())
			);
		$this->log->asDump('begin ' . __METHOD__);
		$file = $this->getSettingFilePath();
		$data = $this->loadFile($file);
		if (!empty($data))
			$settings = @json_decode($data, true);
		unset($data);
		if (!isset($settings) || is_null($settings))
			$settings = array();
		if (!empty($settings))
		{
			if (!empty($settings['dicomView']['infoLabels']['left']['item']))
			{
				$return['labels']['left'] = array();
				$labels = $settings['dicomView']['infoLabels']['left']['item'];
				$count = count($labels);
				for ($i = 0; $i< $count; $i++)
					$return['labels']['left'][] = $this->parseLabel((string)$labels[$i]);
			}
			if (!empty($settings['dicomView']['infoLabels']['right']['item']))
			{
				$return['labels']['right'] = array();
				$labels = $settings['dicomView']['infoLabels']['right']['item'];
				$count = count($labels);
				for ($i = 0; $i< $count; $i++)
					$return['labels']['right'][] = $this->parseLabel((string)$labels[$i]);
			}
		}
		if (empty($return['labels']['left']) &&
			empty($return['labels']['right']))
			$return['labels'] = $this->defaultLabels();

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Parse a single entry of the InfoLabels configuration template. */
	public function parseLabel($label)
	{
		if (trim($label) == '')
			return array();

		preg_match_all('/\((\w{4}),(\w{4})\)/', $label, $matches, PREG_SET_ORDER);
		$return = array();
		$return['label'] = $label;
		if (!empty($matches[0]))
		{
			$return['items'] = array();
			foreach ($matches as $item)
			{
				if (!empty($item[0]) &&
					!empty($item[1]) &&
					!empty($item[2]))
				{
					$return['items'][] = array(
									'group' => hexdec($item[1]),
									'element' => hexdec($item[2]),
									'tag' => $item[0]);
				}
			}
		}
		return $return;
	}
	/** @brief get Branding class

		@return Branding
	 */
	public function getBranding()
	{
		if (is_null($this->branding))
			$this->branding = new Branding();
		return $this->branding;
	}

	/** @brief Return branding parameters if correctly configured and allowed by the license. */
	public function addBranding(&$return)
	{
		$data = array();
		$this->branding = $this->getBranding();
		$data['active'] = (int) $this->branding->active();
		$data['valid'] = (int) $this->branding->isValid();

		if (($data['valid'] == 1) && ($data['active'] == 1))
		{
			$data['companyLogoFile'] = $this->branding->getImageAttributeLocation('companyLogoFile');
			$data['productLogoFile'] = $this->branding->getImageAttributeLocation('productLogoFile');

			if ($this->licenseIsBranding())
			{
				$data['productName'] = $this->branding->getAttribute('productName');
				$data['productVersion'] = $this->branding->getAttribute('productVersion');
				$data['contacts'] = $this->branding->getAttribute('contacts');
				$data['disclamerText'] = $this->branding->getAttribute('disclaimerText');
				$data['copyright'] = $this->branding->getAttribute('copyright');
			}
		}
		$return['branding'] = $data;
	}

	/** @brief Test whether the license allows branding. */
	public function licenseIsBranding()
	{
		include __DIR__ . '/sharedData.php';
		if (Constants::FOR_WORKSTATION && isset($PRODUCT))
			$product = $PRODUCT;
		else
			$product = '';

		if (Constants::FOR_WORKSTATION)
			$file = "/$product.lic";
		else
			$file = "/meddream.lic";
		$licenseFile = __DIR__ . $file;

		if (file_exists($licenseFile))
		{
			$content = @file_get_contents($licenseFile);
			if (!empty($content) &&
				(strpos($content, '<isRebranded>1</isRebranded>') !== false))
				return true;
		}
		return false;
	}
	/** @brief Convert an array to an XML string.

		@param array $data  Array to be converted
		@param string $tab  Indentation substring (used internally via recursion)
	 */
	public function arrayToXmlString($data, $tab = '')
	{
		$xmlLines = array();
		if (!is_null($data) && ($data != ''))
		{
			foreach ($data as $key => $value)
			{
				if (is_array($value))
				{
					if (array_key_exists(0, $value))
					{
						foreach ($value as $item)
						{
							if (is_array($item))
							{
								if (empty($item))
									$xmlLines[] = $tab . '  <' . $key . '/>';
								else
								{
									$item = $this->arrayToXmlString($item, $tab . '  ');
									$xmlLines[] = $tab . '<' . $key . '>';
									$xmlLines[] = $item;
									$xmlLines[] = $tab . '</' . $key . '>';
								}
							}
							else
								if (strlen($item) == 0)
									$xmlLines[] = $tab . '  <' . $key . '/>';
								else
									$xmlLines[] = $tab . '  <' . $key . '>' . $item . '</' . $key . '>';
						}
					}
					else
					{
						$item = $this->arrayToXmlString($value, $tab . '  ');
						if (strlen($item) == 0)
							$xmlLines[] = $tab . '<' . $key . '/>';
						else
						{
							$xmlLines[] = $tab . '<' . $key . '>';
							$xmlLines[] = $item;
							$xmlLines[] = $tab . '</' . $key . '>';
						}
					}
				}
				else
				{
					if (strlen($value) == 0)
						$xmlLines[] = $tab . '<' . $key . '/>';
					else
						$xmlLines[] = $tab . '<' . $key . '>' . $value . '</' . $key . '>';
				}
			}
		}
		unset($data);
		if (!empty($xmlLines))
			return implode("\n", $xmlLines);
		return '';
	}
}