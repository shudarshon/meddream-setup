<?php
/*
	Original name: Job.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>
		kf <kestutis.freigofas@softneta.com>

	Description:
		Manage jobs for some background processing
 */
namespace Softneta\MedDream\Core;

require_once __DIR__ . '/autoload.php';


/** @brief Manage job objects for some background processing.

	addJob() places a job object file in the @c scripts/jobs subdirectory. Name
	of the file consists of second-resolution timestamp and some random numbers;
	the extension is the same as the job name. This way the same job processor
	can handle multiple job objects.

	getJobs() lists object identifiers for a specified job (or all jobs). These
	are sorted in ascending order so that older entries would be picked up earlier.

	getJob(), updateJob(), deleteJob() implement further manipulation. Together
	with getJobs() they are expected to be used by particular job processors (a
	single example so far is SendToDicomLibrary). That is, some other service
	or GUI only creates job objects from scratch, while job processors update or
	delete them as needed.

	Job processors are intended to be run by some different mechanism, for example,
	cron. __There is no method to stop a running processor.__
 */
class Jobs
{
	/** @brief Enable audit logging in getJobs(), updateJob(), getJob(), deleteJob().

		Usually it's not needed as these methods are called by a trusted background
		process.
	 */
	const DETAILED_AUDIT = false;

	protected $log = null;          /** @brief An instance of Logging */
	protected $backend = null;      /** @brief An instance of Backend */


	/** @brief Return a new or existing instance of Backend.

		@return Backend

		If the underlying AuthDB must be connected to the DB, then will request the
		connection once more.
	 */
	private function getBackend()
	{
		if (is_null($this->backend))
		{
			$this->backend = new Backend(array(), true, $this->log);
			if (!$this->backend->authDB->isAuthenticated())
				$this->backend = null;
		}
		return $this->backend;
	}


	public function __construct(Backend $backend = null, Logging $log = null)
	{
		if (is_null($log))
			$this->log = new Logging();
		else
			$this->log = $log;

		$this->backend = $backend;
	}


	/** @brief Return the base directory for <tt>scripts/jobs/*</tt>. */
	public function getRootDir()
	{
		return __DIR__;
	}


	/** @brief Return full path to the working directory, <tt>scripts/jobs/</tt>.

		@retval ''         The directory is missing or not writeable
		@retval otherwise  Full path

		Attempts to create the directory if needed. Checks if it is writeable.
	 */
	public function getJobsDir()
	{
		$dir = $this->getRootDir() . DIRECTORY_SEPARATOR . 'scripts' .
			DIRECTORY_SEPARATOR . 'jobs';
		if (!file_exists($dir))
		{
			@mkdir($dir);
			@chmod($dir, 0777);
		}
		if (file_exists($dir))
		{
			if (is_writable($dir))
				return $dir;
		}
		$this->log->asErr('missing job directory or not writable: ' . $dir);
		return '';
	}


