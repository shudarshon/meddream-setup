<?php
/*
	Original name: uploadattachment.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Backend for "Upload attachment" in the Reporting module
 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;

if (!function_exists('finfo_open'))
	exit('Please enable the fileinfo PHP extension');

if (isset($_REQUEST['cookie']))
	$_COOKIE['sessionCookie'] = $_REQUEST['cookie'];
if (isset($_REQUEST['SSID']))
{
	session_id($_REQUEST['SSID']);
	session_start();
}
else
	if (!strlen(session_id()))
		session_start();			/* BEFORE autoload.php! */

require_once __DIR__ . '/autoload.php';

$audit = new Audit('UPLOAD ATTACHMENT');
$auditDetails = 'study ';
if (isset($_POST['studyUID']))
	$auditDetails .= "'" . $_POST['studyUID'] . "'";
	/* quotes are conditional, too: if the record contains no quotes, then this will
	   indicate absence of the corresponding variable in the request, which might be
	   a sign of hacking
	 */

$auditDetails .= ', note ';
if (isset($_POST['id']))
	$auditDetails .= "'" . $_POST['id'] ."'";

$auditDetails .= ', name ';
if (isset($_FILES['Filedata']['name']))
	$auditDetails .= "'" . $_FILES['Filedata']['name'] ."'";

$auditDetails .= ', size ';
if (isset($_FILES['Filedata']['size']))
	$auditDetails .= $_FILES['Filedata']['size'];

$modulename = basename(__FILE__);
$log = new Logging();

$log->asDump('begin ' . $modulename);
$log->asDump('_FILES: ', $_FILES);
$log->asDump('_REQUEST: ', $_REQUEST);
$log->asDump('_SESSION: ', $_SESSION);

if (isset($_FILES['Filedata']) &&
	!empty($_POST['studyUID']) &&
	!empty($_POST['id']))
{
	$inputFile = $_FILES['Filedata']['tmp_name'];
	if (!file_exists($inputFile))
	{
		$log->asErr("missing: '$inputFile'");
		$audit->log(false, $auditDetails);

		exit('Internal error, see logs');
	}

	/**
	* if not the same client - regenerate new session id
	*/
	if (empty($_SESSION['clientIdMD']))
	{
		@unlink($inputFile);
		$log->asErr("Different client:".$_REQUEST['clientid']);
		$audit->log(false, $auditDetails);
		exit();
	}

	if ((string)$_REQUEST['clientid'] != $_SESSION['clientIdMD'])
	{
		@unlink($inputFile);
		$log->asErr("Different client:".$_REQUEST['clientid']);
		$audit->log(false, $auditDetails);
		exit();
	}

	$backend = new Backend(array('Report'));
	$authDB = $backend->authDB;

	if (!$authDB->isAuthenticated())
	{
		$err = "Not authenticated";
		$log->asErr($err);
		@unlink($inputFile);

		$audit->log(false, $auditDetails);
		exit($err);
	}

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$type = @finfo_file($finfo, $inputFile);
	finfo_close($finfo);
	if ($type === false)
	{
		$audit->log(false, $auditDetails);
		exit('Failed to recognize the file type');
	}
	$log->asDump('$type: ' . $type);
	
	if (strlen($backend->attachmentUploadDir))
	{
		$outputFile = $backend->attachmentUploadDir . DIRECTORY_SEPARATOR .
			date('Ymd-his') . '-' . $authDB->getAuthUser() . '-' .
			basename($_FILES['Filedata']['name']);
		$auditDetails .= ", uploaded to '" . basename($outputFile) . "'";
		
		if (@file_exists($outputFile))
		{
			$log->asErr("file already exists, refusing to overwrite: '$outputFile'");
			$audit->log(false, $auditDetails);
			exit('Destination file collision');
		}
		if (!copy($inputFile, $outputFile))
		{
			$log->asErr("failed to copy '$inputFile' to '$outputFile'");
			$audit->log(false, $auditDetails);
			exit('Copying failed');
		}
		else
			$log->asInfo("copied attachment to '$outputFile'");

		$att = $backend->pacsReport->createAttachment($_POST['studyUID'], $_POST['id'], $type,
			$outputFile, $_FILES['Filedata']['size']);
	}
	else
	{
		$file = @file_get_contents($inputFile);

		$att = $backend->pacsReport->createAttachment($_POST['studyUID'], $_POST['id'], $type,
			$_FILES['Filedata']['name'], $_FILES['Filedata']['size'], $file);
	}
	if (strlen($att['error']))
	{
		$audit->log(false, $auditDetails);
		exit($att['error']);
	}

	$audit->log("SUCCESS, seq '" . $att['seq'] . "'", $auditDetails);
	$log->asInfo('uploaded: ' . $_FILES['Filedata']['name']);
	$log->asDump('end ' . $modulename);
}
else
{
	$audit->log(false, $auditDetails);
	$log->asErr('Bad request');
	exit('Bad request');
}
