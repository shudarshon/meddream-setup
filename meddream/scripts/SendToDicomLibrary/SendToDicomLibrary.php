<?php
/*
	Original name: SendToDicomLibrary.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		send multiple files to dicomlibrary
 */
namespace Softneta\MedDream\scripts;
require_once __DIR__ . '/../../autoload.php';

use Softneta\MedDream\Core\SOP\DicomCommon;
use Softneta\MedDream\Core\Jobs;
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\RetrieveStudy;
use Softneta\MedDream\Core\Study;


class SendToDicomLibrary
{
	public $log;
	protected $backend = null;
	public $url = 'https://dicomlibrary.com/acceptMDMultipleFiles.php';
	public $uids = array();

	/** @brief do not test if already byyn tested
	 * especialy for Q/R
	 *
	 * @var boolean
	 */
	public $alreadyStarted = false;

	/** @brief  Files will be sent in chunks of this size.

		@var int
	 */
	public $minSize = 1000000;


	public function __construct(Backend $backend = null, Logging $log = null)
	{
		if (is_null($log))
			$this->log = new Logging('SendToDL-', __DIR__ . '/log/');
		else
			$this->log = $log;

		$this->backend = $backend;
	}


	/** @brief Return full path to the MedDream installation directory. */
	public function getRootDir()
	{
		return dirname(dirname(__DIR__));
	}


