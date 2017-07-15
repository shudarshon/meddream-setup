<?php

namespace Softneta\MedDream\Core\Pacs\Pacsone;

use Softneta\MedDream\Core\Constants;
use Softneta\MedDream\Core\SOP\DicomCommon;
use Softneta\MedDream\Core\Pacs\AnnotationIface;
use Softneta\MedDream\Core\Pacs\AnnotationAbstract;


/** @brief Implementation of AnnotationIface for <tt>$pacs='PacsOne'</tt>. */
class PacsPartAnnotation extends AnnotationAbstract implements AnnotationIface
{
	public function isSupported($testVersion = false)
	{
		$ourVersion = $this->commonData['pacsoneVersion'];

		if ($ourVersion < '6.4.4')
			return 'DICOM PR not supported on PacsOne version ' .
					var_export($ourVersion, true) . ' (requires > 6.4.3)';
		else
			return '';
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

		/* build the query */
		$sql = 'SELECT 1 FROM series ' .
			"WHERE studyuid='" . $authDB->sqlEscapeString($studyUid) .
				"' AND (modality='PR' OR LOWER(description)='" . Constants::PR_SERIES_DESC .
			"') LIMIT 1";
		$this->log->asDump('$sql = ', $sql);

		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$this->log->asErr("query failed: '" . $authDB->getError() . "'");
			return array('error' => '[Annotation] Database error (1), see logs');
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

		$inttype = 'UNSIGNED';

		/* get information about series and image */
		$sql = 'SELECT series.' . $this->commonData['F_SERIES_UUID'] .
			', sopclass, numrows, modality, numcolumns, path, studyuid' .
			' FROM series' .
			' LEFT JOIN image ON image.seriesuid=series.' . $this->commonData['F_SERIES_UUID'] .
			' WHERE image.' . $this->commonData['F_IMAGE_UUID'] . "='" .
				$authDB->sqlEscapeString($instanceUid) . "'" .
			' LIMIT 1';
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

		$data['seriesuid'] = $row[$this->commonData['F_SERIES_UUID']];
		$data['sopud'] = $row['sopclass'];
		$data['instanceuid'] = $instanceUid;
		$data['currentmodality'] = (string) $row['modality'];
		$data['studyuuid'] = (string) $row['studyuid'];
		$data['numcolumns'] = $row['numcolumns'];
		$data['numrows'] = $row['numrows'];

		/* get information about study and patient */
		$ideographicAndPhonetic = '';
		if ($this->commonData['F_PATIENT_IDEOGRAPHIC'] != '')
			$ideographicAndPhonetic .= $this->commonData['F_PATIENT_IDEOGRAPHIC'] . ',';
		if ($this->commonData['F_PATIENT_PHONETIC'] != '')
			$ideographicAndPhonetic .= $this->commonData['F_PATIENT_PHONETIC'] . ',';

		$tbl = $this->commonData['F_TBL_NAME_STUDY'];
		$sql = "SELECT $tbl." . $this->commonData['F_STUDY_DATE'] .
			", $tbl." . $this->commonData['F_STUDY_TIME'] . ', id,' .
			' accessionnum, referringphysician, origid, lastname, firstname, middlename,' .
			" prefix, suffix,$ideographicAndPhonetic birthdate, sex " .
			" FROM $tbl" .
			" LEFT JOIN patient ON patient.origid=$tbl.patientid" .
			" WHERE $tbl." . $this->commonData['F_STUDY_UUID'] . "='" .
				$authDB->sqlEscapeString($data['studyuuid']) . "'" .
			' LIMIT 1';
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

			$data['patientid'] = $cs->utf8Encode($row['origid']);
			$data['studyid'] = $cs->utf8Encode((string) $row['id']);
			$data['accessionnum'] = $cs->utf8Encode($row['accessionnum']);
			$data['birthdate'] = (string) $row['birthdate'];
			$data['sex'] = (string) $row['sex'];
			$data['studydate'] = $row[$this->commonData['F_STUDY_DATE']];
			$data['studytime'] = $row[$this->commonData['F_STUDY_TIME']];

			$data['patientname'] = $cs->utf8Encode(rtrim($row['lastname'] . '^' .
				$row['firstname'] . '^' . $row['middlename'] . '^' .
				$row['prefix'] . '^' . $row['suffix'], '^'));
			if (!empty($row['ideographic']) && !empty($row['phonetic']))
				$data['patientname'] .= $cs->utf8Encode('=' . $row['ideographic'] . '=' . $row['phonetic']);

			$data['referringphysician'] = $cs->utf8Encode((string) $row['referringphysician']);
		}
		$authDB->free($rs);

		/* get information about series uid->first PR series */
		if ($type == 'dicom')
			$filter = " AND modality='PR'";
		else
			$filter = " AND LOWER(description)='" . Constants::PR_SERIES_DESC . "'";
		$sql = 'SELECT ' . $this->commonData['F_SERIES_UUID'] . ', ' .
			$this->commonData['F_SERIES_SERIESNUMBER'] .
			' FROM series' .
			" WHERE studyuid='" . $authDB->sqlEscapeString($data['studyuuid']) .
				"'$filter" .
			' LIMIT 1';
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

			if (isset($row[$this->commonData['F_SERIES_UUID']]))
			{
				$data['seriesuuid'] = (string) $row[$this->commonData['F_SERIES_UUID']];
				$data['seriesnumber'] = (string) $row[$this->commonData['F_SERIES_SERIESNUMBER']];
			}
		}
		$authDB->free($rs);

		/* if have any PR series - get image last instance in the series */
		if (!empty($data['seriesuuid']))
		{
			$sql = 'SELECT instance' .
				' FROM image' .
				" WHERE seriesuid='" . $authDB->sqlEscapeString($data['seriesuuid']) .
				"' ORDER BY instance DESC" .
				' LIMIT 1';
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

				$data['instancenumber'] = $row['instance'] + 1;
			}
		}
		else
		{
			/* if do not have any PR series - set image instance and get last series number */
			$data['instancenumber'] = 1;

			$sql = 'SELECT ' . $this->commonData['F_SERIES_SERIESNUMBER'] .
				' FROM series' .
				" WHERE studyuid='" . $authDB->sqlEscapeString($data['studyuuid']) . "' " .
				' ORDER BY ' . $this->commonData['F_SERIES_SERIESNUMBER'] . ' DESC' .
				' LIMIT 1';
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

				$data['seriesnumber'] = $row[$this->commonData['F_SERIES_SERIESNUMBER']] + 1;
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

		$sql = 'SELECT series.' . $this->commonData['F_SERIES_UUID'] . ', image.path,' .
				' image.' . $this->commonData['F_IMAGE_UUID'] . " AS imageuid " .
			'FROM series' .
			' LEFT JOIN image ON image.seriesuid=series.' . $this->commonData['F_SERIES_UUID'] .
			" WHERE series.studyuid='" . $authDB->sqlEscapeString($studyUid) .
				"' AND (modality='PR' OR LOWER(series.description)='" .
					Constants::PR_SERIES_DESC . "')";
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

			$path = $this->fp->toLocal($row['path']);
			$instanceuid = $row['imageuid'];
			$seriesuid = $row[$this->commonData['F_SERIES_UUID']];

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
