<?php
/**
 *  This file is part of amfPHP
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file license.txt.
 * @package Amfphp
 */

/**
*  includes
*  */
require_once dirname(__FILE__) . '/ClassLoader.php';

/* 
 * main entry point (gateway) for service calls. instanciates the gateway class and uses it to handle the call.
 * 
 * @package Amfphp
 * @author Ariel Sommeria-klein
 */
$gateway = Amfphp_Core_HttpRequestGatewayFactory::createGateway();

//use this to change the current folder to the services folder. Be careful of the case.
//This was done in 1.9 and can be used to support relative includes, and should be used when upgrading from 1.9 to 2.0 if you use relative includes
//chdir(dirname(__FILE__) . '/Services');
chdir(dirname(__FILE__) . '/../');

/* IE before v9 likes to cache HTTPS requests without a valid reason.
   We could instruct it to revalidate cache, or not to cache at all.
 */
if (isset($_SERVER['HTTP_USER_AGENT']))
{
	$ua = $_SERVER['HTTP_USER_AGENT'];
	$is_old_IE = strpos($ua, 'MSIE 6.') || strpos($ua, 'MSIE 7.') || strpos($ua, 'MSIE 8.');
}
else
	$is_old_IE = false;
if ($is_old_IE)
	session_cache_limiter('nocache');	/* this gives us almost everything needed... */
session_start();
if ($is_old_IE)
	header('Pragma: no-store');			/* ...but there is 'Pragma: no-cache' that doesn't do the job */

$gateway->service();
$gateway->output();


?>
