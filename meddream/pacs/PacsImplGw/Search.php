<?php

namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\SearchIface;
use Softneta\MedDream\Core\Pacs\SearchAbstract;


/** @brief Implementation of SearchIface for <tt>$pacs='GW'</tt>. */
class PacsPartSearch extends SearchAbstract implements SearchIface
{
	public function getStudyCounts()
	{
		return $this->gw->getStudyCounts();
	}


	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax)
	{
		return $this->gw->findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax);
	}
}
