<?php
/*
	Original name: DbImplSqlsrv.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for the newer MS SQL Server
		driver "SQLSRV" (Windows only).
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Support for the newer Windows-only MS SQL driver "SQLSRV" */
class DbImplSqlsrv extends DbAbstract
{
	/** @brief Implementation of DbIface::reconnect().

		For options that can be passed in @p $additionalOptions, see
		https://msdn.microsoft.com/en-us/library/ff628167.aspx . A few
		of them, namely @c UID, @c PWD, @c Database and @c ReturnDatesAsStrings
		will be ignored as they are overwritten locally.
	 */
	public function reconnect($additionalOptions = array())
	{
		/* validate parameters */
		if (!strlen($this->dbHost))
			return '$dbHost must be a non-empty string';
		if (!strlen($this->dbName))
			return '$dbName must be a non-empty string';

		/* make sure our extension exists */
		if (!function_exists('sqlsrv_connect'))
			return 'sqlsrv PHP extension is missing';

		/* do the job */
		$error = null;			/* will be reinitialized later */
		@trigger_error('');
			/* if the connection fails, then error_get_last() must not return
			   messages that are not ours. By default it can do that, as sqlsrv_connect()
			   doesn't update the error state. So make sure the state stays empty;
			   formatConnectError() has a workaround for that.
			 */

		/* add support for more options */
		$localOptions = array('UID' => $this->dbUser,
			'PWD' => $this->dbPassword, 'Database' => $this->dbName,
			'ReturnDatesAsStrings' => true);
		$options = array_merge($additionalOptions, $localOptions);

		$this->connection = sqlsrv_connect($this->dbHost, $options);

			/* ReturnDatesAsStrings is available since php_sqlsrv 1.1:

				http://msdn.microsoft.com/en-us/library/ee376928(v=sql.105).aspx

			   ...while we usually go for 2.x or even 3.x (the latter is for PHP
			   5.4). The parameter apparently has nothing to do with SQL Server
			   itself.
			 */
		if ($this->connection !== false)
			$error = '';

		/* try to capture the error message in case of failure */
		if (is_null($error))
			$error = $this->formatConnectError();

		return $error;
	}


	/** @brief A wrapper for sqlsrv_errors() */
	private function getErrorAsString()
	{
		$msg = '';
		$e = sqlsrv_errors();
		if (!is_null($e))
			foreach ($e as $err)
			{
				if (strlen($msg))
					$msg .= "\n";
				$msg .= '[' . $err['SQLSTATE'] . '] (' . $err['code'] . ') ' . $err['message'];
			}
		return $msg;
	}


	/** @brief Override DbAbstract::getConnectError() */
	public function getConnectError()
	{
		return $this->getErrorAsString();
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

		sqlsrv_close($this->connection);

		$this->connection = null;

		return true;
	}


	/** @brief Implementation of DbIface::query(). */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		if (!$this->connection)
			return false;

		return @sqlsrv_query($this->connection, $sql);
	}


	/** @brief Implementation of DbIface::free(). */
	public function free($result)
	{
		if (!$this->connection)
			return false;

		sqlsrv_free_stmt($result);

		return true;
	}


	/** @brief Implementation of DbIface::fetchAssoc(). */
	public function fetchAssoc(&$result)
	{
		if (!$this->connection)
			return false;

		return sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
	}


	/** @brief Implementation of DbIface::fetchNum(). */
	public function fetchNum(&$result)
	{
		if (!$this->connection)
			return false;

		return sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC);
	}


	/** @brief Implementation of DbIface::getAffectedRows(). */
	public function getAffectedRows($result)
	{
		if (!$this->connection)
			return false;

		return sqlsrv_rows_affected($result);
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
		$this->free($result);
		return $row[0];
	}


	/** @brief Implementation of DbIface::getError(). */
	public function getError()
	{
		if (!$this->connection)
			return __CLASS__ . ': not connected';

		return $this->getErrorAsString();
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
