<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements some methods from PreloadIface. */
abstract class PreloadAbstract implements PreloadIface
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


	/** @brief Default implementation of PreloadIface::configure().
	
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


	/** @brief Default implementation of PreloadIface::fetchInstance().

		Returns @c null to indicate that the call was not needed for the current
		%PACS. Nothing is logged.

		@warning There is no "not implemented" warning in the logs which reminds
		         about a missing function in your descendant of PreloadAbstract.
		         Therefore implementing new PACSes that indeed need this call is
		         more difficult. On the other hand, in other PACSes an empty class
		         is enough for PreloadAbstract.
	 */
	public function fetchInstance($imageUid, $seriesUid, $studyUid)
	{
		return null;
	}


	/** @brief Default implementation of fetchAndSortStudy::fetchAndSortSeries().

		Does nothing, succeeds.
	 */
	public function fetchAndSortSeries(array &$seriesStruct)
	{
		return '';
	}


	/** @brief Default implementation of fetchAndSortStudy::fetchAndSortStudy().

		Does nothing, succeeds.
	 */
	public function fetchAndSortStudy(array &$studyStruct)
	{
		return '';
	}


	/** @brief Default implementation of fetchAndSortStudy::fetchAndSortStudies().

		Does nothing, succeeds.
	 */
	public function fetchAndSortStudies(array &$studyStruct)
	{
		return '';
	}


	/** @brief Default implementation of PreloadIface::removeFetchedFile().

		Does nothing, succeeds.
	 */
	public function removeFetchedFile($path)
	{
		return '';
	}
}
