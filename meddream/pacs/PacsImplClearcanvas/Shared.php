<?php

namespace Softneta\MedDream\Core\Pacs\Clearcanvas;

use Softneta\MedDream\Core\Pacs\SharedIface;
use Softneta\MedDream\Core\Pacs\SharedAbstract;


/** @brief Implementation of SharedIface for <tt>$pacs='ClearCanvas'</tt>. */
class PacsPartShared extends SharedAbstract implements SharedIface
{
	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null)
	{
		return trim(str_replace('^', ' ', $familyName));
	}
}
