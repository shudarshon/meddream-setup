<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\ReportAbstract;


/** @brief Implementation of ReportIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartReport extends ReportAbstract implements ReportIface
{
	public function collectReports($studyUid, $withAttachments = false)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $withAttachments, ')');
		$audit = new Audit('GET REPORTS');

		$return = array('error' => '', 'count' => 0);

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);
			$audit->log(false, "study '$studyUid'");
			return $return;
		}

		$cs = $this->cs;

		if (Constants::FOR_WORKSTATION)
			$cond = ' AND patient.studyuid=study.' . $this->commonData['F_STUDY_UUID'];
		else
			$cond = '';
		$stUuid = $this->commonData['F_STUDY_UUID'];
		$stDate = $this->commonData['F_STUDY_DATE'];
		$stTime = $this->commonData['F_STUDY_TIME'];
		$sql = "SELECT $stUuid, id, patientid, referringphysician, readingphysician, accessionnum," .
				" description, $stDate, $stTime, lastname, firstname, birthdate, history," .
				" GROUP_CONCAT(DISTINCT modality ORDER BY modality SEPARATOR '\\\\') AS modality," .
				'notes' .
			' FROM (' .
				"SELECT study.$stUuid, study.id, patientid, referringphysician, readingphysician," .
					" accessionnum, study.description, study.$stDate, study.$stTime, lastname," .
					' firstname, birthdate, history, modality, studynotes.id AS noteid,' .
					" studynotes.id IS NOT NULL AS 'notes'" .
				' FROM study' .
				" LEFT JOIN studynotes ON study.$stUuid=studynotes." . $this->commonData['F_STUDYNOTES_UUID'] .
					', patient, series' .
				" WHERE study.$stUuid='" . $authDB->sqlEscapeString($studyUid) .
					"' AND patient.origid=study.patientid AND study.$stUuid=series.studyuid$cond" .
			') AS tmp' .
			" GROUP BY $stUuid, noteid, notes" .
			" ORDER BY $stDate DESC, $stTime DESC, noteid DESC";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Report] Database error (1), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "study '$studyUid'");
			return $return;
		}

		$return['studyid'] = '';
		$return['referringphysician'] = '';
		$return['readingphysician'] = '';
		$return['accessionnum'] = '';
		$return['patientid'] = '';
		$return['patientname'] = '';
		$return['patientbirthdate'] = '';
		$return['patienthistory'] = '';
		$return['modality'] = '';
		$return['description'] = '';
		$return['date'] = '';
		$return['time'] = '';
		$return['id'] = $studyUid;
		$return['notes'] = 0;

		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('$row = ', $row);

			$return['studyid'] = $cs->utf8Encode((string) $row['id']);
			$return['referringphysician'] = $cs->utf8Encode((string) $row['referringphysician']);
			$return['readingphysician'] = $cs->utf8Encode((string) $row['readingphysician']);
			$return['accessionnum'] = $cs->utf8Encode((string) $row['accessionnum']);
			$return['patientid'] = $cs->utf8Encode((string) $row['patientid']);
			$return['patientname'] = $cs->utf8Encode($this->shared->buildPersonName((string) $row['lastname'],
				(string) $row['firstname']));
			$return['patientbirthdate'] = (string) $row['birthdate'];
			$return['patienthistory'] = $cs->utf8Encode((string) $row['history']);
			$return['modality'] = (string) $row['modality'];
			$return['description'] = $cs->utf8Encode((string) $row['description']);
			$return['date'] = (string) $row[$this->commonData['F_STUDY_DATE']];
			$return['time'] = (string) $row[$this->commonData['F_STUDY_TIME']];
			$return['id'] = (string) $row[$this->commonData['F_STUDY_UUID']];
			$return['notes'] = (int) $row['notes'];
		}
		$authDB->free($rs);

		$sql = 'SELECT * FROM studynotes WHERE ' . $this->commonData['F_STUDYNOTES_UUID'] .
			"='" . $authDB->sqlEscapeString($studyUid) . "' ORDER BY created DESC, id DESC";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = '[Report] Database error (2), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "study '$studyUid'");
			return $return;
		}

		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result #' . $i . ' = ', $row);

			$return[$i]['id'] = $row['id'];
			$return[$i]['user'] = $row[$this->commonData['F_STUDYNOTES_USERNAME']];
			$return[$i]['created'] = $row['created'];
			$return[$i]['headline'] = $row['headline'];
			$return[$i]['notes'] = $row['notes'];
			$i++;
		}
		$return['count'] = $i;
		$authDB->free($rs);

		if ($withAttachments)
			for ($j = 0; $j < $return['count']; $j++)
			{
				if (!isset($return[$j]['id']))
					continue;

				$r = $this->collectAttachments($studyUid, $return[$j]);
				if (strlen($r['error']))
				{
					$return['error'] = $r['error'];
					$audit->log(false, "study '$studyUid'");
					return $return;
				}
				else
					unset($r['error']);
				$return[$j] = $r;
			}

		$audit->log(true, "study '$studyUid'");
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function getLastReport($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');
		$audit = new Audit('GET LAST REPORT');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$audit->log(false, "study '$studyUid'");
			return array('error' => $err);
		}
		$dbms = $authDB->getDbms();

		$sql = 'SELECT *' .
			' FROM studynotes' .
			' WHERE ' . $this->commonData['F_STUDY_UUID'] . "='" . $authDB->sqlEscapeString($studyUid) .
			"' ORDER BY created DESC, id DESC" .
			' LIMIT 1';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "study '$studyUid'");
			return array('error' => '[Report] Database error (3), see logs');
		}

		$return = array('error' => '');
		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		if ($row)
		{
			$this->log->asDump('$row = ', $row);

			$return['id'] = (string) $row['id'];
			$return['user'] = (string) $row[$this->commonData['F_STUDYNOTES_USERNAME']];
			$return['created'] = (string) $row['created'];
			$return['headline'] = (string) $row['headline'];
			$return['notes'] = (string) $row['notes'];
		}
		else	/* some studies simply do not have reports */
		{
			$return['id'] = null;
			$return['user'] = null;
			$return['created'] = null;
			$return['headline'] = null;
			$return['notes'] = null;
		}

		$audit->log(true, "study '$studyUid'");
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function createReport($studyUid, $note, $date = '', $user = '')
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $note, ', ', $date, ', ', $user, ')');
		$audit = new Audit('SAVE REPORT');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return = 'not authenticated';
			$this->log->asErr($return);
			$audit->log(false, strlen($note) . " byte(s), study '$studyUid'");
			return $return;
		}
		$dbms = $authDB->getDbms();

		if ($date == '')
			$date = 'NOW()';
		else
			$date = "'" . $authDB->sqlEscapeString($date) . "'";

		if ($user == '')
			$user = $authDB->getAuthUser();

		$sql = 'INSERT INTO studynotes (' . $this->commonData['F_STUDYNOTES_UUID'] . ', ' .
				$this->commonData['F_STUDYNOTES_USERNAME'] . ', created, headline, notes)' .
			" VALUES ('" . $authDB->sqlEscapeString($studyUid) . "','" .
				$authDB->sqlEscapeString($user) . "',$date, $date, '" .
				$authDB->sqlEscapeString($note) . "')";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, strlen($note) . " byte(s), study '$studyUid'");
			return '[Report] Database error (4), see logs';
		}
		$numRows = $authDB->getAffectedRows($rs);
		$authDB->free($rs);
		if (!$numRows)
		{
			$this->log->asErr('zero rows affected');
			$audit->log(false, strlen($note) . " byte(s), study '$studyUid'");
			return '[Report] Database error (5), see logs';
		}
		$id = $authDB->getInsertId();
		$this->log->asDump('$id = ', $id);

		$sql = 'SELECT created, ' . $this->commonData['F_STUDYNOTES_USERNAME'] .
			" FROM studynotes WHERE id='$id'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, strlen($note) . " byte(s), study '$studyUid'");
			return '[Report] Database error (6), see logs';
		}

		if (!($row = $authDB->fetchAssoc($rs)))
		{
			$return = 'Problems with database integrity';
			$this->log->asErr('$authDB->fetchAssoc: ' . $return);
			$audit->log(false, strlen($note) . " byte(s), study '$studyUid'");
			return $return;
		}
		$this->log->asDump('$row = ', $row);
		$return['id'] = $id;
		$return['created'] = $row['created'];
		$return['user'] = $row[$this->commonData['F_STUDYNOTES_USERNAME']];
		$authDB->free($rs);

		$audit->log(true, strlen($note) . " byte(s), study '$studyUid'");
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function collectTemplates()
	{
		$this->log->asDump('begin ' . __METHOD__);
		$audit = new Audit('GET TEMPLATES');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$audit->log(false);
			return $err;
		}

		$user = $authDB->getAuthUser();
		$snUuid = $this->commonData['F_STUDYNOTES_UUID'];
		$sql = "SELECT id, $snUuid, headline" .
			' FROM studynotes' .
			' WHERE ' . $this->commonData['F_STUDYNOTES_USERNAME'] . "='" . $authDB->sqlEscapeString($user) .
				"' AND $snUuid LIKE 'TEMPLATE:%'" .
			" ORDER BY $snUuid, headline DESC";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$err = '[Report] Database error (7), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false);
			return $err;
		}

		$return = array();
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump("#$i: \$row = ", $row);

			$return[$i]['id'] = (string) $row['id'];
			$return[$i]['group'] = (string) substr($row[$snUuid], 9);	/* 9: 'TEMPLATE:' */
				/* typecast to string at the very end because the expression yields `false`
				   when there is no text after 'TEMPLATE:'
				 */
			$return[$i]['name'] = (string) $row['headline'];
			$i++;
		}
		$authDB->free($rs);
		$return['count'] = $i;

		$audit->log(true);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function createTemplate($group, $name, $text)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $group, ', ', $name, ', ', $text, ')');
		$audit = new Audit('NEW TEMPLATE');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return = 'not authenticated';
			$this->log->asErr($return);
			$audit->log(false, strlen($text) . " byte(s), named '$name', group '$group'");
			return $return;
		}

		$user = $authDB->getAuthUser();

		$sql = 'INSERT INTO studynotes (' . $this->commonData['F_STUDYNOTES_UUID'] . ', ' .
				$this->commonData['F_STUDYNOTES_USERNAME'] . ', created, headline, notes)' .
			" VALUES ('TEMPLATE:" . $authDB->sqlEscapeString($group) . "', '" .
				$authDB->sqlEscapeString($user) . "', NOW(), '" . $authDB->sqlEscapeString($name) .
				"','" . $authDB->sqlEscapeString($text) . "')";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, strlen($text) . " byte(s), named '$name', group '$group'");
			return '[Report] Database error (8), see logs';
		}
		$numRows = $authDB->getAffectedRows($rs);
		$authDB->free($rs);
		if (!$numRows)
		{
			$this->log->asErr('zero rows affected');
			$audit->log(false, strlen($text) . " byte(s), named '$name', group '$group'");
			return '[Report] Database error (9), see logs';
		}
		$id = $authDB->getInsertId();

		$return[0] = '';
		$return['id'] = $id;
		$return['group'] = $group;
		$return['name'] = $name;

		$audit->log("SUCCESS, id '$id'", strlen($text) . " byte(s), named '$name', group '$group'");
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function updateTemplate($id, $group, $name, $text)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $id, ', ', $group, ', ', $name, ', ', $text, ')');
		$audit = new Audit('EDIT TEMPLATE');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return = 'not authenticated';
			$this->log->asErr($return);
			$audit->log(false, "ID '$id', " . strlen($text) . " byte(s), named '$name', group '$group'");
			return $return;
		}

		$user = $authDB->getAuthUser();

		$sql = 'UPDATE studynotes SET ' .
				$this->commonData['F_STUDYNOTES_UUID'] . "='TEMPLATE:" . $authDB->sqlEscapeString($group) .
				"', " . $this->commonData['F_STUDYNOTES_USERNAME'] . "='" . $authDB->sqlEscapeString($user) .
				"', created=NOW(), headline='" . $authDB->sqlEscapeString($name) .
				"', notes='" . $authDB->sqlEscapeString($text) .
			"' WHERE id=" . $authDB->sqlEscapeString($id);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "ID '$id', " . strlen($text) . " byte(s), named '$name', group '$group'");
			return '[Report] Database error (10), see logs';
		}
		$numRows = $authDB->getAffectedRows($rs);
		$authDB->free($rs);
		if (!$numRows)
		{
			$this->log->asErr('zero rows affected');
			$audit->log(false, "ID '$id', " . strlen($text) . " byte(s), named '$name', group '$group'");
			return '[Report] Database error (11), see logs';
		}

		$audit->log(true, "ID '$id', " . strlen($text) . " byte(s), named '$name', group '$group'");
		$this->log->asDump('end ' . __METHOD__);

		return '';
	}


	public function getTemplate($id)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $id, ')');
		$audit = new Audit('GET TEMPLATE');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return = 'not authenticated';
			$this->log->asErr($return);
			$audit->log(false, "ID '$id'");
			return $return;
		}

		$user = $authDB->getAuthUser();

		$sql = 'SELECT *' .
			' FROM studynotes' .
			' WHERE ' . $this->commonData['F_STUDYNOTES_USERNAME'] . "='" .
				$authDB->sqlEscapeString($user) . "' AND id=" . $authDB->sqlEscapeString($id);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "ID '$id'");
			return '[Report] Database error (12), see logs';
		}

		$return = array('error' => '');
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('$row = ', $row);

			$return['id'] = (string) $row['id'];
			$return['group'] = (string) substr($row[$this->commonData['F_STUDYNOTES_UUID']], 9);
				/* typecast to string at the very end because the expression yields `false`
				   when there is no text after 'TEMPLATE:'
				 */
			$return['name'] = (string) $row['headline'];
			$return['text'] = (string) $row['notes'];
			break;
		}
		$authDB->free($rs);

		$audit->log('SUCCESS, ' . strlen($return['text']) . " byte(s), named '" . $return['name'] .
				"', group '" . $return['group'] . "'",
			"ID '$id'");
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function deleteTemplate($id)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $id, ')');
		$audit = new Audit('DELETE TEMPLATE');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return = 'not authenticated';
			$this->log->asErr($return);
			$audit->log(false, "ID '$id'");
			return $return;
		}

		$user = $authDB->getAuthUser();
		$sql = 'DELETE FROM studynotes' .
			' WHERE ' . $this->commonData['F_STUDYNOTES_USERNAME'] . "='" .
				$authDB->sqlEscapeString($user) . "' AND id=" . $authDB->sqlEscapeString($id);
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, "ID '$id'");
			return '[Report] Database error (13), see logs';
		}
		$numRows = $authDB->getAffectedRows($rs);
		$authDB->free($rs);
		if (!$numRows)
		{
			$this->log->asErr("zero rows affected");
			$audit->log(false, "ID '$id'");
			return '[Report] Database error (14), see logs';
		}

		$audit->log(true, "ID '$id'");
		$this->log->asDump('end ' . __METHOD__);

		return '';
	}


	public function createAttachment($studyUid, $reportId, $mimeType, $fileName, $fileSize, $fileData = null)
	{
		if (is_null($fileData))
			$dataDmp =  'null';
		else
			$dataDmp = '<' . strlen($fileData) . ' byte(s)>';
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $reportId, ', ', $mimeType, ', ',
			$fileName, ', ', $fileSize, ", $dataDmp)");

		$authDB = $this->authDB;

		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$return['error'] = $err;
			return $return;
		}

		$remoteFileName = $this->fp->toRemote($fileName);
		if ($remoteFileName != $fileName)
		{
			$this->log->asInfo("using remote attachment file name '$remoteFileName'");
			$fileName = $remoteFileName;
		}

		if (is_null($fileData))
		{
			$sql = 'INSERT INTO attachment (' . $this->commonData['F_ATTACHMENT_UUID'] .
					', id, path, mimetype, ' . $this->commonData['F_ATTACHMENT_TOTALSIZE'] .
					', data)' .
				" VALUES ('" . $authDB->sqlEscapeString($studyUid) .
				"', " . $authDB->sqlEscapeString($reportId) .
				", '" . $authDB->sqlEscapeString($fileName) .
				"', '" . $authDB->sqlEscapeString($mimeType) .
				"', " . $authDB->sqlEscapeString($fileSize) .
				", '')";
			$this->log->asDump('$sql = ', $sql);
		}
		else
		{
			$sql = 'INSERT INTO attachment (' . $this->commonData['F_ATTACHMENT_UUID'] .
					', id, path, mimetype, ' . $this->commonData['F_ATTACHMENT_TOTALSIZE'] .
					', data)' .
				" VALUES ('" .  $authDB->sqlEscapeString($studyUid) .
				"', '" . $authDB->sqlEscapeString($reportId) .
				"', '" . $authDB->sqlEscapeString($fileName) .
				"', '" . $authDB->sqlEscapeString($mimeType) .
				"', '" . $authDB->sqlEscapeString($fileSize);
			$this->log->asDump('$sql = ', $sql . ', <file data>)');
			$sql .= "', x'" . bin2hex($fileData) . "')";
		}

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Report] Database error (15), see logs');
		}
		$id = $authDB->getInsertId();
		$authDB->free($rs);
		$return = array('error' => '', 'seq' => $id);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function collectAttachments($studyUid, $return)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $return, ')');
		$audit = new Audit('GET ATTACHMENTS');
		$auditDetails = "study '$studyUid', note '" . $return['id'] . "'";

		/* amfPHP 2.0+ sometimes uses an object instead of array */
		if (is_object($return))
			$return = get_object_vars($return);

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$audit->log(false, $auditDetails);
			$return['error'] = $err;
			return $return;
		}

		$sql = 'SELECT seq, path, mimetype FROM attachment' .
			' WHERE ' . $this->commonData['F_ATTACHMENT_UUID'] . "='" . $authDB->sqlEscapeString($studyUid) .
				"' AND id=" . $authDB->sqlEscapeString($return['id']) .
			' ORDER BY seq';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, $auditDetails);
			$return['error'] = '[Report] Database error (16), see logs';
			return $return;
		}

		$return['attachment'] = array();
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			if (isset($row['path']))
			{
				$return['attachment'][$i]['seq'] = $row['seq'];
				$p = str_replace('\\', '/', $row['path']);
					/* basename() on Linux doesn't recognize backslashes as
					   path separators (though on Windows both kinds are OK).
					   This is important for automated tests but also will be
					   useful after migrating an installation to Linux.
					 */
				$return['attachment'][$i]['filename'] = basename($p);
				$return['attachment'][$i]['mimetype'] = $row['mimetype'];
			}
			$i++;
		}
		$authDB->free($rs);

		$return['error'] = '';
		if ($i > 0)
			$return['attachment']['count'] = $i;
		else
			unset($return['attachment']);

		$audit->log(true, $auditDetails);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function getAttachment($studyUid, $seq)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $seq, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			$return['error'] = $err;
			return $return;
		}

		$sizeColName = $this->commonData['F_ATTACHMENT_TOTALSIZE'];
		if ($sizeColName != 'totalsize')
			$sizeColName = $this->commonData['F_ATTACHMENT_TOTALSIZE'] . ' AS totalsize';
		$sql = "SELECT path, mimetype, data, $sizeColName" .
			' FROM attachment' .
			' WHERE seq=' . $authDB->sqlEscapeString($seq) . ' AND ' .
				$this->commonData['F_ATTACHMENT_UUID'] . "='" . $authDB->sqlEscapeString($studyUid) .
			"' LIMIT 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Report] Database error (17), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
			/* won't $log->asDump() it as $row['data'] is usually huge */
		$authDB->free($rs);
		if (!$row)
		{
			$this->log->asErr("record not found: uuid='$studyUid', seq=$seq");
			return array('error' => "Attachment missing ('$studyUid', $seq)");
		}
		$row['error'] = '';
		$row['path'] = $this->fp->toLocal($row['path']);

		$this->log->asDump('end ' . __METHOD__);

		return $row;
	}


	public function deleteAttachment($studyUid, $noteId, $seq)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $noteId, ', ', $seq, ')');

		$audit = new Audit('DELETE ATTACHMENT');
		$auditDetails = "study '$studyUid', note '" . $noteId . "', seq '" . $seq . "'";
		
		$return = array('error' => '');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return["error"] = "not authenticated";
			$this->log->asErr($return["error"]);
			$audit->log(false, $auditDetails);
			return $return;
		}

		/* if attachments are stored as files, then attempt to remove them, too */
		$sql = 'SELECT LENGTH(data) AS len, path FROM attachment WHERE seq=' .
			$authDB->sqlEscapeString($seq) . ' AND ' . $this->commonData['F_ATTACHMENT_UUID'] .
			"='" . $authDB->sqlEscapeString($studyUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");

			/* let's not stop at the moment: it's not critical if the file remains */
		}
		else
		{
			$row = $authDB->fetchAssoc($rs);
			$this->log->asDump('$row = ', $row);
			$authDB->free($rs);

			$innerSize = 0;
			$path = '';
			if (isset($row['len']))
				$innerSize = $row['len'];
			if (isset($row['path']))
				$path = $this->fp->toLocal($row['path']);

			if (!$innerSize && strlen($path) && @file_exists($path))
				if (!@unlink($path))
					$this->log->asErr("failed to remove attachment file '$path'");
		}

		/* delete from the database (regardless of how data was stored) */
		$sql = 'DELETE FROM attachment WHERE seq=' . $authDB->sqlEscapeString($seq) .
				' AND ' . $this->commonData['F_ATTACHMENT_UUID'] . "='" .
				$authDB->sqlEscapeString($studyUid) .
			"' LIMIT 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, $auditDetails);
			return array('error' => '[Report] Database error (18), see logs');
		}
		$authDB->free($rs);

		$return = $this->collectAttachments($studyUid, array('id' => $noteId));

		$audit->log(true, $auditDetails);
		$this->log->asDump('end ' . __METHOD__);
			/* no need to log $result, that was already done in collectAttachments() */

		return $return;
	}
}
