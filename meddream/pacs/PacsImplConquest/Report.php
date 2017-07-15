<?php

namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\ReportAbstract;


/** @brief Implementation of ReportIface for <tt>$pacs='%Conquest'</tt>. */
class PacsPartReport extends ReportAbstract implements ReportIface
{
	public function getLastReport($studyUid)
	{
		/* the default implementation gives a warning in order to encourage
		   individual implementations. Let's do the same but without the warning.
		 */
		return array('error' => '', 'id' => null, 'user' => null, 'created' => null,
			'headline' => null, 'notes' => null);
	}
}
