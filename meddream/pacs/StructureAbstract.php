<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements some methods from StructureIface. */
abstract class StructureAbstract implements StructureIface
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


	/** @brief Default implementation of StructureIface::configure().
	
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


	/** @brief Default implementation of StructureIface::instanceGetStudy().

		Logs and returns an error.
	 */
	public function instanceGetStudy($instanceUid)
	{
		$this->log->asErr('internal: PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of StructureIface::instanceUidToKey().

		Returns the input value and is therefore sufficient for PACSes where
		UIDs are primary keys.
	 */
	public function instanceUidToKey($instanceUid)
	{
		return array('error' => '', 'imagepk' => $instanceUid);
	}


	/** @brief Default implementation of StructureIface::instanceKeyToUid().

		Returns the input value and is therefore sufficient for PACSes where
		UIDs are primary keys.
	 */
	public function instanceKeyToUid($instanceKey)
	{
		return array('error' => '', 'imageuid' => $instanceKey);
	}


	/** @brief Default implementation of StructureIface::seriesGetMetadata().

		Logs and returns an error.
	 */
	public function seriesGetMetadata($seriesUid)
	{
		$this->log->asErr('internal: PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => "not implemented");
	}


	/** @brief Default implementation of StructureIface::seriesUidToKey().

		Returns the input value and is therefore sufficient for PACSes where
		UIDs are primary keys.
	 */
	public function seriesUidToKey($seriesUid)
	{
		return array('error' => '', 'seriespk' => $seriesUid);
	}


	/** @brief Default implementation of StructureIface::studyGetMetadataBySeries().

		Logs and returns an error.
	 */
	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false)
	{
		$this->log->asErr('internal: PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => __FUNCTION__ . ': not implemented for this PACS');
	}


	/** @brief Default implementation of StructureIface::studyGetMetadataByImage().

		Logs and returns an error.
	 */
	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		$this->log->asErr('internal: PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => __FUNCTION__ . ': not implemented for this PACS');
	}


	/** @brief Default implementation of StructureIface::studyListSeries().

		Logs and returns an error.
	 */
	public function studyListSeries($studyUid)
	{
		$this->log->asErr('internal: PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => "not implemented");
	}


	/** @brief Default implementation of StructureIface::studyHasReport().

		Indicates that reports are not supported.
	 */
	public function studyHasReport($studyUid)
	{
		return array('error' => '', 'notes' => 2);
	}


	/** @brief Default implementation of StructureIface::collectRelatedVideoQualities().

		Logs and returns a message about missing implementation. If the %PACS
		accepts videos, then this method needs to be overridden.
	 */
	public function collectRelatedVideoQualities($imageUid)
	{
		$this->log->asErr('PacsStructure::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => "not implemented");
	}
}
