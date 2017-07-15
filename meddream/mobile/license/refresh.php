<?php

use Softneta\MedDream\Core\System;

require_once __DIR__ . '/../../autoload.php';

$system = new System();
$return = $system->refresh();

echo $return;
