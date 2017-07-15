<?php
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\DICOM\DicomTagParser;
use Softneta\MedDream\Core\DicomTags;
use Softneta\MedDream\Core\PresentationStateHandler;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Study;
use Softneta\MedDream\Core\SR;
use Softneta\MedDream\Core\System;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Jobs;
use Softneta\MedDream\Core\HttpUtils;

define('PATH_TO_ROOT', __DIR__ . DIRECTORY_SEPARATOR);
$moduleName = basename(__FILE__); /* for logging */
$basedir = __DIR__; /* for meddream_thumbnail etc */

ini_set('memory_limit', '512M');

require_once(PATH_TO_ROOT . 'autoload.php');
include_once(PATH_TO_ROOT . 'sharedData.php');
include_once(PATH_TO_ROOT . 'dcmsys/quidoSearch.php');

session_start();

$log = null;
$backend = null;
$constants = new Constants();
if (isset($_REQUEST['cmd']))
	$cmd = $_REQUEST['cmd'];
else
	$cmd = '';

switch ($cmd)
{
	case 'getSettings':
		{
			$backend = getBackend(array('Auth', 'Structure'), false);
			$system = new System($backend);

			$log = getLogging();
			$settings = '';
			$arr = $system->readSettingsJsonToArray();
			$arr['qr'] = array();
			$system->addQrConfig($arr['qr']);
			$system->addPrivilegies($arr);
			$system->addToolLinks($arr);
			$system->addShareConfig($arr);

			$arr['debug'] = $log->isLoggingLevelEnabled(Logging::LEVEL_DEBUG);

			if ($backend->pacs == 'DCMSYS')
			{
				if (!isset($arr['dicomView']))
					$arr['dicomView'] = array();
				$arr['dicomView']['defaultViewer'] = '0';
				if (!isset($arr['html']))
					$arr['html'] = array();
				$arr['html']['studyData'] = 2;
				$arr['html']['thumbnail'] = 2;
				$arr['html']['id'] = 'DCMSYS';
			}

			$arr = removeEmptyArrayValues($arr);
			$settings = safeEncode($arr);

			$log->asDump('Settings = ', $arr);
			echo $settings;
		}
		break;

	case 'SaveSettings':
		{
			$return = array('error' => 'empty or bad settings');
			if (!empty($_REQUEST['data']))
			{
				$data = json_decode($_REQUEST['data'], true);
				$log = getLogging();
				$log->asDump('Settings to save = ', $data);
				if (!empty($data))
				{
					/**
					 * remove system configuration settings
					 */
					if (isset($data['privileges']))
						unset($data['privileges']);
					if (isset($data['debug']))
						unset($data['debug']);
					if (isset($data['qr']))
						unset($data['qr']);
					if (isset($data['toolLinks']))
						unset($data['toolLinks']);
					if (isset($data['share']))
						unset($data['share']);
					$backend = getBackend(array(), false);
					$system = new System($backend);
					$return['error'] = $system->saveSettings(safeEncode($data));
				}
			}
			echo safeEncode($return);
		}
		break;

	case 'setLanguage':
		{
			$lng = null;
			if (isset($_REQUEST['lan']))
				$lng = $_REQUEST['lan'];
			$log = getLogging();
			$log->asDump("begin $moduleName|$cmd(", $lng, ')');
			if (is_null($lng))
				$return = array('error' => 'missing parameter "lan"');
			else
			{
				$backend = getBackend(array(), false);
				$system = new System($backend);
				$return = $system->updateLanguage($lng);

				/* the function above was meant for Flash and returns the whole
				   XML-format translation file. No need to waste traffic.
				 */
				if (isset($return['languages']))
					unset($return['languages']);
			}
			echo safeEncode($return);
			$log->asDump("end $moduleName|$cmd");
		}
		break;

	case 'userIsAuthenticated':
		{
			$backend = getBackend(array(), false);
			/* $url = '';
			  if (!$backend->authDB->isAuthenticated())
			  {

			  if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']))
			  $url = 'https://';
			  else
			  $url = 'http://';

			  $url .= $_SERVER['HTTP_HOST'] . str_replace("\\", "/", dirname(dirname($_SERVER['PHP_SELF'])));

			  header('Cache-Control: no-cache');
			  header('Pragma: no-cache');
			  header('Location: ' . $url);
			  } */

			$result = array();

			$result['clientId'] = '';
			if ($backend->authDB->isAuthenticated() && isset($_SESSION['clientIdMD']))
				$result['clientId'] = $_SESSION['clientIdMD'];
			$result['isAuthenticated'] = $backend->authDB->isAuthenticated();

			echo json_encode($result);
		}
		break;

	case 'counts':
		{
			$log = getLogging();
			$log->asDump("begin $moduleName|$cmd");
			$backend = getBackend(array('Search'));
			$return = $backend->pacsSearch->getStudyCounts();
			echo json_encode($return);
			$log->asDump("end $moduleName|$cmd");
		}
		break;

	case 'system':
		{
			$sys = new System();

			$fileContents = str_replace(array("\n", "\r", "\t"), '',
				$sys->license($_REQUEST['id']));

			$fileContents = trim(str_replace('"', "'", $fileContents));

			$simpleXml = simplexml_load_string($fileContents);

			$simpleXml->addChild('connections', $sys->connections());

			$json = json_encode($simpleXml);
			echo $json;
		}
		break;

	case 'branding':
		{
			$sys = new System();
			$sys->addBranding($return);
			if (!empty($return['branding']))
				echo safeEncode($return['branding']);
			else
				echo '{}';
		}
		break;

	/*case 'loadSeriesData': //todo 5.0 patikrinti ar niekur nenaudojamas, jeigu ne i�mesti
		{
			$study = '';
			$studyUID = $_POST['study'];
			$st = new Study();
			if ($studyUID != "")
				$study = $st->getStudyList($studyUID);

			for ($i = 0; $i < $study['count']; $i++)
			{
				if ($study[$i]['id'] == $_POST['series'])
				{
					$series = $study[$i];
				}
			}
			echo json_encode($series);
		}
	break;*/

	case 'loadStudy':
		{
			$log = getLogging();
			$studyUID = "";
			$seriesUID = "";
			$imageUID = "";

			$auditAction = "";
			$auditObject = "";

			$backend = getBackend(array('Structure'));
			$study = "";
			$cached = 0;

			if (isset($_REQUEST['cached']))
				$cached = $_REQUEST['cached'];

			if (isset($_GET['study']))
				$studyUID = urldecode($_GET['study']);
			else
			if (isset($_POST['study']))
				$studyUID = $_POST['study'];
			else
			if (isset($_GET['series']))
				$seriesUID = urldecode($_GET['series']);
			else
			if (isset($_POST['series']))
				$seriesUID = $_POST['series'];
			else
			if (isset($_GET['image']))
				$imageUID = urldecode($_GET['image']);
			else
			if (isset($_POST['image']))
				$imageUID = $_POST['image'];

			if ($studyUID != "")
			{
				$study = $backend->pacsStructure->studyGetMetadata($studyUID, false, $cached == 1);
				if (!$cached)
				{
					$auditAction = 'OPEN STUDY';
					$auditObject = $studyUID;
				}
			}
			elseif ($seriesUID != "")
			{
				$seriesList = explode("|", $seriesUID);
				$study = $backend->pacsStructure->studyGetMetadataBySeries($seriesList);
				$log->asDump('getSeriesList: ', $study);
				$auditAction = 'OPEN SERIES';
				$auditObject = $seriesUID;
			}
			elseif ($imageUID != "")
			{
				$imageList = explode("|", $imageUID);
				$study = $backend->pacsStructure->studyGetMetadataByImage($imageList, $cached == 2);
				$log->asDump('getImageList: ', $study);
				if (!$cached)
				{
					$auditAction = 'OPEN IMAGE';
					$auditObject = $imageUID;
				}
			}

			$audit = new Audit($auditAction);

			if (strlen($study['error']))
			{
				$protocol = 'HTTP/1.0';
				if (isset($_SERVER['SERVER_PROTOCOL']))
					$protocol = $_SERVER['SERVER_PROTOCOL'];
				header($protocol . ' 500 Internal Server Error');

				echo safeEncode(array('error' => $study['error']));
				$audit->log(false, $auditObject);
			}
			else
			{
				for ($i = 0; $i < $study['count']; $i++)
				{
					for ($j = 0; $j < $study[$i]['count']; $j++)
					{
						$study[$i][$j]['load'] = false;
						$study[$i][$j]['bitsStored'] = 0;
						$study[$i][$j]['numFrames'] = 0;
						$study[$i][$j]['xferSyntax'] = '';

						if (isset($study[$i][$j]['bitsstored']))
						{
							$study[$i][$j]['bitsStored'] = intval($study[$i][$j]['bitsstored']);
							unset($study[$i][$j]['bitsstored']);
						}

						$nf = 0;
						if (isset($study[$i][$j]['numframes']))
						{
							$nf = intval($study[$i][$j]['numframes']);
							unset($study[$i][$j]['numframes']);
						}
						if (!$nf)
						{
							/* A TEMPORARY SLOWDOWN.

								The frontend shall *not* expect any metadata except UIDs at this moment,
								however currently it can't otherwise make correct preparations for a
								particular image type. The change is planned in 6.0, and afterwards we'll
								remove this.

								In WADO and DICOM modes, 'path' might be missing (if the file isn't cached
								yet). No sense to continue in that case.
							 */
							if (isset($study[$i][$j]['path']))
							{
								$p = $study[$i][$j]['path'];
								if (strlen($p))
								{
									$meta = meddream_extract_meta(__DIR__, $study[$i][$j]['path'], 0);
									if (!$meta['error'])
									{
										if (isset($meta['numframes']))
											$nf = $meta['numframes'];
									}
									else
										$log->asErr('meddream_extract_meta: ' . var_export($meta, true));
								}
							}
						}
						$study[$i][$j]['numFrames'] = $nf;

						if (isset($study[$i][$j]['xfersyntax']))
						{
							$study[$i][$j]['xferSyntax'] = $study[$i][$j]['xfersyntax'];
							unset($study[$i][$j]['xfersyntax']);
						}
					}
					$study[$i]['studyId'] = $study['uid'];
				}

				changeKeyValue($study, 'firstname', 'firstName', '');
				changeKeyValue($study, 'lastname', 'lastName', '');
				changeKeyValue($study, 'patientid', 'patientId', '');
				changeKeyValue($study, 'sourceae', 'sourceAE', '');
				changeKeyValue($study, 'studydate', 'studyDate', '');
				changeKeyValue($study, 'studytime', 'studyTime', '');

				echo safeEncode($study);
				$audit->log(true, $auditObject);
			}
		}

		break;

	case 'loadSeries': //todo 5.0 patikrinti ir i�mesti
		{
			$study = '';
			$studyUID = $_POST['study'];
			$backend = getBackend(array('Structure'));
			if ($studyUID != "")
				$study = $backend->pacsStructure->studyGetMetadata($studyUID);

			for ($i = 0; $i < $study['count']; $i++)
			{
				if ($study[$i]['id'] == $_POST['series'])
				{
					$series = $study[$i];
				}
			}

			echo json_encode($series);
		}
		break;
	case 'loadStudySearch':
		{
			$log = getLogging();
			$log->asDump("begin $moduleName|$cmd");
			$searchCriteria = array();
			$index = 0;
			if (isset($_REQUEST['filter']))
				if (count($_REQUEST['filter']) > 0)
				{
					for ($i = 0; $i < count($_REQUEST['filter']); $i += 2)
					{
						$searchCriteria[$index] = array();
						$searchCriteria[$index]['name'] = $_REQUEST['filter'][$i];
						$searchCriteria[$index]['text'] = $_REQUEST['filter'][$i + 1];
						$index++;
					}
				}
			/* if ($_REQUEST['filterText1'] != '') {
			  $searchCriteria[$index] = array();
			  $searchCriteria[$index]['name'] = $_REQUEST['filterName1'];
			  $searchCriteria[$index]['text'] = $_REQUEST['filterText1'];
			  $index++;
			  }
			  if ($_REQUEST['filterText2'] != '') {
			  $searchCriteria[$index] = array();
			  $searchCriteria[$index]['name'] = $_REQUEST['filterName2'];
			  $searchCriteria[$index]['text'] = $_REQUEST['filterText2'];
			  } */


			$selectedModality = array();
			if (isset($_REQUEST['modality']))
			{
				$log->asDump('Modality filter received: ', $_REQUEST['modality']);
				if (count($_REQUEST['modality']) > 0)
				{

					$_REQUEST['modality'] = array_map('strtoupper', $_REQUEST['modality']);
					$allModality = array('CR', 'CT', 'DX', 'ECG', 'ES', 'IO',
										'MG', 'MR', 'NM','OT', 'PX', 'RF', 'RG',
										'SC', 'US', 'XA', 'XC');
						//set searching modalities
						$modalities = array();
						foreach ($_REQUEST['modality'] as $tmpModality)
						{
							if (!empty($modalities[$tmpModality]))
								continue;

							$modality = array();
							$modality['name'] = $tmpModality;
							$modality['selected'] = true;
							$modalities[$tmpModality] = $modality;
						}
						//add other modalities from $allModality
						$someIsNotSelected = false;
						foreach ($allModality as $oneOfAllModality)
						{
							if (!empty($modalities[$oneOfAllModality]))
								continue;

							$modality = array();
							$modality['name'] = $oneOfAllModality;
							$modality['selected'] = false;
							$modalities[$oneOfAllModality] = $modality;
							$someIsNotSelected = true;
						}
						//set custom attribute for new modalities
						$diff = array_diff(array_keys($modalities), $allModality);
						foreach ($diff as $modality)
						{
							if (!empty($modalities[$modality]))
								$modalities[$modality]['custom'] = true;
						}
						unset($diff);
						//there is some modalities that is not selected or
						//there is some new modalities
						if ($someIsNotSelected ||
							(count($modalities) != count($allModality)))
							$selectedModality = array_values($modalities);
						unset($modalities);
				}
			}
			$log->asDump('Modality filter will be used: ', $selectedModality);

			$fromDate = NULL; $toDate = NULL;
			if (isset($_REQUEST['startDate']) && $_REQUEST['startDate'] !== '')
			{
				$fromDate = $_REQUEST['startDate'];
			}
			if (isset($_REQUEST['endDate']) && $_REQUEST['endDate'] !== '')
			{
				$toDate = $_REQUEST['endDate']; //date("Y.m.d", strtotime($_REQUEST['endDate']));
			}


			$listMax = 1000;

			$data = array();
			if (isset($_COOKIE["suid"]))
			{

				$quidoSearch = new guidoSearch();
				$data = $quidoSearch->getData($searchCriteria, $fromDate, $toDate,
					$selectedModality, $listMax);
			}
			else
			{
				$backend = getBackend(array('Search'));
				$actions = "";
				if (isset($_REQUEST['actions']) && !empty($_REQUEST['actions']))
				{
					$actions = json_decode($_REQUEST['actions']);
					$log->asDump('Actions filter will be used: ', $actions);
				}
				$data = $backend->pacsSearch->findStudies($actions, $searchCriteria, $fromDate, $toDate,
					$selectedModality, $listMax);
			}

			if (!empty($data['error']))
			{
				$results = array(
					"recordsTotal" => 0,
					"recordsFiltered" => 0,
					"data" => '',
					"error" => $data['error']);
				$json = json_encode($results);

				if ($json === false)
				{
					$e = 'reason unknown';
					if (function_exists('json_last_error'))
						$e = 'code ' . json_last_error();
					$log->asErr("$moduleName: json_encode failed $e");
				}
				exit($json);
			}

			$anotation = new PresentationStateHandler();
			$anotationImg = '<img src="css/icons/anotation.png"/>';
			$count = $data['count'];
			$tmp = array();
			for ($i = 0; $i < $count; $i++)
			{
				$tmp[$i] = array();
				$tmp[$i][0] = '<input type="checkbox" value="' . $data[$i]['uid'] . '">';
				$tmp[$i][1] = '<a class="study-link-h" href="#'.$data[$i]['uid'].'">'
					. '<img src="css/icons/eye-h.png"/></a> '
					. '<a class="study-link-f" style="margin-left:10px" href="#'.$data[$i]['uid'].'">'
					. '<img src="css/icons/eye-f.png"/></a>'
					. '<a class="notes-col" href="#'.$data[$i]['uid'].'">'.$data[$i]['notes'].'</a>'
					. '<span id="reviewed-'.$i.'" style="display: none;">'.$data[$i]['reviewed'].'</span>';

				$tmp[$i][2] = $anotation->hasAnnotations($data[$i]['uid']) ? $anotationImg : '';

				$tmp[$i][3] = isset($data[$i]['patientid']) ? $data[$i]['patientid'] : '';
				$tmp[$i][4] = isset($data[$i]['patientname']) ? $data[$i]['patientname'] : '';
				$tmp[$i][5] = isset($data[$i]['accessionnum']) ? $data[$i]['accessionnum'] : '';
				$tmp[$i][6] = isset($data[$i]['modality']) ? $data[$i]['modality'] : '';
				$tmp[$i][7] = isset($data[$i]['description']) ? $data[$i]['description'] : '';
				$tmp[$i][8] = isset($data[$i]['datetime']) ? $data[$i]['datetime'] : '';
				$tmp[$i][9] = isset($data[$i]['received']) ? $data[$i]['received'] : '';
				$tmp[$i][10] = isset($data[$i]['sourceae']) ? $data[$i]['sourceae'] : '';

				$tmp[$i][11] = isset($data[$i]['patientsex']) ? $data[$i]['patientsex'] : '';
				$tmp[$i][12] = isset($data[$i]['patientbirthdate']) ? $data[$i]['patientbirthdate'] : '';
				$tmp[$i][13] = isset($data[$i]['received']) ? $data[$i]['received'] : '';
				$tmp[$i][14] = isset($data[$i]['uid']) ? $data[$i]['uid'] : '';
			}

			unset($data);

			$results = array(
				"recordsTotal" => $count,
				"recordsFiltered" => $count,
				"data" => $tmp);

			$json = json_encode($results);
			if ($json === false)
			{
				$e = 'reason unknown';
				if (function_exists('json_last_error'))
					$e = 'code ' . json_last_error();
				$log->asErr("$moduleName: json_encode failed ($e) on " . serialize($results));
			}
			echo $json;
			$log->asDump("end $moduleName|$cmd");
		}
		break;

	case 'latestversion':
		$sclass = new System();
		echo safeEncode($sclass->latestVersion());
		break;

	case 'getForwardAEList':
		$AElist = new Study();
		echo json_encode($AElist->getForwardAEList());
		break;

	case 'forwardStudies':
		$uid = "";
		if (isset($_REQUEST['uid']))
			$uid = $_REQUEST['uid'];
		$aet = "";
		if (isset($_REQUEST['aet']))
			$aet = $_REQUEST['aet'];
		$study = new Study();
		echo json_encode($study->forward($uid, $aet, ''));
		break;

	case 'getForwardStatus':
		$study = new Study();
		$return = array('error' => '', 'status' => '');

		if (isset($_POST['jobId']) &&
			($_POST['jobId'] != ''))
		{
			$jobId = addslashes($_REQUEST['jobId']);
			$return['status'] = $study->forwardStatus($jobId);
		}
		else
			$return['error'] = 'Missing job id';

		echo safeEncode($return);
		break;
	case 'getVolumeSizes':
		include_once(PATH_TO_ROOT . 'export.php');
		$size = new Export();
		echo json_encode($size->getVolumeSizes());
		break;
	case 'exportStudies':
		include_once(PATH_TO_ROOT . 'export.php');
		$uid = "";
		if (isset($_REQUEST['uid']))
			$uid = $_REQUEST['uid'];
		$size = "";
		if (isset($_REQUEST['size']))
			$size = $_REQUEST['size'];
		$export = new export();
		echo json_encode($export->media($uid, $size, ''));
		break;
	case 'exportStatus':
		include_once(PATH_TO_ROOT . 'export.php');
		$id = "";
		if (isset($_REQUEST['id']))
			$id = $_REQUEST['id'];
		$export = new export();
		echo json_encode($export->status($id));
		break;
	case 'exportDeleteTemp':
		include_once(PATH_TO_ROOT . 'export.php');
		$timestamp = "";
		if (isset($_REQUEST['timestamp']))
			$timestamp = $_REQUEST['timestamp'];
		$export = new export();
		echo json_encode($export->deleteTemp($timestamp));
		break;
	case 'getExportIso':
		include_once(PATH_TO_ROOT . 'export.php');
		$timestamp = "";
		if (isset($_REQUEST['timestamp']))
			$timestamp = $_REQUEST['timestamp'];
		$mediaLabel = "";
		if (isset($_REQUEST['mediaLabel']))
			$mediaLabel = $_REQUEST['mediaLabel'];
		$export = new export();
		echo json_encode($export->vol($timestamp, $mediaLabel));
		break;
	case 'getExportNotes':
		include_once(PATH_TO_ROOT . 'export.php');
		$timestamp = "";
		if (isset($_REQUEST['timestamp']))
			$timestamp = $_REQUEST['timestamp'];
		$uid = "";
		if (isset($_REQUEST['uid']))
			$uid = $_REQUEST['uid'];
		$export = new export();
		echo json_encode($export->notes($timestamp, $uid));
		break;

	case 'getImagePart':
		$log = getLogging();
		$log->asDump("begin $moduleName?cmd=$cmd");

		$uid = getParam('uid');
		$log->asDump('$uid = ', $uid);

		$step = getParam('step', 1);
		$log->asDump('$step = ', $step);

		try
		{
			$rawAdam7 = new RawAdam7($uid, buildMedDreamCmd(MEDDREAM_GETRAW_PROGRESSIVE), 7);
			header('Content-Type: application/octet-stream');
			$rawAdam7->printStep($step);
		}
		catch (RuntimeException $e)
		{
			HttpUtils::error($e->getMessage());
		}

		$log->asDump("end $moduleName?cmd=$cmd");
		break;

	case 'getSupportedLanguages':
		$sclass = new System();
		echo safeEncode($sclass->getSupportedLanguages());
		break;

	case 'getCachedImageList':
		$studyUid = '';

		$log = getLogging();

		if (!empty($_POST['study']))
		{
			$studyUid = (string) $_POST['study'];
		}
		else
		{
			echo safeEncode(array('error' => 'missing study UID'));
			exit();
		}

		$backend = getBackend(array('Structure'));
		$study = $backend->pacsStructure->studyGetMetadata($studyUid, false, 1);

		$log->asDump('cmd:getCachedImageList, PacsStructure::studyGetMetadata: ' . var_export($study, true));

		$cached = array();
		$ni = 0;
		if (isset($study['count']))
			for ($i = 0; $i < $study['count']; $i++)
				for ($j = 0; $j < $study[$i]['count']; $j++)
				{
					$id = '';
					$path = '';
					if (isset($study[$i][$j]['id']))
						$id = fixId($study[$i][$j]['id']);

					if (isset($study[$i][$j]['path']))
						$path = fixId($study[$i][$j]['path']);

					if (empty($id) || empty($path))
						continue;

					$cached[] = array('id' => $id, 'path' => $path);
					$ni++;
				}

		$log->asDump("returning $ni entries for \$cached = ", $cached);

		unset($study);
		unset($studyUid);

		echo safeEncode($cached);
		break;
	case 'getVideoData':
		{
			echo getVideoData();
		}
		break;
	case 'getInstanceTags':
		{
			echo getInstanceTags(getParam('uid'));
		}
		break;
	case 'getInstancePixels':
		{
            header('Content-Type: application/octet-stream');
			printInstancePixels(getParam('uid'), getParam('frameNum', 0));
		}
		break;
	case 'saveAnnotationObject':
		{
			echo saveAnnotationObject();
		}
		break;
	case 'getAnnotationList':
		{
			echo getAnnotationList();
		}
		break;
	case 'getAnnotationObject':
		{
			echo getAnnotationObject();
		}
		break;
	case 'saveAnnotationJpeg':
		{
			echo saveAnnotationJpeg();
		}
		break;
	case 'getpathById':
		{
			echo getPathById($_POST['instanceID']);
		}
		break;
	case 'loadMpeg2':
		{
			echo loadMpeg2($_REQUEST['instanceID']);
		}

		break;
	case 'loadThumbnail':
		{
			$backend = getBackend(array('Structure', 'Preload'));
			if (!$backend->authDB->isAuthenticated() || !isset($_REQUEST['id']))
			{
				echo safeEncode(array());
				exit();
			}
			$authDB = $backend->authDB;
///TODO
			$sql = "SELECT uuid , xfersyntax,  bitsstored, path FROM image WHERE image.uuid = '".$authDB->sqlEscapeString($_REQUEST['id'])."'";

			$rs = $authDB->query($sql);
			$r = $authDB->fetchNum($rs);

			if (is_array($r))
			{
				$study[0]['modality'] = '';
				$study[0][0]["id"] = $r[0];
				$study[0][0]["bitsstored"] = $r[2];
				$study[0][0]["xfersyntax"] =  $r[1];
				$study[0]["count"] = 10;
				$study[0][0]['path']  =  $r[3];

				echo 'data:image/png;base64,'.base64_encode( writeThumbnail(0, '', $study, 0, 0, 50));
			}
			exit;
		}

		break;
	case 'logPerformance':

		$log = getLogging();
		if ($log->isLoggingLevelEnabled(Logging::LEVEL_DEBUG))
		{
			$uid = getParam('uid');
			$type = getParam('type');
			if (!in_array($type, array('initiated', 'started', 'finished')))
				exit(safeEncode(array('error' => 'invalid time type')));

			$viewer = getParam('viewer');
			$time = getParam('time');

			$filename = PATH_TO_ROOT . 'log' . DIRECTORY_SEPARATOR . 'performance.csv';

			if (!file_exists($filename))
				file_put_contents($filename, "UID;Viewer;Type;Time\n", LOCK_EX | FILE_APPEND);

			file_put_contents($filename, "$uid;$viewer;$type;$time\n", LOCK_EX | FILE_APPEND);
			exit(safeEncode(array('ok' => 1)));
		}

		break;

	case 'dicomTags':
		$log = getLogging();
		if (!empty($_REQUEST['instanceID']))
		{
			$backend = getBackend(array('Structure'));
			$tags = new DicomTags($backend, $log);
			$return = $tags->getTags($_REQUEST['instanceID']);
			echo safeEncode($return);
		}
		else
		{
			$log->asErr('missing instanceID');
			echo '{"error":"missing instanceID"}';
		}
		break;

	case 'formatSR':
		if (!isset($_REQUEST['uid']))
			echo 'a required parameter is missing';
		else
		{
			$sr = new SR();
			$out = $sr->getHtml($_REQUEST['uid']);
			if (strlen($out['error']))
			{
				if ($out['error'] == 'not implemented')
				{
					$backend = new Backend(array(), false);
					$out['error'] = 'Display of SR objects is not implemented for PACS "' . $backend->pacs . '".';
				}

				echo 'Processing failed: ' . $out['error'];
			}
			else
				echo $out['html'];
		}
		break;

	case 'ecg':
		header('Content-Type: application/json');
		echo safeEncode(getEcg());
		break;

	case 'ecgImg':
		try {
			$ecg = getEcg();
			$dpi = getParam('dpi');
			$cols = getParam('cols', 1);
			$mmms = getParam('mmms', 25);
			$mmmV = getParam('mmmV', 10);
			$img = \Softneta\MedDream\Core\ECG\ECGImage::create($ecg, $dpi, $mmms, $mmmV, $cols);

			header('Content-type: image/png');

			imagepng($img);
			imagedestroy($img);
		} catch (Exception $e) {
			exit('{"error":"' . $e->getMessage() . '"}');
		}

		break;

    case 'ecgInfo':

        header('Content-Type: application/json');

        $uid = getParam('uid');
		$backend = getBackend(array('Structure'));
		if (!$backend->authDB->isAuthenticated())
            exit('{"error":"Not Authenticated"}');

        $path = getPathById($uid);
        $tags = DicomTagParser::parseFile($path, 4);

        $info = $tags[0x0040][0xb020][0xfffe][0xe000];

        $a = 0;
        $results = array();
        foreach ($info as $key => $item) {
            $group = $item[0x0040][0xa180]['data'][0];
            if ($group === 0 && isset($item[0x0070])) {
                $results[$group][] = $item[0x0070][0x0006]['data'];
            } elseif ($group != 3 && isset($item[0x0040])) {
                $tag = $item[0x0040];
                $result = $tag[0xa043][0xfffe][0xe000][0x0008][0x0104]['data'];

                if (isset($tag[0xa30a]))
                    $result .= ' ' . $tag[0xa30a]['data'];
                elseif (isset($tag[0xa132]))
                    $result .= ' ' . $tag[0xa132]['data'][0];

                if (isset($tag[0x08ea]))
                    $result .= ' ' . $tag[0x08ea][0xfffe][0xe000][0x008][0x0100]['data'];
                $results[$group][] = $result;
            }
        }
        echo safeEncode($results);

        break;

	case 'tags':

		header('Content-Type: application/json');

		$uid = getParam('uid');
		$backend = getBackend(array('Structure'));
		if (!$backend->authDB->isAuthenticated())
			exit('{"error":"Not Authenticated"}');

        $depth = getParam('depth', 0);
		$filters = json_decode(getParam('tags', '[]'));

		$path = getPathById($uid);
		$tags = DicomTagParser::parseFile($path, $depth);

        function filter($group, $element, $filters, $tags) {
            if (isset($tags[$group]) && isset($tags[$group][$element])) {
                $tag = $tags[$group][$element];
                if (empty($filters))
                    return array(
                        'gr' => $group,
                        'el' => $element,
                        'data' => isset($tag['data']) ? $tag['data'] : $tag
                    );
                elseif (!isset($tag['data']))
                    return filter($filters[0], $filters[1], array_slice($filters, 2), $tag);
            }
            return null;
        }

		$results = array();
		if (count($filters) > 0) {
			foreach ($filters as $filter) {
				if (count($filter) % 2 !== 0)
					exit('{"error":"Tag filter has a wrong number of parameters"}');
				if (($tag = filter($filter[0], $filter[1], array_slice($filter, 2), $tags)) !== null)
					$results[] = $tag;
			}
		} else
			$results = $tags;

		$count = count($results);
		echo safeEncode($count > 0 ? ($count > 1 ? $results : $results[0]['data']) : null);

		break;

	case 'registerLicense':
		if(!empty($_REQUEST['i']))
		{
			$data = $_REQUEST;
			if(!empty($data['cmd']))
				unset($data['cmd']);
			$system = new system();
			$ansver = $system->register($data);
			$ansver = array('error' => '', 'ansver'=>$ansver);
			echo safeEncode($ansver);
		}
		else
			echo '{"error":"Missing data for licensing"}';
		exit;
		break;

	case 'call3d':
		if(!empty($_REQUEST['seriesUid']))
		{
			$system = new system();
			$responce = $system->call3d($_REQUEST['seriesUid']);
			$ansver = array('error' => $responce);
			echo safeEncode($ansver);
		}
		else
			echo '{"error":"Missing series uid"}';
		exit;
		break;

	case 'addJob':
		if (!empty($_REQUEST['jobData']))
		{
			$data = json_decode($_REQUEST['jobData'], true);
			$jobsClass = new Jobs();
			$ansver = $jobsClass->addJob($data);
			echo safeEncode($ansver);
		}
		else
			echo '{"error":"Missing jobData"}';
		exit;
		break;

	default:
		$msg = 'Function not found: "' . var_export($cmd, true) . '"';
		$log = getLogging();
		$log->asErr($msg);
		$rsp = array('error' => $msg);
		echo safeEncode($rsp);
		break;
}

