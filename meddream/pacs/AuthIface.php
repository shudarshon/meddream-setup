<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Authentication/authorization: %PACS logins and user permissions

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.
 */
interface AuthIface extends Configurable, CommonDataImporter
{
	/** @brief A %PACS login, usually based on dedicated users table in the database */
	public function login($database, $user, $password);


	/** @brief User's privileges

		@param  string $privilege  Possible values: @c 'root', @c 'view', @c 'viewprivate',
		        @c 'export', @c 'forward', @c 'upload'

		@retval  true   The privilege is present
		@retval  false  The privilege is absent
		@retval  null   An error occurred
	 */
	public function hasPrivilege($privilege);


	/** @brief First name of the currently logged-in user from its account

		Required for logic of the 'view' privilege.
	 */
	public function firstName();


	/** @brief Last name of the currently logged-in user from its account

		Required for logic of the 'view' privilege.
	 */
	public function lastName();


	/** @brief A "handler" called near end of System::connect().

		@param array $return  A by-ref parameter that will be updated

		@retval string  Error message (empty if success)

		The main purpose is to update with @c true (if applicable) the following:

		@verbatim
$_SESSION[$this->authDB->sessionHeader . 'notesExsist']
$return['attachmentExist']
		@endverbatim

		They have been already initialized with @c false.
	 */
	public function onConnect(array &$return);
}
