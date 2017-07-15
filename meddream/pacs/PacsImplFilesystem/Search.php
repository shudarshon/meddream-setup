<?php

namespace Softneta\MedDream\Core\Pacs\Filesystem;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='FileSystem'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	/** @brief Implementation of SearchIface::getStudyCounts().

		This %PACS won't ever implement the function, so this stub is here
		just to silence the warning from the default implementation.
	 */
	public function getStudyCounts()
	{
		return array('d1' => 0, 'd3' => 0, 'w1' => 0, 'm1' => 0, 'y1' => 0, 'any' => 0);
	}


	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $actions, ', ', $searchCriteria, ', ',
			$fromDate, ', ', $toDate, ', ', $mod, ', ', $listMax, ')');

		$audit = new Audit('SEARCH');

		$audit->log(false);

		return array('error' => 'Search is not supported. Use HIS integration to view studies or individual images.',
			'count' => 0);
	}
}
