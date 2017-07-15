<?php

class MedDreamCmd
{
    private $baseDir;
    private $flags;

    /**
     * @var \Softneta\MedDream\Core\Pacs\PacsStructure
     */
    private $pacsStructure;

    /**
     * @var \Softneta\MedDream\Core\Pacs\PacsPreload
     */
    private $pacsPreload;

    /**
     * @var \Softneta\MedDream\Core\Study
     */
    private $study;

    /**
     * @var \Softneta\MedDream\Core\Logging
     */
    private $log;

    private $cacheDir;

    public function __construct($baseDir = __DIR__, $flags = null, $pacsStructure, $pacsPreload, $study, $log)
    {
        $this->baseDir = $baseDir;
        $this->flags = $flags;

        $this->pacsStructure = $pacsStructure;
        $this->pacsPreload = $pacsPreload;
        $this->study = $study;
        $this->log = $log;

        $this->cacheDir = $baseDir . '/temp/' . date('Ymd');
    }

    public function getMeta($uid)
    {
        $meta = $this->getInstanceMeta($uid);
        if (!$this->isCached($meta['uid']))
            $this->addToCache($meta['uid'], $uid, $meta['path']);
        return unserialize(file_get_contents($this->getTempPath($meta['uid'])));
    }

    public function printRaw($uid, $frameNum = 0, $offset = null, $size = null)
    {
        $meta = $this->getInstanceMeta($uid);
        if (!$this->isCached($meta['uid'], $frameNum))
            $this->addToCache($meta['uid'], $uid, $meta['path'], $frameNum, true, !$this->isCached($meta['uid']));

        self::printFileContent($this->getTempPath($meta['uid'], $frameNum), $offset, $size);
    }

    private static function printFileContent($path, $offset, $size)
    {
        if (!$offset && !$size)
            readfile($path);
        else
        {
            /* give away the file in small chunks, so that a disappeared client
               connection can be detected ASAP
             */
            $fp = fopen($path, 'rb');
            if ($fp)
            {
                fseek($fp, $offset);
                while (!feof($fp) && $size && !connection_aborted())
                {
                    $bs = 8192;
                    if ($bs > $size)
                        $bs = $size;
                    echo fread($fp, $bs);
                    $size -= $bs;
                }
                fclose($fp);
            }
        }
    }

    private function isCached($uid, $frameNum = null)
    {
        return file_exists($this->getTempPath($uid, $frameNum));
    }

