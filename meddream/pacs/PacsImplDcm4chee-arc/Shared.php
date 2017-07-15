<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Pacs\SharedIface;
use Softneta\MedDream\Core\Pacs\SharedAbstract;


/** @brief Implementation of SharedIface for <tt>$pacs='dcm4chee-arc'</tt>. */
class PacsPartShared extends SharedAbstract implements SharedIface
{
	/* database schema doesn't allow NULL in some places but dcm4chee-arc still wants to mark
	   the data as missing and uses a single '*' for that. Will change those to an empty string.
	 */
	public function cleanDbString($str)
	{
		if ($str == '*')
			return '';
		return $str;
	}
}
