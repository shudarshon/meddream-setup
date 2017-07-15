<?php

namespace Softneta\MedDream\Core\QueryRetrieve;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\CharacterSet;


/** @brief Common properties/methods for descendants of QrBasicIface. */
abstract class QrAbstract implements QrBasicIface
{
	protected $log;                 /**< @brief An instance of Logging */
	protected $cs;                  /**< @brief An instance of CharacterSet */
	protected $retrieveEntireStudy; /**< @brief The "RetrieveEntireStudy" parameter */

	/** @brief Connection string of the remote C-FIND/C-MOVE SCP.

		Format: @htmlonly <tt>AET@HOST:PORT</tt> @endhtmlonly
	  */
	protected $remoteListener;

	/** @brief Connection string of the local C-STORE SCP.

		Format: @htmlonly <tt>AET@HOST:PORT</tt> @endhtmlonly
	  */
	protected $localListener;

	/** @brief AET (or the entire connection string) of the local C-FIND/C-MOVE SCU. */
	protected $localAet;

	/** @brief Base URL of the remote WADO service provided by the %PACS */
	protected $wadoAddr;


	/** @brief Default implementation of QrBasicIface::__construct().

		Values are assigned without validation.
	 */
	public function __construct(Logging $log, CharacterSet $cs, $retrieveEntireStudy,
		$remoteConnectionString, $localConnectionString, $localAet, $wadoAddr)
	{
		$this->log = $log;
		$this->cs = $cs;
		$this->retrieveEntireStudy = $retrieveEntireStudy;
		$this->remoteListener = $remoteConnectionString;
		$this->localListener = $localConnectionString;
		$this->localAet = $localAet;
		$this->wadoAddr = $wadoAddr;
	}


	/** @brief Helper function that sorts return value of studyGetMetadata().

		Result sorting is not supported natively by Query/Retrieve, therefore we'll attempt
		to do our own.

		"Public" just for unit tests.
	 */
	public function sortStudy(&$study)
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
			$ser['index'] = $i;

			$instances = array();
			for ($j = 0; $j < $ser['count']; $j++)
			{
				$ser[$j]['index'] = $j;
				$instances[] = $ser[$j];
			}

			usort($instances, 'self::compareInstances');

			$j = 0;
			foreach ($instances as $ins)
			{
				unset($ins['index']);
				$ser[$j++] = $ins;
			}

			$series[] = $ser;
		}

		usort($series, 'self::compareSeries');
		$i = 0;
		foreach ($series as $s)
		{
			unset($s['index']);
			$study[$i++] = $s;
		}
	}


	/** @brief Helper function that sorts return value of seriesGetMetadata().

		Result sorting is not supported natively by Query/Retrieve, therefore we'll attempt
		to do our own.

		"Public" just for unit tests.
	 */
	public function sortSeries(&$series)
	{
		/* usort() needs a numerically-indexed array though ours is indexed in both
			fashions. The easiest way is to sort a copy.

			Afterwards the items are updated by indexing them in the same fashion
			(via 0-based numbers), therefore the original may keep unsorted values
			up to that point.
		 */
		$instances = array();
		for ($j = 0; $j < $series['count']; $j++)
		{
			$ser[$j]['index'] = $j;
			$instances[] = $series[$j];
		}

		usort($instances, 'self::compareInstances');

		$j = 0;
		foreach ($instances as $ins)
		{
			unset($ins['index']);
			$series[$j++] = $ins;
		}
	}


	/** @brief Custom comparison function for series sorting. */
	public static function compareSeries($a, $b)
	{
		/* move unset values to the end */
		if (isset($a['seriesno']))
		{
			if (!isset($b['seriesno']))
				return -1;
			/* both values are now safe for indexing */

			/* move null values to the end */
			if (!is_null($a['seriesno']))
			{
				if (is_null($b['seriesno']))
					return -2;
				/* both values are now safe for logic */

				/* the main logic */
				$value = ((int) $a['seriesno']) - ((int) $b['seriesno']);

				/* 0=equal - must not change order */
				if (($value == 0) && isset($a['index']))
					return ($a['index'] < $b['index']) ? 1: -1;
				return $value;
			}
			else
				if (!is_null($b['seriesno']))
					return 2;
				else
				{
					/* 0=equal - must not change order */
					if (isset($a['index']))
						return ($a['index'] < $b['index']) ? -1: 1;
					return 0;
				}
		}
		else
			if (isset($b['seriesno']))
				return 1;
			else
			{
				/* value is null or 0=equal - must not change order */
				if (isset($a['index']))
					return ($a['index']<$b['index']) ? -1: 1;
				return 0;		/* both unset */
			}
	}


	/** @brief Custom comparison function for instances sorting. */
	public static function compareInstances($a, $b)
	{
		/* move unset values to the end */
		if (isset($a['instanceno']))
		{
			if (!isset($b['instanceno']))
				return -1;
			/* both values are now safe for indexing */

			/* move null values to the end */
			if (!is_null($a['instanceno']))
			{
				if (is_null($b['instanceno']))
					return -2;
				/* both values are now safe for logic */

				/* the main logic */
				$value = ((int) $a['instanceno']) - ((int) $b['instanceno']);

				/* value is 0=equal - must not change order */
				if (($value == 0) && isset($a['index']))
					return ($a['index'] < $b['index']) ? 1: -1;
				return $value;
			}
			else
				if (!is_null($b['instanceno']))
					return 2;
				else
				{
					/* value is null or 0=equal - must not change order */
					if (isset($a['index']))
						return ($a['index'] < $b['index']) ? -1: 1;
					return 0;
				}
		}
		else
			if (isset($b['instanceno']))
				return 1;
			else
			{
				/* value is null or 0=equal - must not change order */
				if (isset($a['index']))
					return ($a['index'] < $b['index']) ? -1: 1;
				return 0;		/* both unset */
			}
	}
}