/**
 * clean array from empty subarrays or null values
 *
 * @param array $data
 * @return array||string
 */
function removeEmptyArrayValues($data)
{
	if (is_null($data) || (count($data) == 0))
		return '';

	foreach ($data as $key => $value)
	{
		if (is_null($data) || (count($data) == 0))
			$value = '';
		else
			if (is_array($value))
				$value = removeEmptyArrayValues($value);
		$data[$key] = $value;
	}
	return $data;
}

function getEcg()
{
	$uid = getParam('uid');
	$backend = getBackend(array('Structure'));
	if (!$backend->authDB->isAuthenticated())
		exit('{"error":"Not Authenticated"}');

	$path = getPathById($uid);
	try
	{
		$loader = new \Softneta\MedDream\Core\ECG\ECGLoader($path);
		return $loader->load(getParam('filtered', 1) === 1);
	} catch (Exception $e) {
		exit('{"error":"' . $e->getMessage() . '"}');
	}
}

function getParam($var, $default = null)
{
	if (isset($_REQUEST[$var]))
		return $_REQUEST[$var];
	if ($default === null)
		exit('{"error":"missing ' . $var . '"}');
	return $default;
}

function safeEncode($data)
{
	$log = getLogging();

	if (version_compare(PHP_VERSION, '5.5.0', '<'))
		$flags = 0;
	else
		$flags = JSON_PARTIAL_OUTPUT_ON_ERROR;

	$j = @json_encode($data, $flags);
	$log->asDump('json_encode: ', $j, ', err: ', json_last_error());
	return $j;
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
	global $backend;
	$log = getLogging();
	if (is_null($backend))
		$backend = new Backend($parts, $withConnection, getLogging());
	else
		$backend->loadParts($parts);

	if (!$backend->authDB->isConnected() && $withConnection)
		$backend->authDB->reconnect();

	return $backend;
}


