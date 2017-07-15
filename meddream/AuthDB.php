<?php
/*
	Original name: authDB.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Unified API for various databases. Caching of login credentials in the
		session.
 */

namespace Softneta\MedDream\Core;

require_once 'autoload.php';


/** @brief %Database support with authentication. */
class AuthDB extends Database\DB
{
	/** @name Validated configuration parameters from config.php */
	/**@{*/
	public $dbHost;
		/**< @brief (string) Host name (or IP address) of the DBMS server.

			Some PACSes might reuse this parameter for a different purpose.
		 */
	public $medreportRootLink;
		/**< @brief (string) Relative web path for MedReport auto-login.

			The auto-login is implemented by simply setting a session cookie for this path
			(see setUserPasswordDatabaseGroup()).
		 */
	/**@}*/

	public $encryptSession;                 /**< @brief (bool) Sensitive values in session are encrypted */
	public $sessionHeader = "meddream_";    /**< @brief Prefix for session variable names */

	public $displayedUser = '';             /**< @brief Alternative user name shown on the "Logoff" button */
	public $userGroup = 'user';             /**< @brief Group to which the user belongs (currently used in MW). */

	/** @name Locally cached parameters from session

		These are identical to DbAbstract::$dbUser etc of the currently chosen DbImpl*.
		The latter, however, are not accessible so far (would need corresponding getters
		and setters).
	 */
	/**@{*/
	protected $authDbName = '';             /**< @brief Name of the database */
	protected $authDbUser = '';             /**< @brief Login user name */
	protected $authDbPassword = '';         /**< @brief Login password */
	/**@}*/

	protected $dbOptions = array();

	/** @brief Our own logging facility (some DbImpl* have individual copies of it). */
	private $log;

	/** @brief An instance of Constants */
	private $constants;


	/** @brief Reinitialize login data from the session.

		When an AuthDB instance is created, it will automatically fetch the login
		data from the session where it was stored by setUserPasswordDatabase().
	 */
	public function getUserPasswordDatabase()
	{
		if ($this->constants->FDL)
		{
			$this->authDbUser = Constants::DL_USER;
			$this->authDbPassword = Constants::DL_PASSWORD;
			$this->authDbName = Constants::DL_DB;
			$this->displayedUser = '';
			return;
		}

		$user = '';
		$password = '';
		$database = '';
		$displayedUser = '';

		if (!isset($_SESSION[$this->sessionHeader . 'authenticatedUser']))
			$_SESSION[$this->sessionHeader . 'authenticatedUser'] = '';

		if (!isset($_SESSION[$this->sessionHeader . 'authenticatedPassword']))
			$_SESSION[$this->sessionHeader . 'authenticatedPassword'] = '';

		if (!isset($_SESSION[$this->sessionHeader . 'authenticatedDatabase']))
			$_SESSION[$this->sessionHeader . 'authenticatedDatabase'] = '';

		if (!isset($_SESSION[$this->sessionHeader . 'displayedUser']))
			$_SESSION[$this->sessionHeader . 'displayedUser'] = '';

		if ($this->encryptSession && function_exists("mcrypt_module_open") &&
			!isset($_GET['app']) && !isset($_GET['sw']) && !Constants::FOR_WORKSTATION)		/* would fail under MedDreamApp/SWS/MWS/OWS */
		{
			$td = mcrypt_module_open('tripledes', '', 'ecb', '');
			$key = substr($_COOKIE['sessionCookie'], 0, mcrypt_enc_get_key_size($td));
			$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
			mcrypt_generic_init($td, $key, $iv);
			if ($_SESSION[$this->sessionHeader . 'authenticatedUser'] != "")
				$user = rtrim(mdecrypt_generic($td, $_SESSION[$this->sessionHeader . 'authenticatedUser']), "\0");
			if ($_SESSION[$this->sessionHeader . 'authenticatedPassword'] != "")
				$password = rtrim(mdecrypt_generic($td, $_SESSION[$this->sessionHeader . 'authenticatedPassword']), "\0");
			if ($_SESSION[$this->sessionHeader . 'displayedUser'] != "")
				$displayedUser = rtrim(mdecrypt_generic($td, $_SESSION[$this->sessionHeader . 'displayedUser']), "\0");
			mcrypt_generic_deinit($td);
			mcrypt_module_close($td);
		}
		else
		{
			$user = $_SESSION[$this->sessionHeader . 'authenticatedUser'];
			$password = $_SESSION[$this->sessionHeader . 'authenticatedPassword'];
			$displayedUser = $_SESSION[$this->sessionHeader . 'displayedUser'];
		}

		$database = $_SESSION[$this->sessionHeader . 'authenticatedDatabase'];

		$this->authDbUser = $user;
		$this->authDbPassword = $password;
		$this->authDbName = $database;
		$this->displayedUser = $displayedUser;

		if (Constants::FOR_WORKSTATION)
			$this->userGroup = $_SESSION[$this->sessionHeader . 'usergroup'];
	}


