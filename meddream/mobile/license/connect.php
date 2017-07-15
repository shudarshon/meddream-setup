<?php

use Softneta\MedDream\Core\System;

require_once __DIR__ . '/../../autoload.php';

$system = new System();
if (isset($_REQUEST['clientid']))
    $clientid = $_REQUEST['clientid'];
else
    $clientid = '';
$return = $system->connect($clientid);
echo json_encode($return);
