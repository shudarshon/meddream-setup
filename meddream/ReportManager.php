<?php
namespace Softneta\MedDream\Core;

use Softneta\MedDream\Core\Pacs\ReportIface;
use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Swf\ManagePrint;

class ReportManager
{
    private static $REQUIRED_TAGS = array(
        'physician' => array(0x0008, 0x0090),
        'birthday' => array(0x0010, 0x0030),
        'history' => array(0x0010, 0x21b0)
    );

    /**
     * @var StructureIface
     */
    private $pacsStructure;

    /**
     * @var ReportIface
     */
    private $pacsReport;

    private $attachmentUploadDir;

    private $authUserName;

    public function __construct($pacsReport, $pacsStructure, $attachmentUploadDir, $authUserName)
    {
        $this->pacsReport = $pacsReport;
        $this->pacsStructure = $pacsStructure;
        $this->attachmentUploadDir = $attachmentUploadDir;
        $this->authUserName = $authUserName;
    }

    public function getTemplates()
    {
        $templates = $this->pacsReport->collectTemplates();
        $results = array();
        for ($i = 0; $i < $templates['count']; $i++) {
            $group = $templates[$i]['group'];
            if (!isset($results[$group]))
                $results[$group] = array();
            $results[$group][] = array(
                'id' => $templates[$i]['id'],
                'name' => $templates[$i]['name'],
                'group' => $group
            );
        }
        return $results;
    }

    public function getTemplate($id)
    {
        $template = $this->pacsReport->getTemplate($id);
        return $template['text'];
    }

    public function saveTemplate($id, $group, $name, $body)
    {
        if (is_numeric($id) && $id >= 0) {
            $this->pacsReport->updateTemplate($id, $group, $name, $body);
            return -1;
        } else {
            $result = $this->pacsReport->createTemplate($group, $name, $body);
            return $result['id'];
        }
    }

    public function removeTemplate($id)
    {
        $this->pacsReport->deleteTemplate($id);
    }

    public function getStudyReport($uid)
    {
        $result = $this->pacsReport->getLastReport($uid);
        self::checkResultForErrors($result);
        if ($result['id'] === null)
            return false;

        return array(
            'id' => $result['id'],
            'content' => $result['notes'],
            'updated' => $result['created']
        );
    }

    public function getStudyReportHTML($uid)
    {
        require_once __DIR__ . '/swf/ManagePrint.php';
        $managePrint = new ManagePrint();
        $report = $managePrint->makePrintData($uid);

        if (!isset($report['template']))
            return '';

        return str_replace('/?' . $_SERVER['QUERY_STRING'], '', $report['template']);
    }

    public function saveStudyReport($uid, $content)
    {
        $result = $this->pacsReport->createReport($uid, $content);
        return array('updated' => $result['created']);
    }

    public function getAttachments($studyUID, $reportID)
    {
        $result = $this->pacsReport->collectAttachments($studyUID, array('id' => $reportID));
        self::checkResultForErrors($result);

        $results = array();
        if (isset($result['attachment'])) {
            $attachments = $result['attachment'];
            for ($i = 0; $i < $attachments['count']; $i++)
                $results[] = array(
                    'id' => $attachments[$i]['seq'],
                    'filename' => basename($attachments[$i]['filename'])
                );
        }
        return $results;
    }

    public function downloadAttachment($studyUID, $attachmentID)
    {
        $attachment = $this->pacsReport->getAttachment($studyUID, $attachmentID);
        self::checkResultForErrors($attachment);
        $filename = basename($attachment['path']);

        header("Content-disposition: attachment; filename=\"{$filename}\"");
        header("Content-Type: {$attachment['mimetype']}");
        if (file_exists($attachment['path'])) {
            readfile($attachment['path']);
        } else if (!empty($attachment['data'])) {
            echo $attachment['data'];
        }
    }

    public function addAttachment($studyUID, $reportID, $attachment)
    {
        $path = $attachment['tmp_name'];
        $filename = $attachment['name'];
        $mimeType = mime_content_type($path);

        if (!empty($this->attachmentUploadDir)) {
            $target = $this->attachmentUploadDir . DIRECTORY_SEPARATOR . $studyUID;
            if (!is_dir($target) && !mkdir($target))
                HttpUtils::error("Unable to create a directory for storing uploads");
            $uploadedName = date('Ymd-His') . "-{$this->authUserName}-$filename";
            $target .= DIRECTORY_SEPARATOR . $uploadedName;

            if (!move_uploaded_file($path, $target))
                HttpUtils::error("Failed to upload the attachment");

            $result = $this->pacsReport->createAttachment($studyUID, $reportID, $mimeType, $target, $attachment['size']);
        } else {
            $result = $this->pacsReport->createAttachment($studyUID, $reportID, $mimeType, $filename, $attachment['size'], file_get_contents($path));
            $uploadedName = $filename;
        }
        self::checkResultForErrors($result);

        return array(
            'id' => $result['seq'],
            'filename' => $uploadedName
        );
    }

    public function removeAttachment($studyUID, $reportID, $attachmentID)
    {
        $this->pacsReport->deleteAttachment($studyUID, $reportID, $attachmentID);
    }

    public function getStudyMetadata($uid)
    {
        $meta = $this->pacsStructure->studyGetMetadata($uid);
        self::checkResultForErrors($meta);

        $report = array(
            'studyId' => $meta['uid'],
            'patientId' => $meta['patientid'],
            'patientName' => trim($meta['firstname'] . ' ' . $meta['lastname']),
            'studyTime' => $meta['studydate'] . ' ' . $meta['studytime']
        );

        $requiredTags = self::$REQUIRED_TAGS;
        for ($i = 0; $i < $meta['count']; $i++) {
            $series = $meta[$i];
            for ($j = 0; $j < $series['count']; $j++) {
                $results = meddream_get_tags(__DIR__, $series[$j]['path']);
                if ($results['error'] != 0 || empty($requiredTags)) continue;
                foreach ($results['tags'] as $tag)
                    foreach ($requiredTags as $key => $id) {
                        if ($tag['group'] == $id[0] && $tag['element'] == $id[1]) {
                            $report[$key] = $tag['data'];
                            unset($requiredTags[$key]);
                        }
                    }
            }
        }

        return $report;
    }

    private static function checkResultForErrors($result)
    {
        if (isset($result['error']) && !empty($result['error']))
            HttpUtils::error($result['error']);
    }
}
