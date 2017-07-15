<?php
/*
	Original name: DbImplMysql.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for MySQL/MySQLi drivers.
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Support for MySQL/MySQLi drivers.

	If MySQLi was detected (the function @c mysqli_connect does exist), will use
	it automatically unless prohibited by the parameter of DbImplMysql::connect().
 */
class DbImplMysql extends DbAbstract
{
	private $useMysqli;        /**< @brief MySQLi was detected so we'll use it instead */


	/** @brief Implementation of DbIface::reconnect().

		@param $additionalOptions See remarks below

		<tt>$additionalOptions['disableMysqli']</tt> (bool): @c true turns off MySQLi
		completely -- only MySQL functions are used afterwards.

		<tt>$additionalOptions['charset']</tt> (string): a non-empty string is given
		to mysql*_set_charset() just after selecting the database.
	 */
	public function reconnect($additionalOptions = array())
	{
		/* validate parameters */
		if (!strlen($this->dbHost))
			return '$dbHost must be a non-empty string';
		if (!strlen($this->dbName))
			return '$dbName must be a non-empty string';

		/* use of MySQLi might be prohibited */
		$enableMysqli = true;
		if (array_key_exists('disableMysqli', $additionalOptions))
			$enableMysqli = !$additionalOptions['disableMysqli'];

		/* make sure our extension exists */
		if ($enableMysqli && function_exists('mysqli_connect'))
			$this->useMysqli = true;
		else
			if (function_exists('mysql_connect'))
				$this->useMysqli = false;
			else
			{
				$option = $enableMysqli ? ' (or mysqli)' : '';
				return "mysql$option PHP extension is missing";
			}

		/* support for the additional parameter "charset" */
		if (array_key_exists('charset', $additionalOptions))
			$charset = $additionalOptions['charset'];
		else
			$charset = '';

		/* do the job */
		$error = null;			/* will be reinitialized later */
		if ($this->useMysqli)
			$this->connection = @mysqli_connect($this->dbHost, $this->dbUser, $this->dbPassword);
		else
			$this->connection = @mysql_connect($this->dbHost, $this->dbUser, $this->dbPassword);
		if ($this->connection)
		{
			if ($this->useMysqli)
				$dbsel = mysqli_select_db($this->connection, $this->dbName);
			else
				$dbsel = mysql_select_db($this->dbName, $this->connection);
			if ($dbsel)
			{
				if (strlen($charset))
					if ($this->useMysqli)
						mysqli_set_charset($this->connection, $charset);
					else
						mysql_set_charset($charset, $this->connection);

				$error = '';
			}
			else
				return "failed to select database '{$this->dbName}'";
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

		if ($this->useMysqli)
			mysqli_close($this->connection);
		else
			mysql_close($this->connection);

		$this->connection = null;
		return true;
	}


	/** @brief Implementation of DbIface::query(). */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_query($this->connection, $sql);
		else
			return mysql_query($sql, $this->connection);
	}


	/** @brief Implementation of DbIface::free(). */
	public function free($result)
	{
		if (!$this->connection)
			return false;

		if (!is_bool($result))		/* it's a resultset */
			if ($this->useMysqli)
				mysqli_free_result($result);
			else
				mysql_free_result($result);

		return true;
	}


	/** @brief Implementation of DbIface::fetchAssoc(). */
	public function fetchAssoc(&$result)
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_fetch_assoc($result);
		else
			return mysql_fetch_assoc($result);
	}


	/** @brief Implementation of DbIface::fetchNum(). */
	public function fetchNum(&$result)
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_fetch_row($result);
		else
			return mysql_fetch_row($result);
	}


	/** @brief Implementation of DbIface::getAffectedRows(). */
	public function getAffectedRows($result)
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_affected_rows($this->connection);
		else
			return mysql_affected_rows($this->connection);
	}


	/** @brief Implementation of DbIface::getInsertId(). */
	public function getInsertId()
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_insert_id($this->connection);
		else
			return mysql_insert_id($this->connection);
	}


	/** @brief Implementation of DbIface::getError(). */
	public function getError()
	{
		if (!$this->connection)
			return __CLASS__ . ': not connected';

		if ($this->useMysqli)
			return mysqli_error($this->connection);
		else
			return mysql_error($this->connection);
	}


	/** @brief Implementation of DbIface::sqlEscapeString().

		NOTE: according to PHP's documentation, @c % and @c _ won't be escaped
		as they are wildcards in some statements.
	*/
	public function sqlEscapeString($stringToEscape)
	{
		if (!$this->connection)
			return false;

		if ($this->useMysqli)
			return mysqli_real_escape_string($this->connection, $stringToEscape);
		else
			return mysql_real_escape_string($stringToEscape, $this->connection);
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		$sql = "SHOW TABLES LIKE '" . $this->sqlEscapeString($name) . "'";

		$rs = $this->query($sql);
		if (!$rs)
			return false;

		$r = $this->fetchNum($rs);
		$this->free($rs);

		return (int) is_array($r);
			/* If result is empty, mysql_fetch_row() returns FALSE, but
			   mysqli_fetch_row() returns NULL. It is better to check for
			   the "right" result which is always an associative array.
			 */
	}
}
