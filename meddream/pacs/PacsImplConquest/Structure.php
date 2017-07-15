<?php

namespace Softneta\MedDream\Core\Pacs\Conquest;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='%Conquest'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$log = $this->log;
		$authDB = $this->authDB;

		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		if ($includePatient)
			$sql = 'SELECT i.ObjectFile, i.DeviceName, i.BitsStored, i.SOPClassUI, p.PatientNam AS fullname' .
				' FROM DICOMImages i' .
				' LEFT JOIN DICOMPatients p ON p.PatientID = i.ImagePat' .
				" WHERE i.SOPInstanc='" . $authDB->sqlEscapeString($instanceUid) . "'";
		else
			$sql = 'SELECT ObjectFile, DeviceName, BitsStored, SOPClassUI FROM DICOMImages' .
				" WHERE SOPInstanc='" . $authDB->sqlEscapeString($instanceUid) . "'";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => "Database error (1), see logs");
		}

		$return = array();
		if ($row = $authDB->fetchAssoc($rs))
		{
			$return['error'] = '';
			$return['uid'] = $instanceUid;

			$log->asDump('result: ', $row);

			$path = $this->shared->getStorageDevicePath($row['DeviceName']);
			if (is_null($path))
			{
				$authDB->free($rs);
				$return['error'] = '[Structure] Configuration error (1), see logs';
				return $return;
			}
			$path .= (string) $row["ObjectFile"];
			$return['path'] = $this->fp->toLocal($path);

			$return['xfersyntax'] = '';
			$return['bitsstored'] = (string) $row["BitsStored"];
			$return['sopclass'] = (string) $row["SOPClassUI"];
			if ($includePatient)
			{
				$return['patientid'] = '';
				$return['fullname'] = $this->cs->utf8Encode((string) $row['fullname']);
				$return['firstname'] = '';
				$return['lastname'] = '';
			}
		}
		else
			$return['error'] = "record not found for instance '$instanceUid'";
		$authDB->free($rs);

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);
		return $return;
	}


	public function seriesGetMetadata($seriesUid)
	{
		$log = $this->log;
		$authDB = $this->authDB;
		$dbms = $this->commonData['dbms'];

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ')');

		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV') || ($dbms == 'POSTGRESQL'))
			$inttype = 'INT';
		else
			$inttype = 'UNSIGNED';
		$sql = 'SELECT i.ObjectFile, i.DeviceName, i.BitsStored, p.PatientNam AS fullname' .
			' FROM DICOMImages i' .
			' LEFT JOIN DICOMPatients p ON p.PatientID = i.ImagePat' .
			" WHERE i.SeriesInst='" . $authDB->sqlEscapeString($seriesUid) .
			"' ORDER BY CAST(i.ImageNumbe AS $inttype), CAST(i.AcqNumber AS $inttype)";
		$log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => 'Database error (2), see logs');
		}

		$fullName = '';
		$series = array('error' => '');
		$i = 0;
		while ($row = $authDB->fetchAssoc($rs))
		{
			$log->asDump("result #$i: ", $row);

			$img = array();

			$path = $this->shared->getStorageDevicePath($row['DeviceName']);
			if (is_null($path))
			{
				$authDB->free($rs);
				$return['error'] = '[Structure] Configuration error (2), see logs';
				return $return;
			}
			$img["path"] = $this->fp->toLocal($path . (string) $row["ObjectFile"]);
			$img["xfersyntax"] = "";
			$img["bitsstored"] = (string) $row["BitsStored"];
			$series["image-".sprintf("%06d", $i++)] = $img;
			$fullName = $row['fullname'];
		}
		$series['count'] = $i;
		$series['firstname'] = '';
		$series['lastname'] = '';
		$series['fullname'] = $fullName;

		$authDB->free($rs);

		$log->asDump('returning: ', $series);
		$log->asDump('end ' . __METHOD__);
		return $series;
	}


	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$dbms = $this->commonData['dbms'];
		$return = array();
		$return['count'] = 0;

		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ', ', $disableFilter, ', ', $fromCache, ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			$return['error'] = 'not authenticated';
			return $return;
		}

		$cs = $this->cs;

		$return['error'] = '';

		$patientid = '';
		$sourceae = '';
		$studydate = '';
		$studytime = '';
		$lastname = '';
		$firstname = '';

		$sql = "SELECT PatientID,StudyDate,StudyTime FROM DICOMStudies WHERE StudyInsta='" .
			$authDB->sqlEscapeString($studyUid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (3), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$patientid = $row["PatientID"];
			$studydate = (string) $row["StudyDate"];
			$studytime = (string) $row["StudyTime"];
		}
		else
		{
			$this->log->asErr('no such study');
			$return['error'] = 'No such study';
			return $return;
		}
		$authDB->free($rs);
		$patient_inst = $patientid;

		$sql = "SELECT PatientNam FROM DICOMPatients WHERE PatientID='" .
			$authDB->sqlEscapeString($patientid) . "'";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (4), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}
		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$lastname = "";
			$firstname = trim(str_replace("^", " ",(string) $row["PatientNam"]));
		}
		$authDB->free($rs);

		$return['lastname'] = $cs->utf8Encode($lastname);
		$return['firstname'] = $cs->utf8Encode($firstname);
		$return['uid'] = $studyUid;
		$return['patientid'] = $cs->utf8Encode($patient_inst);
		$return['sourceae'] = $cs->utf8Encode($sourceae);
		$return['studydate'] = $studydate;
		$return['studytime'] = $studytime;

		$notes = $this->studyHasReport($studyUid);
		$return['notes'] = $notes['notes'];

		$sql = "SELECT SeriesInst,SeriesDesc,Modality,SeriesNumb " .
			"FROM DICOMSeries " .
			"WHERE StudyInsta='" . $authDB->sqlEscapeString($studyUid) . "' " .
				(!$disableFilter ? "AND (Modality IS NULL OR (Modality!='KO' AND Modality!='PR')) " : ' ') .
			'ORDER BY SeriesNumb';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$return['error'] = 'Database error (5), see logs';
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return $return;
		}

		$allSeries = array();
		while ($row1 = $authDB->fetchAssoc($rs))
			$allSeries[] = $row1;
		$authDB->free($rs);

		$i = 0;

		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV') || ($dbms == 'POSTGRESQL'))
			$inttype = 'INT';
		else
			$inttype = 'UNSIGNED';

		foreach ($allSeries as $row1)
		{
			$this->log->asDump('result: ', $row1);

			$modality = (string) $row1["Modality"];
			$seriesUID = $row1["SeriesInst"];
			$description = (string) $row1["SeriesDesc"];

			$sql = "SELECT SOPInstanc,NumberOfFr,ObjectFile,DeviceName,BitsStored,SOPClassUI FROM DICOMImages " .
				"WHERE SeriesInst='" . $authDB->sqlEscapeString($seriesUID) . "' " .
				"ORDER BY CAST(ImageNumbe AS $inttype), CAST(AcqNumber AS $inttype)";
			$this->log->asDump('$sql = ', $sql);

			$rs2 = $authDB->query($sql);
			if (!$rs2)
			{
				$return['error'] = 'Database error (6), see logs';
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return $return;
			}

			$j = 0;
			$return[$i]['count'] = 0;
			$instance_fk = -1;
			while ($row2 = $authDB->fetchAssoc($rs2))
			{
				$this->log->asDump('result: ', $row2);

				$return[$i]["count"]++;
				$return[$i][$j]["id"] = (string) $row2["SOPInstanc"];
				$return[$i][$j]["numframes"] = (string) $row2["NumberOfFr"];
					/* in other PACSes we mark video files by overwriting .numframes with a magic value -99.
						However Conquest does not store Transfer Syntax, and doesn't accept videos altogether.
					 */

				$path = $this->shared->getStorageDevicePath($row2['DeviceName']);
				if (is_null($path))
				{
					$authDB->free($rs2);
					return array('error' => '[Structure] Configuration error (3), see logs');
				}
				$return[$i][$j]["path"] = $this->fp->toLocal($path . (string) $row2["ObjectFile"]);
				$return[$i][$j]["xfersyntax"] = "";
				$return[$i][$j]["bitsstored"] = (string) $row2["BitsStored"];
				$return[$i][$j]['sopclass'] = (string)$row2['SOPClassUI'];
				$j++;
			}

			/* avoid empty series (images might be filtered out by SOP Class etc) */
			if (!$j)
				unset($return[$i]);
			else
			{
				$return['count']++;
				$return[$i]['id'] = $seriesUID;
				$return[$i]['description'] = $cs->utf8Encode($description);
				$return[$i]['modality'] = $modality;
				$i++;
			}

			$authDB->free($rs2);
		}

		if ($return['count'] > 0)
			$return['error'] = '';
		else
			if (empty($return['error']))
				$return['error'] = "No images to display\n(some might have been skipped)";

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}
}
