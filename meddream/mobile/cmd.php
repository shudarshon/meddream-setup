<?php

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\System;

session_start();

$rootDirPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $rootDirPath . 'autoload.php';
include_once $rootDirPath . 'sharedData.php';


$log = new Logging();
$moduleName = 'mobile/cmd.php';
$constants = new Constants();
$backend = null;

if (isset($_REQUEST['cmd']))
    $cmd = $_REQUEST['cmd'];
else
    $cmd = NULL;
switch ($cmd)
{
    case 'system':
    {
        $sys = new System();

        $fileContents = str_replace(array("\n", "\r", "\t"), '', $sys->license($_REQUEST['id']));

        $fileContents = trim(str_replace('"', "'", $fileContents));

        $simpleXml = simplexml_load_string($fileContents);

        $simpleXml->addChild('connections', $sys->connections());

        $json = json_encode($simpleXml);

        echo $json;

    }
        break;

    case 'loadSeriesData':
    {
        $audit = new Audit('OPEN SERIES');

        $study='';
        $studyUID = $_POST['study'];
        $backend = getBackend(array('Structure'));
        if ($studyUID != "")

            $study = $backend->pacsStructure->studyGetMetadata($studyUID);

        // file_put_contents('er_log.txt', $key.' '.$_POST['study'].' '.SHOW_DB.' '.SHOW_USER.' '.SHOW_PASSWORD);

        for($i= 0; $i<$study['count']; $i++)
        {
            if($study[$i]['id'] == $_POST['series'])
            {
                $series = $study[$i];
            }
        }
        echo json_encode($series);
        $audit->log(true, $_POST['series']);
    }
        break;

    case 'getImageData':
    {
        $log->asDump("meddream_extract_meta('.\\', '" . $_POST['path'] . "', 0)");
        $meta = meddream_extract_meta('.\\',  $_POST['path'], 0);
        $log->asDump('meddream_extract_meta: ', $meta);
        echo json_encode($meta);
    }
        break;

    case 'loadImageRawData':
    {
        $pixels = null;
        $log->asDump("meddream_get_raw('" . $_POST['path'] . "')");
        $tags = meddream_get_raw($rootDirPath, $_POST['path'], $pixels);
        $log->asDump('meddream_get_raw: ', $tags);
        $result['tags'] = validateInstanceMetaData($tags);

        if($result['tags']['windowcenter'] == 0 &&  $result['tags']['windowwidth'] == 0)
        {
            $result['tags']['windowcenter']  = ( $result['tags']['pixelmax'] +  $result['tags']['pixelmin']) / 2 + $result['tags']['rescaleintercept'];
            $result['tags']['windowwidth'] =  $result['tags']['pixelmax'] -  $result['tags']['pixelmin'];
        }

        $result['pixels'] = base64_encode($pixels);
        echo json_encode($result);
    }
        break;

    case 'loadImageJpgData':
    {
		$backend = getBackend(array(), false);
        $pixels = null;
        $log->asDump("meddream_get_raw('" . $_POST['path'] . "')");
        $tags = meddream_get_raw($rootDirPath, $_POST['path'], $pixels);
        $log->asDump('meddream_get_raw: ', $tags);
        $result['tags'] = validateInstanceMetaData($tags);

        $uid = 'md-' . rand(10000, 99999);		/* TEMPORARY; a correct UID is needed in $_REQUEST */


        $width = $result['tags']['columns'];
        $height = $result['tags']['rows'];

        if(isset($_POST['size']))
        {
            $size =  $_POST['size'];
        }
        else
        {
            $size =  $width > $height ? $width : $height;
        }

        if(isset($_POST['maxMP']))
        {
            $scaleKoef = calculateImageScaleKoef($size, $_POST['maxMP']);
            $size = intval($size * $scaleKoef);
            $result['tags']['columns'] = intval($width * $scaleKoef);
            $result['tags']['rows'] = intval($height * $scaleKoef);
        }


        if($result['tags']['windowcenter'] == 0 &&  $result['tags']['windowwidth'] == 0)
        {
            $result['tags']['windowcenter']  = ( $result['tags']['pixelmax'] +  $result['tags']['pixelmin']) / 2 + $result['tags']['rescaleintercept'];
            $result['tags']['windowwidth'] =  $result['tags']['pixelmax'] -  $result['tags']['pixelmin'];
        }


        $tempPath = dirname(__FILE__)."/temp/";
        $thumbnail = $tempPath.$uid.".image-tmp.jpg";
        $thumbnail = null;

        $log->asDump("meddream_thumbnail('" . $_POST['path'] . "', '$thumbnail', '$rootDirPath', '" .
            $result['xfersyntax'] . "', " . $result['bitsstored'] . ', ' . $result['windowcenter'] . ', ' .
            $result['windowwidth'] . ', ' .$backend->enableSmoothing. ')');

        $r = meddream_thumbnail($_POST['path'], $thumbnail, $rootDirPath, $size, $result['tags']['xfersyntax'], $result['tags']['bitsstored'],  $result['tags']['windowcenter'], $result['tags']['windowwidth'], $backend->enableSmoothing); //L, W // windowcenter windowwidth
        $log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

        $r = substr($r, 5);
        $r = imagecreatefromstring($r);

        $quality = isset($_POST['quality']) ? $_POST['quality'] :  60;

        ob_start();
        imagejpeg($r, NULL, $quality);
        $img = ob_get_contents();
        ob_end_clean();

        $result['pixels'] = base64_encode( $img );


        echo json_encode($result);
    }

    case 'loadImageData':
    {
        $audit = new Audit('VIEW IMAGE');
        $pixels = null;
        $log->asDump("meddream_get_raw('" . $_POST['path'] . "')");
        $result = meddream_get_raw($rootDirPath, $_POST['path'], $pixels);
        $log->asDump('meddream_get_raw: ', $result);
        $result = validateInstanceMetaData($result);
		if ($result['error'])
		{
			$audit->log(false, $_POST['path']);
			$result = array('error' => 'Processing failed, code ' . $result['error']);
			echo json_encode($result);
			break;
		}
		else
			$result['error'] = '';
        $renderMode = $_POST['mode'];

        if($renderMode === '2' && isset($_POST['maxMP']))
        {
            $maxMP = $_POST['maxMP'];
            if($maxMP * 1000000 <  $result['columns'] * $result['rows'])
            {
                $result['message'] = 'Can\'t displayed the full image quality, because size ('.$result['columns'].' x '.$result['rows'].') is too large. The image will be reduced and displayed as a JPEG';
                $renderMode = '1';
            }
        }

        $result['origColumns'] = $result['columns'];
        $result['origRows'] =  $result['rows'];

        if( $result['windowcenter'] == 0 &&  $result['windowwidth'] == 0)
        {
            $result['windowcenter']  = ( $result['pixelmax'] * $result['rescaleslope'] +  $result['pixelmin'] * $result['rescaleslope']) / 2 + $result['rescaleintercept'];
            $result['windowwidth'] =  $result['pixelmax'] * $result['rescaleslope'] -  $result['pixelmin'] * $result['rescaleslope'];
        }

        if($renderMode === '1' || $result['bitsstored'] === 8 )//$result['samplesperpixel'] === 3)
        {
            $uid = 'md-' . rand(10000, 99999);
            $width = $result['columns'];
            $height = $result['rows'];

            if(isset($_POST['size']))
            {
                $size =  $_POST['size'];
            }
            else
            {
                $size =  $width > $height ? $width : $height;
            }

            if(isset($_POST['maxMP']))
            {
                $scaleKoef = calculateImageScaleKoef($size, $_POST['maxMP']);
                $size = intval($size * $scaleKoef);
                $result['columns'] = intval($result['columns'] * $scaleKoef);
                $result['rows'] = intval($result['rows'] * $scaleKoef);
                $result['pixelspacing'][0] = $result['pixelspacing'][0] / $scaleKoef;
                $result['pixelspacing'][1] = $result['pixelspacing'][1] / $scaleKoef;

            }

            $tempPath = dirname(__FILE__)."/temp/";
            $thumbnail = $tempPath.$uid.".image-tmp.jpg";
            $thumbnail = null;

            $log->asDump("meddream_thumbnail('" . $_POST['path'] . "', '$thumbnail', '$rootDirPath', '" .
                $result['xfersyntax'] . "', " . $result['bitsstored'] . ', ' . $result['windowcenter'] . ', ' .
                $result['windowwidth'] . ')');
            $r = meddream_thumbnail($_POST['path'], $thumbnail, $rootDirPath, $size, $result['xfersyntax'], $result['bitsstored'],  $result['windowcenter'], $result['windowwidth'], true); //L, W // windowcenter windowwidth
            $log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

            $r = substr($r, 5);
            $r = imagecreatefromstring($r);

            $quality = isset($_POST['quality']) ? $_POST['quality'] :  100;

            ob_start();
            imagejpeg($r, NULL, $quality);
            $img = ob_get_contents();
            ob_end_clean();

            $result['pixels'] = "data:image/jpeg;base64,".base64_encode( $img );

            $result['pixelmax'] = 256;  //todo pasitikrnti
            $result['pixelmin'] = 0;
            $result['rescaleintercept'] = 0;
            $result['windowcenter']  = 128;
            $result['windowwidth'] =  256;
            $result['rescaleslope'] = 1; //todo pasitikrnti ar galima

        }
        else
        {
            $result['pixels'] = base64_encode($pixels);
        }

        $result['renderMode'] = intval($renderMode);

        echo json_encode($result);
		$audit->log(true, $_POST['path']);
    }
        break;


    case 'loadStudySearch':
    {
        if(isset($_REQUEST['patientId']))
        {
            $searchCriteria[0] = array();
            $searchCriteria[0]['name'] = "patientid";
            $searchCriteria[0]['text'] = $_REQUEST['patientId'];
        }
        if(isset($_REQUEST['patientName']))
        {
            $searchCriteria[1] = array();
            $searchCriteria[1]['name'] = "patientname";
            $searchCriteria[1]['text'] = $_REQUEST['patientName'];
        }
        if(isset($_REQUEST['description']) && $_REQUEST['description'] != '')
        {
            $searchCriteria[2] = array();
            $searchCriteria[2]['name'] = "description";
            $searchCriteria[2]['text'] =  $_REQUEST['description'];
        }
        $selectedModality = array();
        if(isset($_REQUEST['modality']))
            if($_REQUEST['modality'][0] !== 'All' )
            {
                $allModality = array('CR', 'CT', 'DX', 'ECG', 'ES', 'IO', 'MG', 'MR', 'NM', 'OT', 'PX', 'RF', 'RG', 'SC', 'US', 'XA', 'XC');
                $i = 0;
                foreach ($allModality as $oneOfAllModality)
                {
                    $selectedModality[$i] = array();
                    $selectedModality[$i]['name'] = $oneOfAllModality;
                    $selected = false;
                    foreach ($_REQUEST['modality'] as $oneOfSelectedModality)
                    {
                        if($oneOfSelectedModality == $oneOfAllModality)
                            $selected = true;
                    }
                    $selectedModality[$i]['selected'] = (bool) $selected;
                    $i ++;
                }
            }

        $fromDate = NULL;  $toDate = NULL;

        if($_REQUEST['dateFilterType'] === 'period')
        {
            if(isset($_REQUEST['datePeriod']))
                if($_REQUEST['datePeriod'] != 'Any')
                {
                    $fromDate = date("Y-m-d",strtotime(date("Y-m-d").$_REQUEST['datePeriod']));
                    $toDate = date("Y-m-d");
                }
        }
        else if(isset($_REQUEST['startDate']) && isset($_REQUEST['endDate']))
        {
            $fromDate = $_REQUEST['startDate'];
            $toDate = $_REQUEST['endDate'];
        }

        $listMax = 100;

        $backend = getBackend(array('Search'));
        $result = $backend->pacsSearch->findStudies(array(), $searchCriteria, $fromDate, $toDate, $selectedModality, $listMax);

        echo json_encode($result);
    }
        break;


    case 'loadStudyList':
    {
        $audit = new Audit('OPEN STUDY');
        $study='';
        $studyUID = $_POST['study'];
		$backend = getBackend(array('Structure', 'Preload'));

        if(($backend->pacs == 'DCM4CHEE-ARC' ||  $backend->pacs == 'DCM4CHEE') && strpos($studyUID, '.') >= -1 )
        {
            $key = '';
            check_study_uid($studyUID, $key);
            $studyUID = $key;
        }


        /* todo padaryti kad id keistu external.php
                $external = @file_exists($rootDirPath."external.php");
                if ($external)
                 {
       include_once($rootDirPath.'external.php');
                    if(strpos($studyUID, '.') >= -1 )
                    {
                     //externalAuthentication();
                     externalLoginInfo($authDB->db_host, $authDB->user, $authDB->password);
                     $key='';
                     check_study_uid($studyUID, $key);
                  // file_put_contents('er_log.txt',$studyUID.$key);
                     $studyUID = $key;
                    }
                  }
          */

        $study = $backend->pacsStructure->studyGetMetadata($studyUID);

        if($study['count'] > 1)
        {
            for($i = 0; $i < $study['count']; $i++)
            {
                $list[$i]['id'] = $study[$i]['id'];
                $list[$i]['count'] = $study[$i]['count'];
                $list[$i]['imageId'] = $study[$i][0]['id'];
                $list[$i]['modality'] = $study[$i]['modality'];
                $list[$i]['description'] = $study[$i]['description'];
                $list[$i]['xfersyntax'] = $study[$i][0]['xfersyntax'];
                $list[$i]['bitsstored'] = $study[$i][0]['bitsstored'];
                $list[$i]['numframes'] = $study[$i][0]['numframes'];
                $list[$i]['src'] =  'data:image/png;base64,'.base64_encode( writeThumbnail(0, '', $study, $i, 0, 150));
            }
            $list['count'] = $study['count'];
            $list['type'] = 'vertical';
            $list['studyId'] = $studyUID;
        } else
        {
            if($study[0]['count'] > 1)
            {
                for($i = 0; $i < $study[0]['count']; $i++)
                {
                    $list[$i]['id'] = $study[0]['id'];
                    $list[$i]['imageId'] = $study[0][$i]['id'];
                    $list[$i]['no'] = $i;
                    $list[$i]['modality'] = $study[0]['modality'];
                    $list[$i]['src'] =   'data:image/png;base64,'.base64_encode( writeThumbnail(0, '', $study, 0, $i, 150));
                    $list[$i]['xfersyntax'] = $study[0][$i]['xfersyntax'];
                    $list[$i]['bitsstored'] = $study[0][$i]['bitsstored'];
                    $list[$i]['numframes'] = $study[0][$i]['numframes'];
                }
                $list['count'] = $study[0]['count'];
                $list['type'] = 'horizontal';
                $list['studyId'] = $studyUID;
            }
            else
            {
                $list['studyId'] = $studyUID;
                $list['id'] = $study[0]['id'];
                $list['type'] = 'none';
                $list['imageId'] = $study[0][0]['id'];
                $list['no'] = 1;
                $list['modality'] = $study[0]['modality'];
                $list['xfersyntax'] = $study[0][0]['xfersyntax'];
                $list['bitsstored'] = $study[0][0]['bitsstored'];
                $list['numframes'] = $study[0][0]['numframes'];
            }
        }

        //  file_put_contents('er_log.txt', print_r($list, true));


        echo json_encode($list);
        $audit->log(true, $studyUID);
    }
        break;
    case '':
        echo json_encode('{error:0}');
        break;
}


