<?php

namespace Softneta\MedDream\Core\Pacs\Wado;

use Softneta\MedDream\Core\Pacs\PreloadIface;
use Softneta\MedDream\Core\Pacs\PreloadAbstract;
use Softneta\MedDream\Core\QueryRetrieve\QrAbstract;


/** @brief Implementation of PreloadIface for <tt>$pacs='WADO'</tt>. */
class PacsPartPreload extends PreloadAbstract implements PreloadIface
{
	private function compareInstances($a, $b)
	{
		return QrAbstract::compareInstances($a, $b);
	}


	private function compareSeries($a, $b)
	{
		/* compared to QrAbstract::compareSeries, our arrays contain an additional level
		   with numeric keys. Luckily, the key 0 should always exist, so we can use it.
		 */
		if (isset($a[0]))
			$aa = $a[0];
		else
			return 0;
		if (isset($b[0]))
			$bb = $b[0];
		else
			return 0;

		/* move unset values to the end */
		if (isset($aa['seriesno']))
		{
			if (!isset($bb['seriesno']))
				return -1;
			/* both values are now safe for indexing */

			/* move null values to the end */
			if (!is_null($aa['seriesno']))
			{
				if (is_null($bb['seriesno']))
					return -2;
				/* both values are now safe for logic */

				/* the main logic */
				return ((int) $aa['seriesno']) - ((int) $bb['seriesno']);
			}
			else
				if (!is_null($bb['seriesno']))
					return 2;
				else
					return 0;
		}
		else
			if (isset($bb['seriesno']))
				return 1;
			else
				return 0;		/* both unset */
	}


	function compareSeries2($a, $b)
	{
		/* compared to QrAbstract::compareSeries, our arrays contain an additional level
		   with keys named "image-NNNNNN". Luckily, the key "image-000000" should always
		   exist, so we can use it.
		 */
		if (isset($a['image-000000']))
			$aa = $a['image-000000'];
		else
			return 0;
		if (isset($b['image-000000']))
			$bb = $b['image-000000'];
		else
			return 0;

		/* move unset values to the end */
		if (isset($aa['seriesno']))
		{
			if (!isset($bb['seriesno']))
				return -1;
			/* both values are now safe for indexing */

			/* move null values to the end */
			if (!is_null($aa['seriesno']))
			{
				if (is_null($bb['seriesno']))
					return -2;
				/* both values are now safe for logic */

				/* the main logic */
				return ((int) $aa['seriesno']) - ((int) $bb['seriesno']);
			}
			else
				if (!is_null($bb['seriesno']))
					return 2;
				else
					return 0;
		}
		else
			if (isset($bb['seriesno']))
				return 1;
			else
				return 0;		/* both unset */
	}


	private function sortSeries2(&$series)
	{
		/* our keys are in form 'image-NNNNNN'; usort() needs numeric keys */
		$instances = array();
		foreach ($series as $ins)
			$instances[] = $ins;

		usort($instances, 'self::compareInstances');

		$j = 0;
		foreach ($instances as $ins)
			$series["image-" . sprintf("%06d", $j++)] = $ins;
	}


	/** @brief Sort a study structure in-place */
	private function sortStudy(&$study)
	{
		/* usort() needs numerically-indexed arrays though ours are indexed in both
			fashions. The easiest way is to sort a copy. Two separate levels will be
			needed due to the two-dimensional nature.

			Afterwards the items are updated by indexing them in the same fashion
			(via 0-based numbers), therefore the original may keep unsorted values
			up to that point.
		 */
		$series = array();
		for ($i = 0; $i < $study['count']; $i++)
		{
			$ser = $study[$i];

			$instances = array();
			for ($j = 0; $j < $ser['count']; $j++)
				$instances[] = $ser[$j];

			usort($instances, 'self::compareInstances');

			$j = 0;
			foreach ($instances as $ins)
				$ser[$j++] = $ins;

			$series[] = $ser;
		}

		usort($series, 'self::compareSeries');

		$i = 0;
		foreach ($series as $s)
			$study[$i++] = $s;
	}


