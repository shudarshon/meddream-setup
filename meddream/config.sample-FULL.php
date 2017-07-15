<?php
	/* identifies the accompanying PACS

		Possible values: "PacsOne", "DCM4CHEE", "dcm4chee-arc", "dcm4chee-arc-5",
		"Conquest", "ClearCanvas", "DICOM", "WADO", "FileSystem"

		"DICOM" uses dcmqr for queries and dcmrcv as a C-MOVE listener.

		"WADO" is a link to the target PACS through the dcmqr utility
		(queries, DICOM protocol) and WADO interface (image retrieval,
		HTTP).

		"FileSystem" allows to view individual .dcm files via their partial
		or full paths.
	 */
	$pacs = "dcm4chee-arc-5";

	/* identifies the accompanying Database Management System

		Possible values: "MySQL", "MSSQL", "SQLSRV", "OCI8", "SQLite3"

		"SQLSRV" selects the "Microsoft SQL Server Driver for PHP"
		(php_sqlsrv.dll). "MSSQL" is the older php_mssql.dll extension
		unavailable since PHP 5.3.
	 */
	$dbms = "MySQL";

	/* prefix of the archive directory

		PacsOne		Ignored: names are always absolute

		DCM4CHEE	By default the server root is required, for example,
				"C:\\DCM4CHEE\\dcm4chee-mysql-2.14.7\\server\\default" -- because
				filesystem.dirpath contains a relative prefix, "archive". If you set
				up in advance an _absolute_ filesystem prefix via addRWFileSystem()
				in JMX Console, like "C:\DICOM", this parameter is not used.

		Conquest	Not the actual archive directory but Conquest installation directory
				where the file dicom.ini can be found. The list of configured archive
				directories will be read automatically from that file.

		WADO		A WADO base URL, for example, "http://HOST:PORT/wado"

		DICOM		Not used in this configuration

		ClearCanvas	Ignored: all relevant information is in the database

		FileSystem	Directory common to all paths. At least one path component
				must be given (for example, "C:\") so that this parameter isn't
				empty.
		dcm4chee-arc
					Not used in this configuration

		dcm4chee-arc-5
					Root directories are configured in the LDAP tree via
					entries named "dcmStorageID". As LDAP access is not supported
					in MedDream for now, directories must be set up here, too. Format:
					"ID1=URI1\nID2=URI2\n...".

					Example: $archive_dir_prefix = "fs1=file:///mnt/NAS1\nfs2=file:///mnt/NAS2";				
	 */
	$archive_dir_prefix = "C:\\DCM4CHEE\\dcm4chee-mysql-2.14.7\\server\\default";

	/* host name (or IP address) of the database server

		For MSSQL it is possible to add TCP/IP port using syntax "<server>,<port>".

		The newer SQLSRV is usually happy with only "localhost" but might require
		a full name like "host_name\SQLEXPRESS".

		For WADO/DICOM this must be the caller's connection string,
		"AET@HOST:PORT" or simply "AET", that dcmqr uses to identify itself.
		If a port is specified, it must be free. Note that most target PACSes
		must be configured to accept a particular caller.

		Not used with FileSystem pseudo-DBMS and with SQLite3.
	 */
	$db_host = "localhost";

	/* database name which is offered in the login form

		PacsOne		Ignored: this PACS supports multiple databases and MedDream
				reads their names automatically from files ../../*.ini

		DCM4CHEE	"pacsdb" by default

		Conquest	(MySQL/MSSQL/SQLSRV) "conquest" by default

		WADO/DICOM	A connection string in form of "AET@HOST:PORT|ALIAS" that
				identifies the target PACS. The alias is optional; it will be
				shown in the login form so that address/port is not revealed
				for an average user.

		ClearCanvas	"ImageServer" by default

		FileSystem	Any non-empty string

		SQLite3		Full path to the database file. Alias can be added in form
				of "full_path|ALIAS" for better appearance of the login page.
				The alias provides minuscule security gain as the full path
				will still be visible in the HTML source. Example:
				"C:\\Conquest\\data\\dbase\\conquest.db3|conquest"
	 */
	$login_form_db = "dcm4chee";

	/* impersonate this database user when reading internally-defined user accounts
	   (to log in under those accounts). Empty $pacs_login_user disables this

		PacsOne		Ignored: this PACS uses a different mechanism

		DCM4CHEE	pacs:pacs by default

		Conquest	Ignored: no internally-defined users

		WADO/DICOM	Ignored: logging in is not required at all

		ClearCanvas	Ignored: internal accounts are implemented outside the
				database

		FileSystem	Ignored: logging in is not required
	 */
	$pacs_login_user = "";
	$pacs_login_password = "";

	/* Application Entity Title for the dcmrcv utility

		For DICOM only, other configurations ignore this
	 */
	$dcm4che_recv_aet = "";

	/* The "path" portion for MedReport integration
		(MR will open as CURRENT_HOST_NAME$medreport_root_link?QUERY)

		Leave empty if MR is not installed. Typical value: "/medreport/home.php"
	 */
	$medreport_root_link = "";

	/* Except PacsOne:

		Target Application Entity Titles for the Forward and Save
		Annotations functions

		Basic syntax: "AET@HOST:PORT|DESCRIPTION\nAET@..."
		(\n works only inside double quotes!)

		DESCRIPTION is optional. HOST and PORT won't be shown in the user
		interface.

		The entry where DESCRIPTION ends with "- local" (this string
		exactly), is also used for the Save Annotations function.

	   PacsOne:

		Application Entity Title, Address and Port of the local PACS
		(used to save measurement results via DICOM C-STORE)

		Basic syntax: "AET@HOST:PORT|DESCRIPTION"

		DESCRIPTION can contain anything but must end with "- local"
		(this string exactly). Note that contents of this parameter
		are not visible in the Forward dialog; the latter still gets
		its contents from PacsOne's database.
	 */
	$forward_aets = "";

	/* Except PacsOne:

		Local Application Entity Title for Forward and Save Annotations
		functions

	  PacsOne:

		Local Application Entity Title for the Save Annotations function
	 */
	$local_aet = "";

	/* user who has access to Settings button, Register function, etc

		Required for dcm4chee-arc where every user has its own database,
		and for DCMSYS where "root" might be reserved.

		Useful for DCM4CHEE, replaces the built-in default "admin".

		For remaining configurations it can merely add another "root"
		user besides the hardcoded default.
	 */
	$admin_username = "";

	$m3d_link = "";
	$m3d_link_2 = "";

	/* Multiple links in MedDream context menu

		Basic syntax: "linkname|url|window|restriction\nlinknameN..."

		(\n works only inside double quotes!)

		url:
			{series} will be replaced with Series Instance UID (or, for DCM4CHEE etc,
				simply the value of a primary key in the series table)
			{study} will be replaced with Study Instance UID (or, for DCM4CHEE etc,
				simply the value of a primary key in the study table)
			{patientID} will be replaced with Patient ID
			{accessionNo} will be replaced with Accession Number
			{examDate} will be replaced with Study Date (YYYY-MM-DD)
			{image} will be replaced with Image Instance UID (or, for DCM4CHEE etc,
				simply the value of a primary key in the corresponding table)

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

	/* default character set in DICOM files, database and Q/R client

		The viewer needs UTF-8 and will convert other encodings to it using
		PHP's iconv library.

		For best results from "DICOM Tags" function and SR viewer, the DICOM
		file must contain the (0008,0005) Specific Character Set attribute
		with a correct value. See
		http://dicom.nema.org/medical/dicom/current/output/chtml/part03/sect_C.12.html#sect_C.12.1.1.2 .
		If the attribute is missing, then this parameter is used instead.
		If, in turn, this parameter is empty as well, 'ISO-IR 6' is assumed.

		For best results from database- or Q/R-related encoding conversions
		(e.g., output from Search function), make sure the database encoding is
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
?>
