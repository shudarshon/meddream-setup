<?php
/*
	Original name: DbImplDcmsys.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for $dbms='DCMSYS'.
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Authentication for <tt>$dbms='DCMSYS'</tt>.

	Although <tt>$dbms='DCMSYS'</tt> is not a database, it still provides
	authentication.
 */
class DbImplDcmsys extends DbAbstract
{
	/** @brief Logger for troubleshooting.

		API support is quite complicated, therefore we'll log some details that
		might help to track the cause.

		Might be @c null if the constructor didn't receive an instance of Logging.
		In that case logging is unavailable.
	 */
	private $log;


	/** @brief A constructor for DCMSYS

		@param string $host      Base URL for router's API
		@param string $db        Ignored
		@param string $user      User name
		@param string $password  Password
		@param Logging $logger   A logging facility if needed, see @link $log @endlink
	 */
	public function __construct($host, $db, $user, $password, $logger = null)
	{
		parent::__construct($host, $db, $user, $password, $logger);

		$this->log = $logger;
	}


	/** @brief Implementation of DbIface::reconnect(). */
	public function reconnect($additionalOptions = array())
	{
		include_once(__DIR__ . '/../dcmsys/login.php');

		$result = \dcmsys_login($this->dbUser, $this->dbPassword, $this->dbHost);
		if ($result)
			$this->connection = true;
		else
			$this->connection = null;
				return $result ? '' : 'dcmsys_login() failed';
	}


	/** @brief Implementation of DbIface::close(). */
	public function close()
	{
/*
		include_once(__DIR__ . '/../dcmsys.php');

		if (!dcmsys_disconnect($this, $error))
		{
			if ($this->log)
				$this->log->asErr($error);
		}
		else
			$this->connection = null;
 */
		$this->connection = null;
		return true;
	}


	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		return false;
	}


	public function free($result)
	{
		return false;
	}


	public function fetchAssoc(&$result)
	{
		return false;
	}


	public function fetchNum(&$result)
	{
		return false;
	}


	public function getAffectedRows($result)
	{
		return false;
	}


	public function getInsertId()
	{
		return false;
	}


	public function getError()
	{
		return 'not implemented';
	}


	public function sqlEscapeString($stringToEscape)
	{
		return false;
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		return 0;
	}
}
