<?php

namespace Softneta\MedDream\Core\Pacs\Dicom;

use Softneta\MedDream\Core\Pacs\ExportIface;
use Softneta\MedDream\Core\Pacs\ExportAbstract;


/** @brief Implementation of ExportIface for <tt>$pacs='DICOM'</tt>.

	This class is empty as ExportAbstract provides enough functionality.
	In turn, Loader::load() still expects a file.
*/
class PacsPartExport extends ExportAbstract implements ExportIface
{
}
