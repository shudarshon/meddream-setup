<?php

namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Pacs\PreloadIface;
use Softneta\MedDream\Core\Pacs\PreloadAbstract;


/** @brief Implementation of PreloadIface for <tt>$pacs='%Conquest'</tt>.

	In this PACS, PreloadIface is used only to update structure with
	missing DICOM attributes (at least Transfer Syntax is beneficial due to
	current limitations of meddream_thumbnail). Sorting is not performed
	afterwards.
 */
class PacsPartPreload extends PreloadAbstract implements PreloadIface
{
	/** @brief Update missing attributes in an instance-level study structure entry  */
	private function updateWithDicomAttributes(&$img)
	{
		if (($img['xfersyntax'] == '') && !empty($img['path']))
		{
			$meta = meddream_extract_meta(dirname(dirname(__DIR__)), $img['path'], 0);
			if (!$meta['error'])
			{
				if (isset($meta['xfersyntax']))
					$img['xfersyntax'] = $meta['xfersyntax'];
				if (isset($meta['sernum']))
					$img['seriesno'] = $meta['sernum'];
				if (isset($meta['instancenum']))
					$img['instanceno'] = $meta['instancenum'];
			}
			else
				$this->log->asErr('meddream_extract_meta: ' . var_export($meta, true));
		}
		if ($img['bitsstored'] == '')
			$img['bitsstored'] = 8;		/* irrelevant for a long time, probably since v3 */
	}


	public function fetchAndSortSeries(array &$seriesStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		foreach ($seriesStruct as $imageDir => $img)
			$this->updateWithDicomAttributes($seriesStruct[$imageDir]);

		$this->log->asDump('updated: ', $seriesStruct);
		$this->log->asDump('end ' . __METHOD__);
		return '';
	}


	public function fetchAndSortStudy(array &$studyStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		for ($i = 0; $i < $studyStruct['count']; $i++)
			for ($j = 0; $j < $studyStruct[$i]['count']; $j++)
				$this->updateWithDicomAttributes($studyStruct[$i][$j]);

		$this->log->asDump('updated: ', $studyStruct);
		$this->log->asDump('end ' . __METHOD__);
		return '';
	}


	public function fetchAndSortStudies(array &$studiesStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		foreach ($studiesStruct as $studyDir => $study)
			foreach ($study as $seriesDir => $series)
				foreach ($series as $imageDir => $img)
					$this->updateWithDicomAttributes($studiesStruct[$studyDir][$seriesDir][$imageDir]);

		$this->log->asDump('updated: ', $studiesStruct);
		$this->log->asDump('end ' . __METHOD__);
		return '';
	}
}
