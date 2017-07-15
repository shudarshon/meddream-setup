<?php
	/* This pseudo-PACS is the "PACS GW" component of the Java-based backend.

		EXPERIMENTAL. Do not use on production installations.

		Depending on its configuration, the component may communicate with any
		supported PACS. That is, we are accessing a particular PACS not through
		legacy implementations in ./pacs/* but through a different backend.
	 */
	$pacs = "GW";

	/* base URL for the HTTP endpoints */
	$pacs_gateway_addr = "http://localhost:8000/";

	/* additional user who has access to Settings button, Register function, etc */
	$admin_username = "";

	/* PGW configuration "type: qr": caller's connection string for dcmqr

		Usually it's enough to specify the AE Title. Some setups might require
		address and port in form of "AET@HOST:PORT"; of course the port must not be
		occupied by other processes.

		Note that most target PACSes must be configured to accept a particular caller.
	 */
	$db_host = "MEDDREAM";

	/* PGW configuration "type: qr": connection string for the C-MOVE SCP

		Format: "AET@HOST:PORT".

	   Other configurations: database name for the login form, usually not shown.
	 */
	$login_form_db = "DCM4CHEE@127.0.0.1:11112|PACS";

	/* PGW configuration "type: qr": AE Title of PGW's own C-MOVE listener */
	$dcm4che_recv_aet = "MEDDREAM";

	/* Application Entity Title, Address and Port of the local PACS
	   (used to save measurement results via DICOM C-STORE)

		Basic syntax: "AET@HOST:PORT|DESCRIPTION"

		DESCRIPTION can contain anything but must end with "- local"
		(this string exactly). Note that contents of this parameter
		are not visible in the Forward dialog; the latter still gets
		its contents from PacsOne's database.
	 */
	$forward_aets = "PACS2@127.0.0.1:104|- local";

	/* override MedDream's own Application Entity Title
	   (when using $forward_aets)

		Empty string is equivalent to the default value, "MEDDREAM".
	 */
	$local_aet = "DCMSND";

	/* Multiple links in MedDream context menu

		Basic syntax: "linkname|url|window|restriction\nlinknameN..."

		(\n works only inside double quotes!)

		url:
			{series} will be replaced with Series Instance UID
			{study} will be replaced with Study Instance UID
			{patientID} will be replaced with Patient ID
			{accessionNo} will be replaced with Accession Number
			{examDate} will be replaced with Study Date (YYYY-MM-DD)
			{image} will be replaced with SOP Instance UID

		window:
			_self - open link in the same window
			_blank - open link in a new window (new tab)
			(empty value) - equivalent to _self

		restriction:
			image - display the link for still images only (exclude video, multiframe, SR, PDF, ECG)
			(empty value) - display the link for any object

		Example:
			$m3d_link_3 = "mylink|http://www.testpage.com?uid={series}|_blank|image";
	*/
	$m3d_link_3 = "";

	/* Languages that are enabled.

		Example: "en,de,fr,fi"
	 */
	$languages = "en,lt,ru,de,fi,fr";

	/* default login credentials (for demonstration purposes etc)

		Example:
			$demo_login_user = "u1";
			$demo_login_password = "u1";
	 */
	$demo_login_user = "u1";
	$demo_login_password = "u1";

	/* default character set in DICOM files and database

		The viewer needs UTF-8 and will convert other encodings to it using
		PHP's iconv library.

		For best results from "DICOM Tags" function and SR viewer, the DICOM
		file must contain the (0008,0005) Specific Character Set attribute
		with a correct value. See
		http://dicom.nema.org/medical/dicom/current/output/chtml/part03/sect_C.12.html#sect_C.12.1.1.2 .
		If the attribute is missing, then this parameter is used instead.
		If, in turn, this parameter is empty as well, 'ISO-IR 6' is assumed.

		For best results from database-related encoding conversions (e.g.,
		output from Search function), make sure the database encoding is
		compatible with the one used by the PACS when saving metadata to
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
	$mdpacs_dir = "";
	$m3d_link = "";
	$m3d_link_2 = "";
	$report_text_right_align = false;
	$medreport_root_link = "";
	$his_report_link = "";
	$sop_class_blacklist = "";
	$attachment_upload_dir = "";
?>