	/** @brief Add a new job.

		@param array $data  <tt>array('jobName' => 'free-form name', other data);</tt>

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message, empty if success
			<li><tt>'jobId'</tt> - identifier generated from current timestamp, random number
			                       and <tt>$data['jobName']</tt>. Corresponds to name of the
			                       job object file.
			<li><tt>'jobName'</tt> - duplicate of <tt>$data['jobName']</tt>
		</ul>
	 */
	public function addJob($data)
	{
		$audit = new Audit('ADD JOB');
		$return = array('error' => '', 'jobId' => '');
		$this->log->asDump('begin ' . __METHOD__ . '(', $data, ')');

		if (empty($data['jobName']))
		{
			$audit->log(false, 'missing name');
			$return['error'] = 'missing job name';
			$this->log->asErr($return['error']);
			return $return;
		}
		$backend = $this->getBackend();
		if (is_null($backend))
		{
			$audit->log(false, $data['jobName']);
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		set_time_limit(0);
		ignore_user_abort(true);
		session_write_close();

		$fileName = $this->getJobsDir();
		if (strlen(trim($fileName)) == 0)
		{
			$audit->log(false, $data['jobName']);
			$return['error'] = 'Failed to add job (1), see the log';
			return $return;
		}
		$return['jobId'] = date("YmdHis") . rand(10000, 99999) . '.' . $data['jobName'];
		$return['jobName'] = $data['jobName'];
		$fileName .= DIRECTORY_SEPARATOR . $return['jobId'];
		$data['error'] = '';
		$data['jobStatus'] = 0;
		if (!$this->saveJobData($fileName, $data))
		{
			$audit->log(false, $data['jobName']);
			$return['error'] = 'Failed to add job (2), see the log';
			$this->log->asErr('Failed to add job : ' . $fileName);
		}
		else
			$audit->log('SUCCESS, job id ' . $return['jobId'], $data['jobName']);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Update a job referenced by its identifier.

		@param string $jobId  Identifier created by addJob(). __Same as name of job object file.__
		@param array $data    <tt>array('jobName' => 'free-form name', other data);</tt>

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message, empty if success
			<li><tt>'jobId'</tt> - duplicate of @p jobId
			<li><tt>'jobName'</tt> - duplicate of <tt>$data['jobName']</tt>
		</ul>
	 */
	public function updateJob($jobId, $data)
	{
		$audit = new Audit('UPDATE JOB');
		$this->log->asDump('begin ' . __METHOD__ . '(', $jobId, ', ', $data, ')');

		$return = array('error' => '', 'jobId' => $jobId, 'jobName' => '');

		if (strlen(trim($jobId)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, 'missing id');
			$return['error'] = 'missing job id';
			$this->log->asErr($return['error']);
			return $return;
		}
		if (empty($data['jobName']))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'missing job name';
			$this->log->asErr($return['error']);
			return $return;
		}
		if (!array_key_exists('jobStatus', $data))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'missing job status';
			$this->log->asErr($return['error']);
			return $return;
		}

		$backend = $this->getBackend();
		if (is_null($backend))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		set_time_limit(0);
		ignore_user_abort(true);
		session_write_close();

		$return['jobName'] = $data['jobName'];
		$fileName = $this->getJobsDir();
		if (strlen(trim($fileName)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'Failed to update job (1), see the log';
			return $return;
		}
		$fileName .= DIRECTORY_SEPARATOR . $return['jobId'];
		if (!$this->saveJobData($fileName, $data))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'Failed to update job (2), see the log';
		}
		else
			if (self::DETAILED_AUDIT)
				$audit->log(true, $jobId);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Serialize and save job data to a job object file.

		@param string $fileName  Path to the file
		@param array $data       Array with anything

		@return boolean @c true: saved successfully
	 */
	public function saveJobData($fileName, $data)
	{
		$data = @serialize($data);
		return (@file_put_contents($fileName, $data) !== false);
	}


	/** @brief Load and unserialize a job object file.

		@param string $fileName  Path to the file

		@return array  Array with anything on success; empty array on failure

		@warning If saveJobData() has saved an empty array into this object file, the result
		         is indistinguishable from a failure.
	 */
	public function getJobData($fileName)
	{
		$content = @file_get_contents($fileName);
		if (strlen(trim($content)) != 0)
		{
			$data = @unserialize($content);
			if (is_array($data))
				return $data;
		}
		return array();
	}


	/** @brief Return list of jobs (older files first).

		@param string $jobName   Include only jobs with this name; if empty string, include all

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message, empty if success
			<li><tt>'list'</tt> - numerically-indexed array with names of job identifiers
		</ul>
	 */
	public function getJobs($jobName = '')
	{
		$audit = new Audit('LIST JOBS');
		$this->log->asDump('begin ' . __METHOD__ . '(', $jobName, ')');

		$return = array('error' => '', 'list' => array());

		$backend = $this->getBackend();
		if (is_null($backend))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobName);
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		$directory = $this->getJobsDir();
		if (strlen(trim($directory)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobName);
			$return['error'] = 'Failed to collect jobs, see the log';
			return $return;
		}
		if ($jobName != '')
			$search = '.' . $jobName;
		else
			$search = '';
		$list = array_filter(glob($directory . '/*' . $search), 'is_file');
		if (count($list) > 0)
		{
			array_multisort(array_map(array($this, 'getFilemTime'), $list), SORT_NUMERIC, SORT_ASC, $list);
			foreach ($list as $path)
				$return['list'][] = basename($path);
		}

		if (self::DETAILED_AUDIT)
			$audit->log(true, $jobName);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Load a job referenced by its identifier.

		@param string $jobId  Identifier created by addJob(). __Same as name of job object file.__

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message, empty if success
			<li><tt>'data'</tt> - see <tt>$data</tt> parameter of addJob()
		</ul>
	 */
	public function getJob($jobId)
	{
		$audit = new Audit('GET JOB');
		$this->log->asDump('begin ' . __METHOD__ . '(', $jobId, ')');

		$return = array('error' => '', 'data'=>array());

		if (strlen(trim($jobId)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, 'missing id');
			$return['error'] = 'missing job id';
			$this->log->asErr($return['error']);
			return $return;
		}
		$backend = $this->getBackend();
		if (is_null($backend))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		$directory = $this->getJobsDir();
		if (strlen(trim($directory)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'Failed to load job (1), see the log';
			return $return;
		}
		$fileName = $directory . DIRECTORY_SEPARATOR . $jobId;
		if (file_exists($fileName))
		{
			$return['data'] = $this->getJobData($fileName);
				/* TODO: getJobData should return FALSE in case of error */
			if (self::DETAILED_AUDIT)
				$audit->log(true, $jobId);
		}
		else
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$this->log->asErr('missing file: ' . $fileName);
			$return['error'] = 'Failed to load job (2), see the log';
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Delete a job referenced by its identifier.

		@param string $jobId  Identifier created by addJob(). __Same as name of job object file.__

		@return array  <tt>array('error' => '', 'jobId' => $jobId)</tt>
	 */
	public function deleteJob($jobId)
	{
		$audit = new Audit('DELETE JOB');
		$this->log->asDump('begin ' . __METHOD__ . '(', $jobId, ')');

		$return = array('error' => '', 'jobId'=>$jobId);

		if (strlen(trim($jobId)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, 'missing id');
			$return['error'] = 'missing job id';
			$this->log->asErr($return['error']);
			return $return;
		}
		$backend = $this->getBackend();
		if (is_null($backend))
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$this->log->asErr('not authenticated');
			return array('error' => 'not authenticated');
		}

		$directory = $this->getJobsDir();
		if (strlen(trim($directory)) == 0)
		{
			if (self::DETAILED_AUDIT)
				$audit->log(false, $jobId);
			$return['error'] = 'Failed to delete job (1), see the log';
			return $return;
		}
		$fileName = $directory . DIRECTORY_SEPARATOR . $jobId;
		if (file_exists($fileName))
		{
			if (@unlink($fileName) === false)
			{
				if (self::DETAILED_AUDIT)
					$audit->log(false, $jobId);
				$return['error'] = 'Failed to delete job (2), see the log';
				$this->log->asErr('Failed to delete: ' . $fileName);
			}
			else
				if (self::DETAILED_AUDIT)
					$audit->log(true, $jobId);
		}
		else
		{
			$this->log->asWarn('File does not exist: ' . $fileName);
			if (self::DETAILED_AUDIT)
				$audit->log('SUCCESS (file missing)', $jobId);
		}
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Attempt to get the file modification time.

		@param string $path  Path to the file

		@retval false   File is missing
		@retval int     Modification time

		Will retry up to 10 times if filemtime() fails.
	 */
	public function getFilemTime($path)
	{
		$hastime = false;
		$time = false;
		$count = 0;

		while (!$hastime)
		{
			$count++;
			try
			{
				if (!file_exists($path))
				{
					$time = false;
					break;
				}
				$time = @filemtime($path);

				if ($time !== false)
					$hastime = true;
				else
					$hastime = false;
			}
			catch (Exception $e) // if unclear situation
			{
				sleep(1);
				$hastime = false;
			}
			if ($count > 10)
				break;
		}
		return $time;
	}
}
