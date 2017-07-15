<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc_5;

use Softneta\MedDream\Core\Pacs\SharedIface;
use Softneta\MedDream\Core\Pacs\SharedAbstract;


/** @brief Implementation of SharedIface for <tt>$pacs='dcm4chee-arc-5'</tt>. */
class PacsPartShared extends SharedAbstract implements SharedIface
{
	public function getStorageDevicePath($id)
	{
		if (!array_key_exists($id, $this->commonData['storage_devices']))
		{
			$this->log->asErr("unknown storage device '$id'");
			return null;
		}
		return $this->commonData['storage_devices'][$id];
	}


	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null)
	{
		return trim("$familyName $givenName", ' ');
	}


	/* database schema doesn't allow NULL in some places but dcm4chee-arc-light still wants to mark
	   the data as missing and uses a single '*' for that. Will change those to an empty string.
	 */
	public function cleanDbString($str)
	{
		if ($str == '*')
			return '';
		return $str;
	}
}
