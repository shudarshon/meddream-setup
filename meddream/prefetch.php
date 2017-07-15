<?php
/*
	Original name: prefetch.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Called by Flash viewer to retrieve entire study if $pacs='DICOM'
		(config.php), $retrieve_entire_study (php.ini) is nonzero and one
		or more images of the study are missing in the cache.

		Indeed the entire study will be re-sent due to a single missing
		image. On the other hand, this situation is rare and involves
		periodic refresh in the browser when the study is still being
		uploaded to the PACS. One fine day the viewer will tell what exactly
		differs on the server (number of series/images, values of some
		attributes) and will offer to confirm the retrieval.
 */

use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\QueryRetrieve\QR;

require_once(dirname(__FILE__) . '/autoload.php');

/* various preparations */
set_time_limit(0);
session_start();
ignore_user_abort(true);

/* logging subsystem */
$modulename = basename(__FILE__);
$log = new Logging();
$log->asDump('begin ' . $modulename);
/*
$tma = explode(' ', microtime());
$tm = date('H:i:s', $tma[1]) . substr($tma[0], 1, 7);
file_put_contents('data.lst', "$tm  -  prefetch\n", FILE_APPEND);
//*/

/* if we need that connection_aborted() reacts quick enough, then the loop
   at the end of this function must output & flush a big enough chunk of
   whitespace. We can attempt to parse the string value of output_buffering
   (php.ini) and later use a fraction of it.
 */
$obs = get_cfg_var('output_buffering');
$out_buf_size = (int) $obs;
	/* quite sufficient for our needs as suffixes "K" etc are unlikely */
$obs2 = (string) $out_buf_size;
if ($obs2 != $obs)
{
	$log->asWarn("unable to parse value of output_buffering='$obs', assuming 4 KB");
	$out_buf_size = 0;
}
if (!$out_buf_size)
	$out_buf_size = 4096;

/* authentication is required for security */
$backend = new Backend(array(), false);
if (!$backend->authDB->isAuthenticated())
{
	$err = 'not authenticated';
	$log->asErr($err);
	exit($err);
}

/* validate parameters */
$uid = '';
if (isset($_REQUEST['study']))
	$uid = $_REQUEST['study'];
if (!strlen($uid))
{
	$err = 'wrong parameter(s)';
	$log->asErr("$modulename: $err");
	exit($err);
}
$log->asDump('parameters: study=', $uid);

/* do the job; this is a non-blocking equivalent of QR::fetchStudy() */
$qr = QR::getObj($backend);
$parser = $qr->fetchStudyStart($uid);
if (strlen($parser['error']))
	$err = $parser['error'];
else
{
	$tm_old = 0;	/* better than time() due to immediate mismatch below */
	do
	{
		/* a continuous output is needed to detect a disconnected client; we can
		   periodically output whitespace that is ignored by the client
		 */
		$tm_new = time();
		if ($tm_new != $tm_old)
		{
			echo str_repeat("  \r\n", $out_buf_size / 4);	/* 4: the entire buffer */
			flush();
			$tm_old = $tm_new;
		}

		/* did the output above succeed? */
		if (connection_aborted())
			$qr->fetchStudyBreak($parser);

		/* read and interpret the next line from dcmqr output */
		$r = $qr->fetchStudyContinue($parser);
	} while ($r);

	$err = $qr->fetchStudyEnd($parser);
}

if (strlen($err))
{
	$log->asErr("$modulename reporting: $err");
	echo $err;
}
$log->asDump('end ' . $modulename);

?>
