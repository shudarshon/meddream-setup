<?php

namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='GW'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		return $this->gw->instanceGetMetadata($instanceUid, $includePatient, true);
			/* forcing the 3rd parameter (*not* part of StructureIface).

				It is important for $pacs='DICOM' where the default value (false)
				allows support for RetrieveEntireStudy=0 mode (soon to be
				removed, by the way). Here we can leave "true" as PGW modules
				other than "type: qr" are ignoring the "fromCache" parameter.
			 */
	}


	public function instanceUidToKey($instanceUid)
	{
		return $this->gw->instanceUidToKey($instanceUid);
	}


	public function instanceKeyToUid($instanceKey)
	{
		return $this->gw->instanceKeyToUid($instanceKey);
	}


	public function instanceGetStudy($instanceUid)
	{
		return $this->gw->instanceGetStudy($instanceUid);
	}


	public function seriesGetMetadata($seriesUid)
	{
		return $this->gw->seriesGetMetadata($seriesUid);
	}


	public function seriesUidToKey($seriesUid)
	{
		return $this->gw->seriesUidToKey($seriesUid);
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		return $this->gw->studyGetMetadata($studyUid, $disableFilter, $fromCache);
	}


	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		return $this->gw->studyGetMetadataByImage($imageUids, $disableFilter, $fromCache);
	}


	public function studyListSeries($studyUid)
	{
		return $this->gw->studyListSeries($studyUid);
	}
}
