<?php

namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc_5;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\SOP\DicomCommon;
use Softneta\MedDream\Core\Pacs\AnnotationIface;
use Softneta\MedDream\Core\Pacs\AnnotationAbstract;


/** @brief Implementation of AnnotationIface for <tt>$pacs='dcm4chee-arc-5'</tt>. */
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
			$this->log->asErr("[Annotation] Database error (1): '" . $authDB->getError() . "'");
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
			/* isAuthenticated() was verified in caller (PresentationStateHandler.php) */
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
		$sql = "SELECT$limit_pre series.pk, series_iuid, modality, study_fk," .
				' sop_iuid, sop_cuid, storage_id, storage_path' .
			' FROM series' .
			' LEFT JOIN instance ON instance.series_fk=series.pk' .
			' LEFT JOIN location ON location.instance_fk=instance.pk' .
			' WHERE location.pk=' . $authDB->sqlEscapeString($instanceUid) . $limit_suf;
		if ($dbms == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (2), see logs');
		}

		$row = $authDB->fetchAssoc($rs);
		$authDB->free($rs);
		$this->log->asDump('$row = ', $row);
		if (!$row)
			return array('error' => "no such image '$instanceUid'");
		$data['seriesuid'] = $row['series_iuid'];
		$data['sopud'] = $row['sop_cuid'];
		$data['instanceuid'] = $row['sop_iuid'];
		$data['currentmodality'] = $this->shared->cleanDbString((string) $row['modality']);

		$path = $this->shared->getStorageDevicePath($row['storage_id']);
		if (is_null($path))
		{
			$return['error'] = '[Annotation] Configuration error (1), see logs';
			return $return;
		}
		$path .= DIRECTORY_SEPARATOR . $row['storage_path'];
		$path = $this->fp->toLocal(urldecode($path));		/* urldecode: embedded spaces etc */
		$path = str_replace("\\", '/', $path);

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
		$sql = "SELECT$limit_pre study_date, study_time, study_iuid, study_id, accession_no, pat_birthdate," .
			' pat_sex, pat_id, pat_fn, pat_gn, i_pat_fn, i_pat_gn, p_pat_fn, p_pat_gn, ref_phys_fn,' .
			' ref_phys_gn, i_ref_phys_fn, i_ref_phys_gn, p_ref_phys_fn, p_ref_phys_gn' .
			' FROM study' .
			' LEFT JOIN patient ON patient.pk=study.patient_fk' .
			' LEFT JOIN patient_id ON patient_id.pk=patient.patient_id_fk' .
			' LEFT JOIN (' .
				'SELECT pk AS pat_pk, family_name AS pat_fn, given_name AS pat_gn, i_family_name AS i_pat_fn,' .
					' i_given_name AS i_pat_gn, p_family_name AS p_pat_fn, p_given_name AS p_pat_gn' .
				' FROM person_name' .
			') tmp2 ON tmp2.pat_pk=patient.pat_name_fk' .
			' LEFT JOIN (' .
				'SELECT pk AS ref_phys_pk, family_name AS ref_phys_fn, given_name AS ref_phys_gn,' .
					' i_family_name AS i_ref_phys_fn, i_given_name AS i_ref_phys_gn, p_family_name' .
					' AS p_ref_phys_fn, p_given_name AS p_ref_phys_gn' .
				' FROM person_name' .
			') tmp3 ON tmp3.ref_phys_pk=study.ref_phys_name_fk' .
			' WHERE study.pk=' . $authDB->sqlEscapeString($studypk) . $limit_suf;
		if ($dbms == 'OCI8')
			$sql = "SELECT * FROM ($sql) WHERE ROWNUM <= 1";
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

			$data['studyuuid'] = (string) $row['study_iuid'];
			$data['patientid'] = $cs->utf8Encode($this->shared->cleanDbString($row['pat_id']));
			$data['studyid'] = $cs->utf8Encode($this->shared->cleanDbString($row['study_id']));
			$data['accessionnum'] = $cs->utf8Encode($this->shared->cleanDbString($row['accession_no']));
			$data['birthdate'] = $this->shared->cleanDbString($row['pat_birthdate']);
			$data['sex'] = $this->shared->cleanDbString($row['pat_sex']);
			$data['studydate'] = $this->shared->cleanDbString($row['study_date']);
			$data['studytime'] = $this->shared->cleanDbString($row['study_time']);

			$name = rtrim($row['pat_fn'] . '^' . $row['pat_gn'], '^');
			$data['patientname'] = $cs->utf8Encode($name);
			$name = rtrim($row['i_pat_fn'] . '^' . $row['i_pat_gn'], '^');
			$row['pat_i_name'] = $cs->utf8Encode($name);
			$name = rtrim($row['p_pat_fn'] . '^' . $row['p_pat_gn'], '^');
			$row['pat_p_name'] = $cs->utf8Encode($name);
			if (($row['pat_i_name'] != '') || ($row['pat_p_name'] != ''))
				$data['patientname'] .= '=' . $row['pat_i_name'] . '=' . $row['pat_p_name'];

			$name = rtrim($row['ref_phys_fn'] . '^' . $row['ref_phys_gn'], '^');
			$data['referringphysician'] = $cs->utf8Encode($name);
			$name = rtrim($row['i_ref_phys_fn'] . '^' . $row['i_ref_phys_gn'], '^');
			$row['ref_phys_i_name'] = $cs->utf8Encode($name);
			$name = rtrim($row['p_ref_phys_fn'] . '^' . $row['p_ref_phys_gn'], '^');
			$row['ref_phys_p_name'] = $cs->utf8Encode($name);
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
		$sql = "SELECT$limit_pre series_iuid AS seriesuid, series_no AS seriesnumber" .
			' FROM series' .
			' WHERE study_fk=' . $authDB->sqlEscapeString($studypk) . $filter . $limit_suf;
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
			$sql = "SELECT$limit_pre inst_no as instancenr" .
				' FROM instance' .
				' LEFT JOIN location ON location.instance_fk=instance.pk' .
				" WHERE series_fk=$seriespk" .
				" ORDER BY $orderInstNo DESC, content_date DESC, content_time DESC, location.pk DESC$limit_suf";
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
				return array('error' => '[Annotation] Database error (6), see logs');
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
			/* isAuthenticated() was verified in caller (PresentationStateHandler.php) */

		$sql = 'SELECT series.pk, storage_id, storage_path, location.pk AS imageuid' .
			' FROM series' .
			' LEFT JOIN instance ON instance.series_fk=series.pk' .
			' LEFT JOIN location ON location.instance_fk=instance.pk' .
			" WHERE study_fk='" . $authDB->sqlEscapeString($studyUid) .
				"' AND (modality='PR' OR LOWER(series_desc)='" . Constants::PR_SERIES_DESC . "')";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (7), see logs');
		}

		$data = array();
		while ($row = $authDB->fetchAssoc($rs))
		{
			$this->log->asDump('$row = ', $row);

			$path = $this->shared->getStorageDevicePath($row['storage_id']);
			if (is_null($path))
			{
				$authDB->free($rs);
				$return['error'] = '[Annotation] Configuration error (2), see logs';
				return $return;
			}
			$path .= DIRECTORY_SEPARATOR . $row['storage_path'];
			$path = $this->fp->toLocal(urldecode($path));		/* urldecode: embedded spaces etc */
			$path = str_replace("\\", '/', $path);

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
