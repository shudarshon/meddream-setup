<?php

namespace Softneta\MedDream\Core\Pacs\Dicomdir;

use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='DICOMDIR'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		/* we know in advance that the only user here is 'root'.

			However:
				'export' has no sense;

				'upload' would allow editing of exported notes but this
				is impossible due to read-only medium;

				'forward' would be interesting but isn't officially implemented;

				'share' won't work due to StructureIface::studyGetMetadata().
		 */
		if (($privilege == 'export') || ($privilege == 'forward') ||
				($privilege == 'upload') || ($privilege == 'share'))
			return 0;
		else
			return 1;
	}
}
