<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for SearchIface -based %PACS part. */
class PacsSearch extends Loader implements SearchIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Search', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function getStudyCounts()
	{
		if ($this->notLoaded(__METHOD__))
			return array('d1' => 0, 'd3' => 0, 'w1' => 0, 'any' => 0);

		return $this->pacsInstance->getStudyCounts();
	}


	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax);
	}
}
