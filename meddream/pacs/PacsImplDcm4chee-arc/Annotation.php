<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\PathUtils;
use Softneta\MedDream\Core\SOP\DicomCommon;
use Softneta\MedDream\Core\Pacs\AnnotationIface;
use Softneta\MedDream\Core\Pacs\AnnotationAbstract;


/** @brief Implementation of AnnotationIface for <tt>$pacs='dcm4chee-arc'</tt>. */
class PacsPartAnnotation extends AnnotationAbstract implements AnnotationIface
{
	public function isSupported($testVersion = false)
	{
		return '';	/* supported regardless of $testVersion etc */
	}


	public function isPresentForStudy($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(' . var_export($studyUid, true) . ')');

		$authDB = $this->authDB;
		if (!$authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return false;
		}
			/* won't call isSupported() because its response is constant */

		/* build the query */
		$limit_suf = $authDB->getDbms() == 'OCI8' ? '' : ' LIMIT 1';
		$sql = "SELECT pk FROM series " .
			"WHERE study_fk='" . $authDB->sqlEscapeString($studyUid) .
			"' AND (modality='PR' OR LOWER(series_desc)='" . Constants::PR_SERIES_DESC . "')$limit_suf";
		if ($authDB->getDbms() == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return false;
		}
		$data = $authDB->fetchAssoc($rs);
		$authDB->free($rs);

		$val = is_array($data);
			/* If result is empty, mysql_fetch_row() returns FALSE, but
			   mysqli_fetch_row() returns NULL. It is better to check for
			   the "right" result which is always an associative array.
			 */

		$this->log->asDump('$data = ', $data, ', returning: ', $val);
		$this->log->asDump('end ' . __METHOD__);

		return $val;
	}


