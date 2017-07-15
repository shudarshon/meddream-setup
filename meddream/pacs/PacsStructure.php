<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for StructureIface -based %PACS part. */
class PacsStructure extends Loader implements StructureIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Structure', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->instanceGetMetadata($instanceUid, $includePatient);
	}


	public function instanceGetStudy($instanceUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->instanceGetStudy($instanceUid);
	}


	public function instanceUidToKey($instanceUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->instanceUidToKey($instanceUid);
	}


	public function instanceKeyToUid($instanceKey)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->instanceKeyToUid($instanceKey);
	}


	public function seriesGetMetadata($seriesUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->seriesGetMetadata($seriesUid);
	}


	public function seriesUidToKey($seriesUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->seriesUidToKey($seriesUid);
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->studyGetMetadata($studyUid, $disableFilter, $fromCache);
	}


	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->studyGetMetadataBySeries($seriesUids, $disableFilter, $fromCache);
	}


	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->studyGetMetadataByImage($imageUids, $disableFilter, $fromCache);
	}


	public function studyListSeries($studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->studyListSeries($studyUid);
	}


	public function studyHasReport($studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->studyHasReport($studyUid);
	}


	public function collectRelatedVideoQualities($imageUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->collectRelatedVideoQualities($imageUid);
	}
}
