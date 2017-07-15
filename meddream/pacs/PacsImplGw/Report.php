<?php

namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\ReportAbstract;


/** @brief Implementation of ReportIface for <tt>$pacs='GW'</tt>.

	Must override at least getLastReport(), as HTML Reports window calls it
	just after the opening and must show an error message.
 */
class PacsPartReport extends ReportAbstract implements ReportIface
{
	public function getLastReport($studyUid)
	{
		return array('error' => "Reports not supported for \$pacs='GW'");
	}
}
