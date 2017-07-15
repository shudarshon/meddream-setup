<?php

namespace Softneta\MedDream\Core\Pacs;

use Softneta\MedDream\Core\Configurable;


/** @brief Fetching of objects not available immediately (like in <tt>$pacs='DICOM'</tt>).

	Configurable::configure() is reserved for any additional post-initialization
	tasks. There is no need to parse the configuration here: it was already parsed
	in PacsConfig::configure(), then arrived here via CommonDataImporter.
 */
interface PreloadIface extends Configurable, CommonDataImporter
{
	/** @brief Make a single DICOM file available for use.

		@param string $imageUid   SOP Instance UID
		@param string $seriesUid  Series Instance UID
		@param string $studyUid   %Study Instance UID

		@retval string  Path to the resulting file.
		@retval false   Something failed. The caller should not continue unless the
		                previous path to the file will likely be suitable.
		@retval null    This call was not applicable for the current %PACS. The caller
		                can use the previous path as is.

		This method is called by higher-level md-core code for all PACSes, in order to
		update the path to the DICOM file.

		<ul>
			<li>In PACSes where files are not immediately available, the path initially
			    isn't known at all and the caller will use the returned string for an
			    update. On the other hand, preloading of the entire study might still
			    be not supported (this method then returns @c null) so files will be
			    automatically fetched just before their use, which is slower.
			<li>In other PACSes where the path is always valid, this method returns
			    @c null, thereby indicating that the update isn't needed.
		</ul>

		@note @p $imageUid and @p $seriesUid are allowed to contain trailing
		      components delimited by <tt>'*'</tt>. These will be ignored.
	 */
	public function fetchInstance($imageUid, $seriesUid, $studyUid);


	/** @brief Make all files of a single series available for use, then sort images.

		@param array $seriesStruct  Structure that will be updated in-place

		@return string  Error message (empty if success)

		Format of @p $seriesStruct:

		<ul>
			<li><tt>'image-000000'</tt> ... <tt>'image-NNNNNN'</tt> - @c SINGLE_IMAGE subarray

			Format of @c SINGLE_IMAGE subarray:

			<ul>
				<li><tt>'path'</tt> - (string, __required__) full path to the DICOM file.
				<li><tt>'xfersyntax'</tt> -- (string) Transfer Syntax UID
				<li><tt>'bitsstored'</tt> -- (string) Bits Stored
				<li><tt>'seriesno'</tt> -- (string) Series Number
				<li><tt>'instanceno'</tt> -- (string) Instance Number
			</ul>

			Only <tt>'path'</tt> is required on input. Other elements (not only the ones
			listed above) might be created or updated with data extracted from the  DICOM
			file.
		</ul>
	 */
	public function fetchAndSortSeries(array &$seriesStruct);


	/** @brief Make all files of a single study available for use, then sort series and images.

		@param array $studyStruct  Return value of StructureIface::studyGetMetadata()

		@return string  Error message (empty if success)
	 */
	public function fetchAndSortStudy(array &$studyStruct);


	/** @brief Make all files of multiple studies available for use, then sort series and images.

		@param array $studiesStruct  Structure that will be updated in-place

		@return string  Error message (empty if success)

		Format of @p $studiesStruct:

		<ul>
			<li><tt>'study-000000'</tt> ... <tt>'study-NNNNNN'</tt> - @c SINGLE_STUDY subarray

			Format of @c SINGLE_STUDY subarray:

			<ul>
				<li><tt>'series-000000'</tt> ... <tt>'series-MMMMMM'</tt> - @c SINGLE_SERIES subarray

				Format of @c SINGLE_SERIES subarray:

				<ul>
					<li><tt>'image-000000'</tt> ... <tt>'image-PPPPPP'</tt> - @c SINGLE_IMAGE subarray

					Format of @c SINGLE_IMAGE subarray:

					<ul>
						<li><tt>'study'</tt> - (string, __do not touch__) value of primary key in the studies table
						    (not necessarily the %Study Instance UID)
						<li><tt>'series'</tt> - (string, __do not touch__) value of primary key in the series table
						    (not necessarily the Series Instance UID)
						<li><tt>'image'</tt> - (string, __do not touch__) value of primary key in the images table
						    (not necessarily the SOP Instance UID)
						<li><tt>'path'</tt> - (string, __required__) full path to the DICOM file.
						<li><tt>'xfersyntax'</tt> -- (string) Transfer Syntax UID
						<li><tt>'bitsstored'</tt> -- (string) Bits Stored
						<li><tt>'seriesno'</tt> -- (string) Series Number
						<li><tt>'instanceno'</tt> -- (string) Instance Number
					</ul>

					Only <tt>'path'</tt> is required on input, and <tt>'study'</tt> / <tt>'series'</tt> /
					<tt>'image'</tt> must remain unchanged. Remaining elements (and even new ones) might
					be created or updated with data from the DICOM file.
				</ul>
			</ul>
		</ul>
	 */
	public function fetchAndSortStudies(array &$studiesStruct);


	/** @brief Remove the temporary file that was downloaded by fetchInstance() etc.

		@param string $path  Full path to the file to be removed

		@return string  Error message (empty if success)

		In PACSes where fetchInstance() and related methods create temporary files
		that wouldn't be deleted otherwise, is equivalent to unlink(). In other
		PACSes it does nothing.

		@warning In database-based PACSes this method is effectively called with the
		         path to the original file. We obviously don't want to remove these. The
		         default implementation does nothing and is therefore safe.
	 */
	public function removeFetchedFile($path);
}
