<?php
namespace Softneta\MedDream\Core\ECG;

class ECGImage
{
    private $data;
    private $columns;

    private $width;
    private $height;
    private $length;
    private $minValue;
    private $maxValue;
    private $sampleCount;
    private $sampleLength;
    private $waveformCount;

    /**
     * @var ScaledImage
     */
    private $img;

    /**
     * @param ECGStudy $ecg
     * @param int $dpi
     * @param int $mmPerSecond
     * @param int $mmPerMillivolt
     * @param int $columns
     */
    private function __construct($ecg, $dpi, $mmPerSecond = 25, $mmPerMillivolt = 10, $columns = 1)
    {
        $this->data = $ecg['data'];
        $this->columns = $columns;
        $this->mmPerMillivolt = $mmPerMillivolt;
        $this->setup($ecg['meta'], $dpi, $mmPerSecond, $mmPerMillivolt);
        $this->init();
    }

    private function setup($meta, $dpi, $mmPerSecond, $mmPerMillivolt)
    {
        $this->sampleCount = $meta['sampleCount'];
        $this->waveformCount = $meta['channelCount'];

        $frequency = $meta['frequency'];
        $time = $this->sampleCount / $frequency;

        $this->sampleLength = $mmPerSecond / $frequency;
        $this->minValue = abs($meta['minValue'] * $mmPerMillivolt);
        $this->maxValue = abs($meta['maxValue'] * $mmPerMillivolt);
        $this->length = $time * $mmPerSecond;

        $this->width = $this->length * $this->columns;
        $this->height = $this->waveformCount * ($this->minValue + $this->maxValue) / $this->columns;

        $this->img = new ScaledImage($this->width, $this->height, $dpi);
        $this->img->addColor('gray', 185, 185, 185);
        $this->img->addColor('darkGray', 107, 107, 107);
        $this->img->addColor('lightGray', 217, 217, 217);
    }

    private function init()
    {
        $this->drawGrid();
        $padding = $this->minValue + $this->maxValue;
        $rows = $this->waveformCount / $this->columns;

        for ($x = 0, $i = 0; $x < $this->columns; $x++)
            for ($y = 0; $y < $rows; $y++, $i++)
                $this->drawWaveform($this->data[$i]['attributes'], $this->length * $x, $this->maxValue + $padding * $y);
    }

    private function drawGrid()
    {
        for ($x = 1, $n = 1; $x < $this->width; $x++, $n++)
            $this->img->line($x, 0, $x, $this->height, $n % 25 == 0 ? 'darkGray' : $this->getGridLineColor($n));
        for ($y = 1, $n = 1; $y < $this->height; $y++, $n++)
            $this->img->line(0, $y, $this->width, $y, $this->getGridLineColor($n));
        for ($x = $this->length; $x < $this->width; $x += $this->length)
            $this->img->dashedLine($x, 0, $x, $this->height);
    }

    private function getGridLineColor($lineNum)
    {
        return $lineNum % 5 == 0 ? 'gray' : 'lightGray';
    }

    private function drawWaveform($waveform, $left, $baseline)
    {
        $samples = $waveform['samples'];
        for ($x = 0, $i = 0; $i < $this->sampleCount - 1; $x += $this->sampleLength, $i++) {
            $this->img->line(
                $left + $x, $baseline - $samples[$i] * $this->mmPerMillivolt,
                $left + $x + $this->sampleLength, $baseline - $samples[$i + 1] * $this->mmPerMillivolt);
        }
        $this->img->string(5, $left + 2, $baseline - 8, $waveform['label']);
    }

    private function getImage()
    {
        return $this->img->getImg();
    }

    public static function create($ecg, $dpi, $mmps, $mmmV, $columns)
    {
        $img = new ECGImage($ecg, $dpi, $mmps, $mmmV, $columns);
        return $img->getImage();
    }
}
