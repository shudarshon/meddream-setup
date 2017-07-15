<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements some methods from SharedIface */
abstract class SharedAbstract implements SharedIface
{
	protected $log;         /**< @brief Instance of Logging */
	protected $authDB;      /**< @brief Instance of AuthDB */
	protected $config;      /**< @brief Instance of Configuration */
	protected $cs;          /**< @brief Instance of CharacterSet */
	protected $fp;          /**< @brief Instance of ForeignPath */
	protected $gw;          /**< @brief Instance of PacsGw */
	protected $qr;          /**< @brief Instance of QR */

	/** @brief Array from PacsConfig::exportCommonData() */
	protected $commonData;


	public function __construct(Logging $logger, AuthDB $authDb, Configuration $cfg, CharacterSet $cs,
		ForeignPath $fp, PacsGw $gw, QR $qr)
	{
		$this->log = $logger;
		$this->authDB = $authDb;
		$this->config = $cfg;
		$this->cs = $cs;
		$this->fp = $fp;
		$this->gw = $gw;
		$this->qr = $qr;
	}


	/** @brief Default implementation of SharedIface::configure().
	
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


	/** @brief Default implementation of SharedIface::getStorageDevicePath() that always fails */
	public function getStorageDevicePath($id)
	{
		$this->log->asErr('internal: PacsShared::' . __FUNCTION__ . ' not implemented but still called');
		return false;
	}


	/** @brief Default implementation of SharedIface::buildPersonName() that always fails */
	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null)
	{
		$this->log->asErr('internal: PacsShared::' . __FUNCTION__ . ' not implemented but still called');
		return false;
	}


	/** @brief Default implementation of SharedIface::cleanDbString() that doesn't change the value */
	public function cleanDbString($str)
	{
		return $str;
	}
}
