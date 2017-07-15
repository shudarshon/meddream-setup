<?php
/*
	Original name: DB.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access
 */

namespace Softneta\MedDream\Core\Database;


/** @brief A wrapper for all supported database drivers.

	Exposes the same methods as DbIface. However they are implemented by a
	particular driver (DbImpl* class), an object instance of which was
	initialized as per constructor's arguments. A corresponding file DbImpl*.php
	must also exist.
 */
class DB implements DbIface
{
	/** @brief The normalized name of the driver, remembered just in case */
	protected $dbms;

	/** @brief Delayed report of an error from the constructor.

		A typical pattern is to call connect() immediately after the constructor.
		Some, however, might want to check for errors first. This isn't too
		inflexible, because if the constructor produced an error message, then
		the object is unusable anyway.
	 */
	protected $delayedMessage = 'internal (DB.php): not initialized';

	/** @brief The object instance of a DbImpl* class */
	protected $dbInstance;


	/** @brief Initialize an underlying database driver

		Includes the corresponding file DbImpl*.php and creates an object of that
		class. __If the file doesn't exist, calling most methods of this class will
		return @c false and getError() will return a more specific message.__

		The wildcard <tt>*</tt> in the above file name is replaced with a normalized
		version of @p $dbms (first character is made uppercase, the rest lowercase).

		@param  $dbms                         Name of the driver (case insensitive)
		@param  $host, $db, $user, $password  See constructor of DbAbstract
		@param  $logger                       Either @c null (logging disabled, default)
		                                      or an instance of Logging
		@param  $implDir                      The directory for DbImpl*.php files

		An instance of Logging must be provided in @p $logger to turn on logging.

		@p $implDir is @c null by default, which resolves to the directory of
		DB.php. An empty string obviously means the current directory. A trailing
		directory separator will be added automatically if needed.
	 */
	public function __construct($dbms, $host, $db, $user, $password, $logger = null, $implDir = null)
	{
		$this->dbms = strtoupper(trim($dbms));
		$namePart = ucfirst(strtolower(trim($dbms)));
		$className = "DbImpl$namePart";
		$classNameFull = __NAMESPACE__ . '\\' . $className;

		/* verify whether $dbms is supported */
		$fileName = "$className.php";
		if (is_null($implDir))
			$implDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
		else
			if (strlen($implDir))
			{
				/* add a directory separator if needed */
				$lc = substr($implDir, -1);
				if (($lc != '/') && ($lc != '\\'))
					$implDir .= DIRECTORY_SEPARATOR;
			}
		$fullPath = $implDir . $fileName;
		if (!@file_exists($fullPath))
		{
			$this->delayedMessage = "unsupported \$dbms '$dbms'. Likely the file $fileName " .
				"is simply missing in the directory '$implDir'.";
			return;
		}

		/* construct the appropriate worker object */
		include_once($fullPath);
		$this->dbInstance = new $classNameFull($host, $db, $user, $password, $logger);
		$this->delayedMessage = '';
	}


	/** @brief Getter for @link $delayedMessage @endlink */
	public function getInitializationError()
	{
		return $this->delayedMessage;
	}


	/** @brief Getter for @link $dbms @endlink */
	public function getDbms()
	{
		return $this->dbms;
	}


	public function formatConnectDetails($user = null, $password = null)
	{
		if (!$this->dbInstance)
			return $this->delayedMessage;

		return $this->dbInstance->formatConnectDetails($user, $password);
	}


	public function getUser()
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->getUser();
	}


	public function connect($db, $user, $password, $additionalOptions = array())
	{
		if (!$this->dbInstance)
			return $this->delayedMessage;

		return $this->dbInstance->connect($db, $user, $password, $additionalOptions);
	}


	public function reconnect($additionalOptions = array())
	{
		if (!$this->dbInstance)
			return $this->delayedMessage;

		return $this->dbInstance->reconnect($additionalOptions);
	}


	public function isConnected()
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->isConnected();
	}


	public function setConnection($rawValue = true)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->setConnection($rawValue);
	}


	public function close()
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->close();
	}


	public function query($sql, $returnVarName = '', $bindVarName = '', $data = '')
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->query($sql, $returnVarName, $bindVarName, $data);
	}


	public function free($result)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->free($result);
	}


	public function fetchAssoc(&$result)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->fetchAssoc($result);
	}


	public function fetchNum(&$result)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->fetchNum($result);
	}


	public function getAffectedRows($result)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->getAffectedRows($result);
	}


	public function getInsertId()
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->getInsertId();
	}


	public function getError()
	{
		if (!$this->dbInstance)
			return $this->delayedMessage;

		return $this->dbInstance->getError();
	}


	public function sqlEscapeString($stringToEscape)
	{
		if (!$this->dbInstance)
			return false;

		return $this->dbInstance->sqlEscapeString($stringToEscape);
	}


	public function tableExists($name)
	{
		if (!$this->dbInstance)
			return $this->delayedMessage;

		return $this->dbInstance->tableExists($name);
	}
}
