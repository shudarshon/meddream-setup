<?php
	/* The "DICOM" pseudo-PACS uses dcmqr for queries and dcmrcv as a C-MOVE listener */
	$pacs = "DICOM";

	/* caller's connection string for dcmqr to identify itself

		Usually it's enough to specify the AE Title. Some setups might require
		address and port in form of "AET@HOST:PORT"; of course the port must not be
		occupied by other processes.

		Note that most target PACSes must be configured to accept a particular caller.
	 */
	$db_host = "MEDDREAM";

	/* database name which is offered in the login form

		Actually this is again a connection string in form of "AET@HOST:PORT"
		(or "AET@HOST:PORT|ALIAS") that identifies the target PACS.

		The alias is optional; it will be shown in the login form so that address/port
		is not revealed for an average user. Note, however, that since 5.0 MedDream
		does not display the "database" drop-down in the form unless it contains
		multiple entries.
	 */
	$login_form_db = "DCM4CHEE@127.0.0.1:11112|PACS";

	/* connection string ("AET@HOST:PORT") for the dcmrcv utility */
	$dcm4che_recv_aet = "MEDDREAM@127.0.0.1:11116";

	/* target Application Entity Titles for the Forward function

		Basic syntax: "AET@HOST:PORT|DESCRIPTION\nAET@..."
		(\n works only inside double quotes!)

		DESCRIPTION is optional. HOST and PORT won't be shown in the user
		interface.
	 */
	$forward_aets = "SOMEPACS@SOMEHOST:104|backup archive";

	/* local Application Entity Title for the Forward function */
	$local_aet = "MEDDREAM";

	/* allow searching by more than one modality

		Some PACSes, like DCM4CHEE 4.x/5.x or EpicCache, can only handle a single
		modality. Change this to false and MedDream will require a single modality,
		too.
	 */
	$multiple_modality_search = true;

	/* Languages that are enabled.

		Example: "en,de,fr,fi"
	 */
	$languages= "en,lt,ru";

	/* if study download has finished and THE SAME NUMBER of images (belonging
	   to the current study) is missing from cache for this amount of seconds,
	   then study download is started again
	*/
	$qr_repeat_send_timeout = 30;

	/* after a study is complete for this number of seconds, its structure
	   will be queried _from_the_PACS_ again to detect possible changes
	*/
	$qr_repeat_check_timeout = 300;

	/* how many seconds to wait before re-fetching the study structure
		_from_the_cache_ if AT LEAST ONE image is found there
	*/
	$qr_thumbnail_check_timeout = 0.5;

	/* how many seconds to wait before re-fetching the study structure
		_from_the_cache_ if NO IMAGES are found there
	*/
	$qr_empty_thumbnail_check_timeout = 1;

	/* default character set in DICOM files and from Q/R client

		The viewer needs UTF-8 and will convert other encodings to it using
		PHP's iconv library.

		For best results from "DICOM Tags" function and SR viewer, the DICOM
		file must contain the (0008,0005) Specific Character Set attribute
		with a correct value. See
		http://dicom.nema.org/medical/dicom/current/output/chtml/part03/sect_C.12.html#sect_C.12.1.1.2 .
		If the attribute is missing, then this parameter is used instead.
		If, in turn, this parameter is empty as well, 'ISO-IR 6' is assumed.

		For best results from Q/R-related encoding conversions (e.g.,
		output from Search function), make sure the actual encoding
		is compatible with the one used by the PACS when saving metadata to
		the database. Then update this parameter accordingly. Empty value
		defaults to Latin1.

		Typical values:
			'ISO_IR 6' - Default
			'ISO_IR 100' - Latin alphabet No. 1
			'ISO_IR 101' - Latin alphabet No. 2
			'ISO_IR 109' - Latin alphabet No. 3
			'ISO_IR 110' - Latin alphabet No. 4
			'ISO_IR 144' - Cyrillic
			'ISO_IR 127' - Arabic
			'ISO_IR 126' - Greek
			'ISO_IR 148' - Latin alphabet No. 5
			'ISO_IR 14' - Japanese
			'ISO_IR 87' - Japanese
			'ISO_IR 166' - Thai
			'ISO_IR 149' - Korean
			'ISO_IR 58' - Simplified Chinese
			'ISO_IR 192' - Unicode in UTF-8

		Other values like 'UTF-8', 'WINDOWS-1251', etc are also possible. See
		the documentation for iconv.

		NOTICE: in case of conversion failure, will retry with utf8_encode()
		that is more predictable but assumes Latin1. This yields something
		visible instead of an empty string.

		Example:
			$default_character_set = 'ISO_IR 58';
	 */
	$default_character_set = '';

	/* default character set for annotations

		For best results from annotation functions, the DICOM file being
		annotated must contain the (0008,0005) Specific Character Set
		attribute with a correct value. See
		http://dicom.nema.org/medical/dicom/current/output/chtml/part03/sect_C.12.html#sect_C.12.1.1.2 .
		If the attribute is missing, then this parameter is used instead.
		If, in turn, this parameter is empty as well, 'ISO_IR 6' is assumed.

		Typical values:
			'ISO_IR 6' - Default
			'ISO_IR 100' - Latin alphabet No. 1
			'ISO_IR 101' - Latin alphabet No. 2
			'ISO_IR 109' - Latin alphabet No. 3
			'ISO_IR 110' - Latin alphabet No. 4
			'ISO_IR 144' - Cyrillic
			'ISO_IR 127' - Arabic
			'ISO_IR 126' - Greek
			'ISO_IR 148' - Latin alphabet No. 5
			'ISO_IR 14' - Japanese
			'ISO_IR 87' - Japanese
			'ISO_IR 166' - Thai
			'ISO_IR 149' - Korean
			'ISO_IR 58' - Simplified Chinese
			'ISO_IR 192' - Unicode in UTF-8

		NOTICE: when $default_character_set is also configured, then
		$default_annotation_character_set should have the same value. If your
		PACS updates patient etc records with data from the annotation object
		and doesn't perform any character set conversions in the process, then
		these records might need a different $default_character_set afterwards
		for a correct display.

		Example:
			$default_annotation_character_set = 'ISO_IR 58';
	 */
	$default_annotation_character_set = '';

	/* Share to Dicom Library function: enable the "share" button in viewers */
	$dicomLibraryEnabled = false;

	/* default From: email address for the Share to Dicom Library function

		Example:
			$dicomLibrarySender = 'test@domain.com';
	*/
	$dicomLibrarySender = '';

	/* default Subject: text for emails from the Share to Dicom Library function

		Example:
			$dicomLibrarySubject = 'My institution name';
	*/
	$dicomLibrarySubject = '';

	/* how long should common temporary files be kept

		Use the relative format of strtotime(), that is, with keywords "day", "hour", "minute"
		etc, without the sign (the minus is added automatically where appropriate).
	 */
	$tmp_remove_after = '7 days';

	/* how long should more "critical" files (usually parsed studies and similar large files) be kept */
	$tmp_remove_after_crit = '1 day';

	/* not required in this configuration, please don't change */
	$dbms = "";
	$archive_dir_prefix = "";
	$pacs_login_user = "";
	$pacs_login_password = "";
	$demo_login_user = '';
	$demo_login_password = '';
	$medreport_root_link = "";
	$m3d_link = "";
	$m3d_link_2 = "";
	$admin_username = "";
	$pacs_gateway_addr = "";
?>
