<?php

namespace Softneta\MedDream\Core\QueryRetrieve;


/** @brief Full implementation based on DCM4CHEE2 Toolkit.

	Data is transferred via standard output from the DcmQr tool.
*/
class QrToolkitWrapper extends QrToolkit implements QrNonblockingIface
{
	public function fetchStudyStart($studyUid)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');

		if (!$this->retrieveEntireStudy)
			return array('error' => 'internal: unexpected call to ' . __METHOD__);		/* shoo, lazy programmers */

		/* build the command line */
		$dra = explode('@', $this->localListener);
		$arg = '-L ' . escapeshellarg($this->localAet) . ' ' . escapeshellarg($this->remoteListener) .
			' -qStudyInstanceUID=' . escapeshellarg($studyUid) .
			' -cmove ' . $dra[0];

		/* run the command and parse results */
		session_write_close();		/* against bug #44942 if multiple users are using this function */
		$cmd = $this->toolPath . ' ' . $arg . ' 2>&1';
		$log->asDump('about to run: ', $cmd);
		$descriptors = array(
			0 => array('file', $this->nullDevice, 'r'),
			1 => array('pipe', 'w'),
			2 => array('file', $this->nullDevice, 'w')
		);
		$chld = proc_open($cmd, $descriptors, $pipes);
		if ($chld === FALSE)
		{
			$log->asErr("failed to run dcmqr as '$cmd'");
			return array('error' => '[DICOM] proc_open() failed???');
		}
		$details = proc_get_status($chld);
		stream_set_timeout($pipes[1], 0, 300000);
		stream_set_blocking($pipes[1], 0);
		$log->asDump('started process ', $details['pid']);

