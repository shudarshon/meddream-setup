<?php
/*
	Original name: DbAbstract.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Base class with common code.
 */

namespace Softneta\MedDream\Core\Database;


/** @brief Implements some methods from DbIface, adds login credentials

	@note After extending this class, methods should beware calling connector's functions
		as they might not exist. The simplest pattern is to check DbAbstract::$connection
		everywhere, as __construct() sets it to @c null, while connect()
		updates it if some basic connector function exists.
 */
abstract class DbAbstract implements DbIface
{
	/** @name Reserved for descendants */
	/**@{*/
	protected $connection;      /**< @brief A connection resource handled by a corresponding DBMS connector */
	protected $dbHost;          /**< @brief Host name of the DBMS server, connection string, etc */
	protected $dbName;          /**< @brief Name of the default database */
	protected $dbUser;          /**< @brief Login user name */
	protected $dbPassword;      /**< @brief Login password */
	/**@}*/


	/** @brief A simple initializing constructor

		@param string $host, $db, $user, $password  Assigned to corresponding protected properties
		                                            of this class
		@param Logging $logger                      Ignored in DbAbstract but might be used by descendants

		DB::__construct() passes the @p $logger argument to constructors of
		@c %DbImpl* as some drivers (for example, DbImplOci8.php) need it. It's
		more efficient, though a bit ugly, to define the fifth argument here: in
		that case those @c %DbImpl* that do not need logging, will be happy with
		the default constructor from the parent -- so there's less work on the
		whole.
	 */
	public function __construct($host, $db, $user, $password, $logger = null)
	{
		$this->connection = null;

		$this->dbHost = $host;
		$this->dbName = $db;
		$this->dbUser = $user;
		$this->dbPassword = $password;
	}


	/** @brief Implementation of DbIface::connect() */
	public function connect($db, $user, $password, $additionalOptions = array())
	{
		$this->dbName = $db;
		$this->dbUser = $user;
		$this->dbPassword = $password;
		return $this->reconnect($additionalOptions);
	}


	/** @brief Destructor. Calls DbIface::close().  */
	public function __destruct()
	{
		$this->close();
	}


	/** @brief Implementation of DbIface::getUser(). */
	public function getUser()
	{
		return $this->dbUser;
	}


	public function formatConnectDetails($user = null, $password = null)
	{
		if (is_null($user))
			$user = $this->dbUser;
		if (is_null($password))
			$password = $this->dbPassword;
		if (!strlen($user) && !strlen($password))
			$serverInfo = '';
		else
		{
			/* can't log the password itself: a right password might
			   be revealed merely because the SQL server went away
			 */
			$withPass = (strlen($password) != 0) ? 'YES' : 'NO';

			$serverInfo = "user '$user', with password: $withPass, ";
		}
		$serverInfo .= 'from ';

		/* add IP address and called URL, but only if available */
		if (strtolower(PHP_SAPI) == 'cli')
			$serverInfo .= 'console';
		else
		{
			/* automated tests are too difficult: our infrastructure for them is CLI-based */
			// @codeCoverageIgnoreStart
			$addr = $this->getRealIpAddr();
			$uri = '';
			if (isset($_SERVER['REQUEST_URI']))
				$uri = $_SERVER['REQUEST_URI'];

			$serverInfo .= "$addr, as '$uri'";
			// @codeCoverageIgnoreEnd
		}

		return $serverInfo;
	}


	/** @brief Helper for formatConnectError().

		For some drivers @c error_get_last() does not provide any information,
		a corresponding driver's function must be called instead. These
		implementations can override this function accordingly.

		@warning Make sure the implementation doesn't call a function that doesn't
		         exist. The usual pattern where DbAbstract::$connection is checked,
		         will not work as this variable is still not initialized.

		@retval string Error message (might be empty)
	 */
	public function getConnectError()
	{
		return '';
	}


	/** @brief A connection error diagnostic message used by connect().

		Attempts to provide an error message combined with result of formatConnectDetails().
	 */
	protected function formatConnectError()
	{
		if (function_exists('error_get_last'))		/* PHP 5.2.0+ */
		{
			$error = error_get_last();
			if (!is_null($error))
				$error = trim($error['message']);
			if (!strlen($error))
			{
				$error = $this->getConnectError();
				if (!strlen($error))
					$error = "connect to {$this->dbHost} failed";
			}

			return "$error  (" . $this->formatConnectDetails() . ')';
		}
		else
			/* a very rare situation; we don't support PHP this old anyway. */
			// @codeCoverageIgnoreStart
			return 'failed to connect to the database';
			// @codeCoverageIgnoreEnd
	}


	/** @brief Attempts to return the IP address of the client.

		Addresses reported by proxy take precedence.
	 */
	/** @codeCoverageIgnore */
	static public function getRealIpAddr()
		/* our unit tests are based on CLI, therefore this method must be tested differently */
	{
		/* one case of proxy-added header */
		if (isset($_SERVER['HTTP_CLIENT_IP']))
 		{
			$h = $_SERVER['HTTP_CLIENT_IP'];
			if (strlen($h))
				return $h;
		}

		/* ...and there's another one */
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$h = $_SERVER['HTTP_X_FORWARDED_FOR'];
			if (strlen($h))
				return $h;
		}

		/* probably no proxy */
		if (isset($_SERVER['REMOTE_ADDR']))
		{
			$h = $_SERVER['REMOTE_ADDR'];
			if (strlen($h))
				return $h;
		}

		/* must still return something */
		return '';
	}


	/** @brief Implementation of DbIface::isConnected(). */
	public function isConnected()
	{
		return $this->connection != null;
			/* the following yield false: null, false, empty string */
	}


	/** @brief Implementation of DbIface::setConnection(). */
	public function setConnection($rawValue = true)
	{
		$this->connection = $rawValue;
		return true;
	}
}
