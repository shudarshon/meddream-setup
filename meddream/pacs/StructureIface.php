<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Access to various parts of study structure.

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.

	@note This documentation often mentions "primary key in the images table".
	      In DCM4CHEE this "images table" is actually @c 'files', @c 'file_ref'
	      or @c 'location'.

	@todo Current implementations contain identical seriesIsVideoQuality(); it
	      is possible to move this code to the upper namespace and keep in
	      <tt>..\\..</tt>. Similarly with excludeVideoQualitySeries(). It exhibits
	      differently named array keys, therefore one can either pass key names
	      via parameters or unify keys themselves by using aliases in SQL queries.
 */
interface StructureIface extends Configurable, CommonDataImporter
{
	/** @brief Metadata for a single image.

		@param string $instanceUid     Primary key for the images table. __This is not necessarily a UID.__
		@param bool   $includePatient  Include Patient Name in results. Otherwise the four
		                               corresponding fields will always be empty strings.

		@return array

		Elements of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'path'</tt> - full path to the file
			<li><tt>'xfersyntax'</tt> - Transfer Syntax UID (if supported by %PACS)
			<li><tt>'sopclass'</tt> - SOP Class UID (if supported by %PACS)
			<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
			<li><tt>'patientid'</tt> - Patient ID (if supported by %PACS; __absent if <tt>!$includePatient</tt>__)
			<li><tt>'firstname'</tt> - first name of the patient (if supported by %PACS; __absent if <tt>!$includePatient</tt>__)
			<li><tt>'lastname'</tt> - last name of the patient (if supported by %PACS; __absent if <tt>!$includePatient</tt>__)
			<li><tt>'fullname'</tt> - first and last names combined (if supported by %PACS; __absent if <tt>!$includePatient</tt>__)
			<li><tt>'uid'</tt> - cleaned up version of @p $instanceUid (filesystem friendly).
			    Required in flv.php, imagejpeg.php, getThumbnail.php.
		</ul>

		Unsupported values will be empty strings. Bits Stored defaults to 8.

		@note The underlying instance of AuthDB must be already connected. Failure to
		      ensure that will yield database errors.
		@note In legacy code, Constants::FOR_WORKSTATION was used instead of @p $includePatient.
		      __When adapting for MW/OW/etc, please use <tt>(..., true)</tt> in the caller__.
	 */
	public function instanceGetMetadata($instanceUid, $includePatient = false);


	/** @brief %Study of a single image.

		@param string $instanceUid  Value of primary key in the images table. __This is not
		              necessarily a UID.__

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'studyuid'</tt> - primary key for the studies table. <b>@c null if
			    not found.</b>
		</ul>
	 */
	public function instanceGetStudy($instanceUid);


	/** @brief Convert instance UID to a corresponding primary key in images table

		@param string $instanceUid  Value of SOP Instance UID

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'imagepk'</tt> - primary key for the images table. <b>@c null
			    if @p $instanceUid was not found.</b>
		</ul>

		In PACSes where UIDs are primary keys, will immediately return @p $instanceUid
		in @c 'imagepk'.
	 */
	public function instanceUidToKey($instanceUid);


	/** @brief Convert instance key in images table to a corresponding UID

		@param string $instanceKey  Primary key in the images table

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'imageuid'</tt> - value of SOP Instance UID. <b>@c null if @p $instanceKey
			    was not found.</b>
		</ul>

		In PACSes where UIDs are primary keys, will immediately return @p $instanceKey
		in @c 'imageuid'.
	 */
	public function instanceKeyToUid($instanceKey);


	/** @brief Metadata for a single series.

		@param string $seriesUid  Primary key for the series table. __This is not necessarily a UID.__

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'count'</tt> - number of image subarrays
			<li><tt>'firstname'</tt> - first name of the patient (if supported by %PACS)
			<li><tt>'lastname'</tt> - last name of the patient (if supported by %PACS)
			<li><tt>'fullname'</tt> - raw Patient Name with @c ^ separators (if supported by %PACS)
			<li><tt>'image-000000'</tt>...<tt>'image-NNNNNN'</tt> - image subarrays

				Format of an image subarray:

				<ul>
					<li><tt>'path'</tt> - full path to the file
					<li><tt>'xfersyntax'</tt> - Transfer Syntax UID (if supported by %PACS)
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
				</ul>
		</ul>

		@note The underlying instance of AuthDB must be already connected. Failure to
		      ensure that will yield database errors.
	 */
	public function seriesGetMetadata($seriesUid);


