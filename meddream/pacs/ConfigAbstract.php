<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\Constants;


/** @brief Implements some methods from ConfigIface. */
abstract class ConfigAbstract implements ConfigIface
{
	protected $log;         /**< @brief Instance of Logging */
	protected $config;      /**< @brief Instance of Configuration */
	protected $constants;   /**< @brief Instance of Constants */

	/** @name Storage for configuration parameters (for example, config.php) */
	/**@{*/
	/** @brief <tt>$dbms</tt> (config.php).

		Imported from config.php if passes isset() and then capitalized. Descendants
		will validate/adjust the value. The final value will be passed to AuthDB in
		the PACS class.
	 */
	protected $dbms = '';

	/** @brief <tt>$db_host</tt> (config.php).

		Imported from config.php without any validation; @c null means that this parameter
		is absent in config.php or set to null there. Descendants (AuthDB and underlying
		implementations) will validate/adjust it.
	 */
	protected $dbHost;

	/** @brief Name of database offered in the login form.

		Imported from config.php without any validation; @c null means that this parameter
		is absent in config.php or set to null there. Descendants will validate/adjust it.
	 */
	public $loginFormDb;

	/** @brief <tt>$archive_dir_prefix</tt> (config.php).

		Imported from config.php without any validation (though @c null is automatically
		converted to @c ''); @c null means that this parameter is absent in config.php.
		Descendants will validate/adjust it.
	 */
	public $archiveDirPrefix;

	/** @brief <tt>$sop_class_blacklist</tt>
	 */
	public $sopClassBlacklist = array();

	public $forwardAetList = array('error' => 'internal: AET list not initialized');

	public $localAet = "MEDDREAM";

	public $pacsLoginUser = '';

	public $pacsLoginPassword = '';

	public $adminUsername = '';            /* also can be used to override the built-in "admin" in DCM4CHEE 2.x */

	/** @brief <tt>$pacs_gateway_addr</tt> (config.php).

		Imported from config.php without any validation (though @c null is automatically
		converted to @c ''); @c null means that this parameter is absent in config.php.
		Descendants will validate/adjust it.
	 */
	public $pacsGatewayAddr;

	/** @brief <tt>$dcm4che_recv_aet</tt> (config.php).

		Imported from config.php without any validation; @c null means that this parameter
		is absent in config.php or set to null there. Descendants will validate/adjust it.
	 */
	public $dcm4cheRecvAet;

	/** @brief A copy of meddream.retrieve_entire_study (php.ini) */
	public $retrieveEntireStudy = 0;
	/**@}*/


	public function __construct(Logging $logger, Configuration $cfg)
	{
		$this->log = $logger;
		$this->config = $cfg;
		$this->constants = new Constants();
	}


