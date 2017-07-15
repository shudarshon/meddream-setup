<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Audit;
use Softneta\MedDream\Core\Logging;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Pacs\ForwardIface;
use Softneta\MedDream\Core\Pacs\ForwardAbstract;


/** @brief Implementation of ForwardIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartForward extends ForwardAbstract implements ForwardIface
{
	public function createJob($studyUid, $dstAe)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$audit = new Audit('FORWARD SUBMIT');

		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $dstAe, ')');

		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$audit->log(false, "studies '$studyUid', to '$dstAe'");
			$log->asErr($err);
			return array('error' => $err);
		}
		$err = $authDB->reconnect();
		if (strlen($err))
		{
			$audit->log(false, "studies '$studyUid', to '$dstAe'");
			$this->log->asErr("AuthDB::reconnect() failed: '$err'");
			return array('error' => $err);
		}

		$user = $authDB->sqlEscapeString($authDB->getAuthUser());
		$studyUIDArray = explode(';', $studyUid);

		$return = array('error' => '');
		$ids = array();
		foreach ($studyUIDArray as $uid)
		{
			$auditDetails = "study '$uid', to '$dstAe'";
			$uid = $authDB->sqlEscapeString($uid);

			$sql = 'INSERT INTO dbjob (' . $this->commonData['F_DBJOB_USERNAME'] . ', aetitle,' .
					' type, priority, ' . $this->commonData['F_DBJOB_CLASS'] . ', ' .
					$this->commonData['F_DBJOB_UUID'] . ', submittime, schedule, status)' .
				" VALUES ('{$user}', '{$dstAe}', 'Forward', 1, 'study', '{$uid}', NOW(), -1, 'submitted')";
			$this->log->asDump('$sql = ', $sql);

			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$audit->log(false, $auditDetails);
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return array('error' => '[Forward] Database error (1), see logs');
			}
			$count = $authDB->getAffectedRows($rs);
			if (!$count)
			{
				$audit->log(false, $auditDetails);
				$this->log->asErr('strange number of inserted row(s): ' . var_export($count, true));
				return array('error' => '[Forward] Database error (2), see logs');
			}
			$tmpId = $authDB->getInsertId();
			$authDB->free($rs);

			$audit->log("SUCCESS, job id $tmpId", $auditDetails);

			$ids[] = $tmpId;
		}
		$return['id'] = implode(';', $ids);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function getJobStatus($id)
	{
		$audit = new Audit('FORWARD STATUS');

		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $id, ')');

		if (!$authDB->isAuthenticated())
		{
			$audit->log(false, $id);
			$err = 'not authenticated';
			$log->asErr($err);
			return array('error' => $err);
		}
		$err = $authDB->reconnect();
		if (strlen($err))
		{
			$audit->log(false, $id);
			$this->log->asErr("AuthDB::reconnect() failed: '$err'");
			return array('error' => $err);
		}

		$rs = $authDB->query("SELECT status FROM dbjob WHERE id='" . $authDB->sqlEscapeString($id) . "'");
		if (!$rs)
		{
			$audit->log(false, $id);
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Forward] Database error (3), see logs');
		}

		$return = array('error' => '', 'status' => '');
		$row = $authDB->fetchNum($rs);
		$authDB->free($rs);
		if ($row)
		{
			$audit->log($row[0], $id);
			$return['status'] = $row[0];
		}
		else
		{
			$audit->log(false, $id);
			$this->log->asErr("job $id not found");
			$return['error'] = "[Forward] Job '$id' not found";
			return $return;
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function collectDestinationAes()
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '()');

		if (!$authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$log->asErr($err);
			return array('error' => $err);
		}
		$err = $authDB->reconnect();
		if (strlen($err))
		{
			$this->log->asErr("AuthDB::reconnect() failed: '$err'");
			return array('error' => $err);
		}

		$rs = $authDB->query('SELECT title, description FROM applentity ORDER BY title ASC');
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Forward] Database error (4), see logs');
		}

		$i = 0;
		$return = array('error' => '', 'count' => 0);
		while ($row = $authDB->fetchAssoc($rs))
		{
			$return[$i] = array();
			$return[$i]['data'] = (string) $row['title'];
			$return[$i]['label'] = (string) $row['title'] . ' - ' . $this->cs->utf8Encode((string) $row['description']);
			$i++;
		}
		$authDB->free($rs);
		$return['count'] = $i;

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}
}
