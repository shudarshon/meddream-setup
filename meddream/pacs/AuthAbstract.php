<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements some methods from AuthIface */
abstract class AuthAbstract implements AuthIface
{
	protected $log;         /**< @brief Instance of Logging */
	protected $authDB;      /**< @brief Instance of AuthDB */
	protected $config;      /**< @brief Instance of Configuration */
	protected $cs;          /**< @brief Instance of CharacterSet */
	protected $fp;          /**< @brief Instance of ForeignPath */
	protected $gw;          /**< @brief Instance of PacsGw */
	protected $qr;          /**< @brief Instance of QR */
	protected $shared;      /**< @brief Instance of PacsShared */

	/** @brief Array from PacsConfig::exportCommonData() */
	protected $commonData;


	public function __construct(Logging $logger, AuthDB $authDb, Configuration $cfg, CharacterSet $cs,
		ForeignPath $fp, PacsGw $gw, QR $qr, PacsShared $shared)
	{
		$this->log = $logger;
		$this->authDB = $authDb;
		$this->config = $cfg;
		$this->cs = $cs;
		$this->fp = $fp;
		$this->gw = $gw;
		$this->qr = $qr;
		$this->shared = $shared;
	}


	/** @brief Default implementation of AuthIface::configure().
	
		Does nothing and succeeds.
	 */
	public function configure()
	{
		return '';
	}


	/** @brief A simple setter for @link $commonData @endlink. */
	public function importCommonData($data)
	{
		$this->commonData = $data;
		return '';
	}


	/** @brief The default AuthIface::login() that returns @c false.

		Override this one if the %PACS supports its own logins.
	 */
	public function login($database, $user, $password)
	{
		return false;
	}


	/** @brief The default AuthIface::hasPrivilege() that returns @c false. */
	public function hasPrivilege($privilege)
	{
		return false;
	}


	/** @brief The default AuthIface::firstName() that returns an empty string. */
	public function firstName()
	{
		return '';
	}


	/** @brief The default AuthIface::lastName() that returns an empty string. */
	public function lastName()
	{
		return '';
	}


	/** @brief Default implementation that does nothing and succeeds. */
	public function onConnect(array &$return)
	{
		return '';
	}
}