		$log->asDump('end ' . __METHOD__);
		return array('error' => '',
				/* details of a problem from this function and its counterparts */
			'object' => $studyUid,
				/* UID for error messages later */
			'child' => $chld,
				/* handle from proc_open() */
			'pid' => $details['pid'],
				/* process ID of the child */
			'killed' => false,
				/* killed successfully, no need to repeat */
			'output' => $pipes[1],
				/* a pipe to read the child's output from */
			'state' => 0,
				/* 0	skip lines that consist of a single ".", detect whether
				    	dcmqr has started successfully
				   1	there was an error; collect all further lines until EOF without parsing
				   2	searching for results of C-FIND
				   3	searching for results of C-MOVE and for individual C-MOVE-RSP messages
				   4	everything has been found (or termination in progress), waiting for EOF
				   5	full stop -- EOF detected
				 */
			'errbuf' => ''
				/* in case of some errors, will contain all dcmqr output for troubleshooting */
		);
	}


	public function fetchStudyContinue(&$rsrc)
	{
		if (feof($rsrc['output']))
		{
			if (($rsrc['state'] != 1) && ($rsrc['state'] != 4))
			{
				$this->log->asErr('unexpected EOF from dcmqr (parser state = ' . $rsrc['state'] . ')');
				$rsrc['error'] = '[DICOM] dcmqr terminated prematurely';
			}

			$rsrc['state'] = 5;
			return 0;		/* nothing to do, must finish */
		}

		$readStreams = array($rsrc['output']);
		$otherStreams = null;
		$numAvail = stream_select($readStreams, $otherStreams, $otherStreams, 0, 200000);
		if ($numAvail === 0)
//		{
//			$this->log->asInfo('.');
			return 1;		/* nothing to read, try again */
//		}
		if ($numAvail === false)
		{
			$this->log->asWarn('stream_select failed (parser state = ' . $rsrc['state'] . ')');
			return 1;
		}

		$line = trim(fgets($rsrc['output'], 1024));
		$this->log->asDump('{', $rsrc['state'], '} $line = ', $line);

		if ($rsrc['state'] == 4)
			return 1;		/* read more */

		/* If the first string begins with "HH:MM:SS,SSS INFO", it indicates
		   that connection was initiated successfully.

		   Otherwise we may have an error message in similar form, or a
		   free-form error message if dcmqr didn't like the command line.
		   This does not require any further parsing.
		 */
		if (!$rsrc['state'])
		{
			if ($line == '.')	/* skip output of that additional harmless command */
				return 1;

			if (preg_match('/^\d+:\d+:\d+.\d+ INFO /', $line))
			{
				$rsrc['state'] = 2;
				return 1;
			}
			else
				$rsrc['state'] = 1;
				/* something suspicious here, capture everything from now on.
				   This first $line is captured below, too, because we didn't
				   return() immediately.
				 */
		}

		/* capture everything into the error buffer, if needed

		   In contrast to dcm4che_getimage_DICOM(), here we might have lots of
		   data in state #3 and there is no point in saving it all. Furthermore
		   $line is already dumped to the log. We need only most relevant data,
		   suitable for Logging::asErr().
		 */
		if ($rsrc['state'] != 3)
		{
			$rsrc['errbuf'] .= $line . "\n";
			if ($rsrc['state'] == 1)
				return 1;
		}

		/* this time we'll catch errors and a couple of result strings

			At this point $rsrc['state'] is either 2 or 3.
		 */
		if (preg_match('/^\d+:\d+:\d+.\d+ ERROR /', $line))		/* error reports */
		{
			/* collect all remaining output in $rsrc['errbuf'] */
			$rsrc['state'] = 1;
			return 1;
		}
		$cnt = 0;
		if ($rsrc['state'] == 2)
		{
			/* did the C-FIND command succeed? */
			$parsed = preg_replace("/^\d+:\d+:\d+.\d+ INFO   - Received (\d+) matching .*/",
				"$1", $line, -1, $cnt);
			if ($cnt)
			{
				if ($parsed == '0')
				{
					/* do not continue if the object wasn't found */
					$this->log->asErr("study not found: '" . $rsrc['object'] . "'");
					$this->log->asDump('dcmqr reports: ', trim($rsrc['errbuf']));
					$rsrc['error'] = '[DICOM] object not found';
					return -1;
				}
				else
				{
					/* will check for a different command from now on */
					$rsrc['errbuf'] = '';
					$rsrc['state'] = 3;
				}
			}
		}
		else
			if ($rsrc['state'] == 3)
			{
				/* did the C-MOVE command succeed? */
				$parsed = preg_replace("/^\d+:\d+:\d+.\d+ INFO   - Retrieved (\d+) objects .*/",
					"$1", $line, -1, $cnt);
				if ($cnt)
				{
					$rsrc['state'] = 4;
					if ($parsed == '0')
					{
						/* do not continue if the object wasn't retrieved */
						$this->log->asErr("study wasn't retrieved: '" . $rsrc['object'] . "'");
						$this->log->asDump('dcmqr reports: ', trim($rsrc['errbuf']));
						$rsrc['error'] = '[DICOM] the C-MOVE operation failed';
						return -1;
					}
					else
						/* all is well, a few remaining messages should be consumed until EOF */
						$rsrc['errbuf'] = '';
				}
			}

		return 1;
	}


	public function fetchStudyBreak(&$rsrc)
	{
		if ($rsrc['killed'])
			return;

		$rsrc['state'] = 4;

/*
		$ctlSocket = fsockopen('127.0.0.1', 8500, $errCode, $errStr, 2);
//TODO: meddream.dcmrcv_ctl_port (use it when starting and for port here), also extract IP address from $this->localListener
		if (!$ctlSocket)
			$this->log->asErr("connecting to receiver's control port failed: ($errCode) $errStr");
		else
		{
			$cmd = $rsrc['object'] . "\r\n\r\n";

			stream_set_timeout($ctlSocket, 2);
			fwrite($ctlSocket, $cmd);
			$r = fgets($ctlSocket, 128);
			fclose($ctlSocket);

			if (trim($r) !== '1')
				$this->log->asWarn('on cancel of study ' . $rsrc['object'] . ', the receiver replied with ' .
					var_export($r, true));
		}
*/

		$success = meddream_kill_tree(__DIR__, $rsrc['pid']);
		if ($success)
		{
			$this->log->asInfo('killed process ' . $rsrc['pid']);
			$rsrc['killed'] = true;
		}
		else
			$this->log->asErr('failed to kill process ' . $rsrc['pid']);
	}


	public function fetchStudyEnd(&$rsrc)
	{
		/* this function should be used only after the parsing has ended */
		if ($rsrc['state'] != 5)
			$this->log->asWarn('unexpected parser mode: ' . $rsrc['state']);

		fclose($rsrc['output']);
		proc_close($rsrc['child']);

		/* what if the corresponding strings weren't recognized? */
		if ($rsrc['errbuf'] != '')
		{
			$this->log->asErr('offending dcmqr output(' . $rsrc['state'] . ") is: '" .
				trim($rsrc['errbuf']) . "'");
			return '[DICOM] dcmqr failed, see log';
		}

		return $rsrc['error'];
	}


	public function fetchStudy($studyUid, $silent = false)
	{
		set_time_limit(0);

		$parser = $this->fetchStudyStart($studyUid);
		$e = $parser['error'];
		if (!strlen($e))
		{
			do
			{
				$r = $this->fetchStudyContinue($parser);
				if (($r < 0) && !$silent)
					echo $parser['error'];
			} while ($r);

			$e = $this->fetchStudyEnd($parser);
		}

		return $e;
	}
}
