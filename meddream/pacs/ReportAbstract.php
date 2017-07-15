<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Implements default methods for ReportIface.

	@todo Currently all methods are implemented and the class isn't marked abstract.
	      It is possible for Loader to fall back to this file if the corresponding
	      %PACS part wasn't found.
 */
class ReportAbstract implements ReportIface
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


	/** @brief Default implementation of ReportIface::collectReports().

		Logs and reports an error.
	 */
	public function collectReports($studyUid, $withAttachments = false)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of ReportIface::getLastReport().

		Logs a warning and indicates the report is absent.

		Override this if the %PACS supports reports and you want to export them.
	 */
	public function getLastReport($studyUid)
	{
		$this->log->asWarn('PacsReport::' . __FUNCTION__ . ' not implemented for this PACS');
		return array('error' => '', 'id' => null, 'user' => null, 'created' => null,
			'headline' => null, 'notes' => null);
	}


	/** @brief Default implementation of ReportIface::createReport().

		Logs and reports an error.
	 */
	public function createReport($studyUid, $note, $date = '', $user = '')
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return 'not implemented';
	}


	/** @brief Default implementation of ReportIface::collectTemplates().

		Logs and reports an error.
	 */
	public function collectTemplates()
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of ReportIface::createTemplate().

		Logs and reports an error.
	 */
	public function createTemplate($group, $name, $text)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return 'not implemented';
	}


	/** @brief Default implementation of ReportIface::updateTemplate().

		Logs and reports an error.
	 */
	public function updateTemplate($id, $group, $name, $text)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return 'not implemented';
	}


	/** @brief Default implementation of ReportIface::getTemplate().

		Logs and reports an error.
	 */
	public function getTemplate($id)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return 'not implemented';
	}


	/** @brief Default implementation of ReportIface::deleteTemplate().

		Logs and reports an error.
	 */
	public function deleteTemplate($id)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return 'not implemented';
	}


	/** @brief Default implementation of ReportIface::collectAttachments().

		Logs and reports an error.
	 */
	public function collectAttachments($studyUid, $return)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		$return['error'] = 'not implemented';
		return $return;
	}


	/** @brief Default implementation of ReportIface::createAttachment().

		Logs and reports an error.
	 */
	public function createAttachment($studyUid, $reportId, $mimeType, $fileName, $fileSize, $fileData = null)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of ReportIface::getAttachment().

		Logs and reports an error.
	 */
	public function getAttachment($studyUid, $seq)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief Default implementation of ReportIface::deleteAttachment().

		Logs and reports an error.
	 */
	public function deleteAttachment($studyUid, $noteId, $seq)
	{
		$this->log->asErr('internal: PacsReport::' . __FUNCTION__ . ' not implemented but still called');
		return array('error' => 'not implemented');
	}
}
