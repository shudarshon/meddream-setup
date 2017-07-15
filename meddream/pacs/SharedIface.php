<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Basic functions of a %PACS shared between various parts of it.

	These functions should be useful for more than one %PACS. Otherwise use of
	PACS-local classes is a more correct approach.

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.

	@note An object instance of this class is available in every %PACS part
	      (except PacsConfig) as <tt>$this->shared</tt>.

	@todo There is no method that cleans up "combined" UIDs. Earlier data.php used
	      to perform the cleanup itself; implementations of PreloadIface (and, partially,
	      StructureIface) are also duplicating it. Examples of these UIDs:
	      <ul>
	        <li>Series Instance UID that is present in response of StructureIface::studyGetMetadata()
	            is in some cases (DICOM, WADO) combined with %Study Instance UID;
	        <li>similarly SOP Instance UID might be combined (DICOM, WADO, ClearCanvas)
	            with Series Instance UID and %Study Instance UID;
	        <li>in case of FileSystem, SOP Instance UID is just a part of a filesystem
	            path (often with path separators), however data.php still needs a version
	            of it suitable to use in the name of a temporary file.
	      </ul>
 */
interface SharedIface extends Configurable, CommonDataImporter
{
	/** @brief Map a storage device identifier to its root directory.

		@retval null    Failed, call getInitializationError() for details
		@retval false   Failure in the implementation (the reason is likely logged)
		@retval string  Value of the directory
	 */
	public function getStorageDevicePath($id);


	/** @brief Build a full person name from its parts.

		Up to 5 strings are allowed as parameters. They will be combined with
		<tt>' '</tt>. The resulting string won't have any leading or trailing spaces,
		however might have up to 4 spaces somewhere in the middle.

		@retval null    Failed, call getInitializationError() for details
		@retval false   Failure in the implementation (the reason is likely logged)
		@retval string  The combined name
	 */
	public function buildPersonName($familyName, $givenName = null, $middleName = null, $prefix = null, $suffix = null);


	/** @brief Given a string fetched from database, removes rubbish specific
		to this particular %PACS.

		For example, DCM4CHEE v4+ uses @c '*' to mark absent attributes if the
		database schema disallows @c null.

		@retval null    Failed, call getInitializationError() for details
		@retval string  The adjusted string
	 */
	public function cleanDbString($str);
}
