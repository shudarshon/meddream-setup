<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Pacs\CommonDataImporter;


/** @brief Functions needed both by Auth.php and Search.php

	Providing access from SearchIface to AuthIface would make things
	complicated and require to always load the 'Auth' %PACS part among
	with 'Search' (though it would be of no use for other PACSes).
 */
class PacsOneUser implements CommonDataImporter
{
	protected $log;         /**< @brief Instance of Logging */
	protected $authDB;      /**< @brief Instance of AuthDB */

	/** @brief Array from PacsConfig::exportCommonData(), passed here by Auth.php */
	protected $commonData;


	/** @brief A helper for hasPrivilege().

		This function has lots of return points, and we need to log every returned
		value in hasPrivilege().
	 */
	protected function fetchPrivilege($privilege)
	{
		$authDB = $this->authDB;

		/* superusers can do everything */
		$user = $this->authDB->getAuthUser(true);
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $user)
				return 1;
		if ($user == 'root')
			return 1;
		if (Constants::FOR_RIS && ($user == 'teleHIS'))	/* MedDreamRIS */
			return 1;
		if (Constants::FOR_SW)
		{
			if ($user == 'sw')		/* automatic SWS login */
				return 1;
			if ($user == 'swuser')	/* Web access to SWS */
				return 1;
		}
		if (Constants::FOR_WORKSTATION)				/* MWS/OWS: all users! */
			return 1;

		/* remaining functions: PacsOne-specific permissions, ordinary users */
		if ($privilege == 'root')
		{
			/* since 6.3.3 PacsOne has a System Administration privilege --
			   basically a shared equivalent for "root".
			 */
			$sql = 'SELECT admin FROM privilege WHERE ' .
				$this->commonData['F_PRIVILEGE_USERNAME'] . "='" .
					$authDB->sqlEscapeString($user) . "'";
				/* in earlier versions the column is absent so the query will fail,
				   and we'll proceed checking for the true "root" below.
				 */
			$this->log->asDump('$sql = ', $sql);

			$result = $authDB->query($sql);

			$hasPriv = false;
			if ($result !== false)
			{
				$row = $authDB->fetchNum($result);
				$hasPriv = ((int) $row[0]) != 0;
				$authDB->free($result);
			}
			$this->log->asDump('$hasPriv = ', $hasPriv);
			if ($hasPriv)
				return 1;
		}
		if ($privilege == 'share')
			return 1;

		if (($privilege == 'view') || ($privilege == 'viewprivate'))
			$privilege = $this->commonData['F_PRIVILEGE_VIEWPRIVATE'];
		if (($privilege == 'modify') || ($privilege == 'modifydata'))
			$privilege = $this->commonData['F_PRIVILEGE_MODIFYDATA'];
		$sql = 'SELECT ' . $authDB->sqlEscapeString($privilege) .
			' FROM privilege'.
			' WHERE ' . $this->commonData['F_PRIVILEGE_USERNAME'] . "='" .
				$authDB->sqlEscapeString($user) . "'";
		$this->log->asDump('$sql = ', $sql);

		$result = $authDB->query($sql);

		$hasPriv = false;
		if ($result !== false)
		{
			$row = $authDB->fetchNum($result);
			if ($row)
				$hasPriv = $row[0];
		}
		$authDB->free($result);

		$this->log->asDump('$hasPriv = ', $hasPriv);
		return (int) $hasPriv;
	}


	public function __construct(Logging $logger, AuthDB $authDb)
	{
		$this->log = $logger;
		$this->authDB = $authDb;
	}


	public function importCommonData($data)
	{
		$this->commonData = $data;
		return '';
	}


	public function hasPrivilege($privilege)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $privilege, ')');

		$r = $this->fetchPrivilege($privilege);

		$this->log->asDump('returning: ', $r);
		$this->log->asDump('end ' . __METHOD__);

		return $r;
	}


	public function firstName()
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '()');

		$authDB = $this->authDB;
		$user = $authDB->getAuthUser();
		$log->asDump('$user = ', $user);

		if (($user == 'root') || ($user == 'sw'))
			$return  = '';
		else
		{
			if (Constants::FOR_WORKSTATION)
				$table = 'md_user';
			else
				$table = 'privilege';
			$sql = "SELECT firstname FROM $table WHERE " . $this->commonData['F_PRIVILEGE_USERNAME'] .
				"='" . $authDB->sqlEscapeString($user) . "'";
			$log->asDump('$sql = ', $sql);

			$result = $authDB->query($sql);
			if (!$result)
			{
				$log->asErr("query failed: '" . $authDB->getError() . "'");
				return '';
			}
			else
			{
				$row = $authDB->fetchNum($result);
				$authDB->free($result);
				if ($row)
					$return = $row[0];
				else
					$return = '';
			}
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function lastName()
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '()');

		$authDB = $this->authDB;
		$user = $authDB->getAuthUser();

		if (($user == 'root') || ($user == 'sw'))
			$return = '';
		else
		{
			if (Constants::FOR_WORKSTATION)
				$table = 'md_user';
			else
				$table = 'privilege';
			$sql = "SELECT lastname FROM $table WHERE " . $this->commonData['F_PRIVILEGE_USERNAME'] .
				"='" . $authDB->sqlEscapeString($user) . "'";
			$log->asDump('$sql = ', $sql);

			$result = $authDB->query($sql);
			if (!$result)
			{
				$log->asErr("query failed: '" . $authDB->getError() . "'");
				return '';
			}
			else
			{
				$row = $authDB->fetchNum($result);
				$authDB->free($result);
				if ($row)
					$return = $row[0];
				else
					$return = '';
			}
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);

		return $return;
	}
}