	/** @brief Basic handling of configuration parameters */
	public function configure()
	{
		$cfg = $this->config->data;
		if (!is_array($cfg))
			return 'wrong configuration: ' . var_export($cfg, true);

		/* $local_aet */
		if (isset($cfg['local_aet']) && !empty($cfg['local_aet']))
		{
			$le = trim($cfg['local_aet']);
			if (strlen($le) > 16)
				return "\$local_aet (config.php) longer than 16 characters: '$le'";
			if (strpbrk($le, "\\\n\r\f"))
				return "\$local_aet (config.php) contains an invalid character: '$le'";
			$this->localAet = $le;
		}

		/* $forward_aets: convert to array */
		if (isset($cfg['forward_aets']))
		{
			$lst = array();

			$i = 0;
			$addrs = explode("\n", $cfg['forward_aets']);
			foreach ($addrs as $addr)
				if (strlen($addr))
				{
					$parts = explode('|', $addr);
					$conn = $parts[0];
					if (count($parts) > 1)
						$desc = $parts[1];
					else
						$desc = '';
					if (strlen($conn))
					{
						$aet = explode('@', $conn);
							/* hide Host and Port from unauthorized eyes */

						$lst[$i] = array();
						$lst[$i]['data'] = $conn;
						$lst[$i]['label'] = $aet[0] . " - $desc";	/* NOTE: config.php shall be UTF-8 */
						$i++;
					}
					else
						return "\$forward_aets (config.php) contains a wrong AET definition: '$addr'";
				}
			$lst['count'] = $i;
			if ($i)
				$lst['error'] = '';
			else
				$lst['error'] = 'No forwarding AETs defined in config.php';

			$this->forwardAetList = $lst;
		}

		/* $sop_class_blacklist: convert to array */
		if (isset($cfg['sop_class_blacklist']) && !empty($cfg['sop_class_blacklist']))
		{
			$this->sopClassBlacklist = array();

			$sclist = explode(';', $cfg['sop_class_blacklist']);
			foreach ($sclist as $sc)
			{
				$st = trim($sc);
				if (strlen($st))
					$this->sopClassBlacklist[] = $st;
			}
		}

		/* parameters that are good as is and won't need validation in descendants */
		if (isset($cfg['pacs_login_user']))
			$this->pacsLoginUser = $cfg['pacs_login_user'];
		if (isset($cfg['pacs_login_password']))
			$this->pacsLoginPassword = $cfg['pacs_login_password'];
		if (isset($cfg['admin_username']))
			$this->adminUsername = $cfg['admin_username'];

		/* parameters that will be validated in descendants

			Our class variables shall be suitable for validation and use in the same fashion
			as elements of $cfg[]. Using array_key_exists() to avoid accessing missing elements
			is the right thing -- it doesn't do anything besides that. The default value shall
			be NULL as descendants will typically use isset() which doesn't accept NULLs.
		 */
		if (array_key_exists('pacs_gateway_addr', $cfg))
		{
			$this->pacsGatewayAddr = trim($cfg['pacs_gateway_addr']);
			if (strlen($this->pacsGatewayAddr))
				if (substr($this->pacsGatewayAddr, -1) != '/')			/* ensure trailing path separator */
					$this->pacsGatewayAddr .= '/';
		}
		else
			$this->pacsGatewayAddr = null;
			//TODO: validate as mandatory parameter after all PACSes can use it as an alternative
		if (array_key_exists('db_host', $cfg))
			$this->dbHost = $cfg['db_host'];							/* needed by QR.php and ..\db\*.php */
		else
			$this->dbHost = null;
		if (array_key_exists('login_form_db', $cfg))
			$this->loginFormDb = $cfg['login_form_db'];
		else
			$this->loginFormDb = null;
		if (array_key_exists('archive_dir_prefix', $cfg))
			$this->archiveDirPrefix = trim($cfg['archive_dir_prefix']);
		else
			$this->archiveDirPrefix = null;
		if (array_key_exists('dcm4che_recv_aet', $cfg))
			$this->dcm4cheRecvAet = $cfg['dcm4che_recv_aet'];
		else
			$this->dcm4cheRecvAet = null;


		/* $dbms is not used by some PACSes, validation must be done in descendants */
		if (isset($cfg['dbms']))
			$this->dbms = strtoupper($cfg['dbms']);

		/* For automated tests only, config.php won't ever include this. Normally every
		   PACS will itself decide what to assign to these variables.
		 */
		if (isset($cfg['RetrieveEntireStudy']))
			$this->retrieveEntireStudy = $cfg['RetrieveEntireStudy'];

		return '';
	}


	/** @brief Default implementation suitable for most PACSes */
	public function exportCommonData($what = null)
	{
		return array(
			'dbms' => $this->dbms,
			'db_host' => $this->dbHost,
			'login_form_db' => $this->loginFormDb,
			'archive_dir_prefix' => $this->archiveDirPrefix,
			'sop_class_blacklist' => $this->sopClassBlacklist,
			'forward_aets' => $this->forwardAetList,
			'local_aet' => $this->localAet,
			'pacs_login_user' => $this->pacsLoginUser,
			'pacs_login_password' =>  $this->pacsLoginPassword,
			'admin_username' => $this->adminUsername,
			'pacs_gateway_addr' => $this->pacsGatewayAddr,
			'dcm4che_recv_aet' => $this->dcm4cheRecvAet,
			'retrieve_entire_study' => $this->retrieveEntireStudy
		);
	}


	/** @brief Default implementation suitable for most PACSes */
	public function getWriteableRoot()
	{
		return dirname(__DIR__) . DIRECTORY_SEPARATOR;
	}


	/** @brief Getter for @link $dbms @endlink */
	public function getDbms()
	{
		return $this->dbms;
	}


	/** @brief Getter for @link $dbHost @endlink */
	public function getDbHost()
	{
		return $this->dbHost;
	}


	/** @brief Getter for @link $archiveDirPrefix @endlink */
	public function getArchiveDirPrefix()
	{
		return $this->archiveDirPrefix;
	}


	/** @brief Getter for @link $pacsGatewayAddr @endlink */
	public function getPacsGatewayAddr()
	{
		return $this->pacsGatewayAddr;
	}


	/** @brief Default implementation based on @link $loginFormDb @endlink */
	public function getDatabaseNames()
	{
		return array($this->loginFormDb);
	}


	/** @brief Default implementation based on @link $loginFormDb @endlink */
	public function getLoginFormDb()
	{
		return $this->loginFormDb;
	}


	/** @brief Default implementation suitable for most PACSes */
	public function supportsAuthentication()
	{
		return true;
	}


	/** @brief Default implementation suitable for most PACSes */
	public function canEncryptSession()
	{
		return true;
	}


	/** @brief Default implementation suitable for most PACSes */
	public function getRetrieveEntireStudy()
	{
		return $this->retrieveEntireStudy;
	}


	/** @brief Default implementation suitable for most PACSes */
	public function getDcm4cheRecvAet()
	{
		return $this->dcm4cheRecvAet;
	}
}
