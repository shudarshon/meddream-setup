<?php

/** @brief Unified API for %PACS support */
namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief A wrapper for all supported PACSes.

	Implementation of the entire %PACS consists of more than 50 methods. A class
	this large is hard to maintain, and all methods at once are never used.
	Therefore they are grouped into 9 "PACS parts" similarly to how they are
	used (for example, MedReport.php is the sole user of PacsReport). Before
	use it's possible to initialize only the required parts instead of everything.

	One problem that still remains is direct access to @link $pacsConfig @endlink
	... @link $pacsAnnotation @endlink from code that uses this class (or Backend).
	You will get a fatal error if a particular %PACS part was not initialized.
	Some wrapper function would at least ensure a message in MedDream logs,
	however at a cost of performance implications and more typing.

	Another possible problem is that the <b>%PACS parts do not have access to each
	</b>other; SharedIface attempts to compensate that to some extent. On the one
	hand, this ensures that code remains decoupled, simple and easier to test. On
	the other, it would be nice to move RetrieveStudy down to %PACS support level
	and use only in PACSes that need it, however now this is impossible even after
	serious refactoring.
 */
class PACS
{
	/** @brief Names of %PACS parts (keys) and their need for configure() (values). */
	protected static $PART_NAMES_AND_CFG = array(
		'Config' => true,
		'Shared' => true,
		'Auth' => true,
		'Search' => false,
		'Structure' => true,
		'Preload' => true,
		'Export' => false,
		'Forward' => true,
		'Report' => false,
		'Annotation' => true
		);

	/** @name Validated configuration parameters from config.php */
	/**@{*/
	public $pacs;               /**< @brief Name of the %PACS */
	/**@}*/

	/** @brief Enable (temporarily) the "delayed instantiation" warning in logs.

		This variable is turned off temporarily in __construct() so that planned
		initialization, which goes through the constructor, is silent.
	 */
	private $trackPartInitialization;

	protected $dir;             /**< @brief Cached value of @c $implDir parameter (implementation directory for PACSes) */
	protected $log;             /**< @brief Instance of Logging */
	protected $config;          /**< @brief Instance of Configuration */
	protected $fp;              /**< @brief Instance of ForeignPath */
	protected $gw;              /**< @brief Instance of PacsGw */
	protected $qr;              /**< @brief Instance of QR */
	public $cs;                 /**< @brief Instance of CharacterSet */
	public $authDB;             /**< @brief Instance of AuthDB */

	/** @name Implementation of the currently configured %PACS */
	/**@{*/
	public $pacsConfig = null;      /**< @brief Instance of PacsConfig */
	public $pacsShared = null;      /**< @brief Instance of PacsShared */
	public $pacsAuth = null;        /**< @brief Instance of PacsAuth */
	public $pacsSearch = null;      /**< @brief Instance of PacsSearch */
	public $pacsStructure = null;   /**< @brief Instance of PacsStructure */
	public $pacsPreload = null;     /**< @brief Instance of PacsPreload */
	public $pacsExport = null;      /**< @brief Instance of PacsExport */
	public $pacsForward = null;     /**< @brief Instance of PacsForward */
	public $pacsReport = null;      /**< @brief Instance of PacsReport */
	public $pacsAnnotation = null;  /**< @brief Instance of PacsAnnotation */
	/**@}*/


	/** @brief Helper for loadParts().

		@param string $classSuffix    Suffix of the class name (like "Annotation" in "PacsAnnotation")
		@param bool   $mustConfigure  Whether it is needed to call configure() method of the object
		@param array  $commonData     Some array from PacsConfig::exportCommonData()

		The intended use is to iterate over valid values of @p $classSuffix.
	 */
	protected function instantiatePart($classSuffix, $mustConfigure, $commonData)
	{
		$varName = 'pacs' . $classSuffix;

		/* note the use of variable variables, it's intentional */
		if (!is_null($this->$varName))		/* $this->pacsAuth etc */
			return '';

		$className = 'Pacs' . $classSuffix;
		$classNameFull = __NAMESPACE__ . '\\' . $className;

		/* provide a warning for lazy programmers */
		if ($this->trackPartInitialization)
		{
			$this->log->asWarn(__METHOD__ . ": internal: delayed instantiation of $className");
			$this->log->addBacktrace(Logging::LEVEL_WARNINGS);
		}

		if ($classSuffix == 'Shared')
			$this->$varName = new $classNameFull($this->pacs, $this->log, $this->authDB, $this->config,
				$this->cs, $this->fp, $this->gw, $this->qr, $this->dir);
		else
			$this->$varName = new $classNameFull($this->pacs, $this->log, $this->authDB, $this->config,
				$this->cs, $this->fp, $this->gw, $this->qr, $this->dir, $this->pacsShared);
		$err = $this->$varName->getInitializationError();
		if (strlen($err))
			return $err;

		/* common data might be required in configure(), import it earlier */
		$err = $this->$varName->importCommonData($commonData);
		if (strlen($err))
			return $err;

		if ($mustConfigure)
			return $this->$varName->configure();

		return '';
	}


