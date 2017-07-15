<?php
	/* identifies the accompanying PACS */
	$pacs = "PacsOne";

	/* identifies the accompanying Database Management System */
	$dbms = "MySQL";

	/* host name (or IP address) of the database server */
	$db_host = "localhost";

	/* directory with PacsOne's *.ini files

		MedDream reads Database= statements from these files and therefore
		supports "Multiple Server Instance" installations automatically.
		Another file, php\sharedData.php, is also expected below this
		directory; after reading PacsOne version from there, behavior will
		adjust accordingly.

		Relative names should work without problems (for example, ".").

		Empty string is equivalent to "..\.." (two parent directories
		skipped).
	 */
	$login_form_db = "";

	/* absolute web path to the reports application used by Flash Viewer

		The application opens as CURRENT_HOST_NAME$medreport_root_link?QUERY. Relative
		paths will not work.

		Typical value for HTML reports: "/pacsone/meddream/md5/reports.html" (if
		MedDream is available at "/pacsone/meddream/")

		Typical value for MedReport: "/medreport/home.php"
	 */
	$medreport_root_link = "/pacsone/meddream/md5/reports.html";

	/* base URL to view HIS-hosted reports

		See quick_install-HIS_integration.txt for details.

		If not empty, the Reporting module (or MR due to $medreport_root_link)
		can't be used as the same button will open a HIS report instead.
	 */
	$his_report_link = "";

	/* images with these SOP Classes (delimited by semicolons) will not be shown

		WARNING: by default PacsOne does not have an index on image.sopclass.
		It is strongly recommended to add the index before using this parameter,
		or else the studies may open significantly slower, especially if the
		database is large.

		Typical value: "1.2.840.10008.5.1.4.1.1.66;1.2.840.10008.5.1.4.1.1.4.2;1.3.46.670589.2.5.1.1;1.3.12.2.1107.5.9.1"
	 */
	$sop_class_blacklist = "";

	/* store attachments here instead of the database

		Equivalent to PacsOne's setting "Store uploaded attachment under the above Upload Directory".
		Empty = store in the database.

		Typical value: "D:\\DICOM\\attached"
	 */
	$attachment_upload_dir = "";

	/* additional user who has access to Settings button, Register function, etc */
	$admin_username = "";

	/* Application Entity Title, Address and Port of the local PACS
	   (used to save measurement results via DICOM C-STORE)

		Basic syntax: "AET@HOST:PORT|DESCRIPTION"

		DESCRIPTION can contain anything but must end with "- local"
		(this string exactly). Note that contents of this parameter
		are not visible in the Forward dialog; the latter still gets
		its contents from PacsOne's database.
	 */
	$forward_aets = "";

	/* override MedDream's own Application Entity Title
	   (when using $forward_aets)

		Empty string is equivalent to the default value, "MEDDREAM".
	 */
	$local_aet = "";

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
	$archive_dir_prefix = "";
	$pacs_login_user = "";
	$pacs_login_password = "";
	$dcm4che_recv_aet = "";
	$mdpacs_dir = "";
	$m3d_link = "";
	$m3d_link_2 = "";
	$report_text_right_align = false;
	$pacs_gateway_addr = "";
?>
