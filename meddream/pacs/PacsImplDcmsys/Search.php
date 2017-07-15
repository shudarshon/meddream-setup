<?php

namespace Softneta\MedDream\Core\Pacs\Dcmsys;

use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='DCMSYS'</tt>.

	Stubs suggesting that this part of md-core must not be used by the frontend.
 */
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


	/** @brief A stub suggesting that it must not be used by the frontend. */
	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		$this->log->asErr('not implemented but still called');
		return array('error' => 'not implemented', 'count' => 0);
	}
}
