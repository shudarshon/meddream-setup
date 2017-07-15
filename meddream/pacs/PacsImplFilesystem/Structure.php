<?php

namespace Softneta\MedDream\Core\Pacs\Filesystem;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='FileSystem'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	private function sortFilesByProp($a, $b)
	{
		if (is_null($a['studyuid']) && !is_null($b['studyuid']))
			return -1;
		if (!is_null($a['studyuid']) && is_null($b['studyuid']))
			return 1;
		$v = strcmp($a['studyuid'], $b['studyuid']);
		if ($v)
			return $v;

		if (is_null($a['seriesuid']) && !is_null($b['seriesuid']))
			return -1;
		if (!is_null($a['seriesuid']) && is_null($b['seriesuid']))
			return 1;
		$v = strcmp($a['seriesuid'], $b['seriesuid']);
		if ($v)
			return $v;

		if (is_null($a['instancenum']) && !is_null($b['instancenum']))
			return -1;
		if (!is_null($a['instancenum']) && is_null($b['instancenum']))
			return 1;
		$v = intval($a['instancenum']) - intval($b['instancenum']);
		if ($v)
			return $v;

		if (is_null($a['acqnum']) && !is_null($b['acqnum']))
			return -1;
		if (!is_null($a['acqnum']) && is_null($b['acqnum']))
			return 1;
		$v = intval($a['acqnum']) - intval($b['acqnum']);
		if ($v)
			return $v;

		if (is_null($a['instanceuid']) && !is_null($b['instanceuid']))
			return -1;
		if (!is_null($a['instanceuid']) && is_null($b['instanceuid']))
			return 1;
		return strcmp($a['instanceuid'], $b['instanceuid']);
	}


	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		$path = str_replace("..", "", $this->commonData['archive_dir_prefix'] . $instanceUid);
			/* there's no valid reason to use ".."! */

		$workDir = dirname(dirname(__DIR__));
		$log->asDump('meddream_extract_meta(', $workDir, ', ', $path, ', 0)');
		$meta = meddream_extract_meta($workDir, $path, 0);
		$log->asDump('$meta = ', $meta);

		$return = array('error' => '');
		if ($meta['error'])
			return array('error' => 'Failed to parse file, see extension logs');
		$return['path'] = $path;
		if (isset($meta['xfersyntax']))
			$return['xfersyntax'] = $meta['xfersyntax'];
		else
			$return['xfersyntax'] = '';
		if (isset($meta['mssopclass']))
			$return['sopclass'] = $meta['mssopclass'];
		else
			$return['sopclass'] = '';
		if (isset($meta['bitsstored']))
			$return['bitsstored'] = $meta['bitsstored'];
		else
			$return['bitsstored'] = '';
		if ($includePatient)
		{
			$rawName = $meta['patientname'];
			$groups = explode('=', $rawName);
			$components = explode('^', $groups[0]);
			if (count($components) > 1)
				$return['firstname'] = $components[1];
			else
				$return['firstname'] = '';
			$return['lastname'] = $components[0];
			$return['fullname'] = trim($return['firstname'] . ' ' . $return['lastname']);
		}
		if (isset($meta['mssopinst']))
			$return['uid'] = $meta['mssopinst'];
		else
			$return['uid'] = str_replace(array('/', '\\'), '_', $instanceUid);

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache,  ')');

		$log->asErr('not implemented');

		return array('error' => 'not implemented');
			/* we need a path to a directory while $studyUid is a true UID. In general
			   it could be augmented with the path in studyGetMetadataByImage (similarly
			   to how $pacs='DICOM' uses "combined" UIDs), however later data.php encodes
			   UIDs up to 255 characters only and problems would arise with deeper locations.
			 */
	}


	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false)
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '(', $imageUids, ', ', $disableFilter, ', ', $fromCache,  ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		if (sizeof($imageUids) == 0)
			return array('error' => 'required parameter(s) missing');

		$cs = $this->cs;

		$path = str_replace('..', '', $this->commonData['archive_dir_prefix'] . $imageUids[0]);
			/* there's no valid reason to use '..'! */
		$workDir = dirname(dirname(__DIR__));
		$return = array('count' => 0);

		$firstErrorCode = 0;
		if (!is_dir($path))
		{
			$log->asDump('meddream_extract_meta(', $workDir, ', ', $path, ', 0)');
			$tags = meddream_extract_meta($workDir, $path, 0);
			$log->asDump('$tags = ', $tags);
			if ($tags['error'])
				return array('error' => 'Failed to parse file, see extension logs');

			/* convert non-existing values to empty strings etc */
			if (array_key_exists('studyuid', $tags))
				$fStudyUid = $tags['studyuid'];
			else
				$fStudyUid = 'fake.study.uid';
			$fStudyDate = $tags['studydate'];
			$fPatientId = $tags['patientid'];
			$fPatientName = $tags['patientname'];
			if (array_key_exists('seriesuid', $tags))
				$fSeriesUid = $tags['seriesuid'];
			else
				$fSeriesUid = 'fake.series.id';
			$fModality = $tags['modality'];
			$fSeriesDesc = $tags['seriesdesc'];
			if (array_key_exists('xfersyntax', $tags))
				$fXferSyntax = $tags['xfersyntax'];
			else
				$fXferSyntax = '';
			if (array_key_exists('numframes', $tags))
				$fNumFrames = $tags['numframes'];
			else
				$fNumFrames = 0;
			if (array_key_exists('bitsstored', $tags))
				$fBitsStored = $tags['bitsstored'];
			else
				$fBitsStored = 8;

			if (!$disableFilter)
				if (($fModality == 'PR') || ($fModality == 'KO'))
					return array('error' => "No images to display\n(some might have been skipped)");

			$return['error'] = '';
			$return['count'] = 1;
			$return['uid'] = $fStudyUid;
			$return['studydate'] = $fStudyDate;
			$return['sourceae'] = '';
			$return['notes'] = 2;
			$return['patientid'] = $fPatientId;
			if (strlen($fPatientName))
			{
				$pn = explode('^', $fPatientName);
				$return['lastname'] = $cs->utf8Encode($pn[0]);
				if (count($pn) > 1)
					$return['firstname'] = $cs->utf8Encode($pn[1]);
				else
					$return['firstname'] = '';
			}
			else
			{
				$return['lastname'] = 'surname';
				$return['firstname'] = 'given_name';
			}

			$return[0] = array();
			$return[0]['count'] = 1;
			$return[0]['modality'] = $fModality;
			$return[0]['id'] = $fSeriesUid;		/* !!!TODO: $imageUids[0] for saveSeries.php etc */
			$return[0]['description'] = $fSeriesDesc;

			$return[0][0] = array();
			$return[0][0]['id'] = $imageUids[0];
			$return[0][0]['numframes'] = $fNumFrames;
			$return[0][0]['xfersyntax'] = $fXferSyntax;
			$return[0][0]['bitsstored'] = 8;
			$return[0][0]['path'] = $path;
		}
		else
		{
			/* collect all file names in this directory (subdirectories ignored) */
			$names = array();
			$dh = @opendir($path);
			if ($dh)
			{
				while (($fn = @readdir($dh)) !== FALSE)
					if (!is_dir($path . DIRECTORY_SEPARATOR . $fn))
						$names[] = $fn;
				closedir($dh);
			}
			sort($names, SORT_LOCALE_STRING);

			/* fill $files from DICOM files found in that directory */
			$files = array();
			$numFailed = 0;
			$numIgnored = 0;
			$firstEntry = true;
			$studyUidPrev = null;
			foreach ($names as $n)
			{
				$file = $path . DIRECTORY_SEPARATOR . $n;

				$log->asDump('meddream_extract_meta(', $workDir, ', ', $file, ', 0)');
				$tags = meddream_extract_meta($workDir, $file, 0);
				$log->asDump('$tags = ', $tags);

				/* skip altogether if parser failed */
				if ($tags['error'])
				{
					if (!$firstErrorCode)
						$firstErrorCode = $tags['error'];
					$numFailed++;
					continue;
				}

				/* convert non-existing values to empty strings */
				if (array_key_exists('studyuid', $tags))
					$fStudyUid = $tags['studyuid'];
				else
					$fStudyUid = null;

				/* skip the ones with different study UIDs */
				if ($firstEntry)
				{
					$studyUidPrev = $fStudyUid;
					$firstEntry = false;
				}
				else
					if ($fStudyUid !== $studyUidPrev)
					{
						$numIgnored++;
						continue;
					}

				/* ...continuing */
				$fStudyDate = $tags['studydate'];
				$fPatientId = $tags['patientid'];
				$fPatientName = $tags['patientname'];
				if (array_key_exists('seriesuid', $tags))
					$fSeriesUid = $tags['seriesuid'];
				else
					$fSeriesUid = null;
				$fModality = $tags['modality'];
				$fSeriesDesc = $tags['seriesdesc'];
				if (array_key_exists('mssopinst', $tags))
					$fInstanceUid = $tags['mssopinst'];
				else
					$fInstanceUid = null;
				$fAcqNum = $tags['acqnum'];
				$fInstanceNum = $tags['instancenum'];
				if (array_key_exists('xfersyntax', $tags))
					$fXferSyntax = $tags['xfersyntax'];
				else
					$fXferSyntax = null;
				if (array_key_exists('numframes', $tags))
					$fNumFrames = $tags['numframes'];
				else
					$fNumFrames = null;
				if (array_key_exists('bitsstored', $tags))
					$fBitsStored = $tags['bitsstored'];
				else
					$fBitsStored = 8;

				$files[] = array('path' => $file, 'patientid' => $fPatientId,
					'patientname' => $fPatientName, 'seriesuid' => $fSeriesUid,
					'modality' => $fModality, 'studyuid' => $fStudyUid, 'studydate' => $fStudyDate,
					'seriesdesc' => $fSeriesDesc, 'instanceuid' => $fInstanceUid,
					'acqnum' => $fAcqNum, 'instancenum' => $fInstanceNum,
					'xfersyntax' => $fXferSyntax, 'numframes' => $fNumFrames,
					'bitsstored' => $fBitsStored);
			}
			unset($names);
			if ($numFailed)
				$this->log->asWarn(__METHOD__ . ": $numFailed file(s) likely non-DICOM, in '$path'");
			if ($numIgnored)
				$this->log->asWarn(__METHOD__ . ": $numIgnored file(s) with different Study ID ignored, in '$path'");
			usort($files, array(__CLASS__, 'sortFilesByProp'));

			/* convert to structure tree required by Flash */
			$return['error'] = '';
			$studyUidPrev = -1;
			$seriesUidPrev = -1;
			$firstEntry = true;
			$seriesCount = -1;
			$imageCount = -1;
			for ($k = 0; $k < count($files); $k++)
			{
				if (!$disableFilter)
					if (($files[$k]['modality'] == 'PR') || ($files[$k]['modality'] == 'KO'))
						continue;

				/* study-related information from the 1st entry; goes to top level */
				if ($firstEntry)
				{
					$firstEntry = false;

					$studyUidPrev = $files[$k]['studyuid'];
					$return['uid'] = $studyUidPrev;
					$return['studydate'] = $files[$k]['studydate'];
					$return['patientid'] = $files[$k]['patientid'];
					$return['sourceae'] = '';
					$return['notes'] = 2;

					$pn = explode('^', $files[$k]['patientname']);
					$return['lastname'] = $cs->utf8Encode($pn[0]);
						/* !!!TODO: don't encode if $files[$k]['charset'] === 'ISO_IR 192' */
					if (count($pn) > 1)
						$return['firstname'] = $cs->utf8Encode($pn[1]);
					else
						$return['firstname'] = '';
				}

				/* series-related */
				if ($files[$k]['seriesuid'] !== $seriesUidPrev)
				{
					$seriesUidPrev = $files[$k]['seriesuid'];
					$seriesCount++;
					$return['count']++;
					$return[$seriesCount] = array();
					$return[$seriesCount]['count'] = 0;
					$imageCount = -1;

					$return[$seriesCount]['id'] = $seriesUidPrev;
					$return[$seriesCount]['description'] = $files[$k]['seriesdesc'];
					$return[$seriesCount]['modality'] = $files[$k]['modality'];
				}

				/* image-related */
				$imageCount++;
				$return[$seriesCount]['count']++;
				$return[$seriesCount][$imageCount] = array();
				$idStr = $files[$k]['path'];
				$prefLen = strlen($this->commonData['archive_dir_prefix']);
				if (!strncmp($idStr, $this->commonData['archive_dir_prefix'], $prefLen))
					$idStr = substr($idStr, $prefLen);
				$return[$seriesCount][$imageCount]['id'] = $idStr;
				$return[$seriesCount][$imageCount]['numframes'] = $files[$k]['numframes'];
				$return[$seriesCount][$imageCount]['xfersyntax'] = $files[$k]['xfersyntax'];
				$return[$seriesCount][$imageCount]['bitsstored'] = $files[$k]['bitsstored'];
				$return[$seriesCount][$imageCount]['path'] = $files[$k]['path'];
			}
			if ($firstErrorCode)
				$return['error'] = 'Failed to parse file(s), see extension logs';
			else
				if ($return['count'] < 1)
					$return['error'] = "No images to display\n(some might have been skipped)";
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);

		return $return;
	}
}
