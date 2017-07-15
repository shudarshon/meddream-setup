<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Pacs\ForwardIface;
use Softneta\MedDream\Core\Pacs\ForwardAbstract;


/** @brief Implementation of ForwardIface for <tt>$pacs='dcm4chee-arc'</tt>.

	The upcoming PACS Gateway component supports collectDestinationAes(). For
	remaining methods, ForwardAbstract together with Study.php provide enough
	functionality.
 */
class PacsPartForward extends ForwardAbstract implements ForwardIface
{
	public function collectDestinationAes()
	{
		if (strlen($this->commonData['pacs_gateway_addr']))
			return $this->gw->collectDestinationAes();
		else
			return parent::collectDestinationAes();
	}
}
