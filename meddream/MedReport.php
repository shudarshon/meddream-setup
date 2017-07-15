<?php
/*
	Original name: MedReport.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		A class library that adds server-side read/write access to study
		notes (reports) and their templates.
 */

namespace Softneta\MedDream\Core;

/** @brief A legacy wrapper for Pacs\ReportIface. Adds some PACS-unrelated methods. */
class MedReport
{
	protected $backend = null;      /**< @brief An instance of Backend. */
	protected $log;                 /**< @brief An instance of Logging. */


	/** @brief Return a new or existing instance of Backend.

		@param array   $parts           Names of %PACS parts that will be initialized. This also works
		                                if an instance already exists, thanks to PACS::loadParts().
		@param boolean $withConnection  Is a DB connection required?

		If the underlying AuthDB must be connected to the DB, then will request
		the connection once more.
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	function __construct()
	{
		require_once('autoload.php');
		$this->log = new Logging();
	}


	/** @brief A wrapper for Pacs\ReportIface::createReport(). */
	public function saveStudyNote($studyUID, $note, $date = '', $user = '')
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->createReport($studyUID, $note, $date, $user);
	}


	/** @brief A wrapper for Pacs\ReportIface::collectReports(). */
	public function getStudyNotes($studyUID, $withattachements = false)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->collectReports($studyUID, $withattachements);
	}


	/** @brief A wrapper for Pacs\ReportIface::collectTemplates(). */
	public function getTemplateList()
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->collectTemplates();
	}


	/** @brief A wrapper for Pacs\ReportIface::createTemplate(). */
	public function newTemplate($group, $name, $text)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->createTemplate($group, $name, $text);
	}


	/** @brief A wrapper for Pacs\ReportIface::updateTemplate(). */
	public function editTemplate($id, $group, $name, $text)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->updateTemplate($id, $group, $name, $text);
	}


	/** @brief A wrapper for Pacs\ReportIface::getTemplate(). */
	public function getTemplate($id)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->getTemplate($id);
	}


	/** @brief A wrapper for Pacs\ReportIface::deleteTemplate(). */
	public function deleteTemplate($id)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->deleteTemplate($id);
	}


	/** @brief A wrapper for Pacs\ReportIface::collectAttachments(). */
	public function getAttachement($studyUID, $return)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->collectAttachments($studyUID, $return);
	}


	/** @brief A wrapper for Pacs\ReportIface::deleteAttachment(). */
	public function deleteAttachement($studyUID, $noteID, $seq)
	{
		$backend = $this->getBackend(array('Report'));
		return $backend->pacsReport->deleteAttachment($studyUID, $noteID, $seq);
	}


	/** @brief Related to MedDreamRIS??? */
	public function getReportFromRis($uid)
	{
		$this->log->asDump('begin ' . __METHOD__);

		$return = array('error' => '');

		$backend = $this->getBackend(array('Report'));
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);
			return false;
		}

		if ($uid == '')
		{
			$return['error'] = 'Empty study UID';
			$this->log->asErr($return['error']);
			return $return;
		}

		$cnf = new Configuration();
		$cnf->load();
		if (isset($cnf->data['remote_ris_url']) && ($cnf->data['remote_ris_url'] != ''))
			$url = $cnf->data['remote_ris_url'] . "/getReport.php?uid=$uid";
		else
		{
			$return["error"] = "Failed to get RIS URL'";
			$this->log->asErr($return["error"]);
			return $return;
		}

		$responce = @file_get_contents($url);
		$pass = true;

		if ($responce != '')
		{
			include_once('xml2array.php');
			$data = xml2array($responce, array(),true);
			if (isset($data['list']['uid']) &&
				($data['list']['uid'] == $uid) &&
				isset($data['list']['user']) &&
				isset($data['list']['date']) &&
				isset($data['list']['note']) &&
				($data['list']['note'] != ''))
			{
				if ($data['list']['user'] == '')
					$data['list']['user'] = $authDB->getAuthUser(true);

				$this->log->asDump('$data: ', $data);

				$return['error'] = $this->saveStudyNote($data['list']['uid'], $data['list']['note'],
					$data['list']['date'], $data['list']['user']);
			}
			else
				$pass = false;

		}
		if (!$pass)
		{
			$return["error"] = "Failed to get note";
			$this->log->asErr($return["error"]);
		}
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	static function callHisUrl($url)
	{
		$hdr = "Accept: application/xml\r\n";

		$params = array('http' => array('method' => 'GET',
			'content' => '',
			'timeout' => 15.0,
			'ignore_errors' => true));
		$params['http']['header'] = $hdr;
		$ctx = stream_context_create($params);

		/* send */
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp)
		{
			$err = error_get_last();
			return array(false, $err['message']);
		}
		else
		{
			$headers = $http_response_header;

			if (!count($headers))
				return array(false, 'HTTP headers are missing in the response');
			else
			{
				$status = $headers[0];
				$sa = explode(' ', $status);
				if (count($sa) < 2)
					return array(false, 'strange HTTP Status header: "' . $status . '"');
				else
				{
					$code = $sa[1];
					if ($code != 200)
						return array(false, "HTTP error $code");
				}
			}

			$rsp = @stream_get_contents($fp);
			if ($rsp === false)
			{
				$err = error_get_last();
				return array(false, $err['message']);
			}
			else
				return array($rsp, '');
		}
	}


	/** @brief Fetch related reports from HIS.

		@param string $uid  %Study Instance UID of the study in question

		@return array

		A mechanism that enables to view reports entered in the HIS by replacing the Reporting
		module. The button in the study header will call this function instead of the
		usual functions. <tt>$his_report_link</tt> in config.php, and <tt>+Report</tt> in
		the license, are required.

		Format of the returned array:

		<ul>
		  <li><tt>'error'</tt> - error message (empty if success)
		  <li><tt>'data'</tt> - data to be displayed in the GUI
		</ul>

		Format of <tt>'data'</tt>:

		<ul>
			<li>@c 0 ... @c N - @c SINGLE_REPORT subarray

			Elements of @c SINGLE_REPORT subarray:

			<ul>
				<li><tt>LIN</tt>
				<li><tt>PatientName</tt>
				<li><tt>DocDate</tt>
				<li><tt>DocTitle</tt>
				<li><tt>DocType</tt>
				<li><tt>DocLinkHtml</tt>
				<li><tt>DocLinkPdf</tt>
				<li><tt>AuthorName</tt>
			</ul>
		</ul>

HIS is queried for related reports via a HTTP(s) request at URL <tt>$medreport_root_link$uid</tt>.
The following XML structure is expected in case of success

@code{.xml}
<Documents>
	<Document>
		<LIN>patient ID</LIN>
		<PatientName>last first</PatientName>
		<DocDate>YYYY-MM-DDTHH:II:SS</DocDate>
		<DocTitle>document title</DocTitle>
		<DocType>basically another title</DocType>
		<DocLinkHtml>URL to a HTML version of the report</DocLinkHtml>
		<DocLinkPdf>URL to a PDF version of the report</DocLinkPdf>
		<AuthorName>name of physician who created the report</AuthorName>
	</Document>
	<Document>
		...
	</Document>
	...
</Documents>
@endcode

and in case of failure

@code{.xml}
<Error>
	<Message>error from HIS (for example, "study not found")</Message>
</Error>
@endcode
	 */
	function getReportFromHis($uid)
	{
		$audit = new Audit('GET HIS REPORT');

		$this->log->asDump('begin ' . __METHOD__);

		$return = array();
		$return["error"] = "";
		$backend = $this->getBackend(array(), false);
		if (!$backend->authDB->isAuthenticated())
		{
			$return["error"] = 'not authenticated';
			$this->log->asErr($return["error"]);
			$audit->log(false, "study '$uid'");
			return $return;
		}

		if ($uid == '')
		{
			/* wrong arguments from meddream.swf (or amfPHP?) */
			$return["error"] = 'internal: empty study UID';
			$this->log->asErr($return["error"]);
			$audit->log(false, "study '$uid'");
			return $return;
		}

		if (strlen($backend->hisReportLink))
			$url = $backend->hisReportLink . $uid;
		else
		{
			/* meddream.swf should not call us in case of empty $his_report_link,
			   but what if it does?
			 */
			$return["error"] = 'internal: empty $his_report_link (config.php)';
			$this->log->asErr($return["error"]);
			$audit->log(false, "study '$uid'");
			return $return;
		}

		$this->log->asDump('$url = ' . $url);

		session_write_close();

		list($response, $transport_error) = self::callHisUrl($url);

		$this->log->asDump('$response = ' . $response);

		if ($response !== false)
		{
			include_once('xml2array.php');
			$data = xml2array($response, array(), true);
			$return['data'] = '';

			if (isset($data['Error']['Message']))
			{
				$return["error"] = $data['Error']['Message'];
				$this->log->asErr('error from HIS server: "' . $return["error"] . '"');
				$audit->log(false, "study '$uid'");
			}
			else
			{
				if (isset($data['Documents']['Document']))
					$return['data'] = $data['Documents']['Document'];

				if ($return['data'] == '')
					$return["error"] = "Unrecognized server response:\n\n$response";
				else
					$audit->log(true, "study '$uid'");
			}
		}
		else
		{
			$return["error"] = 'No data from the HIS server';
			if (strlen($transport_error))
				$return["error"] .= ". Details:\n\n" . $transport_error;
			$this->log->asErr($return["error"]);
			$audit->log(false, "study '$uid'");
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}
}

?>
