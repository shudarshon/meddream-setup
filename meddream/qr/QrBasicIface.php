<?php

/** @brief Unified API related to DICOM Query/Retrieve. */
namespace Softneta\MedDream\Core\QueryRetrieve;

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\CharacterSet;


/** @brief Mandatory methods (basic/blocking). */
interface QrBasicIface
{
	/** @brief A minimal constructor.

		@param Logging      $log                     An instance of Logging
		@param CharacterSet $cs                      An instance of CharacterSet
		@param int          $retrieveEntireStudy     0 (old behavior of <tt>$pacs='DICOM'</tt>) or 1
		@param string       $remoteConnectionString  AET, host and port of the remote C-FIND/C-MOVE SCP
		@param string       $localConnectionString   AET, host and port of the local C-STORE SCP
		@param string       $localAet                AET (or the entire connection string) of the local C-FIND/C-MOVE SCU
		@param string       $wadoBaseAddr            Base URL of the remote WADO service
	 */
	public function __construct(Logging $log, CharacterSet $cs, $retrieveEntireStudy,
		$remoteConnectionString, $localConnectionString, $localAet, $wadoBaseAddr);


	/** @brief Search for studies.

		@param string $modality  Modality. Multiple values separated by <tt>'\\'</tt>.

		Format of @p $dateFrom and @p $dateTo shall be "YYYY.MM.DD". The implementation
		will adjust the separators if needed.

		@return array Numerically indexed

		Each subarray of the return value consists of:

		<ul>
			<li><tt>'uid'</tt> - %Study Instance UID
			<li><tt>'id'</tt> - %Study ID
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'patientname'</tt> - Patient Name
			<li><tt>'patientbirthdate'</tt> - always empty string
			<li><tt>'modality'</tt> - Modalities In %Study
			<li><tt>'description'</tt> - %Study Description
			<li><tt>'date'</tt> - %Study Date
			<li><tt>'time'</tt> - %Study Time
			<li><tt>'datetime'</tt> - %Study Date + <tt>' '</tt> + %Study Time
			<li><tt>'notes'</tt> - report presence indicator, always @c 2
			<li><tt>'reviewed'</tt> - always empty string
			<li><tt>'accessionnum'</tt> - Accession Number
			<li><tt>'referringphysician'</tt> - Referring Physician (not supported by some PACSes)
			<li><tt>'readingphysician'</tt> - always empty string
			<li><tt>'sourceae'</tt> - always empty string
			<li><tt>'received'</tt> - always empty string
		</ul>

		Parameters that are non-empty strings are used as search keys.

		Some attributes might not be supported by the %PACS, in that case values will
		be empty strings.

		Limiting the number of returned entries is not supported by DcmQR. Take care not
		to specify a too broad query.

		Results are in original order as returned by the SCP (no additional sorting).
	 */
	public function findStudies($patientId, $patientName, $studyId, $accNum, $studyDesc, $refPhys,
		$dateFrom, $dateTo, $modality);


	/** @brief Return metadata (up to IMAGE level) of specified study.

		@param string $studyUid   %Study Instance UID
		@param bool   $fromCache  Scan the cache only, do not connect to the SCP

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'count'</tt> - number of series subarrays
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'lastname'</tt> - always empty string
			<li><tt>'firstname'</tt> - original value of Patient Name attribute
			<li><tt>'uid'</tt> - %Study Instance UID (duplicates @p $studyUid)
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'sourceae'</tt> - always empty string
			<li><tt>'studydate'</tt> - always empty string
			<li><tt>'studytime'</tt> - always empty string
			<li><tt>'notes'</tt> - always @c 2
			<li>@c 0 ... @c N (numeric keys) - a series subarray

			Format of a series subarray:

			<ul>
				<li><tt>'count'</tt> - number of image subarrays
				<li>@c 0 ... @c M (numeric keys) - an image subarray
				<li><tt>'id'</tt> - Series Instance UID + @c '*' + %Study Instance UID
				<li><tt>'description'</tt> - Series Description
				<li><tt>'modality'</tt> - Modality
				<li><tt>'seriesno'</tt> - Series Number (if supported by %PACS; used for subsequent sorting)

				Format of an image subarray:

				<ul>
					<li><tt>'id'</tt> - SOP Instance UID + @c '*' + Series Instance UID + @c '*' + %Study Instance UID
					<li><tt>'numframes'</tt> - Number Of Frames (if supported by %PACS, otherwise @c 0)
					<li><tt>'path'</tt> - always empty string
					<li><tt>'xfersyntax'</tt> - always empty string
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS, otherwise @c 0)
					<li><tt>'sopclass'</tt> - always empty string
					<li><tt>'instanceno'</tt> - Instance Number (if supported by %PACS; used for subsequent sorting)
					<li><tt>'acqno'</tt> - Acquisition Number (if supported by %PACS; used for subsequent sorting)
				</ul>
			</ul>
		</ul>

		Unsupported values are empty strings.
	 */
	public function studyGetMetadata($studyUid, $fromCache = false);


	/** @brief Return metadata (up to IMAGE level) of specified series.

		@param string $seriesUid  Series Instance UID

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'count'</tt> - number of image subarrays
			<li><tt>'firstname'</tt> - always empty string
			<li><tt>'lastname'</tt> - always empty string
			<li><tt>'fullname'</tt> - always empty string
			<li><tt>'image-000000'</tt>...<tt>'image-NNNNNN'</tt> - image subarrays

				Format of an image subarray:

				<ul>
					<li><tt>'studyid'</tt> - %Study Instance UID
					<li><tt>'seriesid'</tt> - Series Instance UID
					<li><tt>'imageid'</tt> - SOP Instance UID
					<li><tt>'path'</tt> - full path to a cached file (__empty string if cache not used__)
					<li><tt>'xfersyntax'</tt> - always empty string
					<li><tt>'numframes'</tt> - always empty string
					<li><tt>'sopclass'</tt> - always empty string
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
				</ul>
		</ul>

		Unsupported values will be empty strings.

		@note The function decides if it will scan the cache (RetrieveEntireStudy mode on) or
		      connect to the SCP (RetrieveEntireStudy mode off).
	 */
	public function seriesGetMetadata($seriesUid);


	/** @brief Retrieve a DICOM image via C-MOVE given its UIDs.

		@param string $imageUid   SOP Instance UID
		@param string $seriesUid  Series Instance UID
		@param string $studyUid   %Study Instance UID

		@return array

		Elements of the returned value:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'path'</tt> - path to the downloaded image
		</ul>

		In RetrieveEntireStudy mode (on by default for <tt>$pacs='DICOM'</tt>,
		though can be turned off), expects that file already exists in the
		hierarchical cache and takes it from there.

		When RetrieveEntireStudy mode is off, attempts to find the file in
		the memory index of the flat (older) cache, then requests a C-MOVE
		of the specified image.
	 */
	public function fetchImage($imageUid, $seriesUid, $studyUid);


	/** @brief Retrieve a DICOM image via WADO given its UIDs.

		@param string $imageUid   SOP Instance UID
		@param string $seriesUid  Series Instance UID
		@param string $studyUid   %Study Instance UID

		@return array

		Elements of the returned value:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'path'</tt> - path to the downloaded image
		</ul>

		The file is always downloaded anew, there is no caching.

		@note The current architecture makes it quite easy to store the downloaded
		      file in the same hierarchical cache, and renew based on modification
		      timestamp. However performance gain might be too small if the WADO
		      service is present on the same machine.
	 */
	public function fetchImageWado($imageUid, $seriesUid, $studyUid);
}
