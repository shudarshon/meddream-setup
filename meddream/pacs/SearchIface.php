<?php

namespace Softneta\MedDream\Core\Pacs;


/** @brief Search for studies. */
interface SearchIface extends CommonDataImporter
{
	/** @brief Return number of studies which dates fall into certain intervals.

		@return array

		Format of the array:

		<ul>
			<li><tt>'d1'</tt> - number of studies on this day
			<li><tt>'d3'</tt> - number of studies on last three days
			<li><tt>'w1'</tt> - number of studies on last seven days
			<li><tt>'m1'</tt> - number of studies not older than one month ago
			<li><tt>'y1'</tt> - number of studies not older than one year ago
			<li><tt>'any'</tt> - total number of studies
		</ul>

		The default value is all zeroes, and is returned in case of error, not implemented
		function, etc.
	 */
	public function getStudyCounts();


	/** @brief Search for studies.

		@param array  $actions         Patient ID filter (see below)
		@param array  $searchCriteria  Search criteria (see below)
		@param string $fromDate        FROM date of the search interval
		@param string $toDate          TO date of the search interval
		@param string $mod             Modalities (see below)
		@param string $listMax         Limit of number of returned values. Zero means "no limit".

		Format of @p $actions that is recognized and makes a difference:

		@verbatim
array(
	'action' => 'show',
	'options' => 'patient',
	'entry' => array(
		0 => Patient_ID_for_the_filter
	)
);
		@endverbatim

		This adds a filter on Patient ID. Note that @p $searchCriteria can also contain
		<tt>array('name' => 'patientid', 'text' => Patient_ID_for_the_filter)</tt>,
		however an identical value makes no sense and a different value will definitely
		find nothing.

		Format of @p $searchCriteria:

		@verbatim
array(
	0...N => array(
		'name' => <criterion name>,
		'text' => <criterion value>
	)
);
		@endverbatim

		Format of @p $mod:

		@verbatim
array(
	0...N => array(
		'name' => <modality name>,
		'selected' => true|false,
		'custom' => anything		// only presence is checked, value isn't important
	)
);
		@endverbatim

		If the @c 'custom' attribute is missing for all modalities, and @c 'selected'
		is identical everywhere, then no modality filter is added. This is a leftover
		from Flash-based search that always sent a fixed array of modalities, and
		expected no modality filter when all modality checkboxes are checked.

		Recognized names (not necessarily supported on all PACSes) are:

		<ul>
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'patientname'</tt> - Patient Name
			<li><tt>'id'</tt> - %Study ID
			<li><tt>'accessionnum'</tt> - Accession Number
			<li><tt>'description'</tt> - %Study Description
			<li><tt>'referringphysician'</tt> - Referring Physician's Name
			<li><tt>'readingphysician'</tt> - Name of Physician(s) Reading %Study
		</ul>

		Format of @p $fromDate and @p $toDate shall be "YYYY.MM.DD". If the underlying
		DBMS etc can't handle these separators, the implementation must adjust them
		as needed.

		@return array Numerically indexed

		Each subarray of the return value consists of:

		<ul>
			<li><tt>'uid'</tt> - value of primary key in the studies table
			<li><tt>'id'</tt> - %Study ID
			<li><tt>'patientid'</tt> - Patient ID
			<li><tt>'patientname'</tt> - Patient Name
			<li><tt>'patientbirthdate'</tt> - Patient Birth Date
			<li><tt>'modality'</tt> - Modalities In %Study, or at least Modality
			<li><tt>'description'</tt> - %Study Description
			<li><tt>'date'</tt> - %Study Date (not supported by some PACSes)
			<li><tt>'time'</tt> - %Study Time (not supported by some PACSes)
			<li><tt>'datetime'</tt> - %Study Date + <tt>' '</tt> + %Study Time
			<li><tt>'notes'</tt> - report presence indicator (2: unknown, 1: present, 0: absent)
			<li><tt>'reviewed'</tt> - login of the user who first opened the study (not supported
			    by some PACSes)
			<li><tt>'accessionnum'</tt> - Accession Number
			<li><tt>'referringphysician'</tt> - Referring Physician (not supported by some PACSes)
			<li><tt>'readingphysician'</tt> - Reading Physician (not supported by some PACSes)
			<li><tt>'sourceae'</tt> - Source AE (not supported by some PACSes)
			<li><tt>'received'</tt> - receive timestamp (not supported by some PACSes)
		</ul>

		%Study Date and %Study Time (as separate entities) might not be supported, however
		@c 'datetime' must be supported by every %PACS implementation.
	 */
	public function findStudies($actions, $searchCriteria, $fromDate, $toDate, $mod, $listMax);
}