function writeThumbnail($clientid, $out, $study, $s, $i, $thumbnailSize)
{

    global $backend, $rootDirPath, $constants, $log;

    if (isset($study[$s]['modality']))
        if ($study[$s]['modality'] == 'SR')
            return '0';

	/* we might have duplicates in $uuid */
	$uuid = $study[$s][$i]['id'];
	$len = strpos($uuid, "*");
	if ($len !== false)
		$uuid = substr($uuid, 0, $len);

    $tempPath = $rootDirPath."/temp/";

    $bitsstored = $study[$s][$i]["bitsstored"];
    $xfersyntax = $study[$s][$i]["xfersyntax"];

    if ($bitsstored == "") $bitsstored = 8;
    if ($thumbnailSize > 150)
        $thumbnailSize = 150;
    if ($thumbnailSize < 50)
        $thumbnailSize = 50;
    if ($study[$s]["count"] == 1)
        $thumbnailSize = 150;

    $thumbnail = $tempPath.$uuid.".thumbnail-".$thumbnailSize.".jpg";
    $path = str_replace("\\", "/", $study[$s][$i]['path']);

    $pathJPG = '';
    if ($constants->FDL && !Constants::DL_REGENERATE)
    {
        $pathJPG = $path.".thumbnail-".$thumbnailSize.".jpg";
        $need_new = !file_exists($pathJPG);
        if (!$need_new)
        {
            $r = file_get_contents($pathJPG);
            $log->asInfo("cached thumbnail: '$pathJPG'");
        }
    }
    else
        $need_new = true;

    if ($need_new)
    {
        if (!file_exists($thumbnail))
        {
			$r = '';
			$fi = $backend->pacsPreload->fetchInstance($study[$s][$i]['id'], $study[$s]['id'], $study['uid']);
			if (is_string($fi))
				$path = $fi;
			else
				if (!is_null($fi))
					/* simulate an error from meddream_thumbnail, which is in form "*ERR:<numeric code>".
					   md-swf displays the part after "*ERR:" in a pop-up message.
					 */
					$r = '*ERR:cache failure, see logs';

			if (!strlen($r))
			{
				if ($xfersyntax == 'jpg')
				{
					$log->asInfo("thumbnailFromJpeg('$path', $thumbnailSize)");
					$r = thumbnailFromJpeg($path, $thumbnailSize);
					if (strlen($r) == 1)		/* $r is the error location */
					{
						$log->asDump("thumbnailFromJpeg: $r");
						$r = '';
					}
				}
				else
				{
					$log->asInfo("meddream_thumbnail('$path', '$thumbnail', $thumbnailSize, '$xfersyntax', $bitsstored)");
					$r =  meddream_thumbnail($path, $thumbnail, $rootDirPath, $thumbnailSize, $xfersyntax,
						$bitsstored, 0, 0, $backend->enableSmoothing);
					$log->asDump("meddream_thumbnail: " . var_export(substr($r, 0, 6), true));
					if (strlen($r) > 0)
					{
						if ($r[0] == "E")			/* path to an already existing thumbnail */
						{
							$r = substr($r, 5);
							if (file_exists($r))
								$r = file_get_contents($r);
							else
								$r = "";
						}
						else
							if ($r[0] == "2")		/* GD2 data */
							{

								$r = substr($r, 5);
								$r = imagecreatefromstring($r);
								ob_start();
								imagejpeg($r, NULL, 90);
								$r = ob_get_contents();
								ob_end_clean();
								// imagedestroy($r);
							}
						/* otherwise return as is; for example, it could be '?PDF' -- indication
						   to display a PDF icon
						 */
					}
					else
						$log->asErr('meddream_thumbnail failed');
				}

				/* remove the source file that might be created by fetchInstance() */
				$backend->pacsPreload->removeFetchedFile($path);
			}
        }
        else
            $r = file_get_contents($thumbnail);
        if ($constants->FDL)
            file_put_contents($pathJPG, $r);
    }
    return $r;
}

