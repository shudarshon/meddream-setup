<?php
/*
	Original name: index.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Flash embedding. A few event filters. Some interaction between
		HTML and Flash.
 */

require_once __DIR__ . '/autoload.php';

include_once __DIR__ . '/../sharedData.php';

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Branding;
use Softneta\MedDream\Core\System;

global $backend;		/* available when included from home.php */
if (!isset($backend))
	$backend = new Backend(array(), false);
	/* search window in 5.0 calls us directly; no database connection needed, just a session check */
if (!$backend->authDB->isAuthenticated())
{
	$backend->authDB->goHome(true);
	exit;
}
else
	/* search window in 5.0 calls us directly in order to open an image (meddream.php?study=...)

		Not to be confused with HIS integration: in the latter home.php or index.php
		is called with the parameter "study" (or, of course, other parameters not
		needed here).
	 */
	if (!isset($external))
	{
		$querylist = array('study', 'patient', 'series', 'accnum', 'identification', 'file', 'aid');
		$count = count($querylist);
		for ($i = 0; $i < $count; $i++)
		{
			$key = $querylist[$i];
			if (isset($_REQUEST[$key]) && ($_REQUEST[$key] != ''))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = $key;
				$actions['entry'] = array(0=>$_REQUEST[$key]);
				$_SESSION["actions"] = $actions;
				
				//remove prevous external data
				if (isset($_SESSION['login_' . $key]))
					unset($_SESSION['login_' . $key]);
				break;
			}
		}
	}

header('Pragma: public');
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: pre-check=0, post-check=0, max-age=0');
header("Pragma: no-cache");
header("Expires: 0");

/* during HIS integration, includes swf/index.php into home.php */
$swfLocation = '';
$rootLocation = '../';
if (isset($_SERVER['SCRIPT_NAME']))
{
	$items = explode('/', $_SERVER['SCRIPT_NAME']);
	if (!in_array('swf', $items))
	{
		$swfLocation = 'swf/';
		$rootLocation = '';
	}
	unset($items);
}

/* verify whether the SWF file exists */
$swfName = Backend::getSwfFileByVersionAndTime();
$tmp = explode('?', $swfName);
$swfFullName = $swfLocation . $tmp[0] . '.swf';
clearstatcache(false, $swfFullName);
if (!@file_exists($swfFullName))
	exit("The file '$swfFullName' is missing in directory '" . getcwd() . "'");

/* address of the HTML Viewer for situations when Flash or JS aren't working */
$query = '';
foreach ($_REQUEST as $k => $v)
{
	if (strlen($query))
		$query .= '&';
	$query .= $k . '=' . urlencode($v);
}
if (strlen($query))
	$query = '?' . $query;
$htmlViewerLocation = dirname($_SERVER['SCRIPT_NAME']);
if ($htmlViewerLocation != '/')
	$htmlViewerLocation .= '/';
$htmlViewerLocation = str_replace('swf/', '', $htmlViewerLocation);
$htmlViewerLocation .= "md5/$query";

/* Branding */
$sys = new System($backend);
$branding = $sys->getBranding();
if ($sys->licenseIsBranding() && $branding->active() && $branding->isValid())
	$PRODUCT = $branding->getAttribute('productName');
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?php echo $PRODUCT; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="0">
<noscript>
<?php echo "<meta http-equiv=\"refresh\" content=\"7;URL=$htmlViewerLocation\">\n"; ?>
</noscript>
<script src="<?php echo $swfLocation; ?>AC_OETags.js" type="text/javascript"></script>
<script type="text/javascript">
window.name = "MedDreamFlashViewer";
var requiredMajorVersion = 9;
var requiredMinorVersion = 0;
var requiredRevision = 0;
var external = false;

