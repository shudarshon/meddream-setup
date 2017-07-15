<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Support for DICOM/JPEG annotations.

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.
 */
interface AnnotationIface extends Configurable, CommonDataImporter
{
	/** @brief Update the value of Product Version for our UIDs.

		@param string $text  Version string

		@retval string  Error message (empty if success)

		Our UIDs contain a part of product version, and the latter is obtained
		at a higher level. Callers will need to pass Backend::$productVersion
		to this class.
	 */
	public function setProductVersion($text);


	/** @brief Returns a reason why annotations are not supported.

		@param bool $testVersion  Verify version of the %PACS for an additional reason

		@return string  Will be empty if annotations are supported

		Some PACSes do not accept Presentation State SOP Classes at all. Others do
		that depending on the version. __The implementation must detect the situation
		to avoid further errors that are harder to diagnose.__
	 */
	public function isSupported($testVersion = false);


	/** @brief Indicate whether annotations exist for the given study.

		@param string $studyUid  Value of primary key in the studies table

		@retval true   Annotation(s) present
		@retval false  Annotation(s) absent, or an error occurred

		Will also return @c false if the implementation is not initialized.
	 */
	public function isPresentForStudy($studyUid);


	/** @brief Collect information needed for an annotation.

		@param string $instanceUid  Value of primary key in the images table
		@param string $type         Either @c 'dicom' or @c 'jpg'

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'data'</tt> - @c COLLECTED_DATA subarray
		</ul>

		Format of the @c COLLECTED_DATA subarray:

		<ul>
			<li><tt>'date'</tt> - current date
			<li><tt>'time'</tt> - current time
			<li><tt>'seriesuid'</tt> - Series UID of the image
			<li><tt>'sopud'</tt> - SOP Class UID
			<li><tt>'instanceuid'</tt> - SOP Instance UID
			<li><tt>'currentmodality'</tt> - Modality
			<li><tt>'numcolumns'</tt> - Columns
			<li><tt>'numrows'</tt> - Rows
			<li><tt>'studyuuid'</tt> - %Study UID
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'studyid'</tt> - %Study ID
			<li><tt>'accessionnum'</tt> - Accession Number
			<li><tt>'birthdate'</tt> - Patient Birth Date
			<li><tt>'sex'</tt> - Patient's Sex
			<li><tt>'studydate'</tt> - %Study Date
			<li><tt>'studytime'</tt> - %Study Time
			<li><tt>'patientname'</tt> - Patient Name
			<li><tt>'referringphysician'</tt> - Referring Physician
			<li><tt>'seriesuuid'</tt> - Series UID of the annotation series
			<li><tt>'seriesnumber'</tt> - Series Number of the annotation series
			<li><tt>'instancenumber'</tt> - unoccupied Instance Number in the annotation series
			<li><tt>'sopinstance'</tt> - a new SOP Instance UID for an annotation object
		</ul>

		@c 'instancenumber' and @c 'sopinstance' always are generated values.

		@c 'seriesuuid' and @c 'seriesnumber' are generated if a suitable series does
		not exist. Otherwise values from that series are used.

		The annotation series is either a series with modality 'PR' or with Series
		Description equal to Constants::PR_SERIES_DESC. The latter is important
		for JPEG-style annotations as such a series will contain typical
		Secondary Capture images instead of Presentation State objects.
	 */
	public function collectStudyInfoForImage($instanceUid, $type = 'dicom');


	/** @brief Collect primary keys (in the images table) of objects in the annotation series.

		@param string $studyUid  Value of primary key in the studies table

		@return array

		Format of the array:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'seriesimagelist'</tt> - @c ANNOTATION_OBJECTS subarray
		</ul>

		Format of the @c ANNOTATION_OBJECTS subarray:

		@verbatim
	SERIES_PRIMARY_KEY_1...SERIES_PRIMARY_KEY_N => array(
		0...M => IMAGE_PRIMARY_KEY
	)
		@endverbatim
	 */
	public function collectPrSeriesImages($studyUid);
}