function calculateImageScaleKoef($size, $maxMP) //todo pasitobulinti
{
    $maxMP = $maxMP * 1000000;
    if( $size * $size > $maxMP)
        for($i = 1; $i > 0; $i =  $i - 0.05)
        {
            if( ($size* $i) * ($size * $i) < $maxMP)
            {
                return  $i;
            }
        }
    return 1;
}


function check_study_uid($uid, &$key)
{
    global $backend;
	$authDB = $backend->authDB;

    if (!$authDB->connect($authDB->db_host, $authDB->user, $authDB->password))
        return 2;
    if (strpos($uid, '.') !== false)
        $sql = "SELECT pk FROM study WHERE study_iuid='" . $authDB->sqlEscapeString($uid) . "'";
    else
        $sql = "SELECT pk FROM study WHERE pk='" . $authDB->sqlEscapeString($uid) . "'";
    $rs = $authDB->query($sql);
    if (!$rs)
        return 3;
    if ($row = $authDB->fetchNum($rs))
    {
        $key = $row[0];
        return 0;
    }
    return 1;
    // hints (increasing severity for end users): 1 - no record, 2 - connect failed, 3 - wrong query
}


/**
 * return new or existing Backend
 * If the underlying AuthDB must be connected to the DB, then will request the connection once more.
 *
 * @global Backend $backend
 * @param array $parts - Names of PACS parts that will be initialized
 * @param boolean $withConnection - is a DB connection required?
 * @return Backend
 */