/**
 * get loger class or create new
 *
 * @global Logging $log
 * @return Logging
 */
function getLogging()
{
	global $log;

	if ($log == null)
		$log = new Logging();

	return $log;
}


/**
 * change $data $search key with $change key
 * or will creates $data[$change] with $defaultValue value
 *
 * @param array $data - updates data pointer
 * @param string $search - look for $data[$search] value
 * @param string $change - will create $data[$change] = $defaultValue||$data[$search]
 * @param string|int $defaultValue - default value for non existing $change key value
 */
function changeKeyValue(&$data, $search, $change, $defaultValue)
{
	if (isset($data[$search]))
	{
		$data[$change] = $data[$search];
		unset($data[$search]);
	}
	else
		$data[$change] = $defaultValue;
}

function fixId($data)
{
	if (empty($data))
		return $data;
	$parts = explode('*', $data);
	return $parts[0];
}

function getVideoData()
{
	$log = getLogging();

	$data = json_decode(file_get_contents('php://input'), true);

	$path = getPathById($data["instanceId"]);


	$log->asDump("meddream_extract_meta('" . dirname(__FILE__) . "', ' " . $path . "', '0')'");
	$r = meddream_extract_meta(dirname(__FILE__), $path, 0);
	$log->asDump('meddream_extract_meta:  ', $r);

	$result = array(
		'studyDate' => $r['studydate'],
		'seriesUID' => $r['seriesuid'],
		'accNum' => $r['accnum'],
		'ts' => $r['xfersyntax'],
		'monochrome' => 0,
		'pixelOffset' => $r['pixel_locations'][0]['offset'],
		'pixelSize' => $r['pixel_locations'][0]['size'],
		'rows' => $r['rows'],
		'columns' => $r['columns'],
		'patientName' => $r['patientname'], //todo panaikinti ^ ženklus
		'studyDateTime' => $r['studydate'] . ' ' . $r['studytime'], //todo parsintio datas
		'patientId' => $r['patientid'],
		'studyId' => $r['studyuid']
	);

	$qualityList = getVideoQuality($data["instanceId"]);
	if (empty($qualityList['error']))
	{
		$result['quality'] = $qualityList['quality'];
	}


	if (isset($r["privatevttmeta"]))
	{
		$result['privatevttmeta'] = $r["privatevttmeta"];
		$result['privatevttjpeg'] = "data:image/jpeg;base64," . base64_encode($r['privatevttjpeg']);
	}

	return safeEncode($result);
}

