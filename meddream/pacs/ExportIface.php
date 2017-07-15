<?php

namespace Softneta\MedDream\Core\Pacs;


/** @brief Support for the Export/Burn function.

	export.php already contains a minimal PACS-agnostic implementation which
	synchronous nature makes it difficult to ensure a responsive GUI. This
	interface is for PACSes with their own asynchronous implementation (with
	distinct "create" and "check" operations, true progress reporting, etc)
	that is possible to control.
 */
interface ExportIface extends CommonDataImporter
{
	/** @brief Set up a database job for export

		@param string $studyUids     %Study Instance UID. Multiple values delimited by semicolon, <tt>';'</tt>.
		@param string $mediaLabel    Media label (currently not used)
		@param string $size          Volume size in megabytes
		@param string $exportDir     Unique destination directory

		@retval null   Not implemented or unwilling, must use a common implementation
		               in export.php
		@retval array  This implementation has finished

		Format of the array:

		<ul>
		<li><tt>'error'</tt> - error message (empty string if success). One of possible
		    reasons is an initialization error from PacsExport.
		<li><tt>'id'</tt> - job identifier string
		</ul>

		@note The caller (export.php) deliberately initializes the local instance of
		      AuthDB without a database connection. If you're going to access the
		      database, call <tt>$this->authDB->reconnect()</tt> before that.
	 */
	public function createJob($studyUids, $mediaLabel, $size, $exportDir);


	/** @brief Examine a previously created export job

		@param string $id  Job identifier from createJob()

		@retval null   Not implemented or unwilling, must use a common implementation
		               in export.php
		@retval array  This implementation has finished

		Format of the array:

		<ul>
		<li><tt>'error'</tt> - error message (empty string if success). One of possible
		    reasons is an initialization error from PacsExport.
		<li><tt>'status'</tt> - status indicator string
		</ul>

		@note The caller (export.php) deliberately initializes the local instance of
		      AuthDB without a database connection. If you're going to access the
		      database, call <tt>$this->authDB->reconnect()</tt> before that.
	 */
	public function getJobStatus($id);


	/** @brief Verify if a foreign implementation didn't miss some files

		@param string $exportDir   Unique destination directory, used earlier by createJob()

		@retval string  Error message (empty string if success)
		
		Is called just before creating the ISO file.

		The error message might also come from Loader::getInitializationError().
	 */
	public function verifyJobResults($exportDir);


	/** @brief Indicate what volume sizes are supported.

		@return array  <tt>('data' => array, 'default' => string)</tt>

		@c 'data' consists of subarrays like this one:

		@verbatim
array(
	'id' => 'unlimited',
	'type' => 'volume',
	'attributes' => array(
		'name' => LABEL_STRING,
		'size' => '2147483647'
	)
)
		@endverbatim

		@c 'default' shall be equal to @c 'id' of a corresponding entry.
	 */
	public function getAdditionalVolumeSizes();
}
