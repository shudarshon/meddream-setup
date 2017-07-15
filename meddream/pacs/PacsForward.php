<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for ForwardIface -based %PACS part. */
class PacsForward extends Loader implements ForwardIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Forward', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function createJob($studyUid, $dstAe)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->createJob($studyUid, $dstAe);
	}


	public function getJobStatus($id)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->getJobStatus($id);
	}


	public function collectDestinationAes()
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage, 'count' => 0);

		return $this->pacsInstance->collectDestinationAes();
	}
}
