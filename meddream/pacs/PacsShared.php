<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for SharedIface -based %PACS part. */
class PacsShared extends Loader implements SharedIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null)
	{
		$this->load('Shared', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function getStorageDevicePath($id)
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getStorageDevicePath($id);
	}


	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null)
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->buildPersonName($familyName, $givenName, $middleName, $prefix, $suffix);
	}


	public function cleanDbString($str)
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->cleanDbString($str);
	}
}
