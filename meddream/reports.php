<?php
use Softneta\MedDream\Core\Backend;
use Softneta\MedDream\Core\HttpUtils;
use Softneta\MedDream\Core\ReportManager;

define('PATH_TO_ROOT', __DIR__ . DIRECTORY_SEPARATOR);

require_once(PATH_TO_ROOT . 'autoload.php');

session_start();

$backend = new Backend(array('Auth', 'Report', 'Structure'));
if (!$backend->authDB->isAuthenticated())
    HttpUtils::error('Not Authenticated', 401);

$cmd = HttpUtils::getParam('cmd');

$reports = new ReportManager($backend->pacsReport, $backend->pacsStructure, $backend->attachmentUploadDir,
    $backend->authDB->getAuthUser());

function requireUploadPermission() {
    global $backend;
    if (!$backend->pacsAuth->hasPrivilege('upload'))
        HttpUtils::error('No permissions to upload', 403);
}

switch ($cmd) {
    case 'getTemplates':
        HttpUtils::returnJSON($reports->getTemplates());
        break;
    case 'getTemplate':
        header('Content-Type: text/plain');
        echo $reports->getTemplate(HttpUtils::getParam('id'));
        break;
    case 'saveTemplate':
        requireUploadPermission();
        HttpUtils::returnJSON(array(
            'id' => $reports->saveTemplate(
                HttpUtils::getParam('id', -1),
                HttpUtils::getParam('group'),
                HttpUtils::getParam('name'),
                HttpUtils::getParam('body')
            )
        ));
        break;
    case 'removeTemplate':
        requireUploadPermission();
        $reports->removeTemplate(HttpUtils::getParam('id'));
        echo '{}';
        break;
    case 'getStudyMeta':
        HttpUtils::returnJSON($reports->getStudyMetadata(HttpUtils::getParam('uid')));
        break;
    case 'getStudyReport':
        $result = $reports->getStudyReport(HttpUtils::getParam('uid'));
        if ($result === false)
            HttpUtils::error('Report Not Found', 404);
        HttpUtils::returnJSON($result);
        break;
    case 'getStudyReportContent':
        $result = $reports->getStudyReportHTML(HttpUtils::getParam('uid'));
        if ($result === false)
            HttpUtils::error('Report Not Found', 404);
        echo $result;
        break;
    case 'saveStudyReport':
        requireUploadPermission();
        HttpUtils::returnJSON($reports->saveStudyReport(HttpUtils::getParam('uid'), HttpUtils::getParam('content')));
        break;
    case 'getAttachments':
        HttpUtils::returnJSON($reports->getAttachments(HttpUtils::getParam('uid'), HttpUtils::getParam('id')));
        break;
    case 'downloadAttachment':
        $reports->downloadAttachment(HttpUtils::getParam('uid'), HttpUtils::getParam('id'));
        break;
    case 'removeAttachment':
        requireUploadPermission();
        $reports->removeAttachment(HttpUtils::getParam('uid'), HttpUtils::getParam('rid'), HttpUtils::getParam('id'));
        echo '{}';
        break;
    case 'addAttachment':
        requireUploadPermission();
        if (empty($_FILES) || !isset($_FILES['attachment']))
            HttpUtils::error('File Not Specified');

        HttpUtils::returnJSON($reports->addAttachment(
            HttpUtils::getParam('uid'), HttpUtils::getParam('id'), $_FILES['attachment']));
        break;
    default:
        HttpUtils::error('invalid cmd');
}
