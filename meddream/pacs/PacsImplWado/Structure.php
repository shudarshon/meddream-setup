<?php

namespace Softneta\MedDream\Core\Pacs\Wado;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='WADO'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		$return = array('error' => '');

		/* $instanceUid consists of three UIDs, in format "IMAGE*SERIES*STUDY" */
		$ids = explode('*', $instanceUid);
		if (count($ids) != 3)
		{
			$return['error'] = 'UID has ' . count($ids) . ' component(s)';
			$log->asErr($return['error']);
			return $return;
		}

		$pa = $this->qr->fetchImageWado($ids[0], $ids[1], $ids[2]);
		if (strlen($pa['error']))
			$return['error'] = $pa['error'];
		else
		{
			$return['path'] = $pa['path'];
			$return['uid'] = $ids[0];
			$return['bitsstored'] = 8;

			/* the API mandates a lot of parameters so we'll just add empty defaults
			   while there is no full support
			 */
			$return['xfersyntax'] = '';
			$return['sopclass'] = '';
			if ($includePatient)
			{
				$return['patientid'] = '';
				$return['firstname'] = '';
				$return['lastname'] = '';
				$return['fullname'] = '';
			}
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function seriesGetMetadata($seriesUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		$series = $this->qr->seriesGetMetadata($seriesUid);
		if (!empty($series['error']))
			return $series;

		$return = array();
		for ($s = 0; $s < $series['count']; $s++)
		{
			$img = array();
			$img['xfersyntax'] = '';
			$img['bitsstored'] = @$series[$s]['bitsstored'];
			$img['object'] = $series[$s]['imageid'];
			$img['series'] = $series[$s]['seriesid'];
			$img['study'] = $series[$s]['studyid'];
			$return['image-' . sprintf('%06d', $s)] = $img;
		}
		$return['error'] = '';
		$return['count'] = $series['count'];

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		$return = $this->qr->studyGetMetadata($studyUid, $fromCache);
			/* WARNING: cache doesn't work (not implemented yet), data.php etc shall
			   not pass `true` for it
			 */

		if ($return['error'] == '')
		{
			$this->log->asDump('$return = ', $return);
			$this->log->asDump('end ' . __METHOD__);
		}
		return $return;
	}


	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		$return = array('count' => 0, 'error' => 'not authenticated');

		$this->log->asDump('begin ' . __METHOD__ . '(', $imageUids, ', ', $disableFilter, ', ', $fromCache, ')');

$return['error'] = 'not implemented';
$this->log->asErr($return['error']);
return $return;

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr($return['error']);
			return $return;
		}

		if (sizeof($imageUids) == 0)
		{
			$return['error'] = 'mandatory parameters missing';
			$this->log->asErr($return['error']);
			return $return;
		}

		if (!$fromCache)
		{
			$return['error'] = 'HIS integration by Image UID not implemented for $pacs="DICOM"';
			$this->log->asErr($return['error']);
			return $return;
		}

		/* simply convert $imageUids to a different format. Quite ridiculous,
		   however simplifies data.php.
		 */
		$uniqueSeries = array();
		foreach ($imageUids as $img)
		{
			/* extract UIDs from the path */
			$uids = explode('*', $img);
			$imageUid = $uids[0];
			if (count($uids) > 1)
				$seriesUid = $uids[1];
			else
				$seriesUid = '';
			if (count($uids) > 1)
				$studyUid = $uids[2];
			else
				$studyUid = '';

			/* prepare combined UID for series */
			$allids2 = "$seriesUid*$studyUid";

			/* initialize properties common to all series */
			if (!$return['count'])
			{
				$return['uid'] = $studyUid;
				$return['patientid'] = '';
				$return['lastname'] = '';
				$return['firstname'] = '';
			}

			/* perhaps a new series must be added */
			$i = array_search($allids2, $uniqueSeries);
			if ($i === false)
			{
				$i = $return['count']++;	/* ready to use because of 0-based indices */

				$uniqueSeries[] = $allids2;
				$return[$i] = array('count' => 0, 'id' => $allids2,
					'description' => '', 'modality' => '');
			}

			/* finally, we simply augment the selected series */
			$return[$i]['count']++;
			$return[$i][] = array('id' => $img, 'numframes' => '', 'xfersyntax' => '',
				'bitsstored' => '', 'path' => '');
		}
		$return['error'] = '';

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
