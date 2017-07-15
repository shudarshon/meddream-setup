<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief A common loader for wrappers of "PACS parts" (Pacs*.php). */
class Loader implements Configurable
{
	
	/** @brief The normalized name of the implementation, remembered just in case */
	protected $pacs;

	/** @brief The object instance of the PacsPart* class */
	protected $pacsInstance = null;

	/** @brief Delayed report of an error from the constructor.

		Call getInitializationError() just after creating an instance of the class
		to verify if the object is usable.
	 */
	protected $delayedMessage = 'internal (Pacs*.php): not initialized';


	/** @brief Universal implementation for constructors of PacsAnnotation, PacsAuth, etc

		@param string        $pacsPart  Name of %PACS part: Config, Auth, ...
		@param string        $pacsName  Name of the %PACS itself
		@param string        $implDir   Base directory where %PACS implementations are expected
		@param Logging       $logger    An instance of Logging
		@param Configuration $config    An instance of Configuration
		@param CharacterSet  $cs        An instance of CharacterSet
		@param ForeignPath   $fp        An instance of ForeignPath
		@param PacsGw        $gw        An instance of PacsGw
		@param QR            $qr        An instance of QR
		@param AuthDB        $authDb    An instance of AuthDB
		@param PacsShared    $shared    An instance of PacsShared

		The implementation of a %PACS consists of the following directory tree under @p $implDir:

		@verbatim
|
+-- PacsImpl$pacsName
    |
    +-- $pacsPart.php
    +-- ...

		@endverbatim

		<tt>$pacsPart.php</tt> must declare a namespace <tt>Softneta\\MedDream\\Core\\%Pacs\\$pacsName</tt>.

		The class in <tt>$pacsPart.php</tt> must be named <tt>PacsPart$pacsPart</tt>.

		There is no case sensitivity, both variables are automatically converted to Title Case
		(e. g., <tt>Pacsname</tt>, <tt>Pacspart</tt>). However when @p $pacsName is used in the
		namespace name, any occurrences of <tt>'-'</tt> must be changed to <tt>'_'</tt>.

		Therefore descendants of this class (wrappers PacsConfig, PacsAuth, ...) will be able
		to dynamically utilize any implementation, even in parallel if the higher-level code
		supports that.
	 */
	protected function load($pacsPart, $pacsName, $implDir, Logging $logger, Configuration $config,
	/* nullable arguments are those not required for PacsConfig, which has the shortest signature */
		CharacterSet $cs = null, ForeignPath $fp = null, PacsGw $gw = null, QR $qr = null,
		AuthDB $authDb = null, PacsShared $shared = null)
	{
		$this->log = $logger;
		$this->pacs = strtoupper(trim($pacsName));
		$namePart = ucfirst(strtolower(trim($pacsName)));
		$subdir = "PacsImpl$namePart";
		$className = "PacsPart$pacsPart";
		$classNameFull = __NAMESPACE__ . '\\' . str_replace('-', '_', $namePart) . '\\' . $className;
		$fileName = "$pacsPart.php";

		/* verify whether $pacs is supported */
		if (is_null($implDir))
			$implDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		else
			if (strlen($implDir))
			{
				/* add a directory separator if needed */
				$lc = substr($implDir, -1);
				if (($lc != '/') && ($lc != '\\'))
					$implDir .= DIRECTORY_SEPARATOR;
			}
		$implDir .= $subdir . DIRECTORY_SEPARATOR;
		$fullPath = $implDir . $fileName;
		if (!@file_exists($fullPath))
		{
			$this->delayedMessage = "unsupported \$pacs '$pacsName'. The file $fileName " .
				"is missing in the directory '$implDir'.";
			return;
		}

		/* construct the appropriate worker object

			If the class being included still contains some abstract methods,
			then a fatal uncatchable error will occur during include(). The only
			way to diagnose is to look for the fatal error message in PHP's own
			logs or stderr logs.
		 */
		include_once($fullPath);
		if (!class_exists($classNameFull))
		{
			$this->delayedMessage = "unsupported \$pacs '$pacsName'. Likely the name of class $classNameFull" .
				", or its namespace, is simply misspelled in the file $fullPath.";
			return;
		}
		if ($pacsPart == 'Config')
			$this->pacsInstance = new $classNameFull($logger, $config);
		else
			if ($pacsPart == 'Shared')
				$this->pacsInstance = new $classNameFull($logger, $authDb, $config, $cs, $fp, $gw, $qr);
			else
				$this->pacsInstance = new $classNameFull($logger, $authDb, $config, $cs, $fp, $gw, $qr,
					$shared);
		$this->delayedMessage = '';
	}


	/** @brief A helper for descendants that ensures logging of "implementation not loaded" messages */
	protected function notLoaded($method)
	{
		if (is_null($this->pacsInstance))
		{
			$this->log->asErr("$method unavailable: " . $this->delayedMessage);
			return true;
		}

		return false;
	}


	/** @brief Getter for @link $delayedMessage @endlink. */
	public function getInitializationError()
	{
		return $this->delayedMessage;
	}


	/** @brief Common implementation for wrappers of %PACS parts

		@note This wrapper method will be available in every %PACS part, even in those that
		      are not required to provide the underlying implementation.
	 */
	public function configure()
	{
		if (is_null($this->pacsInstance))
			return $this->delayedMessage;

		return $this->pacsInstance->configure();
	}
}
