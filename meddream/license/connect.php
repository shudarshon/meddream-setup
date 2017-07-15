<?php

use Softneta\MedDream\Core\System;

define('PATH_PREFIX', __DIR__.'/../');
include_once(PATH_PREFIX . 'autoload.php');

$system = new System();
if (isset($_REQUEST['clientid']))
    $clientid = $_REQUEST['clientid'];
else
    $clientid = '';
$return = $system->connect($clientid);
echo json_encode($return);

?>
