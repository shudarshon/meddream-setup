<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Wrapper for AnnotationIface -based %PACS part. */
class PacsAnnotation extends Loader implements AnnotationIface
{
	public function __construct($pacs, Logging $logger, AuthDB $authDb, Configuration $config,
		CharacterSet $cs, ForeignPath $fp, PacsGw $gw = null, QR $qr = null, $implDir = null,
		PacsShared $shared = null)
	{
		$this->load('Annotation', $pacs, $implDir, $logger, $config, $cs, $fp, $gw, $qr, $authDb, $shared);
	}


	public function importCommonData($data)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->importCommonData($data);
	}


	public function setProductVersion($text)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->setProductVersion($text);
	}


	public function isSupported($testVersion = false)
	{
		if ($this->notLoaded(__METHOD__))
			return $this->delayedMessage;

		return $this->pacsInstance->isSupported($testVersion);
	}


	public function isPresentForStudy($studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return false;

		return $this->pacsInstance->isPresentForStudy($studyUid);
	}


	public function collectStudyInfoForImage($instanceUid, $type = 'dicom')
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->collectStudyInfoForImage($instanceUid, $type);
	}


	public function collectPrSeriesImages($studyUid)
	{
		if ($this->notLoaded(__METHOD__))
			return array('error' => $this->delayedMessage);

		return $this->pacsInstance->collectPrSeriesImages($studyUid);
	}
}
