<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements default methods for ExportIface.

	@todo Currently all methods are implemented and the class isn't marked abstract.
	      It is possible for Loader to fall back to this file if the corresponding
	      %PACS part wasn't found.
*/
class ExportAbstract implements ExportIface
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


	/** @brief A simple setter for @link $commonData @endlink. */
	public function importCommonData($data)
	{
		$this->commonData = $data;
		return '';
	}


	/** @brief Default implementation of ExportIface::createJob().

		Override this if the %PACS is able to do the export on its own.
	 */
	public function createJob($studyUids, $mediaLabel, $size, $exportDir)
	{
		return null;
	}


	/** @brief Default implementation of ExportIface::createJob().

		Override this if the %PACS is able to do the export on its own.
	 */
	public function getJobStatus($id)
	{
		return null;
	}


	/** @brief Default implementation of ExportIface::createJob().

		Override this if createJob() preserves any reference data that makes the
		verification possible.
	 */
	public function verifyJobResults($exportDir)
	{
		return '';
	}


	/** @brief Default implementation of ExportIface::getAdditionalVolumeSizes().

		Override this if the %PACS supports splitting into volumes.
	 */
	public function getAdditionalVolumeSizes()
	{
		return array('data' => array(), 'default' => '');
	}
}
