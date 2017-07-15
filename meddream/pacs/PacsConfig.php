<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Configuration;


/** @brief Wrapper for ConfigIface -based %PACS part. */
class PacsConfig extends Loader implements ConfigIface
{
	/** @brief Constructs the underlying implementation.

		@param string        $pacs     Name of the %PACS
		@param Logging       $logger   An instance of Logging
		@param Configuration $config   An instance of Configuration
		@param string        $implDir  See parameter with the same name of Loader::load()
	 */
	public function __construct($pacs, Logging $logger, Configuration $config, $implDir = null)
	{
		$this->load('Config', $pacs, $implDir, $logger, $config);
	}


	public function exportCommonData($what = null)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->exportCommonData($what);
	}


	public function getWriteableRoot()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getWriteableRoot();
	}


	public function getDbms()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getDbms();
	}


	public function getDbHost()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getDbHost();
	}


	public function getArchiveDirPrefix()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getArchiveDirPrefix();
	}


	public function getPacsGatewayAddr()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getPacsGatewayAddr();
	}


	public function getDatabaseNames()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getDatabaseNames();
	}


	public function getLoginFormDb()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getLoginFormDb();
	}


	public function supportsAuthentication()
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->supportsAuthentication();
	}


	public function canEncryptSession()
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->canEncryptSession();
	}


	public function getRetrieveEntireStudy()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getRetrieveEntireStudy();
	}


	public function getDcm4cheRecvAet()
	{
		if ($this->notLoaded(__METHOD__))
			return null;

		return $this->pacsInstance->getDcm4cheRecvAet();
	}
}