/**
 * return image (video) other qualities
 *
 * return json array
 * return sample:
 * {"error":"","quality":[{"quality":"Original","imageid":"id1"}
 *         ,{"quality":"1080p","imageid":"id1"},...]
 * or
 * {"error":"","quality":[]}
  or
 * {"error":"some error","quality":[]}
 */
function getVideoQuality($instanceId)
{
	$backend = getBackend(array('Structure'));
	$result = $backend->pacsStructure->collectRelatedVideoQualities($instanceId);
	unset($backend);
	return $result;
}


function buildMedDreamCmd($flags = 0)
{
    global $basedir;

    $backend = getBackend(array('Structure', 'Preload'));
    $study = new Study($backend);

    if ($backend->enableSmoothing)
        $flags |= MEDDREAM_GETRAW_ENABLE_SMOOTHING;

    return new MedDreamCmd($basedir, $flags, $backend->pacsStructure, $backend->pacsPreload, $study, getLogging());
}


function getInstanceTags($uid)
{
    global $moduleName;

    $audit = new Audit('VIEW IMAGE');
    $log = getLogging();
    $log->asDump("begin $moduleName|" . __FUNCTION__);

    $result = null;

    try {
        $md = buildMedDreamCmd(getParam('type', 0));
        $result = $md->getMeta($uid);
    } catch (RuntimeException $e) {
        $audit->log(false, $uid);
        HttpUtils::error($e->getMessage());
    }

    $log->asDump("end $moduleName|" . __FUNCTION__);
    return safeEncode($result);
}


