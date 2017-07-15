<?php
/*
	Original name: dicom.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		A simple wrapper for ../dicom.php
 */

error_reporting(0);
set_time_limit(0);
	/* With very large multiframe files, even meddream_convert2() might trigger the time
	   limit. Better to adjust the limit here instead of just before sending data to the
	   client.
	 */
ignore_user_abort(true);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../dicom.php';
