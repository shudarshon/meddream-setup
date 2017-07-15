<?php
/*
	Original name: DbImplOci8.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for the OCI8 driver.
 */

namespace Softneta\MedDream\Core\Database;
use Softneta\MedDream\Core\Logging;


/** @brief Support for OCI8. */
class DbImplOci8 extends DbAbstract
{
	/** @brief Cached value for getInsertId(). */
	private $lastId;

	/** @brief Cached value for getError(). */
	private $lastError;

	/** @brief Do not make array keys lowercase in fetchAssoc(). */
	private $disableKeyLowercase;

	/** @brief Logger for troubleshooting.

		query() and queryWithBlobAndId() are quite complicated, therefore
		they log some details that might help to track the cause.

		Might be @c null if the constructor didn't receive an instance of Logging.
		In that case logging is unavailable.
	 */
	private $log;


	/** @brief A helper that preserves an error message in @link $lastError @endlink.

	   A few wrappers call oci_free_statement() that resets the value returned by
	   oci_error(). Therefore they also call this function a bit earlier so that
	   the message is not lost.
	 */
	protected function preserveError($success, $rsrc)
	{
		/* it's unlikely that we'll be called in this situation, but do this just in case */
		if (!$this->connection)
			return;

		$msg = '';
		if ($success === false)
		{
			$e = oci_error($rsrc);
			if ($e !== false)	/* possible if $success doesn't come from oci_*() functions */
			{
				$msg = "'" . $e['message'] . "'";
				if ($e['offset'])
					$msg .= ' at position ' . $e['offset'];
				if (strlen($e['sqltext']))
					$msg .= ' in "' . $e['sqltext'] . '"';
			}
		}
		$this->lastError = $msg;
	}


	/** @brief A constructor for OCI8

		@param string $host      Connection string. Can be a name of a local Oracle
			instance, or a Connect Name from tnsnames.ora, or an Easy Connect string
			(if PHP is linked with corresponding Oracle client libraries).
		@param string $db        Ignored
		@param string $user      User name
		@param string $password  Password
		@param Logging $logger   A logging facility, see @link $log @endlink
	 */
	public function __construct($host, $db, $user, $password, $logger = null)
	{
		parent::__construct($host, $db, $user, $password, $logger);

		$this->lastId = false;
		$this->lastError = '';
		$this->disableKeyLowercase = false;

		$this->log = $logger;
	}


	/** @brief Implementation of DbIface::reconnect().

		Supported flags of @p $additionalOptions:

		<tt>'disableKeyLowercase'</tt>, boolean: if @c true, array_change_key_case()
		is not called in fetchAssoc().
	 */
	public function reconnect($additionalOptions = array())
	{
		/* validate parameters */
		if (!strlen($this->dbHost))
			return '$dbHost must be a non-empty string';

		/* make sure our extension exists */
		if (!function_exists('oci_connect'))
			return 'oci8 PHP extension is missing';

		/* cache the option 'disableKeyLowercase' */
		if (array_key_exists('disableKeyLowercase', $additionalOptions))
			$this->disableKeyLowercase = $additionalOptions['disableKeyLowercase'];
		else
			$this->disableKeyLowercase = false;

		/* do the job */
		$error = null;			/* will be reinitialized later */
		$this->connection = @oci_connect($this->dbUser, $this->dbPassword, $this->dbHost);
		if ($this->connection !== false)
		{
			$error = '';
			$this->lastError = '';
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

		oci_close($this->connection);
		$this->connection = null;

		return true;
	}


	/** @brief Implementation of DbIface::query(). */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		if (!$this->connection)
			return false;

		if (strlen($bindVarName))
			return $this->queryWithBlobAndId($sql, $returnVarName, $bindVarName, $data);

		$result = @oci_parse($this->connection, $sql);
		if ($result)
		{
			$this->lastId = null;	/* unknown */

			if (strlen($returnVarName))
			{
				$this->lastId = -1;
					/* a typical integer value; oci_bind_by_name will determine
					   size and type from current contents
					 */

				$r = @oci_bind_by_name($result, $returnVarName, $this->lastId);
				$this->preserveError($r, $result);
				if (!$r)
				{
					$e = $this->lastError;
					if ($this->log)
						$this->log->asErr("bind of variable '$returnVarName' failed: " . $e);
				}
			}

			$r = @oci_execute($result);
			$this->preserveError($r, $result);
			if (!$r)
			{
				@oci_free_statement($result);
				$result = null;
			}
		}
		return $result;
	}