function printInstancePixels($uid, $frameNum = 0)
{
    global $moduleName;

    $audit = new Audit('VIEW IMAGE');
    $log = getLogging();
    $log->asDump("begin $moduleName|" . __FUNCTION__);

    try {
        $md = buildMedDreamCmd(MEDDREAM_GETRAW_JPG_STREAM);
        $md->printRaw($uid, $frameNum);
    } catch (RuntimeException $e) {
        $audit->log(false, $uid);
        HttpUtils::error($e->getMessage());
    }

    $log->asDump("end $moduleName|" . __FUNCTION__);
}


function getAnnotationList()
{
	$studyUID = '';

	if (!empty($_POST['study']))
		$studyUID = $_POST['study'];

	if (trim($studyUID) == '')
	{
		return json_encode(array());
	}
	else
	{
		$handle = new PresentationStateHandler();
		$result = $handle->getStudyPRList($studyUID);

		//$result['error'] = 'some error'
		//$result['series'] = array(
		//						'seriesid_that_have_pr'=>
		//							array(
		//								'imageid1_that_have_pr',
		//								'imageid2_that_have_pr'..
		//							)
		//					)


		return safeEncode($result);
	}
}


function saveAnnotationObject()
{
	$log = getLogging();
	$data = json_decode(file_get_contents('php://input'), true);
	$log->asDump('Annotation data: ', $data);
	$instanceuid = $data['instanceId'];

	/* 		$annotation = array(
	  'description'=>"description" ,
	  'title'=>'title' ,
	  'annotations'=>array(
	  array(
	  'type'=> 'TEXT',
	  'points'=>array(
	  //multiple grapgic lines
	  array('0','0','0','0'),
	  'graphicType'=> array('POLYLINE','POINT', 'CIRCLE',,..),
	  ),
	  'text'=>array(
	  array(
	  //multiple text labels or boxes
	  'text'=>'description',
	  'textpos'=> array('0','0', '10', '12'),
	  'textstyle'=>array('align'=>'LEFT')
	  )
	  )
	  )
	  )
	  );
	 */

	$log->asDump('Annotation data: ', $data);
	$handle = new PresentationStateHandler();
	$return = $handle->anotationToDicom($instanceuid, $data);

	if (isset($return['msg']))
	{
		$return['msg'] = nl2br($return['msg']);
		$log->asDump('Annotation msg: ' . $return['msg']);
	}
	if (isset($return['error']))
	{
		$return['error'] = nl2br($return['error']);
		$log->asDump('Annotation error: ' . $return['error']);
	}

	return safeEncode($return);
}


