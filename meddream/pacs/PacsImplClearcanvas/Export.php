<?php

namespace Softneta\MedDream\Core\Pacs\Clearcanvas;

use Softneta\MedDream\Core\Pacs\ExportIface;
use Softneta\MedDream\Core\Pacs\ExportAbstract;


/** @brief Implementation of ExportIface for <tt>$pacs='ClearCanvas'</tt>.

	This class is empty as ExportAbstract together with export.php provide
	enough functionality. In turn, Loader::load() still expects a file.
 */
class PacsPartExport extends ExportAbstract implements ExportIface
{
}
