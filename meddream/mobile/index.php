<?php

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;

require_once __DIR__ . '/../autoload.php';

if (!strlen(session_id()))
	session_start();

$log = new Logging();

$backend = new Backend(array(), false);
if (!$backend->authDB->isAuthenticated())
{
	$log->asErr('mobile/index.php: not authenticated');
	header('Location: ../index.php');
	exit;
}
else
{
	include 'index.html';
}
