<?php
/*
	Original name: DbImplSqlite3.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for the SQLite3 driver.
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Support for SQLite3 */
class DbImplSqlite3 extends DbAbstract
{
	/** @brief Database access mode ('r' or 'w'). */
	private $mode = 'r';


	/** @brief A constructor for SQLite3
	 *
	 * @param string $host          Ignored
	 * @param string $db            Full path to the database file
	 * @param string $user          Connection mode: 'r' -- read only, 'w' -- read/write
	 * @param string $password      Ignored
	 */
	public function __construct($host, $db, $user, $password)
	{
		parent::__construct($host, $db, $user, $password);

		if (!empty($this->dbUser))
			$this->mode = $this->dbUser;
	}


	/** @brief Implementation of DbIface::reconnect(). */
	public function reconnect($additionalOptions = array())
	{
		if ($this->dbName == '')
			return '$dbName (database file path) must be a non-empty string';

		if (!class_exists('\SQLite3'))
			return 'sqlite3 PHP extension is missing';

		$flag = $this->getMode();
		$result = false;
		try
		{
			$this->connection = new \SQLite3($this->dbName, $flag);
			$result = true;
		}
		catch (\Exception $e)
		{
			return "SQLite3::__construct('" . $this->dbName . "'): " . $e->getMessage();
		}

		/* try to capture the error message in case of failure */
		if (!$result)
			return $this->formatConnectError();

		return '';
	}


	/** @brief Getter for DbImplSqlite3::$mode.
	 *
	 * @return A @c SQLITE3_OPEN_* constant that corresponds to the current mode.
	 */
	protected function getMode()
	{
		if ($this->mode == 'w')
			return SQLITE3_OPEN_READWRITE;
		else
			return SQLITE3_OPEN_READONLY;
	}


	/** @brief Implementation of DbIface::close(). */
	public function close()
	{
		/* Do not call extension functions if there is no connection. This is not only
		   for efficiency but also to avoid a fatal error where ::connect() fails due
		   to absent PHP extension, then this method is eventually called from a
		   destructor and references another missing function.

		   Same in other methods of this class.
		 */
		if (!$this->connection)
			return false;

		$this->connection->close();
		$this->connection = null;

		return true;
	}


	/** @brief Implementation of DbIface::query().

		@retval SQLite3Result   A resultset after successful execution. Note that a resultless
		                        query like INSERT still returns an object instead of @c true,
		                        despite the official documentation.
		@retval false           Failure
	 */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		if (!$this->connection)
			return false;

		if (empty($sql))
			return false;

		/* in some occasions, multiple sessions are interfering with each other
		   because the database becomes locked. Let's retry for 0.1 ... 60 seconds.

		   A simple test needs "BEGIN EXCLUSIVE TRANSACTION" in some other session.
		 */
		for ($i = 0; $i < 20; $i++)
		{
			try          /* exception handling required, too */
			{
				$result = @$this->connection->query($sql);
			}
			catch (Exception $e)
			{
				$result = false;
			}

			if ($result)
				break;

			$err = $this->connection->lastErrorCode();
			if (($err != 5) && ($err != 6))
				break;
				/* 5 -- entire database locked, 6 -- current table locked (see
				   https://www.sqlite.org/rescode.html for more, however other
				   cases are obviously fatal)
				 */
			usleep(1000 * rand(100, 3000));
		}

		return $result;
	}


	/** @brief Implementation of DbIface::free(). */
	public function free($result)
	{
		if (!$this->connection)
			return false;

		if (($result !== false) || ($result !== true))
			$result->finalize();

		return true;
	}


	/** @brief Implementation of DbIface::fetchAssoc(). */
	public function fetchAssoc(&$result)
	{
		if (!$this->connection)
			return false;

		return $result->fetchArray(SQLITE3_ASSOC);
	}


	/** @brief Implementation of DbIface::fetchNum(). */
	public function fetchNum(&$result)
	{
		if (!$this->connection)
			return false;

		return $result->fetchArray(SQLITE3_NUM);
	}


	/** @brief Implementation of DbIface::getAffectedRows(). */
	public function getAffectedRows($result)
	{
		if (!$this->connection)
			return false;

		return $this->connection->changes();
	}


	/** @brief Implementation of DbIface::getInsertId(). */
	public function getInsertId()
	{
		if (!$this->connection)
			return false;

		return $this->connection->lastInsertRowID();
	}


	/** @brief Implementation of DbIface::getError(). */
	public function getError()
	{
		if (!$this->connection)
			return __CLASS__ . ': not connected';

		return $this->connection->lastErrorMsg();
	}


	/** @brief Implementation of DbIface::sqlEscapeString(). */
	public function sqlEscapeString($stringToEscape)
	{
		if (!$this->connection)
			return false;

		return $this->connection->escapeString($stringToEscape);
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		$sql = "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='" .
			$this->sqlEscapeString($name) . "'";

		$rs = $this->query($sql);
		if (!$rs)
			return false;

		$r = $this->fetchNum($rs);
		$this->free($rs);

		return ($r[0] > 0) ? 1 : 0;
	}
}
