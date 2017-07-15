<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for ExportIface -based %PACS part. */
class PacsExport extends Loader implements ExportIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Export', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function createJob($studyUids, $mediaLabel, $size, $exportDir)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->createJob($studyUids, $mediaLabel, $size, $exportDir);
	}


	public function getJobStatus($id)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->getJobStatus($id);
	}


	public function verifyJobResults($exportDir)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->verifyJobResults($exportDir);
	}


	public function getAdditionalVolumeSizes()
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->getAdditionalVolumeSizes();
	}
}
