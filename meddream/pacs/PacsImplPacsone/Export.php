<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\AuthDB;
use Softneta\MedDream\Core\Pacs\ExportIface;
use Softneta\MedDream\Core\Pacs\ExportAbstract;


/** @brief Implementation of ExportIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartExport extends ExportAbstract implements ExportIface
{
	private function getImageCount($studyUID, AuthDB $authDB)
	{
		$return = array('error' => '', 'count' => 0);

		$sql = 'SELECT COUNT(*) FROM image i ' .
			'LEFT JOIN series s ON s.' . $this->commonData['F_SERIES_UUID'] . '=i.seriesuid ' .
			"WHERE s.studyuid='{$studyUID}'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$return['error'] = "[Export] Database error (1), see logs";
			return $return;
		}

		$row = $authDB->fetchNum($rs);
		$this->log->asDump('result: ', $row);
		$authDB->free($rs);

		$return['count'] = $row[0];

		return $return;
	}


	public function createJob($studyUids, $mediaLabel, $size, $exportDir)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $mediaLabel, ', ', $size, ', ', $exportDir, ')');

		/* in case of these two, must fall back to "our own" implementation of export function */
		if (Constants::FOR_WORKSTATION || Constants::FOR_SW)
		{
			$this->log->asInfo(__METHOD__ . ': falling back to ExportAbstract');
			return null;
		}

		$return = array('error' => '');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);
			return $return;
		}
		$err = $authDB->reconnect();
		if (strlen($err))
		{
			$this->log->asErr("AuthDB::reconnect() failed: '$err'");
			return array('error' => $err);
		}

		$user = $authDB->getAuthUser();

		$mediaLabel = '';
		$sql = 'INSERT INTO dbjob SET' .
			' ' . $this->commonData['F_DBJOB_USERNAME'] . "='" . $authDB->sqlEscapeString($user) . "'" .
			", aetitle='_$size'" .
			", type='export'" .
			', ' . $this->commonData['F_DBJOB_CLASS'] . "='study'" .
			', ' .  $this->commonData['F_DBJOB_UUID'] . "='" . $authDB->sqlEscapeString($mediaLabel) . "'" .
			', priority=100' .
			", status='created'" .
			", details='" . $authDB->sqlEscapeString($exportDir) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$return['error'] = "[Export] Database error (2), see logs";
			return $return;
		}

		$id = $authDB->getInsertId();
		$return['id'] = $id;

		$count = 0;
		$studyInstanceUIDArray = explode(';', $studyUids);
		foreach ($studyInstanceUIDArray as $uid)
		{
			$sql = 'REPLACE export SET' .
				" jobid='$id'" .
				', ' . $this->commonData['F_EXPORT_CLASS'] . "='study'" .
				', ' . $this->commonData['F_EXPORT_UUID'] . "='" . $authDB->sqlEscapeString($uid) . "'";
			$this->log->asDump('$sql = ', $sql);

			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				$return['error'] = "[Export] Database error (3), see logs";
				return $return;
			}

			$imageCount = $this->getImageCount($uid, $authDB);
			if (strlen($imageCount['error']))
			{
				$return['error'] = $imageCount['error'];
				return $return;
			}
			$count += $imageCount['count'];
		}
		@file_put_contents($exportDir . DIRECTORY_SEPARATOR . 'count', $count);

		$sql = "UPDATE dbjob SET status='submitted', submittime=NOW() WHERE id='$id'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$return['error'] = "[Export] Database error (4), see logs";
			return $return;
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function getJobStatus($id)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $id, ')');

		/* in case of these two, must fall back to "our own" implementation of export function */
		if (Constants::FOR_WORKSTATION || Constants::FOR_SW)
		{
			$this->log->asInfo(__METHOD__ . ': falling back to ExportAbstract');
			return null;
		}

		$return = array('error' => '', 'status' => '');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$return['error'] = 'not authenticated';
			$this->log->asErr($return['error']);
			return $return;
		}
		$err = $authDB->reconnect();
		if (strlen($err))
		{
			$this->log->asErr("AuthDB::reconnect() failed: '$err'");
			return array('error' => $err);
		}

		$sql = "SELECT status FROM dbjob WHERE id='" . $authDB->sqlEscapeString($id) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			$return['error'] = "[Export] Database error (5), see logs";
			return $return;
		}

		$row = $authDB->fetchNum($rs);
		$authDB->free($rs);
		if ($row)
			$return['status'] = $row[0];
		else
		{
			$this->log->asErr("job $id not found");
			$return['error'] = "[Export] Job '$id' not found";
			return $return;
		}

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function verifyJobResults($exportDir)
	{
		$countFile = $exportDir . DIRECTORY_SEPARATOR . 'count';
		if (!@file_exists($countFile))
			return "Missing file: '$countFile'";

		$expectedCount = @file_get_contents($countFile);
			/* The file won't be removed. This allows to call this function multiple
			   times. Furthermore, export.php has no problem removing the file
			   together with its entire subdirectory.
			 */
		$actualCount = count(glob($exportDir . "/VOL*/PAT*/STU*/SER*/*"));
			/* not /IMA*: PacsOne names the exported SR files (though not Encapsulated PDFs)
			   by a different pattern, /DOC*
			 */
		if ($expectedCount != $actualCount)
			return "PacsOne exported {$actualCount} files, expecting {$expectedCount}";

		return '';
	}


	public function getAdditionalVolumeSizes()
	{
		return array(
			'data' => array(
				array(
					'id' => 'cd',
					'type' => 'volume',
					'attributes' => array(
						'name' => 'CD',
						'size' => '650',
					),
				),
				array(
					'id' => 'dvd',
					'type' => 'volume',
					'attributes' => array(
						'name' => 'DVD',
						'size' => '4812.8', // 4.7GB * 1024
					),
				),
				array(
					'id' => 'dl_dvd',
					'type' => 'volume',
					'attributes' => array(
						'name' => 'Dual-Layer DVD',
						'size' => '8704', // 8.5GB * 1024
					),
				),
			),
			'default' => 'cd'
		);
	}
}
