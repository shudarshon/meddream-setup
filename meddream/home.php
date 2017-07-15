<?php
/*
	Original name: home.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		al <audrius.liutkus@softneta.lt>
		db <daina@softneta.lt>
		nm <nerijus.marcius@softneta.com>

	Description:
		The main HTML entry point. Validates login credentials (or displays the
		login form, if none are given) and loads the UI.
 */

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\InstallValidator;

try		/* we'll attempt to catch exceptions: some of them have a message to the user */
{

session_start();
require_once('autoload.php');

/* initialize absent parameters */
if (!isset($forRIS))
	$forRIS = false;
if (!isset($simpleURL))
	$simpleURL = false;

$htmlMode = false;
if (isset($_REQUEST['htmlMode']))
	if (strlen($_REQUEST['htmlMode']))
		$htmlMode = ('on' == $_REQUEST['htmlMode']);

$mobileMode = false;
if (@file_exists('mobile/mob_detect.php'))
{
	require_once('mobile/mob_detect.php');
	$detect = new Mobile_Detect;
	if ($detect->isMobile() && !$detect->isTablet())
 		$mobileMode = $detect->isMobile();
}

if (!isset($noHisIntegration))
	$noHisIntegration = false;

if ((isset($simpleURL)) && ($simpleURL))
	$_SESSION['basename'] = "";
else
	$_SESSION['basename'] = basename($_SERVER['PHP_SELF']);

$log = new Logging();				/* used in external.php */
$backend = new Backend(array('Auth'), true, $log);
	/* true: connect to the DB immediately, external.php and login need this */
$authDB = $backend->authDB;			/* also in external.php */

if (Constants::FOR_RIS && $forRIS && !$simpleURL)		/* check this after including authdb.php */
	exit('$forRIS = true (index.php) also requires $simpleURL = true');
	/*	In RIS mode, MedDream is used only as a "component". Ability to
		log into MedDream interactively is not guaranteed. Specifically,
		if the user provides wrong credentials, "/home.php" will reload
		as "/", which is actually the RIS login.

		In order to ensure redirection back to RIS, $simpleURL must
		remain true.
	 */

/* under "DCMSYS", only the HTML5 mode is supported (no Flash, no Mobile).
   Must do this after calling the AuthDB constructor, and before providing
   the hint about external.php.
 */
if ($backend->pacs == 'DCMSYS')
{
	$mobileMode = false;
	$htmlMode = true;
}

/* include external.php if needed */
if ($noHisIntegration)
	$external = false;
else
	$external = @file_exists("external.php");
if ($external)
{
	if (!Configuration::endsWithPhpClosingTags(file_get_contents('external.php')))
		exit('external.php has an invalid ending, please make sure it ends with "?>" without any empty lines after');
	include_once("external.php");
}

$userRequests = array();
$querylist = array('patient', 'study', 'series', 'image', 'accnum', 'identification', 'file', 'aid');
for ($i = 0; $i < count($querylist); $i++)
{
	$key = $querylist[$i];
	if (isset($_REQUEST[$key]) && ($_REQUEST[$key] != ''))
		$userRequests[] = $key;
}
/* provide a hint to customers who are trying HIS integration without setting up
   external.php first. This is required only for Flash.
 */
if (!$external && !$mobileMode)
{
	/* memorize the HIS link parameter: the URL to the HTML Viewer needs it, too */
	if (!empty($userRequests))
		exit('Please configure external.php or remove the parameter(s) from the query: ' . implode(',', $userRequests));
}
else
	if ($external && !empty($supportedRequests) && !empty($userRequests))
	{
		/**
		 * See if user using supported request parameters from HIS
		 */
		foreach ($userRequests as $item)
			if (!in_array($item, $supportedRequests))
				exit("external.php does not support the parameter '$item'");
	}

/* display the login form until we're authenticated */
header('Content-Language: en-us');
header('Content-Type: text/html; charset=utf-8');

if ($authDB->isAuthenticated())
	if ($external)
		externalAuthentication();

if (!$authDB->isAuthenticated())
{
	$isUser = isset($_POST['user']);

	$db = "";
	$user = "";
	$password = "";

	if (isset($_POST['db']))
		$db = $_POST['db'];

	if (isset($_POST['user']))
		$user = $_POST['user'];

	if (isset($_POST['password']))
		$password = $_POST['password'];

	if ($external)
	{
		$audit = new Audit('EXTERNAL');

		/* in RIS mode, enable IP addess bans for both RIS and MedDream.

			It's impossible to exclude MedDream because the current value
			of $forRIS doesn't survive redirection in AuthDB::goHome().
			What's worse, redirection always switches to MedDream login
			if $simpleURL = false, and to RIS login otherwise.

			In fact, the current redirection mechanism conflicts with the
			practice that home.php opens MedDream, whereas index.php
			(with $forRIS = true) opens the RIS.

			Ideally, some other file (not index.php) is to be used to open
			the RIS. Then redirection might be able to preserve the name
			of the current file and therefore remain in MedDream or in RIS.
			Consequently, $simpleURL = true won't be supported in RIS mode
			afterwards.
		 */
		if (Constants::FOR_RIS)
			externalLoginInfo($db, $user, $password, $authDB->getRealIpAddr());
		else
			externalLoginInfo($db, $user, $password);
	}

	if (($user == "") && (!$isUser))
	{
		include("login.php");
		exit;
	}

	if ($user == "")
	{
		$authDB->goHome(true, "errorUsernamePassword");
		exit;
	}

	if (!$backend->pacsAuth->login($db, $user, $password))
		if (Constants::FOR_WORKSTATION || !$authDB->login($db, $user, $password))
		{
			/* MedDream/Optomed Workstation uses Backend::login() ONLY */
			$authDB->logoff();
			$authDB->goHome(true, "errorUsernamePassword");
			exit;
		}

	$authDB->goHome(true);
	exit;
}

/* show EULA */
include __DIR__ . DIRECTORY_SEPARATOR . 'sharedData.php';
function showEula()
{
	global $VERSION, $backend;
	$constant = new Constants();
	if (!$constant->FDL && !Constants::FOR_DCMSYS &&
		$backend->pacsAuth->hasPrivilege('root') &&
		(empty($_COOKIE['MedDreamEulaCookiesAgreement']) ||
		($_COOKIE['MedDreamEulaCookiesAgreement'] != $VERSION)))
	{
		header("Location: eula.php?" . $_SERVER['QUERY_STRING']);
		exit;
	}
}
if ($backend->pacs == 'DCMSYS')
{
    $mobileMode = false;
    $htmlMode = true;
}
/* load the UI */
if ($forRIS)
	include('StudyList.php');
else
	if ($mobileMode)
	{
		showEula();
		header("Location: mobile/index.html?" . $_SERVER['QUERY_STRING']);
	}
	else
	{
/*
                if ($backend->pacs == 'DCMSYS' && !isset($_COOKIE['suid']))
                {
                        include_once('dcmsys/login.php');
                        dcmsys_login($user, $password, $authDB->db_host);
                }
 */
		/* if opened via pacsone applet.php which creates $_SESSION['actions'] */
		if (!empty($_SESSION['actions']))
		{
			/* works with swf only, because we need 'include()' and not 'header()':
			   sessionCookie is wrong after header()
			 */
			include('swf/index.php');
			exit();
		}

		$actions = array();
		if ($external)
			externalActions($actions);

		if (!empty($actions))	/* opening via HIS integration, due to external.php */
		{
			$validator  = new InstallValidator();
			$message = $validator->getErrorsAsString($backend->pacs,
				strlen($backend->getPacsConfigPrm('pacs_gateway_addr')) != 0,
				'<br>');
			if ($message != '')
				exit($message);
			if ($actions['option'] == "patient")
				header('Location: md5/search.html');
			else
			{
				if ($htmlMode)
					header('Location: md5/index.html');
				else
					include('swf/index.php');
			}
		}
		else
		{
			showEula();
			header("Location: md5/search.html");
		}
	}

/* display our exception message (most are configuration-related) */
}
catch (Exception $e)
{
	echo "<html><body>\n";
	echo $e->getMessage() . "<br>\n";
	if (Constants::EXCEPTIONS_NO_EXIT > 1)
	{
		echo "\nException trace:\n<pre>";
		echo $e->getTraceAsString() . "</pre>\n";
	}
	echo "</body></html>\n";
}
?>
