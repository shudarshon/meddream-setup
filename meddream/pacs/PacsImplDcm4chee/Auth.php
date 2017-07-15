<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;


/** @brief Implementation of AuthIface for <tt>$pacs='DCM4CHEE'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	public function login($database, $user, $password)
	{
		$pacsLoginUser = $this->commonData['pacs_login_user'];
		$pacsLoginPassword = $this->commonData['pacs_login_password'];

		if ($pacsLoginUser == "")
			return false;
		if (!function_exists("sha1"))
			return false;

		$audit = new Audit('PACS LOGIN');

		$result = false;
		$previous = error_reporting(E_ERROR);

		$this->authDB->logoff();

		$passwordToCompare = base64_encode(sha1($password, true));

		$result = $this->authDB->connect($database, $pacsLoginUser,
			$pacsLoginPassword);
		if ($result)
		{
			$sql = "SELECT passwd FROM users WHERE user_id='" . $this->authDB->sqlEscapeString($user) . "'";
			$rsrc = $this->authDB->query($sql);
			if ($rsrc)
			{
				$row = $this->authDB->fetchNum($rsrc);

				if ($row !== false)
				{
					/* MSSQL behaves strangely (probably due to sp_dbcmptlevel): both empty
					   string and a single space yield a single space on assignment, therefore
					   only NULL is suitable for "unset" value. However NULL is not good for
					   strcmp. It seems that handling NULLs manually is fair enough, provided
					   that the same rules are obeyed when updating passwords.
					 */
					$p = $row[0];
					if (is_null($p))
						$p = '';

					if (!strcmp($p, $passwordToCompare))
					{
						$this->authDB->setUserPasswordDatabaseGroup($pacsLoginUser,
							$pacsLoginPassword, $database, $user);

						/* an audit message

							Only in case of success. home.php always attempts login_alt() first,
							as it's impossible to distinguish PACS logins from database logins.
							If we fail, home.php will call login(), and the latter logs the audit
							message both in case of success and failure.

							As with login(), the "with password" indicator might be useful in revealing
							passwordless accounts: corporate security policy often forbids them.
						 */
						$audit->log(true, $this->authDB->formatConnectDetails($user, $password));
					}
					else
						$result = false;
				}
				else
					$result = false;

				$this->authDB->free($rsrc);
			}
			else
				$result = false;

			/* log an error message

				After a failure here, same login credentials will be retried
				in login(); that will yield another error message which basically
				states that name of an internal user was unsuitable as a database
				user. That's obvious but might erroneously suggest a bug.

				On the other hand, if login_alt() is given a database user
				(instead of internal users for which it's meant), it WILL
				fail. Then we'll have a log message about a database user
				unsuitable as internal user. It's all that we can do.
			 */
			if (!$result)
			{
				$withpass = (strlen($password) != 0) ? 'YES' : 'NO';
				$this->log->asErr("can't log in as internal user '$user'," .
					" with password: $withpass. Will try same credentials for a database user.");
			}
		}

		error_reporting($previous);

		return $result;
	}


	public function hasPrivilege($privilege)
	{
		$dbms = $this->commonData['dbms'];

		/* superusers can do everything */
		if (strlen($this->commonData['admin_username']))
			if ($this->commonData['admin_username'] == $this->authDB->getAuthUser(false))
				return 1;
		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV'))
		{
			if ($this->authDB->getAuthUser(true) == 'sa')
				return 1;
		}
		else
		{
			if ($this->authDB->getAuthUser(true) == 'root')
				return 1;
		}

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
