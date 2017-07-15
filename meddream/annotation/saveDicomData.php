<?php
/**
 * Owner: Nerijus Marčius
 * Date: 2016-02-08
 */

$rootPath = __DIR__.'/../';
$moduleName = 'annotation/' . basename(__FILE__);	/* for logging */


require_once($rootPath.'autoload.php');

session_start();

$log = new Logging();

header("Content-Type: application/json");
$data = json_decode(stripslashes(file_get_contents("php://input")));

$log->asDump('Annotation data: ', $data);

echo json_encode($data);
?>