function handleWheel(event)
{
	var delta = 0;
	if (!event) /* For IE. */
		event = window.event;
	if (event.wheelDelta)
	{
		/* IE/Opera. */
		delta = event.wheelDelta/120;
	}
	else
		if (event.detail)
		{
			/** Mozilla case. */
			delta = -event.detail/3;
		}
	delta = Math.round(delta);
	if (delta)
	{
		var app = window.document["meddream"];
		if (app)
		{
			var o = {x: event.screenX, y: event.screenY,
				delta: delta,
				ctrlKey: event.ctrlKey, altKey: event.altKey,
				shiftKey: event.shiftKey}

			app.handleWheel(o);
		}
	}
	if (event.preventDefault)
		event.preventDefault();
	event.returnValue = false;
}

// ENABLE if wmode=opaque, DISABLE if wmode=gpu
// DOMMouseScroll is for mozilla.
if (window.addEventListener)
	window.addEventListener('DOMMouseScroll', handleWheel, false);
// IE/Opera.
document.onmousewheel = handleWheel;
</script>
</head>

<body style="background-color: #000000; color: #ffffff; margin: 0px; overflow:hidden; scroll: no;">
<script type="text/javascript">
var hasRequestedVersion = DetectFlashVer(requiredMajorVersion, requiredMinorVersion, requiredRevision);
if (hasRequestedVersion)
{
	AC_FL_RunContent(
			"src", "<?php echo $swfLocation . $swfName ?>",
			"width", "100%",
			"height", "100%",
			"align", "middle",
			"id", "meddream",
			"quality", "high",
			"bgcolor", "#000000",
			"name", "meddream",
			"flashvars","<?php
				if (isset($_GET["session"]))
					echo "session=".$_GET["session"]."&";
				?>windowname="+window.name,
			"allowScriptAccess","sameDomain",
			"allowFullScreen","true",
			"type", "application/x-shockwave-flash",
			"pluginspage", "http://www.adobe.com/go/getflashplayer"
		);
}
else
{
	var alternateTarget = '<?php echo $htmlViewerLocation; ?>';
	window.location.href = alternateTarget;
}
</script>

<noscript>
<?php
	echo "<p><b>Caution:</b> JavaScript is turned off, MedDream will not work.</p>\n";
	echo '<p>You can try to continue manually by clicking this link: ' .
		"<a href=\"$htmlViewerLocation\">$htmlViewerLocation</a>,</p>\n";
	echo '...or <a href="' . $rootLocation . 'logoff.php">return to the login form</a>.</p>';
?>
</noscript>

<script type="text/javascript">
var clientid;

