<?php
/*
	Original name: DbImplMssql.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for the older MS SQL Server
		drivers. (Not only the ancient php_mssql extension but also its FreeTDS
		implementation under Linux).
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Support for the older MS SQL Server drivers (Windows/Linux)

		This includes the php_mssql driver for Windows and its FreeTDS equivalent
		under Linux.
 */
class DbImplMssql extends DbAbstract
{
	/** @name Messages/errors filter

	   Might be important for FreeTDS where mssql_get_last_message() literally
	   returns only the last message from the whole bunch, like "The statement
	   has been terminated" that doesn't explain anything.
	 */
	/**@{*/
	/** @brief If nonzero, connect() will call mssql_min_message_severity() (see
		its PHP documentation) with this value.
	 */
	const MSSQL_MSG_SEVERITY = 14;

	/** @brief If nonzero, connect() will call mssql_min_error_severity() (see
		its PHP documentation) with this value.
	 */
	const MSSQL_ERR_SEVERITY = 1;
	/**@}*/


	/** @brief Implementation of DbIface::reconnect(). */
	public function reconnect($additionalOptions = array())
	{
		/* validate parameters */
		if (!strlen($this->dbHost))
			return '$dbHost must be a non-empty string';
		if (!strlen($this->dbName))
			return '$dbName must be a non-empty string';

		/* make sure our extension exists */
		if (!function_exists('mssql_connect'))
			return 'mssql PHP extension is missing';

		/* do the job */
		$error = null;			/* will be reinitialized later */

		$this->connection = @mssql_connect($this->dbHost, $this->dbUser, $this->dbPassword);
		if ($this->connection !== false)
		{
			$dbsel = @mssql_select_db($this->dbName, $this->connection);
			if ($dbsel !== false)
			{
				/* set up the severity if configured */
				if (self::MSSQL_MSG_SEVERITY >= 0)
					mssql_min_message_severity(self::MSSQL_MSG_SEVERITY);
				if (self::MSSQL_ERR_SEVERITY >= 0)
					mssql_min_error_severity(self::MSSQL_ERR_SEVERITY);
				$error = '';
			}
		}

		/* try to capture the error message in case of failure */
		if (is_null($error))
			$error = $this->formatConnectError();

		return $error;
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

		mssql_close($this->connection);

		$this->connection = null;

		return true;
	}


	/** @brief Implementation of DbIface::query(). */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		if (!$this->connection)
			return false;

		return @mssql_query($sql, $this->connection);
	}


	/** @brief Implementation of DbIface::free(). */
	public function free($result)
	{
		if (!$this->connection)
			return false;

		if (!is_bool($result))
			mssql_free_result($result);

		return true;
	}


	/** @brief Implementation of DbIface::fetchAssoc(). */
	public function fetchAssoc(&$result)
	{
		if (!$this->connection)
			return false;

		return mssql_fetch_assoc($result);
	}


	/** @brief Implementation of DbIface::fetchNum(). */
	public function fetchNum(&$result)
	{
		if (!$this->connection)
			return false;

		return mssql_fetch_row($result);
	}


	/** @brief Implementation of DbIface::getAffectedRows(). */
	public function getAffectedRows($result)
	{
		if (!$this->connection)
			return false;

		$r = $this->query('SELECT @@ROWCOUNT');
		if (!$r)
			return null;
		$row = $this->fetchNum($r);
		return $row[0];
	}


	/** @brief Implementation of DbIface::getInsertId(). */
	public function getInsertId()
	{
		if (!$this->connection)
			return false;

		$result = $this->query('SELECT @@IDENTITY');
		if (!$result)
			return null;
		$row = $this->fetchNum($result);
		return $row[0];
	}


	/** @brief Implementation of DbIface::getError(). */
	public function getError()
	{
		if (!$this->connection)
			return __CLASS__ . ': not connected';

		return mssql_get_last_message();
	}


	/** @brief Implementation of DbIface::sqlEscapeString(). */
	public function sqlEscapeString($stringToEscape)
	{
		if (!$this->connection)
			return false;

		/* quotes only (bugfix for 20130712-124055-1373622055.9128) */
		$result = str_replace("'", "''", $stringToEscape);
		$result = str_replace('"', '""', $result);
		return $result;
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		$sql = "SELECT COUNT(*) FROM sys.tables WHERE name='" . $this->sqlEscapeString($name) . "'";

		$rs = $this->query($sql);
		if (!$rs)
			return false;

		$r = $this->fetchNum($rs);
		$this->free($rs);

		return ($r[0] > 0) ? 1 : 0;
	}
}