    private function addToCache($uid, $originalUid, $path, $frameNum = 0, $cacheImage = true, $cacheMeta = true)
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir))
            throw new RuntimeException("Failed to add study to cache");

        $this->log->asDump("meddream_get_raw2('{$path}')");
        $tmpFile = $cacheImage ? $this->getTempPath($uid, $frameNum) : null;
        /** @noinspection PhpUndefinedFunctionInspection */
        $result = meddream_get_raw2($this->baseDir, $path, $tmpFile, $frameNum, $this->flags);
        $this->log->asDump('meddream_get_raw2: ', $result);

        if ($result['error'] !== 0)
            throw new RuntimeException($result['error']);

        /* only if meddream_get_raw2 succeeded: otherwise the file is left for troubleshooting */
        $this->pacsPreload->removeFetchedFile($path);

        if ($cacheMeta) {
            self::validateInstanceMetaData($result);

            $infoLabels = $this->study->getInfoLabels($originalUid);
            if ($infoLabels['error'] !== '')
                throw new RuntimeException($infoLabels['error']);

            $result['labels'] = $infoLabels['labels'];
            file_put_contents($this->getTempPath($uid), serialize($result));
        }
        unset($result);
    }

    private function getTempPath($uid, $frameNum = null)
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $uid . ($frameNum === null
                ? '.ser' : '_' . ($this->flags & ~MEDDREAM_GETRAW_PROGRESSIVE) . '.' . $frameNum . '.raw');
    }

    private function validateInstanceMetaData(&$data)
    {
        if (!isset($data['windowcenter']))
            $data['windowcenter'] = 0;
        if (!isset($data['windowwidth']))
            $data['windowwidth'] = 0;
        if (!isset($data['rescaleslope']))
            $data['rescaleslope'] = 0;
        if (!isset($data['rescaleintercept']))
            $data['rescaleintercept'] = 0;
        if (!isset($data['pixelspacing'][0]))
            $data['pixelspacing'][0] = 0;
        if (!isset($data['pixelspacing'][1]))
            $data['pixelspacing'][1] = 0;
        if (!isset($data['slicethickness']))
            $data['slicethickness'] = 1;
        if (!isset($data['frametime']))
            $data['frametime'] = 66.66;
        if (!isset($data['imageposition'][0]))
            $data['imageposition'][0] = 0;
        if (!isset($data['imageposition'][1]))
            $data['imageposition'][1] = 0;
        if (!isset($data['imageposition'][2]))
            $data['imageposition'][2] = 0;
        if (!isset($data['imageorientation'][0]))
            $data['imageorientation'][0] = 0;
        if (!isset($data['imageorientation'][1]))
            $data['imageorientation'][1] = 0;
        if (!isset($data['imageorientation'][2]))
            $data['imageorientation'][2] = 0;
        if (!isset($data['imageorientation'][3]))
            $data['imageorientation'][3] = 0;
        if (!isset($data['imageorientation'][4]))
            $data['imageorientation'][4] = 0;
        if (!isset($data['imageorientation'][5]))
            $data['imageorientation'][5] = 0;
        if (!isset($data['gantrytilt']))
            $data['gantrytilt'] = 0;
        if (!isset($data['pixelmax']))
            $data['pixelmax'] = 256;  //maxPixelValue = Math.pow(2, bitsStored)
        if (!isset($data['pixelmin']))
            $data['pixelmin'] = 0;
        if (!isset($data['wlpixelmax']))
            $data['wlpixelmax'] = 0;  //maxPixelValue = Math.pow(2, bitsStored)
        if (!isset($data['wlpixelmin']))
            $data['wlpixelmin'] = 0;

        //report view
        if ($data['windowwidth'] == 1)
            $data['windowcenter'] = $data['windowcenter'] - 6;

        if ($data['rescaleslope'] == 0)
            $data['rescaleslope'] = 1;

        if ($data['windowcenter'] == 0 && $data['windowwidth'] == 0) //&& $data['xfersyntax'] != '1.2.840.10008.1.2.5')
        {
            $pixelmax = $data['pixelmax'];
            $pixelmin = $data['pixelmin'];

            if ($data['wlpixelmax'] != 0 || $data['wlpixelmin'] != 0) {
                $pixelmax = $data['wlpixelmax'];
                $pixelmin = $data['wlpixelmin'];
            }

            $data['windowcenter'] = ($pixelmax * $data['rescaleslope'] + $pixelmin * $data['rescaleslope']) / 2 + $data['rescaleintercept'];
            $data['windowwidth'] = $pixelmax * $data['rescaleslope'] - $pixelmin * $data['rescaleslope'];

        }

        if ($data['photometric'] === 'PALETTE COLOR' || $data['error'] != '0') {
            $data['samplesperpixel'] = 3;
        }

        if ($data['xfersyntax'] == '1.2.840.10008.1.2.4.91' && $data['bitsstored'] > 8) {
            $data['samplesperpixel'] = 1;
        }

        if (($data['samplesperpixel'] === 3 /* ||  $result['bitsstored'] === 8 */))   //todo .. = 3 pakeiti i $data['xfersyntax'] == "1.2.840.10008.1.2.4.50" ir patestuoti
        {
            $data['pixelmax'] = 256;
            $data['pixelmin'] = 0;
            $data['windowcenter'] = 128;
            $data['windowwidth'] = 256;
            $data['rescaleslope'] = 1;
            $data['rescaleintercept'] = 0;
        }
    }

    private function getInstanceMeta($uid)
    {
        $meta = $this->pacsStructure->instanceGetMetadata($uid);
        if (strlen($meta['error']))
            throw new RuntimeException($meta['error']);
        return $meta;
    }
}