	/** @brief Log in using the HIS login (external.php).

		@param Backend $backend  An instance of Backend
	 */
	public function login($backend)
	{
		$authDB = $backend->authDB;
		$alreadyLoggedIn = $authDB->isAuthenticated();
		if ($alreadyLoggedIn)
			$this->log->asDump('reusing and keeping an existing login');
		else
		{
			if (!file_exists($this->getRootDir() . '/external.php'))
			{
				$this->log->asErr("Can't login: missing or bad external.php");
			}
			include $this->getRootDir() . '/external.php';
			if (!$authDB->login(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
			{
				$authDB->logoff();
				$this->log->asErr("Can't login: missing or bad external.php");
			}
		}
		$this->log->asDump('Auth: ', $authDB->isAuthenticated());
	}


	/** @brief Return a new or existing instance of Backend.

		@retval null     Failed to log in
		@retval Backend  Success

		If the underlying AuthDB must be connected to the DB, then will request the
		connection once more.
	 */
	public function getBackend()
	{
		if (is_null($this->backend))
		{
			$this->backend = new Backend(array('Structure', 'Preload'), true, $this->log);
			$this->login($this->backend);
			if (!$this->backend->authDB->isAuthenticated())
			{
				$this->log->asErr('not authenticated');
				$this->backend = null;
			}
		}
		return $this->backend;
	}


	/** @brief Send a collection of images to Dicom Library.

		@param array $data  A job object saved by the frontend. Mandatory elements:
		                    @c 'imageuids', @c 'dirIdent', @c 's'.

		@return array  <tt>array('error' => ERROR_MESSAGE)</tt>
	 */
	public function sendFiles($data)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $data, ')');
		$return = array('error' => '');

		$backend = $this->getBackend();
		if (is_null($backend))
			return array('error' => 'reconnect');
		if (empty($data['imageuids']))
		{
			$return['error'] = 'No image(s) specified';
			$this->log->asErr('$error: ' . $return['error']);
			return $return;
		}
		if (empty($data['dirIdent']))
		{
			$return['error'] = 'Missing dirIdent attribute';
			$this->log->asErr('$error: ' . $return['error']);
			return $return;
		}
		$dirIdent = $data['dirIdent'];
		if (empty($data['s']))
		{
			$return['error'] = 'Function requires a non-Demo license';
			$this->log->asErr('$error: ' . $return['error']);
			return $return;
		}
		$serial = $data['s'];

		/* collect paths */
		$anonymize = true;
		if (array_key_exists('anonymize', $data))
			$anonymize = $data['anonymize'];
		$files = array();
		foreach ($data['imageuids'] as $imageUid)
		{
			$return = $this->getImagePath($imageUid);
			if (!empty($return['error']))
				return $return;

			if (empty($return['path']) || !file_exists($return['path']))
			{
				$this->log->asErr('image not found:' . $return['path']);
				$return['error'] = 'Image not found';
				return $return;
			}

			$parts = explode('*', $imageUid);
			$imageUid = $parts[0];

			if ($anonymize)
			{
				$return['path'] = $this->anonymize($return['path']);
				if ($return['path'] == '')
				{
					$return['error'] = 'Failed to anonymize';
					$this->removeTempFiles($files);
					return $return;
				}
			}
			$files[] = array('path' => $return['path'], 'name' => $imageUid);
		}
		$this->log->asDump('$files = ', $files);
		set_time_limit(0);
		ignore_user_abort(true);
		session_write_close();
		$filesCount = count($files);
		$index = 0;
		ini_set('default_socket_timeout', 60);
		foreach ($files as $file)
		{
			$index++;
			$postdata = array(
				'totalSize' => filesize($file['path']), //total file size
				'totalCount' => $filesCount,	//total file count
				'dirIdent' => $dirIdent,	//uniquie directory in the temp
				'fileIdent' => $file['name'], //unique file identification
				'serial' => $serial //serial number as a pass key
			);

			/* on last file - add last attributes */
			if ($index == $filesCount)
			{
				if (array_key_exists('subject', $data))
					$postdata['subject'] = $data['subject'];
				$postdata['mailfrom'] = $data['mailfrom'];
				$postdata['mailto'] = $data['mailto'];
				$postdata['message'] = $data['message'];
			}

			$this->log->asDump('$postdata = ', $postdata);
			$return = $this->sendFileParts($postdata, $file);
			$this->log->asDump('$return = ' , $return);
			if (!empty($return['error']))
			{
				$this->log->asErr($return['error']);
				$return['error'] = 'Failed to upload';

				//delete files
				if ($anonymize)
					$this->removeTempFiles($files);
				$postdata['delete'] = 1;
				$this->postRequest($this->url, $postdata);
				return $return;
			}
		}
		if ($anonymize)
			$this->removeTempFiles($files);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Get path to the image file specified by its primary key.

		@param string $uid  Primary key in the "images" table. __Not necessarily a DICOM SOP Instance UID.__

		@return array - array('error' => '', 'path' => '...')
	 */
	public function getImagePath($uid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $uid, ')');
		$backend = $this->getBackend();

		if (is_null($backend))
		{
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}
		$return = array('error' => '');
		if (!$this->alreadyStarted)
		{
			$this->alreadyStarted = true;
			if ($backend->pacsConfig->getRetrieveEntireStudy())
			{
				$retrieve = new RetrieveStudy(new Study($this->backend), $this->log);
				$return['error'] = $retrieve->verifyAndFetch($uid);
				if (!empty($return['error']))
					return $return;
			}
		}
		$return = $backend->pacsStructure->instanceGetMetadata($uid);

		$this->log->asDump('$return = ' , $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Try to send a file or parts of it.

		Depends on $this->minSize.

		@param array $postdata  Additional headers from the "Send to DL" form
		@param array $file      Attributes like name etc (might include entire content of a small file)

		@return array
	 */
	public function sendFileParts($postdata, $file)
	{
		$return = array('error' => '');

		$size = $postdata['totalSize'];
		if ($size <= $this->minSize)
		{
			$return = $this->postRequest($this->url, $postdata, array('filedata' => $file));
			$return = $this->parseResult($return);
		}
		else
		{
			$partSize = $this->minSize;
			$position = 0;
			$redsize = $partSize;
			while ($size > $position)
			{
				$h = @fopen($file['path'], 'r');
				if ($h)
				{
					if ($position > 0)
					{
						fseek($h, $position);
						$redsize = $partSize - $position;
					}

					$file['content'] = fread($h, $redsize);

					$position += $redsize;
					$partSize = min($size, $position+$this->minSize);
					fclose($h);
					$return = $this->postRequest($this->url, $postdata, array('filedata' => $file));
					$return = $this->parseResult($return);

					if (!empty($return['error']))
						break;
				}
				else
					break;
			}
		}

		return $return;
	}


	/** @brief Parse a response from the DL server.

		@param array $return  See postRequest()

		@return array  <tt>array('error' => ERROR_MESSAGE)</tt>
	 */
	public function parseResult($return)
	{
		if (isset($return['error']))
			if ($return['error'] != '')
				return $return;

		if (isset($return['content']))
		{
			$json = json_decode($return['content'], true);
			if (!is_null($json))
			{
				$return['content'] = $json;
				if (isset($return['content']['status']) &&
					isset($return['content']['message']))
				{
					if (((int)$return['content']['status'] == 0) &&
							($return['content']['message'] == 'OK'))
						return $return;
					else
						$return['error'] = $return['content']['message'];
				}
				else
					$return['error'] = $return['content'];
			}
			else
				$return['error'] = $return['content'];
		}
		else
			$return['error'] = 'Bad answer';

		return $return;
	}


	/** @brief Prepare a request and send to the given URL.

		@param string $url
		@param array $postdata
		@param array $files

		@return array
	 */
	public function postRequest($url, $postdata, $files = null)
	{
		$data = "";
		$boundary = "---------------------" . substr(md5(rand(0, 32000)), 0, 10);

		$crlf = "\r\n";
		//Collect Postdata
		foreach ($postdata as $key => $val)
		{
			$data .= "--$boundary$crlf";
			$data .= "Content-Disposition: form-data; name=\"" . $key . "\"\n\n" . $val . "\n";
		}

		$data .= "--$boundary$crlf";

		//Collect Filedata
		if (!empty($files))
			foreach ($files as $key => $file)
			{
				if (!empty($file['content']))
					$fileContents = $file['content'];
				else
					$fileContents = @file_get_contents($file['path']);

				if ($fileContents == '')
					continue;

				$data .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$file['name']}\"$crlf";
				$data .= "Content-Type: application/dicom$crlf";
				$data .= "Content-Transfer-Encoding: binary$crlf$crlf";
				$data .= $fileContents . "$crlf";
				$data .= "--$boundary--$crlf";
			}

		$params = array('http' => array(
				'method' => 'POST',
				'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary,
				'content' => $data
				),
				"ssl" => array(
					"verify_peer" => false,
					"verify_peer_name" => false
				)
			);

		return $this->requestResponce($url,$params);
	}


	/** @brief Send the request and fetch the response.

		@param string $url
		@param params $params

		@return array
	 */
	private function requestResponce($url,$params)
	{
		$ctx = stream_context_create($params);

		$responce = array('header' => '', 'content' => '', 'error' => '');
		/* send */
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp)
		{
			$err = error_get_last();
			$responce['error'] = $err['message'];
			return $responce;
		}
		else
		{
			$rsp = @stream_get_contents($fp);
			$responce['content'] = $rsp;
			if ($rsp === false)
			{
				$err = error_get_last();
				$responce['error'] = $err['message'];
				return $responce;
			}
			else
			{
				$hd = stream_get_meta_data($fp);
				$responce['header'] = $hd;
				if ($hd === false)
				{
					$err = error_get_last();
					$responce['error'] = $err['message'];
					return $responce;
				}
			}
		}
		return $responce;
	}