	public function collectStudyInfoForImage($instanceUid, $type = 'dicom')
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ')');

		$return = array('error' => '');

		$data = array();
		$tm = time();
		$data['date'] = date('Ymd', $tm);
		$data['time'] = date('His', $tm);
		$datetime = $data['date'] . $data['time'];

		$authDB = $this->authDB;
		$dbms = $authDB->getDbms();
		$cs = $this->cs;

		$limit_pre = '';
		$limit_suf = '';
		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV'))
			$limit_pre = ' TOP(1)';
		else
			if ($dbms != "OCI8")
				$limit_suf = ' LIMIT 1';

		if (($dbms == 'MSSQL') || ($dbms == 'SQLSRV') || ($dbms == 'POSTGRESQL'))
			$inttype = 'INT';
		else
			$inttype = 'UNSIGNED';

		/*
		  get information about series and image
		 */
		$sql = "SELECT$limit_pre series.pk,series.series_iuid as seriesuid,series." .
				'study_fk, file_ref.filepath, filesystem.fs_uri,series.modality,' .
				' instance.sop_cuid as sopclass, instance.sop_iuid as imageuid' .
			' FROM series' .
			' LEFT JOIN instance ON instance.series_fk=series.pk' .
			' LEFT JOIN file_ref ON file_ref.instance_fk=instance.pk' .
			' LEFT JOIN filesystem ON file_ref.filesystem_fk=filesystem.pk' .
			' WHERE instance.replaced=0 AND file_ref.pk=' . $authDB->sqlEscapeString($instanceUid) . $limit_suf;
		if ($dbms == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (1), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		$this->log->asDump('$row = ', $row);
		if (!$row)
			return array('error' => "no such image '$instanceUid'");
		$data['seriesuid'] = $row['seriesuid'];
		$data['sopud'] = $row['sopclass'];
		$data['instanceuid'] = $row['imageuid'];
		$data['currentmodality'] = $this->shared->cleanDbString((string) $row['modality']);

		$path = PathUtils::stripUriPrefix((string) $row["fs_uri"]);
		$path .= DIRECTORY_SEPARATOR . $row["filepath"];
		$path = $this->fp->toLocal(str_replace("\\", '/', urldecode($path)));		/* urldecode: embedded spaces etc */

		clearstatcache(false, $path);
		if (!file_exists($path))
		{
			$return['error'] = 'DICOM file was not found';
			$this->log->asErr($return['error'] . ": '" . $path . "'");
			return $return;
		}

		$arrdata = meddream_extract_meta(dirname(dirname(__DIR__)), $path, 0);
		$this->log->asDump('meddream_extract_meta $arrdata: ', $arrdata);

		$seriespk = $row['pk'];
		$studypk = $row['study_fk'];
		$data['numcolumns'] = $arrdata['columns'];
		$data['numrows'] = $arrdata['rows'];

		/*
		  get information about study and patient
		 */
		$sql = "SELECT$limit_pre patient.pat_id as origid,patient.pat_name,patient.pat_i_name,patient.pat_p_name," .
				'patient.pat_birthdate as birthdate, patient.pat_sex as sex,' .
				'study.study_date as studydate,study.study_time as studytime, study.study_iuid as studyuid,' .
				'study.study_id as id, study.accession_no as accessionnum,' .
				' study.ref_physician as referringphysician,study.ref_phys_i_name,study.ref_phys_p_name' .
			' FROM study' .
			' LEFT JOIN patient ON patient.pk=study.patient_fk' .
			' WHERE study.pk=' . $authDB->sqlEscapeString($studypk) . $limit_suf;
		if ($dbms == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (2), see logs');
		}

		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			$data['studyuuid'] = (string) $row['studyuid'];
			$data['patientid'] = $cs->utf8Encode($this->shared->cleanDbString($row['origid']));
			$data['studyid'] = $this->shared->cleanDbString($row['id']);
			$data['accessionnum'] = $this->shared->cleanDbString($row['accessionnum']);
			$data['birthdate'] = $this->shared->cleanDbString($row['birthdate']);
			$data['sex'] = $this->shared->cleanDbString($row['sex']);
			$data['studydate'] = $this->shared->cleanDbString($row['studydate']);
			$data['studytime'] = $this->shared->cleanDbString($row['studytime']);

			//requires to replace data
			foreach ($row as $key => $value)
			{
				//remove * from the beginning
				if (substr($row[$key], 0, 2) == '*^')
					$row[$key] = substr($row[$key], 1);
				//remove * from the end
				if (substr($row[$key], -2) == '^*')
					$row[$key] = substr($row[$key], 0, strlen($row[$key]) - 1);

				//replace * in the middle of ^^
				$row[$key] = str_replace('^*^', '^^', $row[$key]);
				$row[$key] = str_replace('^*^', '^^', $row[$key]);

				//replace * with empty string
				if ($row[$key] == '*')
					$row[$key] = '';
			}

			$data['patientname'] = $cs->utf8Encode($row['pat_name']);
			$row['pat_i_name'] = $cs->utf8Encode($row['pat_i_name']);
			$row['pat_p_name'] = $cs->utf8Encode($row['pat_p_name']);

			if (($row['pat_i_name'] != '') || ($row['pat_p_name'] != ''))
				$data['patientname'] .= '=' . $row['pat_i_name'] . '=' . $row['pat_p_name'];

			$data['referringphysician'] = $cs->utf8Encode($row['referringphysician']);
			$row['ref_phys_i_name'] = $cs->utf8Encode($row['ref_phys_i_name']);
			$row['ref_phys_p_name'] = $cs->utf8Encode($row['ref_phys_p_name']);

			if (($row['ref_phys_i_name'] != '') || ($row['ref_phys_p_name'] != ''))
				$data['referringphysician'] .= '=' . $row['ref_phys_i_name'] . '=' . $row['ref_phys_p_name'];
		}

		/*
		  get information about series uid->first PR series
		 */
		if ($type == 'dicom')
			$filter = " AND modality='PR'";
		else
			$filter = " AND LOWER(series_desc)='" . Constants::PR_SERIES_DESC . "'";
		$sql = "SELECT$limit_pre series_iuid as seriesuid, series_no as seriesnumber" .
			' FROM series' .
			' WHERE study_fk=' . $authDB->sqlEscapeString($studypk) . $filter . $limit_suf;
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (3), see logs');
		}

		if ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('result: ', $row);

			if (isset($row['seriesuid']))
			{
				$data['seriesuuid'] = (string) $row['seriesuid'];
				$data['seriesnumber'] = (string) $row['seriesnumber'];
			}
		}

		/*
		  if have any PR series - get image last instance in the series
		 */
		if (!empty($data['seriesuuid']))
		{
			if ($dbms == "OCI8")
				$orderInstNo = "LPAD(instance.inst_no, 255)";
					/* can't use CAST(..., INT) or TO_NUMBER(): absent values are marked by '*'
					  which yield ORA-01722
					 */
			else
				$orderInstNo = "CAST(instance.inst_no AS $inttype)"; /* MySQL, MSSQL, etc */
			$sql = "SELECT$limit_pre instance.inst_no as instancenr" .
				' FROM instance, file_ref, filesystem' .
				' WHERE file_ref.instance_fk=instance.pk AND' .
					' file_ref.filesystem_fk=filesystem.pk AND instance.series_fk=' .
					$authDB->sqlEscapeString($seriespk) . ' AND instance.replaced=0' .
				" ORDER BY $orderInstNo DESC, instance.content_date, instance.content_time, " .
					"file_ref.pk DESC$limit_suf";
			if ($dbms == 'OCI8')
				$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
			$this->log->asDump('$sql = ', $sql);

			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return array('error' => '[Annotation] Database error (4), see logs');
			}

			if ($row = $authDB->fetchAssoc($rs))
			{
				$this->log->asDump('result: ', $row);

				$data['instancenumber'] = $row['instancenr'] + 1;
			}
		}
		else
		{
			/*
			  if do not have any PR series - set image instance and get last series number
			 */
			$data['instancenumber'] = 1;

			$sql = "SELECT$limit_pre series_no as seriesnumber FROM series" .
				' WHERE study_fk=' . $authDB->sqlEscapeString($studypk) .
				" ORDER BY seriesnumber DESC$limit_suf";
			if ($dbms == 'OCI8')
				$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
			$this->log->asDump('$sql = ', $sql);

			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$this->log->asErr("query failed: '" . $authDB->getError() . "'");
				return array('error' => '[Annotation] Database error (5), see logs');
			}

			if ($row = $authDB->fetchAssoc($rs))
			{
				$this->log->asDump('result: ', $row);

				$data['seriesnumber'] = $row['seriesnumber'] + 1;
			}

			//generate new series uid
			$data['seriesuuid'] = DicomCommon::generateUid(Constants::ROOT_UID,
					Constants::PRODUCT_ID, substr($this->productVersion, 0, 10), 2, $datetime,
					$data['seriesnumber']);
		}

		//generate new image uid
		$data['sopinstance'] = DicomCommon::generateUid(Constants::ROOT_UID,
				Constants::PRODUCT_ID, substr($this->productVersion, 0, 10), 3, $datetime,
				$data['instancenumber']);

		foreach ($data as $key => $value)
			if ($data[$key] == '*')
				$data[$key] = '';

		$return['data'] = $data;
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	public function collectPrSeriesImages($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');

		$authDB = $this->authDB;

		$sql = 'SELECT series.pk,file_ref.filepath,filesystem.fs_uri,file_ref.pk as imageuid ' .
			'FROM series ' .
			'LEFT JOIN instance ON instance.series_fk=series.pk ' .
			'LEFT JOIN file_ref ON file_ref.instance_fk=instance.pk ' .
			'LEFT JOIN filesystem ON file_ref.filesystem_fk=filesystem.pk ' .
			"WHERE instance.replaced=0 AND series.study_fk='" . $authDB->sqlEscapeString($studyUid) .
			"' AND (series.modality='PR' OR LOWER(series.series_desc)='" . Constants::PR_SERIES_DESC . "') " .
			'ORDER BY series_no';
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (6), see logs');
		}

		$data = array();
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('$row = ', $row);

			$path = PathUtils::stripUriPrefix((string) $row["fs_uri"]);
			$path .= DIRECTORY_SEPARATOR . $row["filepath"];
			$path = $this->fp->toLocal(str_replace("\\", '/', urldecode($path)));		/* urldecode: embedded spaces etc */

			$instanceuid = $row['imageuid'];
			$seriesuid = $row['pk'];

			clearstatcache(false, $path);
			if (!@file_exists($path))
			{
				$this->log->asWarn(__METHOD__. ": missing or inaccessible file '$path'");
				continue;
			}

			if (!isset($data[$seriesuid]))
				$data[$seriesuid] = array();

			$data[$seriesuid][$instanceuid] = $path;
		}
		$return = array('error' => '', 'seriesimagelist' => $data);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}
}
