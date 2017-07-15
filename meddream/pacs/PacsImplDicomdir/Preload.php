<?php

namespace Softneta\MedDream\Core\Pacs\Dicomdir;

use Softneta\MedDream\Core\Pacs\PreloadIface;
use Softneta\MedDream\Core\Pacs\PreloadAbstract;


/** @brief Implementation of PreloadIface for <tt>$pacs='DICOMDIR'</tt>.

	This class is empty as PreloadAbstract provides enough functionality.
	In turn, Loader::load() still expects a file.
 */
class PacsPartPreload extends PreloadAbstract implements PreloadIface
{
}