	private function sortStudy2(&$study)
	{
		/* our keys are in form 'series-NNNNNN' and 'image-NNNNNN'; usort() needs numeric keys */
		$series = array();
		foreach ($study as $ser)
		{
			$instances = array();
			foreach ($ser as $ins)
				$instances[] = $ins;

			usort($instances, 'self::compareInstances');

			$j = 0;
			foreach ($instances as $ins)
				$ser["image-" . sprintf("%06d", $j++)] = $ins;

			$series[] = $ser;
		}

		usort($series, 'self::compareSeries2');

		$i = 0;
		foreach ($series as $s)
			$study["series-" . sprintf("%06d", $i++)] = $s;
	}


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


	public function fetchInstance($imageUid, $seriesUid, $studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $imageUid, ', ', $seriesUid, ', ',
			$studyUid, ')');

		$len = strpos($imageUid, "*");
		if ($len !== false)
			$imageUid = substr($imageUid, 0, $len);
		$len = strpos($seriesUid, "*");
		if ($len !== false)
			$seriesUid = substr($seriesUid, 0, $len);

		$pa = $this->qr->fetchImageWado($imageUid, $seriesUid, $studyUid);
		if (strlen($pa['error']))
			$return = false;
		else
			$return = $pa['path'];

		$this->log->asDump('returning: ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function fetchAndSortSeries(array &$seriesStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		foreach ($seriesStruct as $imageDir => $img)
		{
			$path = $this->fetchInstance($img['object'], $img['series'], $img['study']);
			if ($path === false)
				return 'series preload failed, see logs';
			$path = str_replace('\\', '/', $path);

			$seriesStruct[$imageDir]['path'] = $path;
			$this->updateWithDicomAttributes($seriesStruct[$imageDir]);
		}

		$this->sortSeries2($seriesStruct);

		$this->log->asDump('sorted: ', $seriesStruct);
		$this->log->asDump('end ' . __METHOD__);
		return '';
	}


	public function fetchAndSortStudy(array &$studyStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		for ($i = 0; $i < $studyStruct['count']; $i++)
		{
			for ($j = 0; $j < $studyStruct[$i]['count']; $j++)
			{
				$path = $this->fetchInstance($studyStruct[$i][$j]['id'], $studyStruct[$i]['id'],
					$studyStruct['uid']);
				if ($path === false)
					return 'study preload failed, see logs';
				$path = str_replace('\\', '/', $path);

				$studyStruct[$i][$j]['path'] = $path;

				$this->updateWithDicomAttributes($studyStruct[$i][$j]);
			}
		}

		$this->sortStudy($studyStruct);

		$this->log->asDump('sorted: ', $studyStruct);
		$this->log->asDump('end ' . __METHOD__);
		return '';
	}


	public function fetchAndSortStudies(array &$studiesStruct)
	{
		$this->log->asDump('begin ' . __METHOD__);

		foreach ($studiesStruct as $studyDir => $study)
		{
			foreach ($study as $seriesDir => $series)
			{
				foreach ($series as $imageDir => $img)
				{
					$path = $this->fetchInstance($img['object'], $img['series'], $img['study']);
					if ($path === false)
						return 'study preload failed, see logs';
					$path = str_replace('\\', '/', $path);

					$studiesStruct[$studyDir][$seriesDir][$imageDir]['path'] = $path;
					$this->updateWithDicomAttributes($studiesStruct[$studyDir][$seriesDir][$imageDir]);
				}
			}

			$this->sortStudy2($studiesStruct[$studyDir]);
		}

		$this->log->asDump('sorted: ', $studiesStruct);
		$this->log->asDump('end ' . __METHOD__);
	}


	public function removeFetchedFile($path)
	{
		if (!@unlink($path))
			$this->log->asWarn("failed to remove temporary file: '$path'");
		else
			$this->log->asDump("removed: '$path'");
		return '';
	}
}
