<?php
	/* identifies the accompanying PACS

		Possible values: "dcm4chee-arc", "dcm4chee-arc-5"
	 */
	$pacs = "dcm4chee-arc-5";

	/* identifies the accompanying Database Management System

		Possible values: "MySQL", "OCI8"
	 */
	$dbms = "OCI8";

	/* host name (or IP address) of the database server

		MySQL	Host name or IP address

		OCI8	A connection string. Can be a name of a local Oracle instance,
			or a Connect Name from tnsnames.ora, or an Easy Connect string
			(if PHP is linked with corresponding Oracle client libraries).
	 */
	$db_host = "XE";

	/* database name which is offered in the login form

		OCI8	Ignored as the entire drop-down is hidden.
	 */
	$login_form_db = "";

	/* root directory of every storage device

		dcm4chee-arc-5  Root directories are configured in the LDAP tree via
			entries named "dcmStorageID". As LDAP access is not supported
			in MedDream for now, directories must be set up here, too. Format:
			"ID1=URI1\nID2=URI2\n...".

			Example: $archive_dir_prefix = "fs1=file:///mnt/NAS1\nfs2=file:///mnt/NAS2";

		dcm4chee-arc    Not used: root directories are stored in the database
	 */
	$archive_dir_prefix = "fs1=file:///DICOM";

	/* absolute web path to the reports application used by Flash Viewer

		The application opens as CURRENT_HOST_NAME$medreport_root_link?QUERY. Relative
		paths will not work.

		Typical value for HTML reports: "/meddream/md5/reports.html" (if MedDream is
		available at "/meddream/")

		Typical value for MedReport: "/medreport/home.php"
	 */
	$medreport_root_link = "/meddream/md5/reports.html";

	/* base URL to view HIS-hosted reports

		See quick_install-HIS_integration.txt for details.

		If not empty, the Reporting module can't be used as the same button
		will open a HIS report instead.
	 */
	$his_report_link = "";

	/* target Application Entity Titles for the Forward function

		Basic syntax: "AET@HOST:PORT|DESCRIPTION\nAET@..."
		(\n works only inside double quotes!)

		DESCRIPTION is optional. HOST and PORT won't be shown in the user
		interface.
	 */
	$forward_aets = "SOMEPACS@SOMEHOST:104|backup archive";

	/* local Application Entity Title for the Forward function */
	$local_aet = "MEDDREAM";

	/* store attachments here instead of the database

		By default attachments are stored in the database so the latter might
		become very large.
	 */
	$attachment_upload_dir = "";

	/* user who has access to Settings button, Register function, etc */
	$admin_username = "";

	/* Multiple links in MedDream context menu

		Basic syntax: "linkname|url|window|restriction\nlinknameN..."

		(\n works only inside double quotes!)

		url:
			{series} will be replaced with value of primary key in the series table
				(this is *not* the Series Instance UID)
			{study} will be replaced with value of primary key in the study table
				(this is *not* the Study Instance UID)
			{patientID} will be replaced with Patient ID
			{accessionNo} will be replaced with Accession Number
			{examDate} will be replaced with Study Date (YYYY-MM-DD)
			{image} will be replaced with value of primary key in the corresponding
				table (this is *not* the SOP Instance UID)

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
	$languages= "en,lt,ru";

	/* default login credentials (for demonstration purposes etc)

		Example:
			$demo_login_user = 'demo';
			$demo_login_password = 'demo';
	 */
	$demo_login_user = '';
	$demo_login_password = '';

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
	$dcm4che_recv_aet = "";
	$m3d_link = "";
	$m3d_link_2 = "";
	$pacs_login_user = "";
	$pacs_login_password = "";
	$report_text_right_align = false;
?>
