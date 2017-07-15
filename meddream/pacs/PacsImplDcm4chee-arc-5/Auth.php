<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc_5;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='dcm4chee-arc-5'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		/* superusers can do everything */
		$user = $this->authDB->getAuthUser(true);
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $user)
				return 1;
		if (($this->commonData['dbms'] != 'OCI8') && ($user == 'root'))
				return 1;

		/* remaining functions, ordinary users */
		if (($privilege == 'view') || ($privilege == 'viewprivate') ||
				($privilege == 'export') || ($privilege == 'upload') ||
				($privilege == 'forward') || ($privilege == 'share'))
			return 1;
			/* forward, export and upload are available to everybody under most
			   PACSes.

			   In MedDream, "upload" controls MedReport integration (this
			   privilege is one of conditions to display corresponding icons
			   in the UI)
			 */

		return 0;
	}


	public function onConnect(array &$return)
	{
		$notesTableExists = $this->authDB->tableExists('studynotes');
		$_SESSION[$this->authDB->sessionHeader . 'notesExsist']  = $notesTableExists;
		if (!$notesTableExists)
			$return['attachmentExist'] = 0;
		else
			$return['attachmentExist'] = $this->authDB->tableExists('attachment');
		return '';
	}
}
