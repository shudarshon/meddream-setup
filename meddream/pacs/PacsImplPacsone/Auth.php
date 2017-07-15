<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\CharacterSet;
use Softneta\MedDream\Core\ForeignPath;
use Softneta\MedDream\Core\Pacs\AuthIface;
use Softneta\MedDream\Core\Pacs\AuthAbstract;
use Softneta\MedDream\Core\Pacs\PacsShared as GenericPacsShared;
use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;

require_once __DIR__ . '/PacsOneUser.php';


/** @brief Implementation of AuthIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartAuth extends AuthAbstract implements AuthIface
{
	protected $pu;          /**< @brief Instance of PacsOneUser */


	/** @brief Makes sure the local instance of PacsOneUser is created */
	public function __construct(Logging $logger, AuthDB $authDb, Configuration $cfg, CharacterSet $cs,
		ForeignPath $fp, PacsGw $gw, QR $qr, GenericPacsShared $shared)
	{
		parent::__construct($logger, $authDb, $cfg, $cs, $fp, $gw, $qr, $shared);
		$this->pu = new PacsOneUser($logger, $authDb);
	}


	/** @brief Makes sure the local instance of PacsOneUser is initialized */
	public function importCommonData($data)
	{
		$e = $this->pu->importCommonData($data);
		if ($e)
			return $e;
		return parent::importCommonData($data);
	}


	public function login($database, $user, $password)
	{
		if (!Constants::FOR_WORKSTATION)
			return false;
			/* won't check $pacs_login_user (config.php): MW/OW/VS100 etc use an SQLite database
			   that can be accessed without credentials
			 */
		if (!function_exists("sha1"))
			return false;

		$audit = new Audit('PACS LOGIN');

		$result = false;
		$previous = error_reporting(E_ERROR);

		$this->logoff();

		$password_to_compare = base64_encode(sha1($password, true));

		$result = $this->connect($database, $this->pacs_login_user,
			$this->pacs_login_password);
		if ($result)
		{
			$sql = "SELECT password,usergroup FROM md_user WHERE user='" . $this->sqlEscapeString($user) . "'";

			$rsrc = $this->query($sql);
			if ($rsrc)
			{
				$row = $this->fetchNum($rsrc);

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

					if (!strcmp($p, $password_to_compare))
					{
						$userGroup = '';
						if (isset($row[1]))
							$userGroup = $row[1];

						$this->setUserPasswordDatabaseGroup($this->pacs_login_user, $this->pacs_login_password,
							$database, $user, $userGroup);

						/* an audit message

							Only in case of success. home.php always attempts login_alt() first,
							as it's impossible to distinguish PACS logins from database logins.
							If we fail, home.php will call login(), and the latter logs the audit
							message both in case of success and failure.

							As with login(), the "with password" indicator might be useful in revealing
							passwordless accounts: corporate security policy often forbids them.
						 */
						$audit->log(true, $this->formatConnectDetails($user, $password));
					}
					else
						$result = false;
				}
				else
					$result = false;
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
		return $this->pu->hasPrivilege($privilege);
	}


	public function firstName()
	{
		return $this->pu->firstName();
	}


	public function lastName()
	{
		return $this->pu->lastName();
	}


	public function onConnect(array &$return)
	{
		$_SESSION[$this->authDB->sessionHeader . 'notesExsist']  = true;
		$return['attachmentExist'] = true;

		return '';
	}
}
