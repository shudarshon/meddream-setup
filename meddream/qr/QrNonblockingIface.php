<?php

namespace Softneta\MedDream\Core\QueryRetrieve;


/** @brief Mandatory methods (nonblocking). */
interface QrNonblockingIface
{
	/** @brief Start retrieval of a study given its UID. */
	public function fetchStudyStart($studyUid);


	/** @brief Stateful single-line parser for fetchStudyStart().

		@retval -1  Error. Call me again until 0 is returned. It might be worth to
		            remember the condition.
		@retval  0  EOF. This condition is permanent. proc_close() will not block as
		            the child has finished.
		@retval  1  Some unimportant line was parsed successfully. Just call me again.

		@note proc_close() is not called automatically in case of error as it
		      might deadlock due to an unread pipe. It would be not quite correct
		      to proc_terminate() either, as it might happen too soon and the PACS
		      will not close the association properly. __Only repeating calls until
		      0 is seen guarantee a correct cleanup.__
	 */
	public function fetchStudyContinue(&$rsrc);


	/** @brief Terminate the ongoing retrieval of a study. */
	public function fetchStudyBreak(&$rsrc);


	/** @brief Cleanup after fetchStudyStart() and associated parsing. */
	public function fetchStudyEnd(&$rsrc);


	/** @brief A blocking all-in-one version (start and finish the retrieval)

		@param string $studyUid  %Study Instance UID
		@param bool   $silent    Do not write any error messages to stdout

		@return string  Error message (empty string if successful)
	 */
	public function fetchStudy($studyUid, $silent = false);
}
