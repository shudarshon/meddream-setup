<?php
/*
	Original name: DbIface.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. An interface that enforces it.
 */

/** @brief Unified API for database support.

	@todo Return value of close() and free() only indicates that the connection existed
	      before the call. Current implementations do not attempt to return the actual
	      success indicator given by the corresponding connector function.
 */
namespace Softneta\MedDream\Core\Database;


/** @brief Mandatory methods. */
interface DbIface
{
	/** @brief Make a connection to the DBMS server, using explicit parameters.

		Connection parameters will be stored for later use regardless of whether
		the operation succeeded.

		@retval string  An error message. Empty string means success.
	 */
	public function connect($db, $user, $password, $additionalOptions = array());


	/** @brief Make a connection to the DBMS server, using implicit parameters.

		Cached connection parameters (username etc) are used.

		@param $additionalOptions  Array with options specific to a particular DbImpl*

		@retval string  An error message. Empty string means success.
	 */
	public function reconnect($additionalOptions = array());


	/** @brief Close the current connection

		@retval false  Some error occurred, getError() will return the message
		@retval true   Connection has been closed
	 */
	public function close();


	/** @brief Indicate whether a connection has been made, therefore other functions should succeed

		@retval false  Not connected or not initialized, getError() will be more specific

		@note "Not connected" and "not initialized" are treated equally to avoid
		      problems with the traditional syntax <tt>if ($db->isConnected) ...</tt>.
	 */
	public function isConnected();


	/** @brief Setter for DbAbstract::$connection

		@param $rawValue  Value to assign

		@retval false  Some error occurred, getError() will return the message
		@retval true   Changed successfully

		If @c false is assigned, afterwards it is treated identically to @c null
		which is the default "not connected" indicator.
	 */
	public function setConnection($rawValue = true);


	/** @brief A getter for the login user name

		@retval false   Some error occurred, getError() will return the message
		@retval string  User name
	 */
	public function getUser();


	/** @brief Builds a message for logging of connection details etc.

		@param $user      An override for user name. By default, a cached value is used.
		@param $password  An override for password. By default, a cached value is used.

		Returns a string that includes user name and a flag indicating whether
		the password is not empty. This information is omitted if both @p $user
		and @p $password are empty.

		For the CLI SAPI, the string also includes a corresponding indication,
		<tt>"from console"</tt>. For other SAPIs, the client IP address and the URI
		by which the current page was accessed, are included.

		If the underlying instance of DbImpl* is still not initialized, returns
		a corresponding error message.

		@p $user, @p $password are here for descendants that use a more abstract
		authentication where a database account known in advance is used to
		fetch credentials from some custom table. When those credentials are
		correct, we want to include *them* in the log record instead, together
		with IP address and URI.
	 */
	public function formatConnectDetails($user = null, $password = null);


	/** @brief Send any kind of query

		@param $sql            SQL expression
		@param $returnVarName  If not empty: special case for Oracle #1 (see below)
		@param $bindVarName    If not empty: special case for Oracle #2 (see below)
		@param $data           Special case for Oracle #2 (see below)

		@retval false     Some error occurred, call getError() for details
		@retval true      Success without a resultset
		@retval resource  Resultset

		<b>Oracle #1</b>: for a query that updates an auto-increment column.
		@p $sql must contain " RETURNING ... INTO $returnVarName". This function
		additionally calls oci_bind_by_name() so that a cached result is
		available for getInsertId() later.

		<b>Oracle #2</b>: for a query that updates a BLOB column (and possibly
		an auto-increment column). @p $sql must contain "RETURNING ... INTO
		$bindVarName"; oci_bind_by_name() will bind this variable to @p $data
		and so transfer contents to OCI8. An auto-increment column requires a
		non-empty @p $returnVarName and a correspondingly altered RETURNING
		statement.
	 */
	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null);


	/** @brief Free resources associated with a resultset

		@retval false  Some error occurred, getError() will return the message
		@retval true   If $result was a resultset, then its resources were released
	 */
	public function free($result);


	/** @brief Read an associative array from the recordset

		@retval false     Some error occurred, getError() will return the message
		@retval resource  Resultset
	 */
	public function fetchAssoc(&$result);


	/** @brief Read a numeric array from the recordset
	
		@retval false     Some error occurred, getError() will return the message
		@retval resource  Resultset
	 */
	public function fetchNum(&$result);


	/** @brief Get number of rows affected by the last query

		@retval false  Some error occurred, getError() will return the message
		@retval int    Number of affected rows
	 */
	public function getAffectedRows($result);


	/** @brief Get ID of the record inserted by the last query

		@retval false  Some error occurred, getError() will return the message
		@retval int    ID of the record
	 */
	public function getInsertId();


	/** @brief Get error message associated with the last query

		@retval string Error message (might be empty). This also includes messages related
		               to a not connected state or a not initialized instance of underlying
		               DbImpl*.
	 */
	public function getError();


	/** @brief Safely escape a value for a SQL statement

		@retval false  Some error occurred, getError() will return the message
		@retval string The escaped string
	 */
	public function sqlEscapeString($stringToEscape);


	/** @brief Check if the table exists

		@retval null  Some error occurred, getError() will return the message
		@retval 1|0   Indicates whether it exists or not

		@note Do not forget to check for @c null if your logic needs to distinguish between
		      "table is really missing" and "can't determine due to failure".
	 */
	public function tableExists($name);
}