	/** @brief Constructor.

		@param Logging       $log               An instance of Logging
		@param Configuration $cnf               An instance of Configuration
		@param CharacterSet  $cs                An instance of CharacterSet
		@param ForeignPath   $fp                An instance of ForeignPath
		@param PacsGw        $gw                An instance of PacsGw. If @c null, will be created internally.
		@param QR            $qr                An instance of QR. If @c null, will be created internally.
		@param array         $loadWhichParts    Passed to loadParts()
		@param bool          $performDbConnect  Passed to AuthDB::__construct(), see its parameter with the same name
		@param string        $implDir           Passed to @link PacsConfig::__construct() @endlink etc, see their parameter
		                                        with the same name. __Reserved for unit tests.__
		@param bool          $noInitialization  Do not call initialize(); the constructor essentialy does nothing, however it
		                                        also won't fail via exit() or exception if initialize() returns an error.
		                                        __Reserved for unit tests.__
		@param AuthDB        $authDB            An instance of AuthDB. If @c null, will be created internally. __Reserved
		                                        for unit tests.__
	 */
	public function __construct(Logging $log, Configuration $cnf, CharacterSet $cs, ForeignPath $fp,
		PacsGw $gw = null, QR $qr = null, $loadWhichParts = array(), $performDbConnect = true,
		$implDir = null, $noInitialization = false, AuthDB $authDB = null)
	{
		$this->log = $log;
		$this->config = $cnf;
		$this->cs = $cs;
		$this->fp = $fp;
		$this->gw = $gw;
		$this->qr = $qr;
		$this->authDB = $authDB;
		$this->dir = $implDir;

		if ($noInitialization)
			return;

		$this->trackPartInitialization = false;
		$err = $this->initialize($loadWhichParts, $performDbConnect);
		$this->trackPartInitialization = true;
		if ($err)
		{
			// @codeCoverageIgnoreStart
			$log->asErr('fatal: ' . $err);
			if (Constants::EXCEPTIONS_NO_EXIT)
				throw new \Exception($err);
			else
				exit($err);
			// @codeCoverageIgnoreEnd
		}
	}


	/** @brief Create the specified set of objects <tt>$this->pacs*</tt> if needed.

		@param array $which  A numerically-indexed array with case-insensitive strings "Auth",
		                    "Search", etc. @c null is a shortcut for all possible options.
		                     An empty array is a valid way to do nothing and succeed.

		@return  string  Error indicator, either from getInitializationError() or configure().

		Will create only those objects that are still uninitialized. Calling this
		function multiple times with the same parameter has no consequences whatsoever.

		Part names must be known, see @link $PART_NAMES_AND_CFG @endlink. The name @c "Config"
		is reserved and not allowed; @link $pacsConfig @endlink is created by initialize().
		@c "Shared" is also not allowed, its object instance is created automatically.

		RATIONALE: only a limited set of %PACS functions is usually required in places
		where this class is used. One can save resources by specifying only certain "parts
		of the PACS". Afterwards it is even possible (however not recommended) to call
		loadParts() on existing object and load any missing parts.

		@note Initialization of *additional* (not planned previously via a constructor
		      argument) %PACS parts is supported as a last resort only. @htmlonly <u>Any
		      such use will be logged to aid code cleanup.</u> @endhtmlonly The intended
		      use of the PACS class is to provide adequate @c $loadWhichParts for the
		      constructor.
	 */
	public function loadParts($which = array())
	{
		$allKnown = array_keys(self::$PART_NAMES_AND_CFG);
		if (is_null($which))
			$requestedKnown = array_diff($allKnown, array('Config'));
		else
		{
			if (!count($which))
				return '';		/* some optimization for speed */

			/* canonicalize names */
			array_walk($which, create_function('&$v, $k', '$v = ucfirst(strtolower($v));'));

			/* extract unknown and known names */
			$requestedUnknown = array_diff($which, $allKnown);
			if (count($requestedUnknown))
				return __METHOD__ . ': internal: $which contains unrecognized part names: [' .
					join(', ', $requestedUnknown) . ']';
			$requestedKnown = array_intersect($which, $allKnown);
			if (in_array('Config', $requestedKnown))
				return __METHOD__ . ': internal: "Config" in $which is not allowed';
			if (in_array('Shared', $requestedKnown))
				return __METHOD__ . ': internal: "Shared" in $which is not allowed';
		}

		$commonData = $this->pacsConfig->exportCommonData();

		/* iterate over those validated names */
		foreach ($requestedKnown as $part)
		{
			$err = $this->instantiatePart($part, self::$PART_NAMES_AND_CFG[$part], $commonData);
			if (strlen($err))
				return $err;
		}
		return '';
	}


