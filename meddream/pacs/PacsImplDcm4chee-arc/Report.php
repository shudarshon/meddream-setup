<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\ReportAbstract;


/** @brief Implementation of ReportIface for <tt>$pacs='dcm4chee-arc'</tt>. */
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

		if ($authDB->getDbms() == "OCI8")
			$studynotesExpr = 'NVL2(studynotes.pk, 1, 0)';
		else
			$studynotesExpr = "studynotes.pk IS NOT NULL";
		$sql = 'SELECT study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, series.modality,' .
				" study_desc, study_date, study_time, accession_no, ref_physician, $studynotesExpr" .
				' AS notes' .
			' FROM study' .
			' LEFT JOIN studynotes ON study.pk=studynotes.study_fk, patient, series' .
			" WHERE study.pk=" . $authDB->sqlEscapeString($studyUid) . " AND patient.pk=study.patient_fk" .
				' AND series.study_fk=study.pk' .
			' GROUP BY study.pk, study_iuid, study_id, pat_id, pat_name, pat_birthdate, series.modality,' .
				' study_desc, study_date, study_time, accession_no, ref_physician, studynotes.pk' .
			' ORDER BY study_date DESC, study_time DESC';
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

			$return["studyid"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["study_id"]));
			$return["referringphysician"] = $cs->utf8Encode($this->shared->cleanDbString(trim(str_replace("^", " ",
				(string) $row["ref_physician"]))));
			$return["readingphysician"] = '';
			$return["accessionnum"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["accession_no"]));
			$return["patientid"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["pat_id"]));
			$return["patientname"] = $cs->utf8Encode(trim(str_replace("^", " ",
				$this->shared->cleanDbString((string) $row["pat_name"]))));
			$return["patientbirthdate"] = $this->shared->cleanDbString((string) $row["pat_birthdate"]);
			$return["patienthistory"] = "";
			$return["modality"] = $this->shared->cleanDbString((string) $row["modality"]);
			$return["description"] = $cs->utf8Encode($this->shared->cleanDbString((string) $row["study_desc"]));
			$return["date"] = $this->shared->cleanDbString($row["study_date"]);
			$return["time"] = $this->shared->cleanDbString($row["study_time"]);
			$return["id"] = (string) $row["pk"];
			$return["notes"] = (int) $row["notes"];
		}
		$authDB->free($rs);

		$sql = 'SELECT * FROM studynotes WHERE study_fk=' . $authDB->sqlEscapeString($studyUid) .
			' ORDER BY created DESC, pk DESC';
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

			$return[$i]['id'] = $row['pk'];
			$return[$i]['user'] = $row['username'];
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

		if ($dbms == 'OCI8')
			$limit_suf = '';
		else
			$limit_suf = ' LIMIT 1';
		$sql = "SELECT pk, username, created, headline, notes FROM studynotes WHERE study_fk=" .
			$authDB->sqlEscapeString($studyUid) . " ORDER BY created DESC, pk DESC$limit_suf";
		if ($dbms == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
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

			$return['id'] = (string) $row['pk'];
			$return['user'] = (string) $row['username'];
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
		{
			if ($dbms == "OCI8")
				$date = "TO_DATE('" . date('Y-m-d H:i:s') . "', 'YYYY-MM-DD HH24:MI:SS')";
			else
				$date = 'NOW()';
		}
		else
			$date = "'" . $authDB->sqlEscapeString($date) . "'";

		if ($user == '')
			$user = $authDB->getAuthUser();

		if ($dbms == "OCI8")
			$sql = 'INSERT INTO studynotes (study_fk, template, username, created, headline,' .
				' notes, pk)' .
				" VALUES ('" .  $authDB->sqlEscapeString($studyUid) . "', ' ', '" .
				$authDB->sqlEscapeString($user) . "', $date, $date, EMPTY_BLOB()," .
				' studynotes_pk_seq4.nextval) RETURNING notes, pk INTO :noteContents, :ID';
			/* NOTE: `notes` column is BLOB here, not CLOB/NCLOB, in order to be truly independent
			   from NLS issues etc
			 */
		else
			$sql = 'INSERT INTO studynotes (study_fk, template, username, created, headline, notes)' .
				" VALUES ('" .  $authDB->sqlEscapeString($studyUid) . "', '', '" .
				$authDB->sqlEscapeString($user) . "', $date, $date, '" .
				$authDB->sqlEscapeString($note) . "')";
		$this->log->asDump('$sql = ', $sql);

		if ($dbms == "OCI8")
			$rs = $authDB->query($sql, ':ID', ':noteContents', $note);
		else
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

		$sql = 'SELECT created,username FROM studynotes WHERE pk=' . $id;
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
		$return['user'] = $row['username'];
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
		$sql = "SELECT pk, template, headline FROM studynotes WHERE username='" .
			$authDB->sqlEscapeString($user) . "' AND study_fk IS NULL ORDER BY template, headline DESC";
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

			$return[$i]['id'] = (string) $row['pk'];
			$return[$i]['group'] = (string) $row['template'];
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

		if ($authDB->getDbms() == "OCI8")
			$sql = 'INSERT INTO studynotes (study_fk, template, username, created, headline, notes, pk)' .
				" VALUES (NULL, '" . $authDB->sqlEscapeString($group) . "', '" .
				$authDB->sqlEscapeString($user) . "', TO_DATE('" . date('Y-m-d H:i:s') .
				"', 'YYYY-MM-DD HH24:MI:SS'), '" . $authDB->sqlEscapeString($name) .
				"', EMPTY_BLOB(), studynotes_pk_seq4.nextval)" .
				' RETURNING notes, pk INTO :notesText, :ID';
		else
			$sql = 'INSERT INTO studynotes (study_fk, template, username, created, headline, notes)' .
				" VALUES (NULL, '" . $authDB->sqlEscapeString($group) . "', '" .
				$authDB->sqlEscapeString($user) . "', NOW(), '" . $authDB->sqlEscapeString($name) .
				"', '" . $authDB->sqlEscapeString($text) . "')";
		$this->log->asDump('$sql = ', $sql);

		if ($authDB->getDbms() == "OCI8")
			$rs = $authDB->query($sql, ':ID', ':notesText', $text);
		else
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
		if ($authDB->getDbms() == "OCI8")
		{
			$date = "TO_DATE('" . date('Y-m-d H:i:s') . "', 'YYYY-MM-DD HH24:MI:SS')";
			$sql = 'UPDATE studynotes SET study_fk=NULL,' .
				" template='" . $authDB->sqlEscapeString($group) . "'," .
				" username='" . $authDB->sqlEscapeString($user) . "'," .
				" created=$date," .
				" headline='" . $authDB->sqlEscapeString($name) . "'," .
				" notes=EMPTY_BLOB()" .
				" WHERE pk=" . $authDB->sqlEscapeString($id) .
				' RETURNING notes INTO :notesText';
		}
		else
			$sql = 'UPDATE studynotes SET study_fk=NULL,' .
				" template='" . $authDB->sqlEscapeString($group) . "'," .
				" username='" . $authDB->sqlEscapeString($user) . "'," .
				" created=NOW()," .
				" headline='" . $authDB->sqlEscapeString($name) . "'," .
				" notes='" . $authDB->sqlEscapeString($text) . "'" .
				" WHERE pk=" . $authDB->sqlEscapeString($id);
		$this->log->asDump('$sql = ', $sql);

		if ($authDB->getDbms() == "OCI8")
			$rs = $authDB->query($sql, '', ':notesText', $text);
		else
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

		$sql = "SELECT * FROM studynotes WHERE username='" . $authDB->sqlEscapeString($user) .
			"' AND pk=" . $authDB->sqlEscapeString($id);
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

			$return['id'] = (string) $row['pk'];
			$return['group'] = (string) $row['template'];
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
		$sql = "DELETE FROM studynotes WHERE username='" . $authDB->sqlEscapeString($user) .
			"' AND pk=" . $authDB->sqlEscapeString($id);
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
		$dbms = $authDB->getDbms();

		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asDump($err);
			$return['error'] = $err;
			return $return;
		}

		if ($dbms == "OCI8")
		{
			$seqName = ', seq';
			$seqValue = ', attachment_seq_seq4.nextval';
		}
		else
		{
			$seqName = '';
			$seqValue = '';
		}

		if (is_null($fileData))
		{
			$sql = "INSERT INTO attachment (uuid, id, path, mimetype, totalsize, data$seqName)" .
				" VALUES ('" . $authDB->sqlEscapeString($studyUid) .
				"', " . $authDB->sqlEscapeString($reportId) .
				", '" . $authDB->sqlEscapeString($fileName) .
				"', '" . $authDB->sqlEscapeString($mimeType) .
				"', " . $authDB->sqlEscapeString($fileSize) .
				", ''$seqValue)";
			if ($dbms == "OCI8")
				$sql .= ' RETURNING seq INTO :ID';
			$this->log->asDump('$sql = ', $sql);

			if ($dbms == "OCI8")
				$rs = $authDB->query($sql, ':ID');
			else
				$rs = $authDB->query($sql);
		}
		else
		{
			if ($dbms == "OCI8")
			{
				$sql = "INSERT INTO attachment (uuid, id, path, mimetype, totalsize, data, seq)" .
					" VALUES ('" .  $authDB->sqlEscapeString($studyUid) .
					"', '" . $authDB->sqlEscapeString($reportId) .
					"', '" . $authDB->sqlEscapeString($fileName) .
					"', '" . $authDB->sqlEscapeString($mimeType) .
					"', '" . $authDB->sqlEscapeString($fileSize) .
					"', EMPTY_BLOB(), attachment_seq_seq4.nextval) RETURNING data, seq INTO :fileContents, :ID";
				$this->log->asDump('$sql = ', $sql);

				$rs = $authDB->query($sql, ':ID', ':fileContents', $fileData);
			}
			else
			{
				$sql = 'INSERT INTO attachment (uuid, id, path, mimetype, totalsize, data)' .
					" VALUES ('" .  $authDB->sqlEscapeString($studyUid) .
					"', '" . $authDB->sqlEscapeString($reportId) .
					"', '" . $authDB->sqlEscapeString($fileName) .
					"', '" . $authDB->sqlEscapeString($mimeType) .
					"', '" . $authDB->sqlEscapeString($fileSize);
				$this->log->asDump('$sql = ', $sql . ', <file data>)');
				$sql .= "', x'" . bin2hex($fileData) . "')";

				$rs = $authDB->query($sql);
			}
		}

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
			$this->log->asDump($err);
			$audit->log(false, $auditDetails);
			$return['error'] = $err;
			return $return;
		}

		$sql = 'SELECT seq, path, mimetype FROM attachment' .
			" WHERE uuid=" . $authDB->sqlEscapeString($studyUid) .
			" AND id=" . $authDB->sqlEscapeString($return["id"]) .
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

		$return["attachment"] = array();
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			if (isset($row["path"]))
			{
				$return["attachment"][$i]["seq"] = $row["seq"];
				$return["attachment"][$i]["filename"] = basename($row["path"]);
				$return["attachment"][$i]["mimetype"] = $row["mimetype"];
			}
			$i++;
		}
		$authDB->free($rs);

		$return['error'] = '';
		if ($i > 0)
			$return["attachment"]["count"] = $i;
		else
			unset($return["attachment"]);

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
			$this->log->asDump($err);
			$return['error'] = $err;
			return $return;
		}

		$sql = 'SELECT path, mimetype, data, totalsize FROM attachment' .
			' WHERE seq=' . $authDB->sqlEscapeString($seq) . ' AND uuid=' .
			$authDB->sqlEscapeString($studyUid);
		if ($authDB->getDbms() == "OCI8")
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
		else
			$sql = "$sql LIMIT 1";
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
			return array('error' => '[Report] Database error (18), see logs');
		}
		$row['error'] = '';

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
			$authDB->sqlEscapeString($seq) . " AND uuid='" .
			$authDB->sqlEscapeString($studyUid) . "'";
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
				$path = $row['path'];

			if (!$innerSize && strlen($path) && @file_exists($path))
				if (!@unlink($path))
					$this->log->asErr("failed to remove attachment file '$path'");
		}

		/* delete from the database (regardless of how data was stored) */
		$sql = 'DELETE FROM attachment WHERE seq=' . $authDB->sqlEscapeString($seq) .
			" AND uuid=" . $authDB->sqlEscapeString($studyUid);
		if ($authDB->getDbms() == "OCI8")
			$sql = "$sql AND ROWNUM <= 1";
		else
			$sql = "$sql LIMIT 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$audit->log(false, $auditDetails);
			return array('error' => '[Report] Database error (19), see logs');
		}
		$authDB->free($rs);

		$return = $this->collectAttachments($studyUid, array('id' => $noteId));

		$audit->log(true, $auditDetails);
		$this->log->asDump('end ' . __METHOD__);
			/* no need to log $result, that was already done in collectAttachments() */

		return $return;
	}
}