function getAnnotationObject()
{
	$log = getLogging();
	$instanceid = '';

	if (!empty($_POST['instanceuid']))
		$instanceid = $_POST['instanceuid'];

	if (trim($instanceid) == '')
	{
		return json_encode(array());
	}
	else
	{
		$handle = new PresentationStateHandler();
		$result = $handle->getImagePrlist($instanceid);

		//$result['error'] = 'some error'
		//$result['prlist'] = array(
		//						array(
		//							'description'=>"description" ,
		//							'title'=>'title' ,
		//							'type'=>'dicom',
		//							'annotations'=>array(...)
		//						),
		//						array(
		//							'description'=>"description" ,
		//							'title'=>'title' ,
		//							'type'=>'jpg',
		//							'instanceid'=>'some instance id',
		//							'path'=>'full_path_to_dicom_file'
		//						),
		//						...
		//					)
		return safeEncode($result);
	}
}


function saveAnnotationJpeg()
{
	$log = getLogging();
	$data = json_decode(file_get_contents('php://input'), true);
	$log->asDump('Annotation data: ', $data);

	$instanceuid = $data['instanceId'];

	$base64img = $data['base64img'];
	$base64img = str_replace('data:image/jpeg;base64,', '', $base64img);
	$base64img = str_replace(' ', '+', $base64img);
	$pixels = base64_decode($base64img);

	$data['file'] = realpath(PATH_TO_ROOT) . DIRECTORY_SEPARATOR . 'temp' .
		DIRECTORY_SEPARATOR . 'pr' . date("YmdHis") . rand(100000, 99999999) . '.tmp';

	$tmp = $data['file'];
	file_put_contents($tmp, $pixels);
	unset($base64img);
	unset($pixels);
	unset($data['base64img']);

	$handle = new PresentationStateHandler();
	$return = $handle->jpgToDicom($instanceuid, $data);
	$handle->deleteFile($tmp);

	if (isset($return['msg']))
	{
		$return['msg'] = nl2br($return['msg']);
		$log->asDump('Annotation msg: ' . $return['msg']);
	}
	if (isset($return['error']))
	{
		$return['error'] = nl2br($return['error']);
		$log->asDump('Annotation error: ' . $return['error']);
	}

	return safeEncode($return);
}


