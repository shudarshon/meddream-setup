<?php
/*
	Original name: ManagePrint.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Server-side support for the "Print" (not to be confused with "DICOM
		Print") UI button. Converts every image in a study/series to JPEG,
		and saves to a temporary file; UI will use those files in a temporary
		DHTML page for printing.
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\MedReport;
use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;


class ManagePrint
{
	var $defaultPrintTemplate = 'default';
	var $stamps = 'stamps';
	var $backend = null;
	var $rootDir = null;
	private $log;


	function __construct()
	{
		$this->log = new Logging();

		$this->methodTable = array
		(
			"collectSeriesData" => array(
				"description" => "collect series data",
				"access" => "remote"),
			"makePrintData" => array(
				"description" => "print report/report with images",
				"access" => "remote")
		);
	}


	/**
	 * return new or existing instance of Backend
	 * If the underlying AuthDB must be connected to the DB, then will request the connection once more.
	 * Also initializes $this->rootDir.
	 *
	 * @param array $parts - Names of PACS parts that will be initialized
	 * @param boolean $withConnection - is a DB connection required?
	 * @return Backend
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		$this->rootDir = $this->backend->pacsConfig->getWriteableRoot();

		return $this->backend;
	}


	function getTimestamp()
	{
		list($usec, $sec) = explode(" ", microtime());
		list($int, $dec) = explode(".", $usec);
		$dec = date("YmdHis").sprintf("%06d", (int)($dec / 100));
		return $dec;
	}


	function setImage($img)
	{
		$rootDir = $this->rootDir;

		$path = $img['path'];
		$tmp = $img['tmp'];
		if (isset($img['uid']))
			$uid = $img['uid'];
		else
			$uid = '';

		$xfersyntax = $img['xfersyntax'];
		$bitsstored = $img['bitsstored'];
		if ($bitsstored == '') $bitsstored = 8;
		$thumbnail = $tmp . $uid . '-' . $this->getTimestamp() . '.tmpprint';

		$this->log->asDump('$img: ', $img);

		if ($xfersyntax == 'jpg')
		{
			if (@copy($path, $thumbnail))
				return $thumbnail;
			else
				return '';
		}

		if (!defined('MEDDREAM_THUMBNAIL_JPG'))
		{
			$this->log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
			return '';
		}
		$flags = 100 | MEDDREAM_THUMBNAIL_JPG | MEDDREAM_THUMBNAIL_JPG444;

		$this->log->asDump("meddream_thumbnail('$path', '$thumbnail', '$xfersyntax', $bitsstored, $flags)");
		$r = meddream_thumbnail($path, $thumbnail, dirname(__DIR__), 10000, $xfersyntax,
			$bitsstored, 0, 0, $this->backend->enableSmoothing, $flags);
		$this->log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

		if (strlen($r) < 1) return '';

		if ($r[0] == '2')
		{
			if (function_exists('imagecreatefromstring'))
			{
				$r = substr($r, 5);
				$r = imagecreatefromstring($r);
				imagejpeg($r, $thumbnail, 100);
				imagedestroy($r);
			}
			else
			{
				$this->log->asErr('GD2 extension is missing');
				return '';
			}
		}
		else
			if ($r[0] == 'J')
			{
				$r = substr($r, 5);
				file_put_contents($thumbnail, $r);
			}
			else	/* assume 'E' */
			{
				$r = substr($r, 5);
				if (file_exists($r))
					copy($r, $thumbnail);
			}

		if (!file_exists($thumbnail))
			return '';
		else
			return $thumbnail;
	}


	function collectSeriesData($uid, $layout = 1, $studyUid = '')
	{
		$return = array();
		$this->log->asDump('begin ' . __METHOD__ . '(', $uid, ', ', $layout, ', ', $studyUid, ')');
		$audit = new Audit('PRINT SERIES');

		if ($uid == '')
		{
			$audit->log(false);
			$return['error'] = 'Required parameter(s) missing';
			$this->log->asErr($return['error']);
			return $return;
		}

		set_time_limit(0);
		ini_set('memory_limit', '1024M');

		$backend = $this->getBackend(array('Structure', 'Preload'));
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);

			$audit->log(false, $uid);
			return $return;
		}
		$return['error'] = '';

		$this->deleteOldFiles();

		$rootDir = $this->rootDir;

		if ($backend->pacsConfig->getRetrieveEntireStudy())
		{
			$parts = explode('*', $uid);
			$studyUid = end($parts);

			$retrieve = new RetrieveStudy(new Study(), $this->log);
			$err = $retrieve->verifyAndFetch($studyUid);
			if ($err)
			{
				$return['error'] = $err;
				$audit->log(false, $uid);
				return $return;
			}
		}

		$files = $backend->pacsStructure->seriesGetMetadata($uid);
		if (strlen($files['error']))
		{
			$return['error'] = $files['error'];

			$audit->log(false, $uid);
			return $return;
		}
		unset($files['fullname']);
		unset($files['firstname']);
		unset($files['lastname']);
		unset($files['error']);
		unset($files['count']);
			/* the above are not required any more and might confuse further code */

		$err = $backend->pacsPreload->fetchAndSortSeries($files);
		if ($err != '')
		{
			$return['error'] = $err;

			$audit->log(false, $uid);
			return $return;
		}

		$images = array();
		$pages = array();
		foreach ($files as $img)
		{
			$removeTmp = true;
			if (($img['xfersyntax'] != '1.2.840.10008.1.2.4.103') &&
					($img['xfersyntax'] != '1.2.840.10008.1.2.4.100') &&
					($img['xfersyntax'] != 'MP4'))
			{
				$img['tmp'] = $rootDir . 'temp' . DIRECTORY_SEPARATOR;
				$imgpath = $this->setImage($img);
				if ($imgpath != '')
					$images[] = 'temp/'.basename($imgpath);
				else
					$removeTmp = false;
			}

			/* remove the source file that might be created by fetchAndSortSeries().
				If setImage() fails, they won't be removed for diagnostics.
			 */
			if ($removeTmp)
				$backend->pacsPreload->removeFetchedFile($img['path']);
		}
		for ($i = 0; $i < count($images); $i++)
		{
			$imageGroup[] = $images[$i];

			if ((count($imageGroup) == $layout) ||
				(($i+1) == count($images)))
			{
				if (count($imageGroup) > 0)
				{
					$imageGroup['count'] = count($imageGroup);
					$pages[] = $imageGroup;
				}
				$imageGroup = array();
			}
		}
		$pages['count'] = count($pages);
		$return['pages'] = $pages;

		$this->log->asDump("result: ", $return);
		$this->log->asDump('end ' . __METHOD__);

		$audit->log($return['error'] == '', $uid);
		return $return;
	}


	function collectStudyData($uid, $layout = 1)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $uid, ', ', $layout, ')');

		if ($uid == '')
			return array('error' => 'parameters missing');

		$backend = $this->getBackend(array('Structure'));

		$ser = $backend->pacsStructure->studyListSeries($uid);
		if (strlen($ser['error']))
			return array('error' => $ser['error']);

		$return = array('error' => '');

		set_time_limit(0);
		ini_set('memory_limit', '1024M');

		$this->deleteOldFiles();

		$pages = array();
		$count = 0;

		for ($i = 0; $i < $ser['count']; $i++)
		{
			$seriesUID = $ser[$i];

			$temp = $this->collectSeriesData($seriesUID, $layout, $uid);

			if (isset($temp['pages']) && (count($temp['pages']) > 0))
			{
				$pages = array_merge($pages, $temp['pages']);
				$count += $temp['pages']['count'];
			}
			else
				if (isset($temp['error']) && ($temp['error'] != ''))
					$return['error'] = $temp['error'];
		}

		session_write_close();
		$images = array();
		$pages['count'] = $count;
		$return['error'] = '';
		$return['pages'] = $pages;

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
			/* no audit log: collectSeriesData() does it already, and a more reliable way
			   of refactoring requires an additional parameter of collectSeriesData(). This
			   also means an update in Flash -- not possible at the moment.
			 */

		return $return;
	}


	function deleteOldFiles()
	{
		$this->log->asDump('begin '.__METHOD__);
		
		$rootDir = $this->rootDir;
		
		$results = glob($rootDir . "temp/{*}.tmpprint", GLOB_BRACE);

		if (count($results) > 0)
			foreach ($results as $value)
			{
				try
				{
					if (filemtime($value) < strtotime("-1 minutes"))
					{
						unlink($value);
						$this->log->asDump('deleted: ', $value);
					}
				}
				catch(Exception $e)
				{
					$this->log->asErr($e->getMessage());
				}
			}
		$this->log->asDump('end '.__METHOD__);
	}


	/**
     * build html from study last note
     *
     * @param string $studyUID - study UID
     * @param array $extraFileds Default=array() - extra fields
     * @return array $return - ('error' => '', 'template' => '')
     */
	function makePrintData($studyUID, $withstudyimage = false, $extraFileds = array())
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUID, ', ', $withstudyimage, ', ', $extraFileds, ')');
		$audit = new Audit('PRINT REPORT');

		if (trim($studyUID) == '')
		{
			$audit->log(false);
			$return['error'] = 'required parameter(s) missing';
			$this->log->asErr($return);
			return $return;
		}
		$auditDetails = "study '$studyUID'";

		$return = array();
		$return["error"] = '';

		$backend = $this->getBackend(array('Report', 'Structure'));	/* Structure: for collectStudyData() later */
		if (!$backend->authDB->isAuthenticated())
		{
			$audit->log(false, $auditDetails);
			$return['error'] = 'not authenticated';
			$this->log->asErr($return);
			return $return;
		}

		$return["template"] = '';

		$reports = $backend->pacsReport->collectReports($studyUID);
		if (isset($reports["error"]) && ($reports["error"] != ''))
		{
			$audit->log(false, $auditDetails);
			$return["error"] = $reports["error"];
			return $return;
		}

		//no report
		if (isset($reports[0]))
		{
			$reports = array_merge($reports, $reports[0]);

			if (isset($reports['count']))
				unset($reports['count']);

			unset($reports['error']);
			$reports['homeUrl'] = $this->fullUrl();

			if (isset($reports['notes']))
				$reports['notes'] = $this->cleanNote($reports['notes']);

			$reports['stamp'] = $this->getStamp($reports['user']);
			$reports['studyUID'] = $studyUID;
			$reports['uid'] = $studyUID;
			$reports['images'] = '';
			$reports['todayDate'] = date("Y-m-d");
			$reports['todayTime'] = date("H:i:s");

			if (count($extraFileds) > 0)
			{
				$this->log->asDump('$extraFileds: ', $extraFileds);

				foreach ($extraFileds as $key => $value)
					$reports[$key] = $value;
			}

			$this->log->asDump('$reports: ', $reports);
		}

		if (!empty($reports['modality']))
			$modality = $reports['modality'];
		else
			$modality = '';

		$template = $this->getTemplate($modality);
		$this->log->asDump('$template: ', $template);

		//empty temlate
		if ($template == '')
		{
			$audit->log(false, $auditDetails);
			$return["error"] = 'No print template';
			$this->log->asDump('$return: ', $return);
			return $return;
		}

		if ($withstudyimage)
		{
			$collectedData = $this->collectStudyData($studyUID, 1);
			if ($collectedData["error"] != "")
			{
				$audit->log(false, $auditDetails);
				$return["error"] = $collectedData["error"];

				$this->log->asDump('$return: ',$return);

				return $return;
			}
			else
			{
				if (isset($collectedData['pages']))
					$reports['images'] = $this->formImagesHtml($collectedData['pages']);
			}
			$this->log->asDump('images: ', $reports['images']);
		}

		foreach ($reports as $key => $value)
		{
			if (is_integer($key))
				continue;
			$template = str_replace('{' . $key . '}', $value, $template);
		}
		unset($reports['error']);

		$return["error"] = '';
		$return["template"] = $template;

		$audit->log(true, $auditDetails);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	/**
     * clean note from flash format
     *
     * @param string $note - note text
     * @return string  - note text
     */
	function cleanNote($note)
	{
		$note = str_replace('<TEXTFORMAT LEADING="2">', '', $note);
		$note = str_replace('</TEXTFORMAT>', '', $note);
		$note = str_replace('KERNING="0"', '', $note);
		$note = str_replace('SIZE="', 'style="SIZE:', $note);
		$note = nl2br($note);
		return $note;
	}


	/**
     * get template context by modality, if modality not exist - returns default.html
     *
     * @param string $modality - modality
     * @return string  - template context
     */
	function getTemplate($modality)
	{
		$dir = dirname(__DIR__).DIRECTORY_SEPARATOR;

		$path = $dir . 'printtemplate' . DIRECTORY_SEPARATOR . $modality . '.html';
		if (file_exists($path))
			return file_get_contents($path);
		else
		{
			$path = $dir . 'printtemplate' . DIRECTORY_SEPARATOR . $this->defaultPrintTemplate . '.html';
			if (file_exists($path))
				return file_get_contents($path);
		}
		return '';
	}


	/**
     * get current url, what calls cript
     *
     * @return string  - url without script name
     */
	function fullUrl()
	{
		$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
		$sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
		$protocol = substr($sp, 0, strpos($sp, "/")) . $s;
		$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);

		$protocol = $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . str_replace(basename($_SERVER['SCRIPT_FILENAME']),
			'', $_SERVER['REQUEST_URI']);
		$protocol = str_replace('flashservices/?session=medreport', '', $protocol);
		$protocol = str_replace('flashservices/?session=meddream', '', $protocol);
		$protocol = str_replace('&windowname=MedDreamFlashViewer', '', $protocol);
		$protocol = str_replace('&windowname=MedDreamViewer', '', $protocol);
		$protocol = str_replace('/swf/', '', $protocol);
		
		return $protocol;
	}


	/**
     * get stamp html
     *
     * @param string $stamp - file name
     * @return string  - img html
     */
	function getStamp($stamp)
	{
		if (($this->stamps != '') && ($stamp != ''))
		{
			$stamp = DIRECTORY_SEPARATOR . $this->stamps .
						DIRECTORY_SEPARATOR . $stamp . '.jpg';
			
			$file = dirname(__DIR__) . $stamp;

			$stamp = str_replace('\\', '/', $stamp);
			if (file_exists($file))
				return '<img src="' . $this->fullUrl() . $stamp . '"/>';
		}
		else
			return '';
	}


	/**
     * form html of images
     *
     * @param array $pages - array with files
     * @param int $l - numbers of files in a column
     * @param int $w - numbers of files in a row
     * @return string  -  html
     */
	function formImagesHtml($pages)
	{
		$html = '';
		if (isset($pages[0]))
			for ($k = 0; $k < $pages['count']; $k++)
			{
				for ($i = 0; $i < $pages[$k]['count']; $i++)
				{
					$html .= "<div style=\"width:100%; height:100%; clear:both; page-break-after:always;\" >
									<img style=\"height:auto; width:auto; max-width:100%; max-height:100%;\" src=\"" .$this->fullUrl().'/'.$pages[$k][$i]. "\" >
									</div>";
				}
			}
		return $html;
	}
}
