<?php

use Softneta\MedDream\Core\System;

include_once(__DIR__ . '/../autoload.php');

$system = new System();
$return = $system->refresh();

if ($return == 'reconnect')
	exit('reconnect');

echo $return;

?>