	/** @brief Preserve login data in the session for subsequent initialization
		       of AuthDB instances.
	 */
	public function setUserPasswordDatabaseGroup($user, $password, $database, $displayedUser, $group = null)
	{
		if ($this->encryptSession && function_exists("mcrypt_module_open") &&
			!isset($_GET['app']) && !isset($_GET['sw']) && !Constants::FOR_WORKSTATION)
		{
			$key = function_exists("hash") ? hash("MD5", session_id()) : session_id();
			setcookie("sessionCookie", $key, time()+7*24*60*60);
			$this->log->asDump('$medreport_root_link: ', $this->medreportRootLink);
			if ($this->medreportRootLink != "")
			{
				/* remove MR-specific cookie if medreportRootLink is a directory

					This one might remain on the client machine while the server
					did forget about it and a new session was created. As a result,
					the other cookie that we are setting below won't have any effect
					due to lower priority.
				 */
				if (substr($this->medreportRootLink, -4) != '.php')
					setcookie('sessionCookie', '', time() - 3600*12, $this->medreportRootLink);

				/* log in MR so that it can open from MD

					If medreportRootLink points to a file (for example, home.php as
					suggested by config.php), then this yields a correct path, identical
					to one created by MR itself.

					If it points to a directory, then our path is not specific enough
					and would therefore be ignored. That's why a more specific cookie
					was removed above.
				 */
				$path = str_replace('\\', '/', dirname($this->medreportRootLink));
				setcookie('sessionCookie', $key, time()+7*24*60*60, $path);
				$this->log->asInfo('prepared a MR login for path ', $path);
			}

			$td = mcrypt_module_open('tripledes', '', 'ecb', '');
			$key = substr($key, 0, mcrypt_enc_get_key_size($td));
			$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
			mcrypt_generic_init($td, $key, $iv);
			if ($user != "")
				$_SESSION[$this->sessionHeader . 'authenticatedUser'] = mcrypt_generic($td, $user);
			if ($password != "")
				$_SESSION[$this->sessionHeader . 'authenticatedPassword'] = mcrypt_generic($td, $password);
			if ($displayedUser != "")
				$_SESSION[$this->sessionHeader . 'displayedUser'] = mcrypt_generic($td, $displayedUser);
			mcrypt_generic_deinit($td);
			mcrypt_module_close($td);
		}
		else
		{
			$_SESSION[$this->sessionHeader . 'authenticatedUser'] = $user;
			$_SESSION[$this->sessionHeader . 'authenticatedPassword'] = $password;
			$_SESSION[$this->sessionHeader . 'displayedUser'] = $displayedUser;
		}

		$_SESSION[$this->sessionHeader . 'authenticatedDatabase'] = $database;
		if (Constants::FOR_DCMSYS)
			$_SESSION[$this->sessionHeader . 'DcmsysAuthCookie'] = $this->connection;

		$this->authDbUser = $user;
		$this->authDbPassword = $password;
		$this->authDbName = $database;
		$this->displayedUser = $displayedUser;

		if (!is_null($group))
		{
			$this->userGroup = $group;
			$_SESSION[$this->sessionHeader . 'usergroup'] = $group;
		}
	}


