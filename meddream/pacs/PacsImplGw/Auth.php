<?php

namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='GW'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		/* superusers can do everything */
		$user = $this->authDB->getAuthUser(true);
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $user)
				return 1;
		if ($user == 'root')
			return 1;

		/* remaining functions, ordinary users */
		if (($privilege == 'view') || ($privilege == 'viewprivate') ||
				($privilege == 'export') || ($privilege == 'forward') ||
				($privilege == 'share'))
			return 1;
			/* forward, export and upload are available to everybody under most
			   PACSes.

			   In MedDream, "upload" controls MedReport integration (this
			   privilege is one of conditions to display corresponding icons
			   in the UI)
			 */

		return 0;
	}
}
