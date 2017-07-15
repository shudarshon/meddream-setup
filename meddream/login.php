<?php
/*
	Original name: login.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Initializes and displays the login form
 */

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Branding;
use Softneta\MedDream\Core\InstallValidator;
use Softneta\MedDream\Core\System;

require_once('autoload.php');

require_once("xml2array.php");
include_once('sharedData.php');


if (!isset($backend))
{
	$backend = new Backend(array(), false);
	$backend->authDB->goHome(true);
	exit;
}

function getDatabaseOptions($names)
{
	$result = '';
	if (count($names))
	{
		foreach ($names as $db)
		{
			if (is_array($db))
				$result .= '<option value="' . $db[0] . '">' . $db[1] . '</option>';
			else
				$result .= '<option value="'. $db .'">' . $db .'</option>';
		}
	}
	return $result;
}


function getAction()
{
	if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']))
		$url = 'https://';
	else
		$url = 'http://';

	$url .= $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
	//if (empty($_SERVER['HTTP_X_FORWARDED_HOST']))
	//	$url .= $_SERVER['HTTP_HOST'];
	//else
	//	$url .= $_SERVER['HTTP_X_FORWARDED_HOST'];
	//$url .= $_SERVER['PHP_SELF'];

	/* preserve existing query parameters, excluding 'htmlMode'; this one
	   must come from a form field, and therefore can't be present in the
	   form action URL
	 */
	$uri = $_SERVER['REQUEST_URI'];
	$get = '';
	if (strpos($uri, '?', 0) !== false)
		foreach ($_REQUEST as $k => $v)
			if ($k !== 'htmlMode')
			{
				if (strlen($get))
					$get .= '&';
				else
					$get .= '?';
				$get .= "$k=$v";
			}
	$url .= $get;
	return $url;
}

/* read the login form template */
$loginHTML = @file_get_contents('login.html');
if ($loginHTML === false)
	exit('login.html not found');

/* prepare for translations of {$message} in the login form (most labels
   are translated by the frontend)
*/
$message = '';
$tr = $backend->tr;
$err = $tr->configure();
if (is_string($err) && strlen($err))
	$message = $err;
	/* although this situation was handled earlier by Backend::__construct()
	   as a fatal stop, the situation might change immediately after that
	 */
$translationLoadFailure = $tr->load();	/* will display at the very end */
$labelRefresh = $tr->translate('login\Refresh', 'Refresh');

/* some conditioning of $_GET['fatal'], $_GET['obj'], $_GET['type'] */
if (isset($_GET['fatal']))
	$fatal =  $_GET['fatal'];
else
	$fatal = 0;
if (isset($_GET['obj']) && strlen($_GET['obj']))
{
	$obj = $_GET['obj'];
	$obj_t = " '" . $obj . "'";
}
else
{
	$obj = "";
	$obj_t = "";
}
if (isset($_GET['type']) && strlen($_GET['type']))
	$type = $_GET['type'];
else
	$type = "";

/* convert some keywords in $_GET['message'] to user-readable strings */
$retryAfterMinutes = 0;
foreach ($backend->preparedFilesDir as $dir)
	if (!@is_dir($dir))
	{
		$message = '<b>Warning:</b> entry in $prepared_files_dir (config.php) is not a directory: "' . $dir . '"';
		break;
	}
		/* check for warning conditions first, they will be overwritten by error conditions */
if (isset($_GET['message']))
{
	$message = strtolower($_GET['message']);

	/* errorTooManyFailures ::= 'errorTooManyFailures', '-', retry_after_minutes;

		separate those strings as retry_after_minutes will be used when
		formatting the final message
	 */
	$messageArray = explode('-', $message);
	if (count($messageArray) == 2)
		if ($messageArray[0] == 'errortoomanyfailures')
		{
			$message = $messageArray[0];
			$retryAfterMinutes = $messageArray[1];
		}
}
if ($message == 'errorusernamepassword')
	$message = $tr->translate('login\errorUsernamePassword',
		'Error: Please check your username and password');
elseif ($message == 'errorobjnotfound')
{
	if (!strlen($type))
		$type = $tr->translate('login\errorObjNotFound1', 'object');
	$message = ucwords($type) . $obj_t . ' ' .
		$tr->translate('login\errorObjNotFound2', 'not found');
	if (!$fatal)
		$message .= '.<br><br>' . $tr->translate('login\errorObjNotFound3',
			'You may try to find it manually after logging in');
}
elseif ($message == 'errorvalidateconnect')
	$message = $tr->translate('login\errorValidateConnect',
		"Error: Can't connect to the database for HIS link validation.<br><br>" .
		'Please contact your system administrator');
elseif ($message == 'errorvalidatequery')
	$message = $tr->translate('login\errorValidateQuery',
		'Error: HIS link validation query failed.<br><br>Please contact your system administrator');
elseif ($message == 'errortoomanyfailures')
	$message = $tr->translate('login\errorTooManyFailures',
		'Error: too many login failures, please try in {n} min',
		array('{n}' => $retryAfterMinutes));

