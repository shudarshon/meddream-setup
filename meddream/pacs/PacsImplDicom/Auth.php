<?php

namespace Softneta\MedDream\Core\Pacs\Dicom;

use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='DICOM'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function hasPrivilege($privilege)
	{
		/* superusers can do everything */
		$user = $this->authDB->getAuthUser(true);
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $user)
			{
				return 1;
			}
		if ($user == 'root')
		{
			return 1;
		}

		/* remaining functions, ordinary users */
		if (($privilege == 'view') || ($privilege == 'viewprivate') ||
				($privilege == 'export') || ($privilege == 'forward') ||
				($privilege == 'share'))
		{
			return 1;
			/* forward, export and upload are available to everybody under most
			   PACSes.

			   In MedDream, "upload" controls MedReport integration (this
			   privilege is one of conditions to display corresponding icons
			   in the UI)
			 */
		}

		return 0;
	}


	public function onConnect(array &$return)
	{
		/* PGW manages its own instance of DcmRcv, do not start ours */
		if (strlen($this->commonData['pacs_gateway_addr']))
			return;

		session_write_close();		/* otherwise the session file is occupied by java.exe */
		$rootDir = dirname(dirname(__DIR__));
		$tools = $rootDir . DIRECTORY_SEPARATOR . 'dcm4che' .
			DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR;
		$r = meddream_dcmrcv_start($tools, $this->commonData['dcm4che_recv_aet'], $rootDir);
		if (!empty($r))
		{
			$this->log->asErr('meddream_dcmrcv_start: ' . $r);

			/* clean up the message a bit and show it to the user */
			if ($r[0] == '*')
				$r = substr($r, 1);
			$return['error'] = $r;
		}
	}
}
