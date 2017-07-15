<?php
/*
	Original name: medreport.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		An amfPHP wrapper for ../MedReport.php
 */

namespace Softneta\MedDream\Swf;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\MedReport as CoreMedReport;


class Medreport extends CoreMedReport
{
	function __construct()
	{
		parent::__construct();
		
		$this->methodTable = array
		(
			"getStudyNotes" => array(
				"description" => "Get Study Notes",
				"access" => "remote"),
			"saveStudyNote" => array(
				"description" => "Save Study Note",
				"access" => "remote"),
			"getTemplateList" => array(
				"description" => "Get Template List",
				"access" => "remote"),
			"newTemplate" => array(
				"description" => "New Template",
				"access" => "remote"),
			"getTemplate" => array(
				"description" => "Get Template",
				"access" => "remote"),
			"editTemplate" => array(
				"description" => "Edit Template",
				"access" => "remote"),
			"deleteTemplate" => array(
				"description" => "Delete Template",
				"access" => "remote"),
			"getReportFromRis" => array(
				"description" => "",
				"access" => "remote"),
			"deleteStudyNotes" => array(
				"description" => "",
				"access" => "remote"),
			"getAttachement" => array(
				"description" => "get specific note attachement list",
				"access" => "remote"),
			"deleteAttachement" => array(
				"description" => "delete one attachement",
				"access" => "remote"),
			"getReportFromHis" => array(
				"description" => "get report from HIS system",
				"access" => "remote")
		);
	}

	public function saveStudyNote($studyUID, $note, $date = '', $user = '')
	{
		return parent::saveStudyNote($studyUID, $note, $date, $user);
	}

	public function getStudyNotes($studyUID, $withattachements = false)
	{
		return parent::getStudyNotes($studyUID, $withattachements);
	}

	public function getTemplateList()
	{
		return parent::getTemplateList();
	}

	public function newTemplate($group, $name, $text)
	{
		return parent::newTemplate($group, $name, $text);
	}

	public function editTemplate($id, $group, $name, $text)
	{
		return parent::editTemplate($id, $group, $name, $text);
	}

	public function getTemplate($id)
	{
		return parent::getTemplate($id);
	}

	public function deleteTemplate($id)
	{
		return parent::deleteTemplate($id);
	}

	public function getReportFromRis($uid)
	{
		return parent::getReportFromRis($uid);
	}

	/* currently not used by the GUI??? */
	public function deleteStudyNotes($studyUID, $name)
	{
		return parent::deleteStudyNotes($studyUID, $name);
	}


	/* called from:
		1) directly from getStudyNotes() for all reports in a loop,
		2) directly from deleteAttachement() for the current report,
		3) however also from Flash for the current report after uploading a new attachment.
	 */
	public function getAttachement($studyUID, $return)
	{
		return parent::getAttachement($studyUID, $return);
	}

	public function deleteAttachement($studyUID, $noteID, $seq)
	{
		return parent::deleteAttachement($studyUID, $noteID, $seq);
	}

	public function getReportFromHis($uid)
	{
		return parent::getReportFromHis($uid);
	}
}
