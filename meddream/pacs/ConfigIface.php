<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Basic bits of a %PACS that also do not need a database connection.

	@note Descendant classes will not contain an instance of AuthDB. One of purposes of
	      this %PACS part is to help in PACS-specific initialization of AuthDB.

	Configurable::configure() shall import all configuration parameters and
	exportCommonData() -- wrap them with an array. This way they will reach
	other %PACS parts via their importCommonData().
 */
interface ConfigIface extends Configurable
{
	/** @brief Export parameters etc useful for other %PACS parts

		@param array $what  Array of names. @c null means everything.

		@retval  string  Error message
		@retval  array   Exported data

		PacsConfig sets up things needed by other %PACS parts. PACS::loadParts() will
		pass the data returned by this function, to importCommonData() of other parts.

		This method already does something useful. When extending it, do not forget
		to call parent::exportCommonData() and supplement the result.
	 */
	public function exportCommonData($what = null);


	/** @brief Indicates if authentication (including @link AuthDB::login() @endlink) is supported

		@retval  string  Error message
		@retval  true    Authentication is supported

		Currently used to decide whether the login form needs some pre-filled values.
	 */
	public function supportsAuthentication();


	/** @brief Indicates if it's possible to use encryption for values of some session variables.

		@retval  string  Error message
		@retval  true    Encryption is allowed

		Very old versions of PacsOne do not use encryption in their own sessions and
		MedDream will not be able to reuse credentials from such a session. Also encryption
		is not used in MedDreamWorkstation and its derivatives. These implementations
		will need to return @c false as appropriate.
	 */
	public function canEncryptSession();


	/** @brief Return base directory for ./temp and ./log that is writeable

		In some configurations like DICOMDIR, @c \_\_DIR\_\_ is read-only so a different
		directory is specified instead.

		@retval  null    Failure, use getInitializationError() for the message text
		@retval  string  Directory with a trailing path separator
	 */
	public function getWriteableRoot();


	/** @brief Return the value of the parameter <tt>$dbms</tt> from config.php

		@retval  null    Failure, use getInitializationError() for the message text
		@retval  string  Name of the DBMS
	 */
	public function getDbms();


	/** @brief Return the value of the parameter <tt>$db_host</tt> from config.php

		@retval  string|null  Value of the parameter

		@c null may also mean failure to call the method, therefore you will need to
		use getInitializationError() to detect such a situation.
	 */
	public function getDbHost();


	/** @brief Return the value of the parameter <tt>$archive_dir_prefix</tt> from config.php

		@retval  null    Failure, use getInitializationError() for the message text
		@retval  string  Value of the parameter
	 */
	public function getArchiveDirPrefix();


	/** @brief Return the value of the parameter <tt>$pacs_gateway_addr</tt> from config.php

		@retval  null    Failure, use getInitializationError() for the message text
		@retval  string  Value of the parameter
	 */
	public function getPacsGatewayAddr();


	/** @brief Return database names for the login form

		@retval  string  Error message
		@retval  array   Numerically-indexed array with database names. __At least one
		                 element will always be available.__ Elements, in turn, can be
		                 strings (simple case) or arrays ([0] is the raw value and [1]
		                 is the alias).

		The main difference from getLoginFormDb() is the format of the value. Furthermore,
		implementations are allowed to apply additional processing to make the value more
		human-readable.

		@warning Implementations must return an error message instead of an empty array.
		         A "valid" vay to pass an empty database name is <tt>array('')</tt>.
	 */
	public function getDatabaseNames();


	/** @brief Return the value of the parameter <tt>$login_form_db</tt> from config.php

		@retval  null    Failure, use getInitializationError() for the message text
		@retval  string  Value of the parameter

		@warning Implementations must return the exact value without discarding any
		         information.
	 */
	public function getLoginFormDb();


	/** @brief Gets a validated copy of meddream.retrieve_entire_study (php.ini)

		@retval null  Failed, call getInitializationError() for details
		@retval int   1 (default) or 0

		@note The value underwent validation. A particular %PACS might decide that this
		      option is not supported, and keep it equal to zero regardless of what you
		      specify in php.ini.
	 */
	public function getRetrieveEntireStudy();


	/** @brief Return the value of the parameter <tt>$dcm4che_recv_aet</tt> from config.php

		@retval  string|null  Value of the parameter

		@c null may also mean failure to call the method, therefore you will need to
		use getInitializationError() to detect such a situation.
	 */
	public function getDcm4cheRecvAet();
}
