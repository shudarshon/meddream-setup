<?php

namespace Softneta\MedDream\Core\Pacs\Wado;

use Softneta\MedDream\Core\Pacs\ForwardIface;
use Softneta\MedDream\Core\Pacs\ForwardAbstract;


/** @brief Implementation of ForwardIface for <tt>$pacs='WADO'</tt>.

	This class is empty as ForwardAbstract together with Study.php provide
	enough functionality. In turn, Loader::load() still expects a file.
 */
class PacsPartForward extends ForwardAbstract implements ForwardIface
{
}
