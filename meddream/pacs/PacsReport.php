<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for ReportIface -based %PACS part. */
class PacsReport extends Loader implements ReportIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Report', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function collectReports($studyUid, $withAttachments = false)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->collectReports($studyUid, $withAttachments);
	}


	public function getLastReport($studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->getLastReport($studyUid);
	}


	public function createReport($studyUid, $note, $date = '', $user = '')
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->createReport($studyUid, $note, $date, $user);
	}


	public function collectTemplates()
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->collectTemplates();
	}


	public function createTemplate($group, $name, $text)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->createTemplate($group, $name, $text);
	}


	public function updateTemplate($id, $group, $name, $text)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->updateTemplate($id, $group, $name, $text);
	}


	public function getTemplate($id)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->getTemplate($id);
	}


	public function deleteTemplate($id)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->deleteTemplate($id);
	}


	public function collectAttachments($studyUid, $return)
	{
		if ($this->notLoaded(__METHOD__))
		{
			$return['error'] = $this->delayedMessage;
			return $return;
		}

		return $this->pacsInstance->collectAttachments($studyUid, $return);
	}


	public function createAttachment($studyUid, $reportId, $mimeType, $fileName, $fileSize,
		$fileData = null)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->createAttachment($studyUid, $reportId, $mimeType, $fileName,
			$fileSize, $fileData);
	}


	public function getAttachment($studyUid, $seq)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->getAttachment($studyUid, $seq);
	}


	public function deleteAttachment($studyUid, $noteId, $seq)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->deleteAttachment($studyUid, $noteId, $seq);
	}
}