function getBackend($parts = array(), $withConnection = true)
{
	global $backend, $log;

	if (is_null($backend))
	{
		$backend = new Backend($parts, $withConnection, $log);

		if (!$backend->authDB->isAuthenticated())
		{
			$log->asErr("$moduleName: not authenticated");
//			header('Location:index.php');
//			exit;
			$backend->authDB->logoff();
			$backend->authDB->goHome(false);
		}
	}
	else
		$backend->loadParts($parts);

	if (!$backend->authDB->isConnected() && $withConnection)
		$backend->authDB->reconnect();

	return $backend;
}


function validateInstanceMetaData($data)
{
    if (!isset($data['columns']))
        $data['columns'] = 0;
    if (!isset($data['rows']))
        $data['rows'] = 0;
    if (!isset($data['samplesperpixel']))
        $data['samplesperpixel'] = 0;
    if (!isset($data['bitsstored']))
        $data['bitsstored'] = 0;
    if (!isset($data['photometric']))
        $data['photometric'] = '';
    if (!isset($data['xfersyntax']))
        $data['xfersyntax'] = '';
    if (!isset($data['windowcenter']))
        $data['windowcenter'] = 0;
    if (!isset($data['windowwidth']))
        $data['windowwidth'] = 0;
    if (!isset($data['rescaleslope']))
        $data['rescaleslope'] = 1;
    if (!isset($data['rescaleintercept']))
        $data['rescaleintercept'] = 0;
    if (!isset($data['pixelspacing'][0]))
        $data['pixelspacing'][0] = 0;
    if (!isset($data['pixelspacing'][1]))
        $data['pixelspacing'][1] = 0;
    if (!isset($data['slicethickness']))
        $data['slicethickness'] = 1;
    if (!isset($data['frametime']))
        $data['frametime'] = 66.66;
    if (!isset($data['imageposition'][0]))
        $data['imageposition'][0] = 0;
    if (!isset($data['imageposition'][1]))
        $data['imageposition'][1] = 0;
    if (!isset($data['imageposition'][2]))
        $data['imageposition'][2] = 0;
    if (!isset($data['imageorientation'][0]))
        $data['imageorientation'][0] = 0;
    if (!isset($data['imageorientation'][1]))
        $data['imageorientation'][1] = 0;
    if (!isset($data['imageorientation'][2]))
        $data['imageorientation'][2] = 0;
    if (!isset($data['imageorientation'][3]))
        $data['imageorientation'][3] = 0;
    if (!isset($data['imageorientation'][4]))
        $data['imageorientation'][4] = 0;
    if (!isset($data['imageorientation'][5]))
        $data['imageorientation'][5] = 0;
    if (!isset($data['gantrytilt']))
        $data['gantrytilt'] = 0;
    if (!isset($data['pixelmax']))
        $data['pixelmax'] = 256;  //maxPixelValue = Math.pow(2, bitsStored)
    if (!isset($data['pixelmin']))
        $data['pixelmin'] = 0;
    if (!isset($data['wlpixelmax']))
        $data['wlpixelmax'] = 0;  //maxPixelValue = Math.pow(2, bitsStored)
    if (!isset($data['wlpixelmin']))
        $data['wlpixelmin'] = 0;

    if (($data['samplesperpixel'] === 3 || $data['bitsstored'] === 8)){
        $data['pixelmax'] = 256;
        $data['pixelmin'] = 0;
        $data['rescaleintercept'] = 0;
        $data['windowcenter'] = 128;
        $data['windowwidth'] = 256;
        $data['rescaleslope'] = 1;
        $data['proctime'] = 0;
    }

    if ($data['wlpixelmin'] == 0 && $data['wlpixelmax'] == 0 )
    {
        $data['wlpixelmin'] = $data['pixelmin'];
        $data['wlpixelmax'] = $data['pixelmax'];
    }

    //report view
    if ($data['windowwidth'] == 1)
        $data['windowcenter'] = $data['windowcenter'] - 6;

    if ($data['rescaleslope'] == 0)
        $data['rescaleslope'] = 1;

    if ($data['windowcenter'] == 0 && $data['windowwidth'] == 0) //&& $data['xfersyntax'] != '1.2.840.10008.1.2.5')
    {
        $data['windowcenter'] = ( $data['wlpixelmax'] * $data['rescaleslope'] + $data['wlpixelmin'] * $data['rescaleslope']) / 2 + $data['rescaleintercept'];
        $data['windowwidth'] = $data['wlpixelmax'] * $data['rescaleslope'] - $data['wlpixelmin'] * $data['rescaleslope'];
    }

    if (($data['photometric'] === 'PALETTE COLOR') || $data['error'] != '0')
    {
        $data['samplesperpixel'] = 3;
    }

    if (($data['xfersyntax'] == '1.2.840.10008.1.2.4.91') && ($data['bitsstored'] > 8))
    {
        $data['samplesperpixel'] = 1;
    }

    if (($data['samplesperpixel'] === 3 /* ||  $result['bitsstored'] === 8 */))   //todo .. = 3 pakeiti i $data['xfersyntax'] == "1.2.840.10008.1.2.4.50" ir patestuoti
    {
        $data['pixelmax'] = 255;
        $data['pixelmin'] = 0;
        $data['windowcenter'] = 127;
        $data['windowwidth'] = 255;
        $data['rescaleslope'] = 1;
        $data['rescaleintercept'] = 0;
    }
    return $data;
}