	/** @brief Convert series UID to a corresponding primary key in series table

		@param string $seriesUid  Value of DICOM Series UID, (0020,000e)

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'seriespk'</tt> - primary key for the series table. <b>@c null if
			    $seriesUid was not found.</b>
		</ul>

		In PACSes where UIDs are primary keys, will immediately return $seriesUid
		in @c 'seriespk'.
	 */
	public function seriesUidToKey($seriesUid);


	/** @brief Return metadata of a study.

		@param string $studyUid       Primary key for the study table. __This is not necessarily
		                              a UID.__
		@param bool   $disableFilter  Do not exclude series with modality 'PR' or 'KO'
		@param bool   $fromCache      Look into the cache and return series/image UIDs only

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'count'</tt> - number of series subarrays
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'lastname'</tt> - patient's last name
			<li><tt>'firstname'</tt> - patient's first name
			<li><tt>'uid'</tt> - primary key for studies table (__not necessarily %Study Instance UID__;
			        duplicates @p $studyUid)
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'sourceae'</tt> - Source AE Title (if supported by %PACS)
			<li><tt>'studydate'</tt> - %Study Date
			<li><tt>'studytime'</tt> - %Study Time
			<li><tt>'notes'</tt> - 0: no report for this study, 1: report exists, 2: reports unsupported
			<li>@c 0 ... @c N (numeric keys) - a series subarray

			Format of a series subarray:

			<ul>
				<li><tt>'count'</tt> - number of image subarrays
				<li>@c 0 ... @c M (numeric keys) - an image subarray
				<li><tt>'id'</tt> - primary key for series table (__not necessarily Series Instance UID__)
				<li><tt>'description'</tt> - Series Description
				<li><tt>'modality'</tt> - Modality

				Format of an image subarray:

				<ul>
					<li><tt>'id'</tt> - primary key for images table (__not necessarily SOP Instance UID__)
					<li><tt>'numframes'</tt> - Number Of Frames (if supported by %PACS)
					<li><tt>'path'</tt> - full path to the DICOM file
					<li><tt>'xfersyntax'</tt> - Transfer Syntax UID (if supported by %PACS)
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
					<li><tt>'sopclass'</tt> - SOP Class UID (if supported by %PACS)
				</ul>
			</ul>
		</ul>

		Unsupported values are empty strings.
	 */
	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false);


	/** @brief Return a part of study with only specified series present.

		@param array $seriesUids     Array with primary keys for the series table. __These are not
		                             necessarily UIDs.__
		@param bool  $disableFilter  Do not exclude series with modality 'PR' or 'KO'
		@param bool  $fromCache      Look into the cache and return series/image UIDs only

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'count'</tt> - number of series subarrays
			<li><tt>'error'</tt> - an error message, empty if success
			<li>@c 0 ... @c N (numeric keys) - a series subarray
			<li><tt>'uid'</tt> - primary key for studies table (__not necessarily %Study Instance UID__)
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'lastname'</tt> - patient's last name
			<li><tt>'firstname'</tt> - patient's first name
			<li><tt>'sourceae'</tt> - Source AE Title (if supported by %PACS)
			<li><tt>'studydate'</tt> - %Study Date
			<li><tt>'notes'</tt> - 0: no report for this study, 1: report exists, 2: reports unsupported

			Format of a series subarray:

			<ul>
				<li><tt>'id'</tt> - primary key for series table (__not necessarily Series Instance UID__)
				<li><tt>'description'</tt> - Series Description
				<li><tt>'modality'</tt> - Modality
				<li><tt>'count'</tt> - number of image subarrays
				<li>@c 0 ... @c M (numeric keys) - an image subarray

				Format of an image subarray:

				<ul>
					<li><tt>'id'</tt> - primary key for images table (__not necessarily SOP Instance UID__)
					<li><tt>'path'</tt> - full path to the DICOM file
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
					<li><tt>'xfersyntax'</tt> - Transfer Syntax UID (if supported by %PACS)
					<li><tt>'numframes'</tt> - Number Of Frames (if supported by %PACS)
				</ul>
			</ul>
		</ul>

		There might be multiple series subarrays if @p $seriesUids contains more than one key.

		Unsupported values are empty strings.
	 */
	public function studyGetMetadataBySeries($seriesUids, $disableFilter = false, $fromCache = false);


	/** @brief Return a part of study with only specified images present.

		@param array $imageUids      Array with primary keys for the images table. __These are not
		                             necessarily UIDs.__
		@param bool  $disableFilter  Do not exclude series with modality 'PR' or 'KO'
		@param bool  $fromCache      Look into the cache and return series/image UIDs only

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'count'</tt> - number of series subarrays
			<li><tt>'error'</tt> - an error message, empty if success
			<li>@c 0 ... @c N (numeric keys) - a series subarray
			<li><tt>'uid'</tt> - primary key for studies table (__not necessarily %Study Instance UID__)
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'lastname'</tt> - patient's last name
			<li><tt>'firstname'</tt> - patient's first name
			<li><tt>'sourceae'</tt> - Source AE Title (if supported by %PACS)
			<li><tt>'notes'</tt> - 0: no report for this study, 1: report exists, 2: reports unsupported

			Format of a series subarray:

			<ul>
				<li><tt>'id'</tt> - primary key for series table (__not necessarily Series Instance UID__)
				<li><tt>'modality'</tt> - Modality
				<li><tt>'description'</tt> - Series Description
				<li>@c 0 ... @c M (numeric keys) - an image subarray
				<li><tt>'count'</tt> - number of image subarrays

				Format of an image subarray:

				<ul>
					<li><tt>'id'</tt> - primary key for images table (__not necessarily SOP Instance UID__)
					<li><tt>'path'</tt> - full path to the DICOM file
					<li><tt>'xfersyntax'</tt> - Transfer Syntax UID (if supported by %PACS)
					<li><tt>'numframes'</tt> - Number Of Frames (if supported by %PACS)
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
				</ul>
			</ul>
		</ul>

		There might be multiple series/images subarrays if @p $imageUids contains more than one key.

		Unsupported values are empty strings.
	 */
	public function studyGetMetadataByImage($imageUids, $disableFilter = false, $fromCache = false);


	/** @brief List series of given study.

		@param string $studyUid  Primary key in a studies table

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'count'</tt> - number of series
			<li>@c 0 ... @c N - primary key in a series table
		</ul>
	 */
	public function studyListSeries($studyUid);


	/** @brief Indicate if the study has an associated report.

		@param string $studyUid  Primary key in a studies table

		@return array  <tt>('error' => ERROR_MESSAGE, 'notes' => INDICATOR)</tt>

		Possible values of @c INDICATOR are 2 (unknown / reports unsupported), 1 (report present)
		and 0 (report absent).
	 */
	public function studyHasReport($studyUid);


	/** @brief Collect video quality-related images.

		@param string $imageUid  Value of primary key in the images table

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'quality'</tt> - @c VIDEO_QUALITIES subarray

			Format of the @c VIDEO_QUALITIES subarray:

			<ul>
				<li>@c 0 ... @c N - @c DISTINCT_QUALITY subarray

				Format of the @c DISTINCT_QUALITY subarray:

				<ul>
					<li><tt>'quality'</tt> - resolution code (@c 'Original', @c '1080P', ... @c '360P')
					<li><tt>'imageid'</tt> - primary key in the images table
				</ul>
			</ul>
		</ul>

		This function is called on the image with original quality, and the returned
		array will contain entries for corresponding images of lower quality.

		For studies converted by VideoRouter. Helps the GUI to select lower-quality
		videos by a dedicated control. Those videos are hidden from the study structure
		due to another reason: StructureIface::studyGetMetadata() calls a locally
		implemented excludeVideoQualitySeries() that filters them out.
	 */
	public function collectRelatedVideoQualities($imageUid);
}
