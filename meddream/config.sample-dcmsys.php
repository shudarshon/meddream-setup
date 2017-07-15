<?php
	/* The "DCMSYS" pseudo-PACS is for "DCMSYS Router" from Dicom Systems.
	   Search is performed via QIDO-RS. Metadata and binaries are downloaded
	   via WADO-RS.
	 */
	$pacs = "DCMSYS";

	/* router's base URL */
	$db_host = "https://localhost/api/";

	/* user who has access to Settings button, Register function, etc */
	$admin_username = "";

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

	/* how long should common temporary files be kept

		Use the relative format of strtotime(), that is, with keywords "day", "hour", "minute"
		etc, without the sign (the minus is added automatically where appropriate).
	 */
	$tmp_remove_after = '7 days';

	/* how long should more "critical" files (usually parsed studies and similar large files) be kept */
	$tmp_remove_after_crit = '1 day';

	/* not required in this configuration, please don't change */
	$dbms = "";
	$pacs_login_user = "";
	$pacs_login_password = "";
	$archive_dir_prefix = "";
	$login_form_db = "";
	$dcm4che_recv_aet = "";
	$medreport_root_link = "";
	$forward_aets = "";
	$local_aet = "";
	$m3d_link = "";
	$m3d_link_2 = "";
	$default_character_set = '';
?>