function setClientID(id)
{
	clientid = id;
}
window.onbeforeunload = function()
{
	var xmlHttpReq = null;

	if (window.XMLHttpRequest)
		xmlHttpReq = new XMLHttpRequest();
	else
		if (window.ActiveXObject)
			xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");

	if (xmlHttpReq == null) return;

	xmlHttpReq.onreadystatechange = function() { if (xmlHttpReq.readyState!=4) return false; }
	xmlHttpReq.open("GET", '<?php echo $rootLocation; ?>disconnect.php?clientid=' + clientid+'&windowname='+window.name, false);
	xmlHttpReq.send(null);
}
function closeWindow()
{
	window.open('', '_parent', '');
	window.close();
}
function keyDownHandler(e)
{
	if (document.all)
	{
		var evnt = window.event;
		x = evnt.keyCode;
	}
	else
	{
		x = e.keyCode;
	}

	if (meddream)
		meddream.keyDown(x);

	if (!document.all)
	{
		window.captureEvents(Event.KEYPRESS);
		window.onkeypress = keyDownHandler;
	}
	else
	{
		document.onkeypress = keyDownHandler;
	}
}
if (meddream)
{
	meddream.focus();
}
function addStudy(url)
{
	var uid = "";
	for (i = (url.length - 1); i >= 0 ; i--)
	{
		if (url.charAt(i) == "=") break;
		uid = url.charAt(i) + uid;
	}
	if (meddream)
	{
		meddream.addStudy(uid);
	}
}
function moveIFrame(x,y,w,h)
{
	var frameRef = document.getElementById("myFrame");
	frameRef.style.left = x;
	frameRef.style.top = y;
	var iFrameRef = document.getElementById("myIFrame");
	iFrameRef.width = w;
	iFrameRef.height = h;
}
function hideIFrame()
{
    document.getElementById("myFrame").style.visibility="hidden";
}
function showIFrame()
{
    document.getElementById("myFrame").style.visibility="visible";
}
function loadIFrame(url)
{
	if (url != "")
		document.getElementById("myFrame").innerHTML = "<iframe id='myIFrame' src='" + url + "'frameborder='0'></iframe>";
	else
		document.getElementById("myFrame").innerHTML = "";
}
var hMedReport;
function openReport(url)
{
	try
	{
		hMedReport = window.open("", "MedReport","status=1,toolbar=0,menubar=0,fullscreen=0,location=0,scrollbars=0,resizable=1,width="+screen.width+",height="+screen.height+",left=0,top=0");
		if (hMedReport.openReport)
		{
			hMedReport.focus();
			hMedReport.openReport(url);
		}
		else
		{
			hMedReport = window.open(url, "MedReport","status=1,toolbar=0,menubar=0,fullscreen=0,location=0,scrollbars=0,resizable=1,width="+screen.width+",height="+screen.height+",left=0,top=0");
			if (hMedReport)
				hMedReport.focus();
		}
	}
	catch (err)
	{
		h = window.open(url, "","status=1,toolbar=0,menubar=0,fullscreen=0,location=0,scrollbars=0,resizable=1,width=320,height=200,left="+((screen.width-320)/2)+",top="+((screen.height-200)/2)+"");
		h.focus();
	}
}
function doPrint(content)
{
	document.getElementById("printFrame").innerHTML = content;
	printContainer("printFrame");
}
function supportLocalStorage()
{
  try {
    return 'localStorage' in window && window['localStorage'] !== null;
  } catch (e)
  {
    return false;
  }
}
function getLocalStorage(storage)
{
	if (supportLocalStorage())
	{
		if (localStorage[storage])
			return localStorage[storage];
		else
			return "";
	}
	return "";
}
function setLocalStorage(storage,storagedata)
{
	if (supportLocalStorage())
		localStorage[storage] = storagedata;
}
function getSession()
{
	return document.cookie;
}
function downloadFile(url)
{
	var iframe;
	iframe = document.getElementById("hiddenDownloader");
	if (iframe == null) {
			iframe = document.createElement('iframe');
			iframe.id = "hiddenDownloader";
			iframe.style.visibility = 'none';
			document.body.appendChild(iframe);
	}
	iframe.src = url;
}
//swf do not resizes for chrome on window maximize
 window.onresize = function() {
    document.body.style.height = '100%';
}
function openSettings(){
	var winName = "MedDreamSettings";
	var url = "<?php echo $rootLocation;?>md5/settings.html";
	var win = window.open("", winName);
	try{
		if(typeof win.MD != "object") {
			win.document.location = url;
		}
	}catch(e) {
		win = window.open(url, "_blank");
		win.name = winName;
	}
	win.focus();
}
</script>
<div id="myFrame" style="position:absolute;background-color:transparent;border:0px;visibility:hidden;"></div>
<div id="printFrame"></div>
<script type="text/javascript" src="<?php echo $swfLocation; ?>js/jquery-1.7.2.min.js" charset="utf-8"></script>
<script type="text/javascript" src="<?php echo $swfLocation; ?>js/jquery.jqprint-0.3.js" charset="utf-8"></script>
<script type="text/javascript">
	$(window).bind("storage", function (e) {
	if (e.originalEvent.key === "logoff") {
		 if(localStorage["logoff"] != undefined)
			localStorage.removeItem("logoff");
		window.close();	
	}
});
</script>
</body>
</html>