function getPathById($imageID)
{
	$log = getLogging();
	$backend = getBackend(array('Structure'));
	$meta = $backend->pacsStructure->instanceGetMetadata($imageID);
	return $meta['path'];
}


function loadMpeg2($instanceID)
{
	require_once(PATH_TO_ROOT . 'flv.php');
	$flv = new flv(getBackend(array('Structure')));
	$result = $flv->load($instanceID, 'mp4');
	echo $result;
}


function writeThumbnail($clientid, $out, $study, $s, $i, $thumbnailSize)
{
	global $backend, $constants, $basedir, $log;

	if (isset($study[$s]['modality']))
		if ($study[$s]['modality'] == 'SR')
			return '0';
	$uuid = $study[$s][$i]['id'];

	/* in some PACSes $uuid contains additional components, which are delimited by
	   characters not allowed in file names ($thumbnail below depends on $uuid);
	   let's clean them out
	 */
	$len = strpos($uuid, "*");
		if ($len !== false)
			$uuid = substr($uuid, 0, $len);

	$tempPath = $basedir."/temp/";

	global $DD;
	if ($DD)
	{

		$tempPath = session_save_path() . "/temp/";
		if (!file_exists($tempPath))
			mkdir($tempPath);
		$basedir = session_save_path() . "/";
	}

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
			$fi = $backend->pacsPreload->fetchInstance($uuid, $study[$s]['id'], $study['uid']);
			if (is_string($fi))
				$path = $fi;

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
				if (!defined('MEDDREAM_THUMBNAIL_JPG'))
				{
					$log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
					$r = '';
				}
				else
				{
					$flags = 90 | MEDDREAM_THUMBNAIL_JPG;

					$log->asInfo("meddream_thumbnail('$path', '$thumbnail', $thumbnailSize, '$xfersyntax', $bitsstored, $flags)");
					$r =  meddream_thumbnail($path, $thumbnail, $basedir, $thumbnailSize, $xfersyntax,
						$bitsstored, 0, 0, $backend->enableSmoothing, $flags);
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

								if (function_exists('imagecreatefromstring'))
								{
									$r = imagecreatefromstring($r);
									ob_start();
									imagejpeg($r, NULL, 90);
									$r = ob_get_contents();
									ob_end_clean();
//									imagedestroy($r);
								}
								else
								{
									$log->asErr('GD2 extension is missing');
									$r = '';
								}
							}
							else
								if ($r[0] == 'J')
									$r = substr($r, 5);
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
		else
			$r = file_get_contents($thumbnail);
			if ($constants->FDL)
				file_put_contents($pathJPG, $r);
	}
	return $r;
}