	/** @brief Constructor.

		@param string        $dbms            A copy of <tt>$dbms</tt> (config.php)
		@param Logging       $log             An instance of Logging
		@param Configuration $cnf             An instance of Configuration
		@param boolean       $encryptSession  Authentication-related data in session is (to be) encrypted
		@param boolean       $performConnect  If @c true, will connect to the database automatically
		@param boolean       $initFromSession If @c true, will fetch credentials from session. __Reserved for unit tests.__
	 */
	public function __construct($dbms, Logging $log, Configuration $cnf, $encryptSession = true, $performConnect = true, $initFromSession = true)
	{
		$this->log = $log;
		$this->encryptSession = $encryptSession;

		$this->constants = new Constants();

		/* $db_host: simply imported

			Will be validated further, part of validation is in reconnect() (DbImpl*.php).

			Can't require a non-empty string as some pseudo-PACSes do not use a database.
		 */
		if (isset($cnf->data['db_host']))
			$this->dbHost = trim($cnf->data['db_host']);
		else
			$this->dbHost = '';

		/* $db_options: simply imported */
		if (isset($cnf->data['db_options']))
			$this->dbOptions = $cnf->data['db_options'];

		/* $medreport_root_link: simply imported */
		if (isset($cnf->data['medreport_root_link']))
			$this->medreportRootLink = $cnf->data['medreport_root_link'];

		/* turn off warnings and notices */
		$previous = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);

		if ($this->constants->FDL)
			$this->sessionHeader = Constants::DL_SESS_HDR;
		else
			if (isset($_GET['session']))
			{
				if ($_GET['session'] == "pacsone")
					$this->sessionHeader = "";
				else
					$this->sessionHeader = $_GET['session'] . "_";
			}

		$this->displayedUser = '';
		if ($initFromSession)
			$this->getUserPasswordDatabase();	/* NOTE: $this->authDb* were probably initialized */
		if (!strlen($this->authDbUser) && Constants::FOR_DICOMDIR)
			$this->setUserPasswordDatabaseGroup('root', 'user', 'DICOMDIR', 'root');

		/* initialize the underlying class "DB" */
		parent::__construct($dbms, $this->dbHost, $this->authDbName, $this->authDbUser,
			$this->authDbPassword, $log);
		$err = $this->getInitializationError();
		if (strlen($err))
		{
			$log->asErr('fatal: ' . $err);
			if (Constants::EXCEPTIONS_NO_EXIT)
				throw new \Exception($err);
			else
				exit($err);
		}

		if (Constants::FOR_DCMSYS)
			$this->setConnection();
		else
			if ($performConnect)
				if (strlen($this->authDbUser))	/* initialization might fail due to empty session etc */
				{
					$err = $this->reconnect($this->dbOptions);
					if (strlen($err))
						$this->log->asErr("failed to reconnect: $err");
				}

