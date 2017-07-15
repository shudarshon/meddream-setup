<?php

namespace Softneta\MedDream\Core\Pacs\Dicom;

use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\ReportAbstract;


/** @brief Implementation of ReportIface for <tt>$pacs='DICOM'</tt>.

	This class is empty as ReportAbstract provides enough functionality.
	In turn, Loader::load() still expects a file.
 */
class PacsPartReport extends ReportAbstract implements ReportIface
{
}
