<?php
/*
	Original name: eula.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		nm <nerijus.marcius@softneta.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>
		es <eitautas.simaitis@softneta.com>

	Description:
		Shows the EULA dialog first time the software is used, independently
		on the type of the viewer
 */

include __DIR__ . '/autoload.php';
use Softneta\MedDream\Core\System;

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	include __DIR__ . DIRECTORY_SEPARATOR . 'sharedData.php';
	setcookie('MedDreamEulaCookiesAgreement', $VERSION, 2145909600);
		/* 2038-01-01, approx. 2 weeks until 32-bit overflow. PHP_INT_MAX is totally unsuitable for 64-bit. */
	header( "Location: index.php?" . $_SERVER['QUERY_STRING']);
}

/**
 * try get eula path by language or
 * by default language
 *
 * @return string - path
 */
function getEulaPath()
{
	$defLang = 'en';
	$lang = '';
	if (!empty($_COOKIE['userLanguage']))
		if (strlen($_COOKIE['userLanguage']) == 2)
			$lang = (String) $_COOKIE['userLanguage'];

	$path = __DIR__ . DIRECTORY_SEPARATOR . 'locales' .
										DIRECTORY_SEPARATOR . $lang .
										DIRECTORY_SEPARATOR . 'eula.txt';

	if (!file_exists($path))
	{
		$path = __DIR__ . DIRECTORY_SEPARATOR . 'locales' .
										DIRECTORY_SEPARATOR . $defLang .
										DIRECTORY_SEPARATOR . 'eula.txt';

		if (!file_exists($path))
			$path = '';

	}
	return $path;
}
$eula = '';
$eulaPath = getEulaPath();

if (!empty($eulaPath))
	$eula = file_get_contents($eulaPath, true);
else
	header("Location: index.php?" . $_SERVER['QUERY_STRING']);

?><!DOCTYPE html>
<html>
    <meta charset="utf-8">
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
    <style type="text/css">
    html, body {
        overflow: hidden;
        height: 100%;
    }

    body {
        background-color: #27272b;
    }

    .wrapper {
        height: 100%;
        display: table;
        width: 80%;
        margin: 0 auto;
    }

    .header {
        display: table-row;
        padding: 100px 0 ;
        height: 60px;
    }

    .main {
        height: 100%;
        display:table-cell;
        width: 100%;
    }

    .footer {
        padding-top: 5px;
        padding-bottom: 5px;
        display: table-row;
        height:50px;

    }

    .logo-container{
        width: 100%;
        height: 100%;
        margin-top: 10px;
    }

    .eula-text{
        width:100%;
        height:100%
    }

    .btn-submit
    {
        margin-top: 5px;
        float: right;
    }

    /**************************
      DEFAULT BOOTSTRAP STYLES
    **************************/

    .btn {

        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 14px;
        font-weight: normal;

        display: inline-block;
        margin-bottom: 0;
        line-height: 1.42857143;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        cursor: pointer;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        background-image: none;
        border: 0 none;
        border-radius: 4px;
        padding: 5px 8px;
    }

    .btn-primary {
        color: #fff;
        background-color: #e51b48;

    }

    .btn:focus, .btn:active:focus, .btn.active:focus {
        outline: 0 none;
    }

    .btn-primary:hover, .btn-primary:focus, .btn-primary:active, .btn-primary.active, .open > .dropdown-toggle.btn-primary {
        background: #fff;
        color: #e51b48;

    }
    .btn-primary:active, .btn-primary.active {
        background: #e51b48;
        box-shadow: none;
        color: #fff;
    }
	.colum-item{
		display:table-row;
        width: 100%;
	}
	.cookie-text{
		color: #ffffff;
		padding-bottom: 20px;
		padding-top: 20px;
		font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 14px;
        font-weight: normal;
	}
    </style>
<head lang="en">
    <meta charset="UTF-8">
    <title></title>
</head>
<body>

<div class="wrapper">
    <div class="header">
        <div class="logo-container">
            <?php
			$companyLogoFile = '';
			$sys = new System();
			$branding = $sys->getBranding();
			if ($branding->active() && $branding->isValid())
				$companyLogoFile = $branding->getImageAttributeLocation('companyLogoFile');
			if (empty($companyLogoFile))
				$companyLogoFile = 'assets/img/companyLogo.png';
			echo '<img src="' . $companyLogoFile . '" alt="Company logo">';
			?>
        </div>
    </div>
    <div class="main">
        <textarea class="eula-text" readonly><?php echo $eula?></textarea>
    </div>
	<div class="colum-item">
		<div class="cookie-text">
		Cookies help us deliver our services. If you continue to use our services, you agree to our use of cookies.
		</div>
	</div>
    <div class="footer">
        <form method="post" action= <?php echo 'eula.php?'.$_SERVER['QUERY_STRING']?> >
            <input type="submit" data-i18n="[value]eula.Agree" value="Agree"  class="btn btn-primary btn-submit">
        </form>
    </div>
</div>
    <script src="assets/js/jquery-2.2.0.min.js" charset="utf-8"></script>
    <script src="assets/js/i18next.min.js"></script>
    <script src="assets/js/i18nextXHRBackend.min.js"></script>
    <script src="assets/js/i18nextBrowserLanguageDetector.min.js"></script>
    <script src="assets/js/jquery-i18next.min.js"></script>
    <script src="js/translate.js"></script>
</body>
</html>
