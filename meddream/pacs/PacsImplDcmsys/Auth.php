<?php

namespace Softneta\MedDream\Core\Pacs\Dcmsys;

use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='DCMSYS'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		if (($privilege == 'export') || ($privilege == 'forward') ||
				($privilege == 'upload') || ($privilege == 'share'))
			return 0;

		/* in order to open Settings dialog, $admin_username must be set up */
		if ($privilege == 'root')
			return (int) ((strlen($this->commonData['admin_username'])) &&
				($this->authDB->getAuthUser(true) == $this->commonData['admin_username']));

		/* remaining functions, ordinary users */
		return 1;		/* 'view', 'viewprivate' */
	}
}
