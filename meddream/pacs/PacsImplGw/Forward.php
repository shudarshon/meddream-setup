<?php

namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\ForwardIface;
use Softneta\MedDream\Core\Pacs\ForwardAbstract;


/** @brief Implementation of ForwardIface for <tt>$pacs='GW'</tt>.

	Only collectDestinationAes is defined in the Gateway's API.
 */
class PacsPartForward extends ForwardAbstract implements ForwardIface
{
	public function collectDestinationAes()
	{
		return $this->gw->collectDestinationAes();
	}
}