if ($message == '')
{
	$validator = new InstallValidator($tr);
	$message = $validator->getErrorsAsString($backend->pacs,
		strlen($backend->getPacsConfigPrm('pacs_gateway_addr')) != 0,
		'<br>');
	if ($message != '')
		$fatal = 1;
}
if (!strlen($message))
	$message = $translationLoadFailure;
	/* issues with backend translation have the lowest precedence; this message
	   will be visible only if there are no other problems
	  */

$searchArray = array();
$replaceArray = array();

$searchArray[0] = '{$databaseOptions}';
$searchArray[1] = '{$action}';
$searchArray[2] = '{$product}';
$searchArray[3] = '{$version}';
$searchArray[4] = '{$copyright}';
$searchArray[5] = '{$message}';
$searchArray[6] = '{$languages}';
$searchArray[7] = '{demo_login_user}';
$searchArray[8] = '{demo_login_password}';
$searchArray[9] = '{$loginLogoFile}';

$databases = $backend->pacsConfig->getDatabaseNames();
if (!is_array($databases))	/* an error message */
	exit("Failed to retrieve database names for the login form. Details:<br>\n$databases");
if (count($databases) < 2)		/* do not display a single choice */
{
	/* with SQLite3 etc, elements of $databases contain not only the name but also the alias */
	$dbName = $databases[0];
	if (count($dbName) > 1)
		$dbName = $dbName[0];

	$replaceArray[0] = '<input type="hidden" name="db" value="' . $dbName . '">';
	$labelDatabase = '';
		/* this also includes dcm4chee-arc, DCMSYS, etc where database is not required */
}
else
	$replaceArray[0] = getDatabaseOptions($databases);

/* generate some HTML for language choice buttons */
$languageChoices = '';
$supp = $tr->supported($ignored);
foreach ($supp as $lng)
{
	$lngUC = strtoupper($lng);
	$languageChoices .= "<label class=\"btn btn-primary $lng\">\r\n";
	$languageChoices .= "<input type=\"radio\" name=\"language\" id=\"$lng\" value=\"$lng\">$lngUC</label>\r\n";
}

$loginLogo = '<div class="md-logo"></div>
                    <div class="vertical-line"></div>
                    <div class="brand-logo"></div>';

/* rebranding */
$sys = new System($backend);
$branding = $sys->getBranding();
if ($branding->active())
{
	if (!$branding->isValid())
	{
		$message = $tr->translate('branding\errorValidateBranding',
				'Error: Branding file rebranding/rebranding_configuration.json is not valid');
	}
	else
	{
		$logo = $branding->getImageAttributeLocation('loginLogoFile');
		if ($logo != '')
			$loginLogo = '<img src="' . $logo . '">';
		else
			$loginLogo = '';

		if($sys->licenseIsBranding())
		{
			$PRODUCT = $branding->getAttribute('productName');
			$VERSION = $branding->getAttribute('productVersion');
			$COPYRIGHT = $branding->getAttribute('copyright');
		}
	}
}
unset($sys);

$replaceArray[1] = getAction();
$replaceArray[2] = $PRODUCT;
$replaceArray[3] = $VERSION;
$replaceArray[4] = $COPYRIGHT;
$replaceArray[5] = $message;
$replaceArray[6] = $languageChoices;
$replaceArray[7] = $backend->demoLoginUser;
$replaceArray[8] = $backend->demoLoginPassword;
$replaceArray[9] = $loginLogo;

$loginHTML = str_replace($searchArray, $replaceArray, $loginHTML);

/* some configurations don't need any credentials but too much checks on empty $user
   make that difficult. We'll add the default login value "user", but only if
   Backend::$demoLoginUser is not set up (user's choice is more important).
 */
if (!strlen($backend->demoLoginUser) && !$backend->pacsConfig->supportsAuthentication())
	$loginHTML = str_replace('name="user"', 'name="user" value="user"', $loginHTML);

/* hide the login form if logging in would not help */
if ($fatal)
{
	$loginHTML = str_replace('<form ', '<!-- <form ', $loginHTML);
	$loginHTML = str_replace('/form>', '/form> -->', $loginHTML);

	/* fatal=2 adds a "Refresh" button */
	if (strlen($obj) && strlen($type) && ($fatal > 1))
	{
		$refresh = "<div align=\"center\"><br>\n" .
			"\t\t\t\t\t<form name=\"refresh\" method=\"get\" action=\"\">\n" .
			"\t\t\t\t\t\t<input type=\"hidden\" name=\"$type\" value=\"$obj\">\n";

		/* preserve any remaining query keys */
		foreach ($_REQUEST as $k => $v)
			if (($k != 'message') && ($k != 'obj') && ($k != 'fatal') && ($k != 'type'))
				$refresh .= "\t\t\t\t\t\t<input type=\"hidden\" name=\"$k\" value=\"$v\">\n";

		$refresh .= "\t\t\t\t\t\t<input class=\"btn btn-primary btn-default button-control-gray\" type=\"submit\" data-i18n=\"[value]login.Refresh\">\n" .
			"\t\t\t\t\t</form><br><br>\n\t\t\t\t</div>";
		$loginHTML = str_replace('<div id="refresh" hidden></div>', $refresh, $loginHTML);
	}
}

/**
 * formatted final html
 */
print($loginHTML);

?>
