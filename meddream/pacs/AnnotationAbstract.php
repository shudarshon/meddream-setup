<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements default methods for AnnotationIface

	@todo Currently all methods are implemented and the class isn't marked abstract.
	      It is possible for Loader to fall back to this file if the corresponding
	      %PACS part wasn't found.
 */
class AnnotationAbstract implements AnnotationIface
{
	protected $log;         /**< @brief Instance of Logging */
	protected $authDB;      /**< @brief Instance of AuthDB */
	protected $config;      /**< @brief Instance of Configuration */
	protected $cs;          /**< @brief Instance of CharacterSet */
	protected $fp;          /**< @brief Instance of ForeignPath */
	protected $gw;          /**< @brief Instance of PacsGw */
	protected $qr;          /**< @brief Instance of QR */
	protected $shared;      /**< @brief Instance of PacsShared */

	/** @brief A copy of Backend::$productVersion */
	protected $productVersion;

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


	/** @brief Default implementation of AnnotationIface::configure().
	
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


	/** @brief A simple setter for @link $productVersion @endlink. */
	public function setProductVersion($text)
	{
		$this->productVersion = $text;
		return '';
	}


	/** @brief Default implementation of Annotationiface::isSupported().

		Fails with a predefined error message.
	 */
	public function isSupported($testVersion = false)
	{
		return 'no annotation support (not implemented)';
	}


	/** @brief Default implementation of Annotationiface::isPresentForStudy().

		Returns @c false.
	 */
	public function isPresentForStudy($studyUid)
	{
		return false;
	}


	/** @brief Default implementation of Annotationiface::collectStudyInfoForImage().

		Returns a "not implemented" error message.
	 */
	public function collectStudyInfoForImage($instanceUid, $type = 'dicom')
	{
		$this->log->asErr('internal: PacsAnnotation::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of Annotationiface::collectPrSeriesImages().

		Returns a "not implemented" error message.
	 */
	public function collectPrSeriesImages($studyUid)
	{
		$this->log->asErr('internal: PacsAnnotation::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}
}
