<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Support for the Forward function.

	Study.php already contains a minimal PACS-agnostic implementation which
	synchronous nature makes it difficult to ensure a responsive GUI. This
	interface is for PACSes with their own asynchronous implementation (with
	distinct "create" and "check" operations, true progress reporting, etc)
	that is possible to control.

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.
 */
interface ForwardIface extends Configurable, CommonDataImporter
{
	/** @brief Set up a database job for forward

		@param string $studyUid  Value of primary key in the study table
		@param string $dstAe     AE Title (or the entire connection string) where to send the study

		@retval null   Not implemented or unwilling, must use a common implementation
		               in Study.php
		@retval array  This implementation has finished

		Format of the array:

		<ul>
		<li><tt>'error'</tt> - error message (empty string if success). One of possible
		    reasons is initialization error from PacsForward.
		<li><tt>'id'</tt> - job identifier string
		</ul>

		@note The caller (Study.php) deliberately initializes the local instance of
		      AuthDB without a database connection. If you're going to access the
		      database, call <tt>$this->authDB->reconnect()</tt> before that.
	 */
	public function createJob($studyUid, $dstAe);


	/** @brief Examine a previously created forward job

		@param string $id  Value from createJob()

		@retval null   Not implemented or unwilling, must use a common implementation
		               in Study.php
		@retval array  This implementation has finished

		Format of the array:

		<ul>
		<li><tt>'error'</tt> - error message (empty string if success). One of possible
		    reasons is initialization error from PacsForward.
		<li><tt>'status'</tt> - status string
		</ul>

		@note The caller (Study.php) deliberately initializes the local instance of
		      AuthDB without a database connection. If you're going to access the
		      database, call <tt>$this->authDB->reconnect()</tt> before that.
	 */
	public function getJobStatus($id);


	/** @brief Get list of destination AEs

		@retval array  <tt>('error' => ERROR_MESSAGE, 'count' => NUM_AES, 0...NUM_AES-1 => AE)</tt>

		Format of @c AE subarray:

		<ul>
		<li><tt>'data'</tt> - AE Title or the entire connection string
		<li><tt>'label'</tt> - a label for the user interface
		</ul>

		@note The caller (Study.php) deliberately initializes the local instance of
		      AuthDB without a database connection. If you're going to access the
		      database, call <tt>$this->authDB->reconnect()</tt> before that.
	 */
	public function collectDestinationAes();
}
