<?php
/*
	Original name: sysInfo.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kt <kestutis.triponis@softneta.com>

	Description:
		Collect information about license and server
 */

set_time_limit(0);
error_reporting(E_ALL | E_STRICT);

function send_post($destination, $content)
{
	$options = array('http' => array(
		'method' => 'POST',
		'content' => $content,
		'timeout' => 30,
		'header' => "Content-Type: application/xml\r\n"
	));
	$context = stream_context_create($options);
	return @file_get_contents($destination, false, $context);
}

$stats = array();

$licenseFile = dirname(__FILE__) . '/meddream.lic';
if (!file_exists($licenseFile))
	exit('N/A');

$license = simplexml_load_file($licenseFile);
if ($license === false)
	$stats['error'] = libxml_get_last_error();

if (!empty($license->host))
	$stats['host'] = (string) $license->host;
if (!empty($license->productID))
	$stats['productID'] = (string) $license->productID;
if (!empty($license->software))
	$stats['software'] = (string) $license->software;
if (!empty($license->registeredTo))
	$stats['registeredTo'] = (string) $license->registeredTo;
if (!empty($license->maxUsers))
	$stats['maxUsers'] = (int) $license->maxUsers;
if (!empty($license->module))
	$stats['modules'] = (string) $license->module;
if (!empty($license->validTo))
	$stats['validTo'] = (string) $license->validTo;
if (!empty($license->updatesTo))
	$stats['updatesTo'] = (string) $license->updatesTo;

$stats['connections'] = meddream_connections();
$stats['version'] = meddream_version();

$rc = send_post('http://www.softneta.com/service/meddream/', json_encode($stats));
exit((string) strlen($rc));
?>