	/** @brief Delete any existing temporary files.

		@param array $files  <tt>$files[]['temp']</tt> contains the path
	 */
	private function removeTempFiles($files)
	{
		$count = count($files);
		for ($i = 0; $i<$count; $i++)
			if ((strpos($files[$i]['path'], 'temp') !== false) &&
				(strpos($files[$i]['path'], 'cached') === false))
			{
				$this->log->asDump('removeTempFiles = ' , $files[$i]['path']);
				@unlink ($files[$i]['path']);
			}
	}


	/** @brief Generate a new UID.

		@param int $type  Study (1), series (2), image (3)
		@param int $data  Additional metadata like Series Number

		@retval ''      Failed due to not authenticated $this->backend
		@retval string  Generated UID
	 */
	private function generateUid($type = 1, $data = 1)
	{
		$backend = $this->getBackend();
		if (!is_null($backend))
		{
			return DicomCommon::generateUid(Constants::ROOT_UID,
				Constants::PRODUCT_ID, $backend->productVersion,
				$type, date("YmdHis"), $data);
		}
		return '';
	}


	/** @brief Anonymize a file.

		@param string $path  Path to an original file

		@retval ''      Something failed
		@retval string  Path to an anonymized file
	 */
	private function anonymize($path)
	{
		if (!file_exists($path))
			return '';
		$rootDir = $this->getRootDir();
		$metadata = meddream_extract_meta($rootDir, $path, 0);
		$this->log->asDump('meddream_extract_meta: ', $metadata);

		$studyuid = '';
		if (!empty($metadata['studyuid']))
		{
			if (empty($this->uids[$metadata['studyuid']]))
				$this->uids[$metadata['studyuid']] = $this->generateUid(1);
			$studyuid = $this->uids[$metadata['studyuid']];
		}
		$seriesuid = '';
		if (!empty($metadata['seriesuid']) && array_key_exists('sernum', $metadata))
		{
			if (empty($this->uids[$metadata['seriesuid']]))
				$this->uids[$metadata['seriesuid']] = $this->generateUid(2, (int) $metadata['sernum']);
			$seriesuid = $this->uids[$metadata['seriesuid']];
		}
		$imageuid = '';
		if (!isset($metadata['instancenum']))
		{
			$metadata['instancenum'] = 0;
		}
		$imageuid = $this->generateUid(3, $metadata['instancenum']);

		$this->log->asDump('$studyuid = ', $studyuid);
		$this->log->asDump('$seriesuid = ', $seriesuid);
		$this->log->asDump('$imageuid = ', $imageuid);
		if (empty($studyuid) || empty($seriesuid) || empty($imageuid))
			return '';
		//do anonimize
		$path = $this->fixPath($path);
		$out = $this->fixPath($rootDir . '/temp/' . basename($path));
		if (PHP_OS != "WINNT")
			$anonimizer = $this->fixPath($rootDir . '/anonymizer/anonymizer.sh');
		else
			$anonimizer = $this->fixPath($rootDir . '/anonymizer/anonymizer.bat');
		$command = '"'. $anonimizer . '" "'. $path .'" "' . $out . '"';
		$command .= ' ' . $studyuid . ' ' . $seriesuid . ' ' . $imageuid;
		$command .= ' 2>&1';

		$outCmd = array();
		$this->log->asDump('$command = ', $command);
		$return['error'] = $this->tryExec($command, $outCmd);

		/* any output of the anonymizer likely means an error */
		$outCmd = trim(implode("\n", $outCmd));
		if (strlen($outCmd))
			$this->log->asErr('$outCmd = ' . var_export($outCmd, true));

		if (!file_exists($out))
			return '';

		return $out;
	}


