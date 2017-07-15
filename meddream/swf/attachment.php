<?php
/*
	Original name: attachment.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Backend for "Download attachment" in the Reporting module
 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

require_once __DIR__ . '/autoload.php';

if (!strlen(session_id()))
	session_start();

$studyUID = "";
if (isset($_REQUEST["study"]))
	$studyUID = $_REQUEST["study"];
$seq = "";
if (isset($_REQUEST["seq"]))
	$seq = $_REQUEST["seq"];

$audit = new Audit('GET ATTACHMENT');
$auditDetails = "study '" . $studyUID . "', seq '" . $seq ."'";

$modulename = basename(__FILE__);
$log = new Logging();

$log->asDump('begin ' . $modulename);

if (($studyUID == "") || ($seq == ""))
{
	$log->asErr("parameter(s) missing: study '$studyUID', sequence '$seq'");
	$audit->log(false, $auditDetails);
	exit;
}

$backend = new Backend(array('Report'));
if (!$backend->authDB->isAuthenticated())
{
	header('403 Forbidden');
	$log->asErr('Not authenticated');
	$audit->log(false, $auditDetails);
	exit();
}

$row = $backend->pacsReport->getAttachment($studyUID, $seq);
if (strlen($row['error']))
{
	$audit->log(false, $auditDetails);
	exit('SQL error, see logs');
}

if (isset($row["mimetype"]) && isset($row["path"]))		/* a quite strange legacy condition, likely always true */
{
	$path = $row["path"];
	$fileName = basename($path);
	$auditDetails .= ", name '" . $fileName . "'";
	
	header('Pragma: public');
	header('Content-Type: ' . $row["mimetype"]);
	header('Content-Disposition: attachment; filename="' . $fileName . '"');
	header('Pragma: no-cache');
	header('Expires: 0');

	if (strlen($row["data"]))
	{
		$size = strlen($row['data']);

		$log->asInfo("downloading from database, $size byte(s)");

		$audit->log(true, $auditDetails);
		header('Content-Length: ' . $row['totalsize']);
		echo $row['data'];
	}
	else
		if (@file_exists($path))
		{
			$size = @filesize($path);		/* prefer the real size */
			if ($size === false)
				$size = $row['totalsize'];

			$log->asInfo("downloading from file '$path', $size byte(s)");

			$audit->log(true, $auditDetails);
			header("Content-Length: $size");
			readfile($path);
		}
		else
		{
			$log->asErr("attached file missing: '$path' ($studyUID|$seq)");
			$audit->log(false, $auditDetails);

			header('Content-Length: 0');
			/* TODO: an error message instead of download (must output the
			   Content-Disposition header later)
			 */
		}
}
else
	$audit->log(false, $auditDetails);

$log->asDump('end ' . $modulename);
