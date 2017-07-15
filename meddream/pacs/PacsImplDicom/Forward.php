<?php

namespace Softneta\MedDream\Core\Pacs\Dicom;

use Softneta\MedDream\Core\Pacs\ForwardIface;
use Softneta\MedDream\Core\Pacs\ForwardAbstract;


/** @brief Implementation of ForwardIface for <tt>$pacs='DICOM'</tt>.

	This class is empty as ForwardAbstract together with Study.php provide
	enough functionality. In turn, Loader::load() still expects a file.
 */
class PacsPartForward extends ForwardAbstract implements ForwardIface
{
}