	/** @brief Return names of parts that are loaded

		@return array See the @c $which parameter of loadParts()
	 */
	public function getLoadedParts()
	{
		$result = array();

		foreach (self::$PART_NAMES_AND_CFG as $k => $v)
		{
			$varName = "pacs$k";
			if (!is_null($this->$varName))		/* variable variable: $this->pacsAuth etc */
				$result[] = $k;
		}

		return $result;
	}


	/** @brief The actual initialization for the constructor.

		"Public" just for automated tests. __You should never call this function directly.__
	 */
	public function initialize($pacsParts, $performDbConnect)
	{
		$cfg = $this->config->data;
		if (!is_array($cfg))
			return __METHOD__ . ': wrong configuration: ' . var_export($cfg, true);

		/* import $pacs

			Will be validated in the constructor when instantiating the corresponding
			objects.
		 */
		if (!isset($cfg['pacs']) || empty($cfg['pacs']))
			return __METHOD__ . ': $pacs (config.php) is not set';
		$this->pacs = strtoupper($cfg['pacs']);

		/* create an instance of PacsConfig on which AuthDB depends */
		$this->pacsConfig = new PacsConfig($this->pacs, $this->log, $this->config, $this->dir);
		$err = $this->pacsConfig->getInitializationError();
		if (strlen($err))
			return $err;
		$err = $this->pacsConfig->configure();
		if (strlen($err))
			return $err;

		/* create an instance of AuthDB on which other parts (and PacsGw) depend */
		if (is_null($this->authDB))
		{
			$this->authDB = new AuthDB($this->pacsConfig->getDbms(), $this->log, $this->config,
				$this->pacsConfig->canEncryptSession(), $performDbConnect);
		}

		/* create an instance of PacsGw */
		if (is_null($this->gw))
		{
			$this->gw = new PacsGw($this->pacsConfig->getPacsGatewayAddr(), $this->log, $this->cs,
				$this->fp, $this->authDB);
		}

		/* create an instance of QR */
		if (is_null($this->qr))
		{
			/* our reuse of configuration parameters, while acceptable in specific version
			   of config.php, looks a bit strange here. An instance of QR will be created
			   for every kind of PACS; even those that aren't using it, will have a copy
			   initialized with nonsensical values. However those values are not a problem
			   (initialization is quite lightweight) until we aren't actually using QR in
			   "other" PACSes. The latter will, of course, introduce dedicated parameters.
			 */
			$remoteListener = explode('|', $this->pacsConfig->getLoginFormDb());
			$this->qr = new QR($this->log, $this->cs, $this->pacsConfig->getRetrieveEntireStudy(),
				$remoteListener[0], $this->pacsConfig->getDcm4cheRecvAet(),
				$this->pacsConfig->getDbHost(), $this->pacsConfig->getArchiveDirPrefix());
		}

		/* create PacsShared unconditionally */
		$err = $this->instantiatePart('Shared', true, $this->pacsConfig->exportCommonData());
		if (strlen($err))
			return $err;

		/* create remaining parts of the PACS that need AuthDB */
		return $this->loadParts($pacsParts);
	}


	/** @brief Return value of a parameter exported by a local PacsConfig instance.

		@param string $name  Name of the parameter

		@retval null   Error; either the instance is not initialized, or there is no
		               parameter by such a name
		@retval mixed  Value of an existing parameter. Obviously it should not be @c null
		               or else you won't be able to distinguish it from an error condition.

		Allows to access PACS -related parameters. Typical use is for building
		SQL queries in external.php for PacsOne.
	 */
	public function getPacsConfigPrm($name)
	{
		if (is_null($this->pacsConfig))
		{
			$this->log->asErr("pacsConfig not initialized (requested '$name')");
			return null;
		}
		$cd = $this->pacsConfig->exportCommonData();
		if (!array_key_exists($name, $cd))
		{
			$this->log->asErr("unknown parameter '$name'");
			return null;
		}
		return $cd[$name];
	}
}
