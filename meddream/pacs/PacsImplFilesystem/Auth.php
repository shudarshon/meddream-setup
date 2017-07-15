<?php

namespace Softneta\MedDream\Core\Pacs\Filesystem;

use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='FileSystem'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		if (($privilege == 'forward') ||	/* meddream.swf is not fully initialized in HIS integration mode*/
				($privilege == 'export'))	/* simply not implemented */
			return 0;

		/* superusers can do everything */
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $this->authDB->getAuthUser(true))
				return 1;
		if ($this->authDB->getAuthUser(true) == "root")
			return 1;

		/* remaining functions, ordinary users */
		if (($privilege == "view") || ($privilege == "viewprivate") ||
				($privilege == 'share'))
			return 1;

		return 0;
	}
}
