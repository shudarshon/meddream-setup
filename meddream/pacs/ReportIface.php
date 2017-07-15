<?php

namespace Softneta\MedDream\Core\Pacs;


/** @brief Support for Reporting function.

	Reports are only added, their full history is available. No possibility
	to remove or replace a report. By convention, only the last version of
	the report matters.

	Templates are a special kind of reports. The record is not linked to a
	study and the title begins with @c "TEMPLATE:".
 */
interface ReportIface extends CommonDataImporter
{
	/** @brief Collect all reports for a particular study.

		@param string $studyUid         Primary key of a study record to which reports are linked
		@param bool   $withAttachments  Include attachments

		@return array

		Elements of the return value:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'id'</tt> - equal to @p $studyUid
			<li><tt>'studyid'</tt> - %Study ID
			<li><tt>'referringphysician'</tt> - Referring Physician (if supported by %PACS)
			<li><tt>'readingphysician'</tt> - Name of Physician(s) Reading %Study (if supported by %PACS)
			<li><tt>'accessionnum'</tt> - Accession Number
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'patientname'</tt> - Patient Name
			<li><tt>'patientbirthdate'</tt> - Patient Birth Date
			<li><tt>'patienthistory'</tt> - Additional Patient History (if supported by %PACS)
			<li><tt>'modality'</tt> - Modality
			<li><tt>'description'</tt> - %Study Description
			<li><tt>'date'</tt> - %Study Date
			<li><tt>'time'</tt> - %Study Time
			<li><tt>'notes'</tt> - report presence indicator (1: present, 0: absent)
			<li><tt>'count'</tt> - number of @c SINGLE_NOTE subarrays
			<li><tt>0...N-1</tt> - @c SINGLE_NOTE subarrays

			Format of a @c SINGLE_NOTE subarray:

			<ul>
				<li><tt>'id'</tt> - value of primary key in the @c studynotes table
				<li><tt>'user'</tt> - login of the user who saved this report
				<li><tt>'created'</tt> - timestamp
				<li><tt>'headline'</tt> - the same timestamp
				<li><tt>'notes'</tt> - report text
			</ul>
		</ul>
	 */
	public function collectReports($studyUid, $withAttachments = false);


	/** @brief Fetch the last report for a particular study.

		@param string $studyUid  Primary key of a study record to which the report is linked

		@retval array   Details of the operation

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'id'</tt> - value of primary key in the @c studynotes table
			<li><tt>'user'</tt> - login of the user who saved this report
			<li><tt>'created'</tt> - timestamp
			<li><tt>'headline'</tt> - the same timestamp
			<li><tt>'notes'</tt> - report text.
		</ul>

		If no reports exists for this study, then @c 'error' will be an empty
		string and all remaining elements will be @c null.

		This function is called during the Export operation.
	 */
	public function getLastReport($studyUid);


	/** @brief Create a new report for a study.

		@param string $studyUid  Primary key of a study record to which the report is linked
		@param string $note      Text of the report
		@param string $date      Timestamp (displayed as a heading) of the report
		@param string $user      Login of the user who creates the report

		@retval string  Error message
		@retval array   Details of a successful operation

		Format of the returned array:

		<ul>
			<li><tt>'id'</tt> - primary key of the created record
			<li><tt>'created'</tt> - timestamp, equal to @p $date
			<li><tt>'user'</tt> - user's login, equal to @p $user
		</ul>
	 */
	public function createReport($studyUid, $note, $date = '', $user = '');


	/** @brief Enumerate all existing templates for the current user.

		@retval string  Error message
		@retval array   Details of a successful operation

		Format of the returned array:

		<ul>
			<li><tt>'count'</tt> - number of @c SINGLE_TEMPLATE subarrays
			<li>@c 0 ... @c N - @c SINGLE_TEMPLATE subarrays

			Format of a @c SINGLE_TEMPLATE subarray:

			<ul>
				<li><tt>'id'</tt> - value of primary key in the @c studynotes table
				<li><tt>'group'</tt> - template group
				<li><tt>'name'</tt> - template title
			</ul>
		</ul>

		Contents of the template are not provided, use getTemplate() for a more
		detailed array.
	 */
	public function collectTemplates();


