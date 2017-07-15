<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Pacs\SharedIface;
use Softneta\MedDream\Core\Pacs\SharedAbstract;


/** @brief Implementation of SharedIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartShared extends SharedAbstract implements SharedIface
{
	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null)
	{
		return trim("$familyName $givenName", ' ');
	}


	/* remove ^ from person name. Older versions of PacsOne use more of these. */
	public function cleanDbString($str)
	{
		$str = str_replace('^', ' ', $str);
		return trim($str);
	}
}
