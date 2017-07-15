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

define('UPLOAD_DIR', $rootPath.'log/');
$fileName = isset($_REQUEST['title']) ? $_REQUEST['title'].'.jpg' : 'demo_test.jpg';
$description = isset($_REQUEST['description']) ? $_REQUEST['description'] : '';
$jpegData = $_REQUEST['jpegData'];

$base64img = str_replace('data:image/jpeg;base64,', '', $jpegData);
$base64img = str_replace(' ', '+', $base64img);
$data = base64_decode($base64img);

$file = UPLOAD_DIR . $fileName;
file_put_contents($file, $data);

$result = array();

$result['msg'] = $fileName. ' file save success';
echo json_encode($result);