	/** @brief Correct some slashes in the path.

		@param string $path

		@return string  A possibly updated copy of @p $path
	 */
	private function fixPath($path)
	{
		$path = str_replace("\\", '/', $path);
		return $path;
	}


	/** @brief Execute an external program in a safe way.

		@param string $comand  Full command line including the program itself
		@param array  $out     Will hold the output on return

		@return string  Additional error message
	 */
	function tryExec($comand, &$out = array())
	{
		if (trim($comand) == '')
			return '';

		$sessionId = session_id();
		session_write_close();
		set_time_limit(0);

		$error = '';
		try
		{
			exec($comand, $out);
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
		}

		session_id($sessionId);
		if (!strlen(session_id()))
			session_start();

		return $error;
	}


	/** @brief A single iteration over existing job objects.

		Will process all found jobs intended for this class (type "sendToDL").
	 */
	function run()
	{
		$audit = new Audit('RUN JOB');

		/* login */
		$backend = $this->getBackend();
		if (is_null($backend))
		{
			$audit->log(false, 'SendToDicomLibrary');
			return date("Y-m-d H:i:s") . ' See the log: not connected';
		}

		/* search */
		$jobsClass = new Jobs($backend, $this->log);
		$jobslist = $jobsClass->getJobs('sendToDL');
		if (empty($jobslist['error']) && !empty($jobslist['list']))
		{
			$jobslist = $jobslist['list'];
			foreach ($jobslist as $jobId)
			{
				/* get content */
				$jobData = $jobsClass->getJob($jobId);
				if (empty($jobData['error']) &&
					!empty($jobData['data']) &&
					empty($jobData['data']['error']))
				{
					$auditDetails = $jobId;
					if (isset($jobData['data']['mailto']))
						$addr = $jobData['data']['mailto'];
					else
						$addr = '<unspecified>';
					$auditDetails .= ": to $addr";
					$uidQuote = '';
					if (isset($jobData['data']['imageuids']))
					{
						$num = count($jobData['data']['imageuids']);
						if ($num)
							$uidQuote = ", first UID '" . $jobData['data']['imageuids'][0] . "'";
					}
					else
						$num = 0;
					$auditDetails .= ", $num image(s)";
					$auditDetails .= $uidQuote;

					/* send */
					$result = $this->sendFiles($jobData['data']);
					if (!empty($result['error']))
					{
						$audit->log(false, $auditDetails);
						$jobData['data']['error'] = $result['error'];
						$result = $jobsClass->updateJob($jobId, $jobData['data']);
						return date("Y-m-d H:i:s") . ' See the log: ' . $jobId . ' ' . $jobData['data']['error'];

					}
					else
					{
						$audit->log(true, $auditDetails);
							/* log this before DELETE JOB, as the latter is a separate thing.
							   Failure to remove the job simply means that it will be executed
							   again, with its own success indication.
							 */

						/* delete */
						$result = $jobsClass->deleteJob($jobId);
						if (!empty($result['error']))
						{
							$jobData['data']['error'] = $result['error'];
							$result = $jobsClass->updateJob($jobId, $jobData['data']);
							return date("Y-m-d H:i:s") . ' See the log: ' . $jobId . ' ' . $jobData['data']['error'];
						}
						else
						{
							$this->log->asInfo('Sent successfully: ' . var_export($jobData['data'], true));
						}
					}
				}
				unset($jobData);
			}
		}

		return '';
	}
}
