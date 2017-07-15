<?php

namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Pacs\SharedIface;
use Softneta\MedDream\Core\Pacs\SharedAbstract;


/** @brief Implementation of SharedIface for <tt>$pacs='%Conquest'</tt>. */
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
}
