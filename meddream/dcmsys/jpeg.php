<?php

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;

if (!strlen(session_id()))
    session_start();

$root_dir = __DIR__ . '/..';
include_once("$root_dir/autoload.php");

$backend = new Backend(array(), false);
$authDB = $backend->authDB;

$log = new Logging();



$url = 'https://localhost/WADO?requestType=WADO&studyUID='.$_REQUEST['studyUID'].'&seriesUID='.$_REQUEST['seriesUID'].'&objectUID='.$_REQUEST['objectUID'].'&contentType=image/jpeg;';
if(isset($_REQUEST['windowWidth']) && isset($_REQUEST['windowCenter']))
{
    $url .= '&windowWidth='.$_REQUEST['windowWidth'].'&windowCenter='.$_REQUEST['windowCenter'];
}
if(isset($_REQUEST['imageQuality']))
{
    $url .= '&imageQuality='.$_REQUEST['imageQuality'];
}
if(isset($_REQUEST['rows']) && isset($_REQUEST['columns']))
{
    $url .= '&rows='.$_REQUEST['rows'].'&columns='.$_REQUEST['columns'];
}
if(isset($_REQUEST['storage']))
{
    $url .= '&storage='.$_REQUEST['storage'];
}
if(isset($_REQUEST['frameNumber']))
{
    $url .= '&frameNumber='.$_REQUEST['frameNumber'];
}

$log->asDump("(dcmsys/jpeg.php) JPEG request:  ", $url);

//file_put_contents( 'aa.txt' , $url );
//&windowWidth=1&windowCenter=200'
//&annotation=patient,technique&rows=512&columns=512&region=0.3,0.4,0.5,0.5&imageQuality=100'
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //can get protected content SSL

if (isset($_COOKIE['suid']))
{
    curl_setopt( $ch, CURLOPT_COOKIE, 'suid='.$_COOKIE['suid'] );
}
else
{
    $tmpfname = "$root_dir/log/cookie_" . $authDB->getAuthUser() . '.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);   //set cookie to skip site ads
    curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
}

$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpcode === 200)
{
     $log->asDump("(dcmsys/jpeg.php) JPEG response successfully completed");
     header("Content-Type: image/jpeg");
     echo $result;
}
else
{
	$log->asDump(" (dcmsys/jpeg.php) JPEG response error: ",
		'http code: ' . $httpcode . ' error: ' . curl_error($ch));
    echo 'http code: '.$httpcode.' error: '.curl_error($ch);
}
curl_close($ch);
?>
