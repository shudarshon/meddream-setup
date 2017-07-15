<?php
namespace Softneta\MedDream\Core\ECG;

class ECGWaveform
{
    private static $UNITS = array(
        'mV' => 1,
        'uV' => 0.001,
    );

    private $id;
    private $label;

    private $baseline;
    private $sensitivity;
    private $sensitivityCorrectionFactor;
    private $scale;

    private $filterLowFrequency;
    private $filterHighFrequency;
    private $notchFilterFrequency;

    private $samples = array();

    public function __construct($meta)
    {
        $this->id = self::getValue($meta[0x0208], 0x0100);
        $this->label = self::getValue($meta[0x0208], 0x0104);

        $this->baseline = $meta[0x0213]['data'];
        $this->sensitivity = $meta[0x0210]['data'];
        $this->sensitivityCorrectionFactor = $meta[0x0212]['data'];
        $this->scale = self::$UNITS[self::getValue($meta[0x0211], 0x0100)];

        $this->filterLowFrequency = isset($meta[0x0220]) ? $meta[0x0220]['data'] : 0;
        $this->filterHighFrequency = isset($meta[0x0221]) ? $meta[0x0221]['data'] : 0;
        $this->notchFilterFrequency = isset($meta[0x0222]) ? $meta[0x0222]['data'] : 0;
    }

    private static function getValue($group, $key)
    {
        return $group[0xfffe][0xe000][0x0008][$key]['data'];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return substr($this->label, 5);
    }

    public function getSensitivity()
    {
        return $this->sensitivity;
    }

    public function getFilterLowFrequency()
    {
        return $this->filterLowFrequency;
    }

    public function getFilterHighFrequency()
    {
        return $this->filterHighFrequency;
    }

    public function getNotchFilterFrequency()
    {
        return $this->notchFilterFrequency;
    }

    public function &getSamples()
    {
        return $this->samples;
    }

    public function setSamples($samples)
    {
        $this->samples = $samples;
    }

    public function getMinValue()
    {
        return min($this->samples);
    }

    public function getMaxValue()
    {
        return max($this->samples);
    }

    public function addSample($sample)
    {
        $this->samples[] = ($sample + $this->baseline) *
            $this->sensitivityCorrectionFactor *
            $this->sensitivity *
            $this->scale;
    }

    public function slice($offset, $length = null)
    {
        $this->samples = array_slice($this->samples, $offset, $length);
    }
}