	/** @brief Create a new template.

		@param string $group  Template group
		@param string $name   Template title
		@param string $text   Contents

		@retval string  Error message
		@retval array   Details of a successful operation

		Format of the returned array:

		<ul>
			<li><tt>'id'</tt> - primary key of the created record in the @c studynotes table
			<li><tt>'created'</tt> - timestamp, equal to @p $date
			<li><tt>'user'</tt> - user's login, equal to @p $user
		</ul>
	 */
	public function createTemplate($group, $name, $text);


	/** @brief Update an existing template.

		@param string $id     Primary key of the record in the @c studynotes table
		@param string $group  Template group
		@param string $name   Template title
		@param string $text   Contents

		@return string  Error message (empty if success)
	 */
	public function updateTemplate($id, $group, $name, $text);


	/** @brief Fetch a particular template record, including the template text.

		@param string $id     Value of primary key for the record

		@retval string  Error message
		@retval array   Details of a successful operation

		Format of the returned array:

		<ul>
			<li><tt>'id'</tt> - primary key of the record in the @c studynotes table
			    (equal to @p $id)
			<li><tt>'group'</tt> - template group
			<li><tt>'name'</tt> - template title
			<li><tt>'text'</tt> - contents
		</ul>
	 */
	public function getTemplate($id);


	/** @brief Remove a template record.

		@param string $id     Primary key for the record

		@retval string  Error message (empty if success)
	 */
	public function deleteTemplate($id);


	/** @brief Collect attachments for a particular report.

		@param string $studyUid  Primary key of the study record
		@param array  $return    Array with initial values for the returned array. The element
		                         @c 'id' is mandatory and specifies primary key of the corresponding
		                         report record.

		@return array

		The returned array is a copy of @p $return with the following values __added__:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'attachment'</tt> - @c ATTACHMENT_LIST subarray

			Format of the @c ATTACHMENT_LIST subarray:

			<ul>
				<li><tt>'count'</tt> - number of @c SINGLE_ATTACHMENT subarrays
				<li><tt>0...N-1</tt> - @c SINGLE_ATTACHMENT subarrays

				Format of the @c SINGLE_ATTACHMENT subarray:

				<ul>
					<li><tt>'seq'</tt> - primary key of a record in the @c attachments table
					<li><tt>'filename'</tt> - name of the file
					<li><tt>'mimetype'</tt> - MIME Type of the file
				</ul>
			</ul>
		</ul>

		This method is called:

		<ol>
		  <li>directly from collectReports() for all reports in a loop if the <tt>$withAttachments</tt>
		      parameter commands that,
		  <li>directly from deleteAttachment() for the current report,
		  <li>from the frontend for the current report after uploading a new attachment.
		</ol>
	 */
	public function collectAttachments($studyUid, $return);


	/** @brief Add a file attachment to the study.

		@param string $studyUid  Primary key of the study record to which the file will be attached
		@param string $reportId  Primary key of the associated report record
		@param string $mimeType  MIME Type of the file
		@param string $fileName  Name of the file. Can be either the original name, or a full
		                         path to a unique copy.
		@param string $fileSize  Size of the file, in bytes
		@param string $fileData  Contents of the file. If @c null, then @p $fileName should be
		                         a full path, or it might not be possible to find the file later.

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'seq'</tt> - primary key of the created record in the @c attachments table
		</ul>

		@p $fileData is @c null when attachments are stored on disk; it contains the
		file contents when attachments are stored directly into the database table.
	 */
	public function createAttachment($studyUid, $reportId, $mimeType, $fileName, $fileSize,
		$fileData = null);


	/** @brief Fetch an attachment record from the database.

		@param string $studyUid  Primary key of the study record
		@param string $seq       Primary key of the record in the @c attachments table

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'path'</tt> - original name, or the full path to a renamed copy
			<li><tt>'mimetype'</tt> - MIME Type of the file
			<li><tt>'totalsize'</tt> - size of the file
			<li><tt>'data'</tt> - file contents, will be an empty string if @c 'path'
			    is the full path to a copy
		</ul>
	 */
	public function getAttachment($studyUid, $seq);


	/** @brief Remove an attachment.

		@param string $studyUid  Primary key of the study record
		@param string $noteId    Primary key of the report record
		@param string $seq       Primary key of the attachment record

		@return array, see collectAttachments()

		Removes an associated file (if the attachment was stored as a file, not
		directly into the database). Then removes the attachment record.

		At the end calls collectAttachments() and returns its return value.
	 */
	public function deleteAttachment($studyUid, $noteId, $seq);
}