		error_reporting($previous);
	}


	public function __destruct()
	{
		$previous = error_reporting(E_ERROR);
		$this->close();
		error_reporting($previous);
	}


	/** @brief A wrapper for legacy callers

		@retval  true   Connected
		@retval  false  Did not connect, the actual error was logged

		Legacy callers, especially old versions of external.php, expect a different
		return value (boolean instead of string).
	 */
	public function connect($database, $user, $password, $additionalOptions = array())
	{
		if ($user == "")
			return false;

		$opt = $additionalOptions;
		if (empty($opt))
			$opt = $this->dbOptions;

		$err = parent::connect($database, $user, $password, $opt);
		$success = $err == '';
		if (!$success)
			$this->log->asErr($err);

		return $success;
	}


	/** @brief A simple database authentication. Stores credentials in the session if successful.
	 */
	public function login($database, $user, $password, $displayedUser = "")
	{
		$audit = new Audit('LOGIN');

		$result = false;
		$this->displayedUser = "";
		$previous = error_reporting(E_ERROR);

		$this->logoff();

		$result = $this->connect($database, $user, $password);

		if ($result)
			$this->setUserPasswordDatabaseGroup($user, $password, $database, $displayedUser);

		$audit->log($result, $this->formatConnectDetails($user, $password));

		error_reporting($previous);

		return $result;
	}


	/** @brief An opposite of login().

		Removes credentials from the session and from local variables.
	 */
	public function logoff()
	{
		$this->close();

		if (isset($_SESSION[$this->sessionHeader . 'authenticatedDatabase']))
			unset($_SESSION[$this->sessionHeader . 'authenticatedDatabase']);

		if (isset($_SESSION[$this->sessionHeader . 'authenticatedUser']))
			unset($_SESSION[$this->sessionHeader . 'authenticatedUser']);

		if (isset($_SESSION[$this->sessionHeader . 'authenticatedPassword']))
			unset($_SESSION[$this->sessionHeader . 'authenticatedPassword']);

		if (isset($_SESSION[$this->sessionHeader . 'displayedUser']))
			unset($_SESSION[$this->sessionHeader . 'displayedUser']);

		$this->authDbUser = "";
		$this->authDbPassword = "";
		$this->authDbName = "";
		$this->displayedUser = "";

		return true;
	}


	/** @brief Indicates if the session contains cached credentials. */
	public function isAuthenticated()
	{
		if ($this->constants->FDL)
			return $this->authDbUser != '';

		return (isset($_SESSION[$this->sessionHeader . 'authenticatedUser']))
				&& ($_SESSION[$this->sessionHeader . 'authenticatedUser'] != "")
				&& ($this->authDbUser != "");
	}


	/** @brief Preserve user's privileges in the session, using current @link $sessionHeader @endlink.

	 	Used in system::connect().
	 */
	public function setPrivileges($privileges)
	{
		$_SESSION[$this->sessionHeader . 'privileges'] = $privileges;
	}


	/** @brief Fetches user's privileges from the session, using current @link $sessionHeader @endlink.

	 	Used in system::connect().
	 */
	public function getPrivileges()
	{
		if (isset($_SESSION[$this->sessionHeader . 'privileges']))
			return $_SESSION[$this->sessionHeader . 'privileges'];
		else
			return null;
	}


	/** @brief Getter for @link $authDbUser @endlink that prefers @link $displayedUser @endlink.

		@param  $avoidAlias  If @c true, then do not use @link $displayedUser @endlink.
	 */
	public function getAuthUser($avoidAlias = false)
	{
		if ($avoidAlias || ($this->displayedUser == ''))
			return $this->authDbUser;
		else
			return $this->displayedUser;
	}


	/** @brief Go back to the login page and display an error message

		This is more suitable for the Backend class, however external.php at customers'
		servers (lots of installations) expect this method here.
	 */
	public function goHome($sendGet = false, $message = "", $fatal = 0, $obj = "", $type = "")
	{
		/* PHP documentation stresses out that the Location: header in HTTP 1.1 *must*
		   use an absolute URI (this includes scheme, hostname and path). How about
		   proxies, then?
		 */
		if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']))
			$url = 'https://';
		else
			$url = 'http://';

		$htmlMode = isset($_REQUEST['htmlMode']) && ('on' == $_REQUEST['htmlMode']);

		if (!$htmlMode)
		{
			if (strpos($_SERVER['PHP_SELF'], 'swf/') !== false)
				$_SERVER['PHP_SELF'] = str_replace('swf/', '', $_SERVER['PHP_SELF']);
		}
		else
		{
			if (strpos($_SERVER['PHP_SELF'], 'md5/') !== false)
				$_SERVER['PHP_SELF'] = str_replace('md5/', '', $_SERVER['PHP_SELF']);
		}

		$url .= $_SERVER['HTTP_HOST'] . str_replace("\\", "/", dirname($_SERVER['PHP_SELF']));
		if (substr($url, -1) != "/")
			$url .= "/";

		if ((isset($_SESSION['basename'])) && ($_SESSION['basename'] != ""))
			$url .= $_SESSION['basename'];

		if ($sendGet)
		{
			if (strlen($message))
				$_GET['message'] = $message;
			else
				if (isset($_GET['message']))
					unset($_GET['message']);
			if ($fatal)
				$_GET['fatal'] = $fatal;
			else
				if (isset($_GET['fatal']))
					unset($_GET['fatal']);
			if (strlen($obj))
				$_GET['obj'] = $obj;
			else
				if (isset($_GET['obj']))
					unset($_GET['obj']);
			if (strlen($type))
				$_GET['type'] = $type;
			else
				if (isset($_GET['type']))
					unset($_GET['type']);
			if ($htmlMode)
				$_GET['htmlMode'] = 'on';
			else
				if (isset($_GET['htmlMode']))
					unset($_GET['htmlMode']);

			$firstGet = true;
			foreach ($_GET as $key => $value)
			{
				if ($firstGet)
				{
					$url .= "?";
					$firstGet = false;
				}
				else
					$url .= "&";
				$url .= $key . "=" . $value;
			}
		}

		/* need "@header" for PHPUnit but we'd still like to know if headers were output
		   too late due to some error output occurring earlier. Will attempt to detect a
		   failure.
		 */
		@trigger_error('', E_USER_NOTICE);		/* reset to a known value */
		@header('Cache-Control: no-cache');
		@header('Pragma: no-cache');
		@header('Location: ' . $url);
		$e = error_get_last();
		if (count($e))
			if (strlen($e['message']))			/* does it come from the "reset" above? */
				$this->log->asErr('header() failed: ' . $e['message']);
	}
}
