<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for PreloadIface -based %PACS part. */
class PacsPreload extends Loader implements PreloadIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Preload', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function fetchInstance($imageUid, $seriesUid, $studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->fetchInstance($imageUid, $seriesUid, $studyUid);
	}


	public function fetchAndSortSeries(array &$seriesStruct)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->fetchAndSortSeries($seriesStruct);
	}


	public function fetchAndSortStudy(array &$studyStruct)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->fetchAndSortStudy($studyStruct);
	}


	public function fetchAndSortStudies(array &$studiesStruct)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->fetchAndSortStudies($studiesStruct);
	}


	public function removeFetchedFile($path)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->removeFetchedFile($path);
	}
}
