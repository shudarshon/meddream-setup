<?php
define('MAX_EXECUTION_TIME', 18000); // Maximum execution time in seconds, should be less than 1 day

use Softneta\MedDream\Core\Configuration;
use Softneta\MedDream\Core\Logging;

define('PATH_TO_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once(PATH_TO_ROOT . 'autoload.php');


class DeleteTemp
{
    private $tempDir;
    private $timeLimit;
    private $numFound;
    private $numFailed;
    private $lastProgressTime;
    private $moduleName;

    /**
     * @var Logging
     */
    private $log;

    public function __construct($tempDir, $log, $moduleName)
    {
        $this->log = $log;
        $this->moduleName = $moduleName;
        $this->tempDir = $tempDir;
        $this->timeLimit = time() + MAX_EXECUTION_TIME;
        $this->numFound = $this->numFailed = 0;
        $this->lastProgressTime = time();
    }

    public function __destruct()
    {
        $this->showProgress(true);
        $this->log->asInfo($this->moduleName . ': finished');
    }

    public function runDelete($pattern, $removeAfter = '1 day', $flags = null)
    {
        $olderThan = strtotime('-' . $removeAfter);
        foreach (glob($this->tempDir . $pattern, $flags) as $tmp)
            $this->delete($tmp, $olderThan);
    }

    private function showProgress($immediately = false)
    {
        $show = true;
        if (!$immediately) {
            $tm = time();
            if ($tm < ($this->lastProgressTime + 60))
                $show = false;
            else
                $this->lastProgressTime = $tm;
        }
        if ($show)
            $this->log->asInfo(basename(__FILE__) . ': ' . $this->numFound . ' found, ' . $this->numFailed . ' not removed');
    }

    private function delete($file, $olderThan)
    {
        if ($this->timeLimit < time()) {
            $this->log->asWarn($this->moduleName . ': execution time limit exceeded');
            exit();
        }

        if (is_dir($file))
            return $this->deleteDirectory($file, $olderThan);
        elseif (filemtime($file) > $olderThan)
            return false;
        else {
            $result = true;
            $this->numFound++;
            if (!unlink($file)) {
                $this->numFailed++;
                $result = false;
            }
            $this->showProgress();
            return $result;
        }
    }

    private function deleteDirectory($dir, $olderThan)
    {
        $result = true;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file)
            $result &= $this->delete($file, $olderThan);
        return $result && rmdir($dir);
    }
}


$module = basename(__FILE__);
$log = new Logging();
$log->asInfo("$module: started");

if (PHP_SAPI !== 'cli') {
    $log->asErr('Trying to execute deleteTemp using "' . PHP_SAPI . '"');
    exit('Execution allowed only in CLI mode!');
}

if (isset($argv) && in_array('--force', $argv)) {
    $remove_after = '-1 day';
    $remove_after_crit = '-1 day';
} else {
    $remove_after = '7 days';
    $remove_after_crit = '1 day';

    $cnf = new Configuration();
    $err = $cnf->load();
    if ($err)
        echo $err;
    else {
        if (isset($cnf->data['tmp_remove_after']))
            $remove_after = $cnf->data['tmp_remove_after'];
        if (isset($cnf->data['tmp_remove_after_crit']))
            $remove_after_crit = $cnf->data['tmp_remove_after_crit'];
    }
}

$deleteTemp = new DeleteTemp(PATH_TO_ROOT . 'temp' . DIRECTORY_SEPARATOR, $log, $module);

$deleteTemp->runDelete("[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]", $remove_after_crit, GLOB_ONLYDIR);

$deleteTemp->runDelete("*.export.tmp", $remove_after_crit, GLOB_ONLYDIR);
$deleteTemp->runDelete("??????????????????????.pdf", $remove_after_crit);
$deleteTemp->runDelete("images_*.zip", $remove_after_crit);
$deleteTemp->runDelete("study_*.zip", $remove_after_crit);
$deleteTemp->runDelete("cached/*.*", $remove_after_crit);
$deleteTemp->runDelete("*.tmpprint", $remove_after_crit);
$deleteTemp->runDelete("*.tmp.dcm", $remove_after_crit);
$deleteTemp->runDelete("*.tiff", $remove_after_crit);

$deleteTemp->runDelete("*.thumbnail-150.jpg", $remove_after);
$deleteTemp->runDelete("*.thumbnail-50.jpg", $remove_after);
$deleteTemp->runDelete("*.thumbnail*.jpg", $remove_after);
$deleteTemp->runDelete("*.image-tmp.jpg", $remove_after);
$deleteTemp->runDelete("*.smooth", $remove_after);
$deleteTemp->runDelete("*.prep", $remove_after);
$deleteTemp->runDelete("*.mpeg", $remove_after);
$deleteTemp->runDelete("*.tmp", $remove_after);
$deleteTemp->runDelete("*.flv", $remove_after);
$deleteTemp->runDelete("*.mp4", $remove_after);
$deleteTemp->runDelete("*.out", $remove_after);
$deleteTemp->runDelete("*.fwd", $remove_after);
