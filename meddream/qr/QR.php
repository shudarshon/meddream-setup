<?php

namespace Softneta\MedDream\Core\QueryRetrieve;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\Backend;


/** @brief The top-level wrapper. */
class QR implements QrBasicIface, QrNonblockingIface
{
	protected $oldImpl;         /**< @brief An instance of QrToolkitWrapper */


	/** @brief Object factory with optional autoconfiguration */
	public static function getObj(Backend $backend = null)
	{
		if (is_null($backend))
			$backend = new Backend(array(), false);

		$retrieveEntireStudy = $backend->getPacsConfigPrm('retrieve_entire_study');
		$lfd = explode('|', $backend->getPacsConfigPrm('login_form_db'));
		$remoteListener = $lfd[0];
		$localListener = $backend->getPacsConfigPrm('dcm4che_recv_aet');
		$localAet = $backend->getPacsConfigPrm('db_host');
		$wadoAddr = $backend->getPacsConfigPrm('archive_dir_prefix');

		return new QR($backend->log, $backend->cs, $retrieveEntireStudy, $remoteListener,
			$localListener, $localAet, $wadoAddr);
	}


	/** @brief Creates instance of QrToolkitWrapper. */
	public function __construct(Logging $log, CharacterSet $cs, $retrieveEntireStudy,
		$remoteConnectionString, $localConnectionString, $localAet, $wadoAddr)
	{
		$this->oldImpl = new QrToolkitWrapper($log, $cs, $retrieveEntireStudy,
			$remoteConnectionString, $localConnectionString, $localAet, $wadoAddr);
	}


	public function findStudies($patientId, $patientName, $studyId, $accNum, $studyDesc, $refPhys,
		$dateFrom, $dateTo, $modality)
	{
		return $this->oldImpl->findStudies($patientId, $patientName, $studyId, $accNum, $studyDesc,
			$refPhys, $dateFrom, $dateTo, $modality);
	}


	public function studyGetMetadata($studyUid, $fromCache = false)
	{
		return $this->oldImpl->studyGetMetadata($studyUid, $fromCache);
	}


	public function seriesGetMetadata($seriesUid)
	{
		return $this->oldImpl->seriesGetMetadata($seriesUid);
	}


	public function fetchImage($imageUid, $seriesUid, $studyUid)
	{
		return $this->oldImpl->fetchImage($imageUid, $seriesUid, $studyUid);
	}


	public function fetchImageWado($imageUid, $seriesUid, $studyUid)
	{
		return $this->oldImpl->fetchImageWado($imageUid, $seriesUid, $studyUid);
	}


	public function fetchStudyStart($studyUid)
	{
		return $this->oldImpl->fetchStudyStart($studyUid);
	}


	public function fetchStudyContinue(&$rsrc)
	{
		return $this->oldImpl->fetchStudyContinue($rsrc);
	}


	public function fetchStudyBreak(&$rsrc)
	{
		return $this->oldImpl->fetchStudyBreak($rsrc);
	}


	public function fetchStudyEnd(&$rsrc)
	{
		return $this->oldImpl->fetchStudyEnd($rsrc);
	}


	public function fetchStudy($studyUid, $silent = false)
	{
		return $this->oldImpl->fetchStudy($studyUid, $silent);
	}
}
