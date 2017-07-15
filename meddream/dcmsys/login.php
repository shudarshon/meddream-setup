<?php

use Softneta\MedDream\Core\Logging;

function headerCallback($ch, $line)
{
    if (preg_match('/^Set-Cookie: suid=\s*([^;]*)/mi', $line, $cookie) == 1)
    {
        $_COOKIE['suid'] = $cookie[1];
        setcookie('suid', $cookie[1]);
    }
    return strlen($line);
}

function dcmsys_login($user, $password, $db_host)
{
$root_dir = __DIR__ . '/..';
include_once("$root_dir/autoload.php");
$log = new Logging();

//$log->asDump(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $db_host.'login');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, 'login='.$user.'&password='.$password);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  //follow by header location
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HEADER, true);          //get header (not head) of site
//curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //can get protected content SSL
$tmpfname = "$root_dir/log/cookie_$user.txt";
curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);   //set cookie to skip site ads
curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, "headerCallback");

$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$log->asDump("(dcmsys/login.php) Autentication result:", $result);
$log->asDump("(dcmsys/login.php) Autentication httpcode:", $httpcode);
$log->asDump("(dcmsys/login.php) kita  $user, $password, $db_host:");

curl_close($ch);
if ($httpcode === 200 || $httpcode === 202)
{
    return true;
}
else
{
    return false;
}

//echo $httpcode . $result;
}
?>