	/** @brief A helper that supports a BLOB parameter */
	protected function queryWithBlobAndId($sql, $returnVarName, $bindVarName, $data)
	{
		$success = false;

		$result = @oci_parse($this->connection, $sql);
		if (!$result)
		{
			$e = $this->lastError;
			if ($this->log)
				$this->log->asErr('oci_parse failed: ' . $e);
		}
		else
		{
			$blob = @oci_new_descriptor($this->connection, OCI_D_LOB);
			$this->preserveError($blob, $result);
			if ($blob === false)
			{
				$e = $this->lastError;
				if ($this->log)
					$this->log->asErr('oci_new_descriptor failed: ' . $e);
			}
			else
			{
				$this->lastId = null;	/* unknown */

				if (strlen($returnVarName))
				{
					$this->lastId = -1;
						/* a typical integer value; oci_bind_by_name will determine
						   size and type from current contents
						 */
					$r = @oci_bind_by_name($result, $returnVarName, $this->lastId);
					$this->preserveError($r, $result);
					if (!$r)
					{
						$e = $this->lastError;
						if ($this->log)
							$this->log->asErr("bind of variable '$returnVarName' failed: " . $e);
					}
				}

				$r = @oci_bind_by_name($result, $bindVarName, $blob, -1, OCI_B_BLOB);
				$this->preserveError($r, $result);
				if (!$r)
				{
					$e = $this->lastError;
					if ($this->log)
						$this->log->asErr("bind of variable '$bindVarName' failed: " . $e);
				}
				else
				{
					$r = @oci_execute($result, OCI_NO_AUTO_COMMIT);
					$this->preserveError($r, $result);
					if (!$r)
					{
						$e = $this->lastError;
						if ($this->log)
							$this->log->asErr('oci_execute failed: ' . $e);
					}
					else
					{
						$r = @$blob->save($data);
						$this->preserveError($r, $result);
						if (!$r)
						{
							$e = $this->lastError;
							if ($this->log)
								$this->log->asErr('OCI-Lob::save failed: ' . $e);
							@oci_rollback($result);
						}
						else
						{
							$r = @oci_commit($this->connection);
							$this->preserveError($r, $result);
							if (!$r)
							{
								$e = $this->lastError;
								if ($this->log)
									$this->log->asErr('oci_commit failed: ' . $e);
							}
							else
								$success = true;
						}
					}
				}

				@oci_free_descriptor($blob);
			}

			if (!$success)
			{
				@oci_free_statement($result);
				$result = false;
					/* the caller must call ::free() if we succeed, a non-FALSE return value indicates that */
			}
		}

		return $result;
	}


	/** @brief Implementation of DbIface::free(). */
	public function free($result)
	{
		if (!$this->connection)
			return false;
		if (!is_bool($result))		/* it's a resultset */
			oci_free_statement($result);
		return true;
	}


	/** @brief Implementation of DbIface::fetchAssoc(). */
	public function fetchAssoc(&$result)
	{
		if (!$this->connection)
			return false;

		$r = oci_fetch_array($result, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
		if (!$this->disableKeyLowercase && is_array($r))
			$r = array_change_key_case($r);

		return $r;
	}


	/** @brief Implementation of DbIface::fetchNum(). */
	public function fetchNum(&$result)
	{
		if (!$this->connection)
			return false;

		return oci_fetch_array($result, OCI_NUM + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
	}


	/** @brief Implementation of DbIface::getAffectedRows(). */
	public function getAffectedRows($result)
	{
		if (!$this->connection)
			return false;

		$r = @oci_num_rows($result);
		$this->preserveError($r, $result);
		return $r;
	}


	/** @brief Implementation of DbIface::getInsertId(). */
	public function getInsertId()
	{
		if (!$this->connection)
			return false;

		return $this->lastId;
			/* for this to work, you MUST call ::queryWith*($sql) and use proper $sql  */
	}


	/** @brief Implementation of DbIface::getError(). */
	public function getError()
	{
		if (!$this->connection)
			return __CLASS__ . ': not connected';

		return $this->lastError;
	}


	/** @brief Implementation of DbIface::sqlEscapeString(). */
	public function sqlEscapeString($stringToEscape)
	{
		return addslashes($stringToEscape);
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		$sql = "SELECT COUNT(*) FROM user_tables WHERE table_name='" .
			$this->sqlEscapeString(strtoupper($name)) . "'";

		$rs = $this->query($sql);
		if (!$rs)
			return false;

		$r = $this->fetchNum($rs);
		$this->free($rs);

		return ($r[0] > 0) ? 1 : 0;
	}
}
