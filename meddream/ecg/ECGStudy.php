<?php
namespace Softneta\MedDream\Core\ECG;

class ECGStudy
{
    private static $SAMPLE_INTERPRETATIONS = array(
        'SB' => 'c',  // signed 8 bit linear
        'UB' => 'C',  // unsigned 8 bit linear
        'SS' => 's',  // signed 16 bit linear
        'US' => 'S',  // unsigned 16 bit linear
    );

    private $tags;
    private $meta;
    private $data;

    /**
     * @var ECGWaveform[]
     */
    private $waveforms;

    private $samples;

    private function __construct($tags, $meta)
    {
        $this->tags = $tags;
        $this->meta = $meta;
        $this->data = isset($meta[0x003a]) ? $meta[0x003a] : $meta;
        self::parseChannelMeta($this->data[0x0200]);
    }

    private function parseChannelMeta($channels)
    {
        foreach ($channels[0xfffe][0xe000] as $i => $channel)
            $this->waveforms[$i] = new ECGWaveform($channel[0x003a]);
    }

    private function getSampleInterpretation()
    {
        $data = $this->getData();
        return $data[0x5400][0x1006]['data'];
    }

    public function getNumberOfChannels()
    {
        return $this->data[0x0005]['data'][0];
    }

    public function getSamplingFrequency()
    {
        return $this->data[0x001a]['data'];
    }

    public function getWaveforms()
    {
        return $this->waveforms;
    }

    public function getMinValue()
    {
        return min(array_map(function (ECGWaveform $waveform) {
            return $waveform->getMinValue();
        }, $this->waveforms));
    }

    public function getMaxValue()
    {
        return max(array_map(function (ECGWaveform $waveform) {
            return $waveform->getMaxValue();
        }, $this->waveforms));
    }

    private function loadData($dcmFile)
    {
        $format = self::$SAMPLE_INTERPRETATIONS[$this->getSampleInterpretation()] . '*';
        $numOfChannels = $this->getNumberOfChannels();
        $samples = $this->readSamples($dcmFile);

        $channel = 0;
        $this->samples = array();
        foreach (unpack($format, $samples) as $sample) {
            $this->waveforms[$channel]->addSample($sample);
            if (++$channel >= $numOfChannels)
                $channel = 0;
        }
    }

    private function readSamples($dcmFile)
    {
        $data = $this->getData();
        $tag = $data[0x5400][0x1010];
        return self::readFile($dcmFile, $tag['offset'], $tag['vl']);
    }

    private function getData() {
        return isset($this->meta[0x5400]) ? $this->meta : $this->tags[0x5400][0x0100][0xfffe][0xe000];
    }

    private static function readFile($file, $offset, $size)
    {
        $fp = fopen($file, 'rb');
        fseek($fp, $offset);
        $data = fread($fp, $size);
        fclose($fp);
        return $data;
    }

    public function clipSides()
    {
        $samples = current($this->waveforms)->getSamples();
        $left = self::findFirstInteresting($samples);
        $right = self::findLastInteresting($samples, count($samples) - 1);
        $length = $right - $left + 1;

        foreach ($this->waveforms as $waveform)
            $waveform->slice($left, $length);
    }

    private static function findFirstInteresting($samples, $from = 0)
    {
        $index = $from;
        $value = $samples[$index];
        while ($samples[++$index] === $value) ;
        return ($index - 1) === $from ? ($index - 1) : self::findFirstInteresting($samples, $index);
    }

    private static function findLastInteresting($samples, $from)
    {
        $index = $from;
        $value = $samples[$index];
        while ($samples[--$index] === $value) ;
        return ($index + 1) === $from ? ($index + 1) : self::findLastInteresting($samples, $index);
    }

    public function passFilter()
    {
        $N = $this->waveforms[0]->getSensitivity() < 0.01 ? 6 : 2;
        $low = min($this->waveforms[0]->getFilterLowFrequency(), $this->waveforms[0]->getFilterHighFrequency());
        $high = max($this->waveforms[0]->getFilterLowFrequency(), $this->waveforms[0]->getFilterHighFrequency());
        $noche = $this->waveforms[0]->getNotchFilterFrequency();

        $b1 = array();
        $a1 = array();
        $b2 = array();
        $b3 = array();
        $a2 = array(1);
        if ($noche > 0) {
            $coff = $this->nocheCoff($noche * 10, $noche);
            $b1 = $coff[0];
            $a1 = $coff[1];
        }
        if ($low > 0)
            $b2 = $this->FIR1LOW($N, ($low / $this->getSamplingFrequency()));
        if ($high > 0)
            $b3 = $this->FIR1HIGH($N, ($high / $this->getSamplingFrequency()));

        foreach ($this->waveforms as $waveform) {
            $y = $waveform->getSamples();
            if ($noche > 0) $y = $this->filter($b1, $a1, count($y), $y, 0, 1);
            if ($low > 0) $y = $this->filter($b2, $a2, count($y), $y, 0, 1);
            if ($high > 0) $y = $this->filter($b3, $a2, count($y), $y, 0, 1);
            $waveform->setSamples($y);
        }
    }

    private function nocheCoff($fs, $w0)
    {
        $fn = $fs / 2;
        $w0 = $w0 * pi() / $fn;
        $band = 5 * pi() / $fn;
        $k1 = cos($w0) * (-1);
        $k2 = (1 - tan($band / 2)) / (1 + tan($band / 2));
        $a = array(2, (2 * $k1 * (1 + $k2)), (2 * $k2));
        $b = array((1 + $k2), (2 * $k1 * (1 + $k2)), (1 + $k2));
        return array($b, $a);
    }

    private function FIR1LOW($N, $Wn)
    {
        $gain = 0;
        $N = $N + 1;
        $Pr_L = $N;
        $odd = $N - ($N / 2) * 2;
        $c1 = $Wn / 2;
        $nhlf = ($N + 1) / 2;
        $b = array();
        if ($odd)
            array_push($b, 2 * $c1);

        for ($i = $odd; $i < $nhlf; $i++) {
            $c = pi() * ($i + 0.5 * (1 - $odd));
            array_push($b, sin(2 * $c1 * $c) / $c);
        }

        $bb = array();
        for ($i = $odd, $j = $nhlf - 1; $i < $nhlf; $i++, $j--)
            array_push($bb, $b[$j]);

        for ($i = $nhlf, $j = 0; $i < $Pr_L; $i++, $j++)
            array_push($bb, $b[$j]);

        $wind = 0.54 - 0.46 * cos((2 * pi() * $i) / ($N - 1));
        for ($i = 0; $i < $Pr_L; $i++) {
            $bb[$i] = $bb[$i] * $wind;
            $gain += $bb[$i];
        }
        $gain = abs($gain);
        for ($i = 0; $i < $Pr_L; $i++)
            $bb[$i] = $bb[$i] / $gain;
        return $bb;
    }

    private function FIR1HIGH($N, $Wn1)
    {
        $gain = 0;
        $N = $N + 2;
        $Pr_L = $N;
        $odd = 1;
        $c1 = $Wn1 / 2;
        $nhlf = ($N + 1) / 2;
        $b = array();
        array_push($b, 2 * $c1);

        for ($i = $odd; $i < $nhlf; $i++) {
            $c = pi() * $i;
            array_push($b, sin((2 * $c1 * $c)) / $c);
        }

        $bb = array();
        array_push($bb, $b[0]);
        for ($i = $odd, $j = $nhlf - 1; $i < $nhlf; $i++, $j--)
            array_push($bb, $b[$j]);
        for ($i = $nhlf, $j = 0; $i < $Pr_L; $i++, $j++)
            array_push($bb, $b[$j]);

        $wind = 0.54 - 0.46 * cos((2 * pi() * $i) / ($N - 1));
        for ($i = 0; $i < $Pr_L; $i++) {
            $bb[$i] = $bb[$i] * $wind;
            if (($nhlf - 1) != $i)
                $bb[$i] = $bb[$i] * (-1);
            else
                $bb[$i] = 1 - $bb[$i];
            $gain += $bb[$i] * pow((-1), $i);
        }
        for ($i = 0; $i < $Pr_L; $i++)
            $bb[$i] = $bb[$i] / abs($gain);
        return $bb;
    }

    public function removeBaselineDrift()
    {
        $p = $this->choseAmph();
        $qrs = $this->getRPoints($this->waveforms[$p]->getSamples());

        $count = $this->getNumberOfChannels();
        for ($p = 0; $p < $count; $p++) {
            if (count($qrs) > 0) {
                $i = 0;
                foreach ($qrs as $item) {
                    if ($i == 0)
                        $this->fixFilterKnots($p, $i, $item, 1);
                    else
                        $this->fixFilterKnots($p, $i, $item);
                    $i = $item;
                }
                $this->fixFilterKnots($p, $i, count($this->waveforms[$p]->getSamples()) - 1, 2);
            }
        }
    }

    private function choseAmph()
    {
        $result = array(0, 0);
        $count = $this->getNumberOfChannels();
        for ($i = 0; $i < $count; $i++) {
            $samples = $this->waveforms[$i]->getSamples();
            $testSize = count($samples) / 20;

            $sum = 0;
            for ($j = 0; $j < $testSize; $j++)
                $sum += $samples[$j];

            $tmp = $sum / $testSize;

            if (abs($result[1]) < abs($tmp)) {
                $result[0] = $i;
                $result[1] = $tmp;
            }
        }

        return $result[0];
    }

    private function getRPoints($arr)
    {
        $B1 = array(1, 0, 0, 0, 0, 0, -2, 0, 0, 0, 0, 0, 1);
        $A1 = array(1, -2, 1);
        $y1 = $this->filter($B1, $A1, count($arr), $arr, 0, 1);

        $B2 = array(-1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 32, -32, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1);
        $A2 = array(32, -32);
        $y2 = $this->filter($B2, $A2, count($y1), $y1, 0, 1);

        $B3 = array(0.2, 0.1, 0, -0.1, -0.2);
        $A3 = array(1);
        $y3 = $this->filter($B3, $A3, count($y2), $y2, 0, 1);

        $y4 = array();
        for ($i = 0; $i < count($y1); $i++)
            $y4[$i] = (int)(10 * pow($y3[$i], 2));

        $average = 0;
        $dataTemp = array();
        for ($i = 32; $i < count($y4); $i++) {
            $sum = 0;
            for ($j = $i - 32; $j < $i; $j++)
                $sum += $y4[$j];
            $num = $sum / 32;
            $average += $num;
            $dataTemp[] = array('ii' => $i, 'nums' => $num);
        }
        $average /= count($y1);

        $pp = 0;
        $pass = false;
        $rpoints = array();
        for ($i = 0; $i < count($dataTemp); $i++) {
            if (($dataTemp[$i]['nums'] < $average) && ($pp < 0 || ($dataTemp[$i]['ii'] - $pp) > 0)) {
                if (!$pass) {
                    $rpoints[] = $dataTemp[$i]['ii'];
                    $pass = true;
                    $pp = $dataTemp[$i]['ii'];
                }
            } else
                $pass = false;
        }

        if (count($rpoints) > 2)
            $rpoints[0] = $rpoints[1];

        return $rpoints;
    }

    private function filter($b, $a, $wid, $x, $cut = 0, $leds = 12)
    {
        $y = array();
        $alen = count($a);
        $blen = count($b);
        for ($i = 0; $i < $leds; $i++) {
            $p = ($wid * $i);
            for ($n = 0; $n < $blen; $n++) {
                array_push($y, 0);
                for ($k = 0; $k <= $n; $k++) {
                    $y[$n + $p] += ($b[$k] * ($x[$n - $k + $p]));
                    if (count($a) > 1)
                        if (($k >= 1) && ($n >= 1) && ($k < $alen))
                            $y[$n + $p] -= ($a[$k] * $y[$n - $k + $p]);
                }

                if ($alen > 0)
                    $y[$n + $p] = $y[$n + $p] / $a[0];

            }

            for ($n = $blen; $n < ($wid - $cut); $n++) {
                array_push($y, 0);
                for ($k = 0; $k < $blen; $k++) {
                    $y[$n + $p] += ($b[$k] * ($x[$n - $k + $p]));
                    if ($alen > 1)
                        if (($k >= 1) && ($k < count($a)))
                            $y[$n + $p] -= ($a[$k] * $y[$n - $k + $p]);
                }
                if ($alen > 0)
                    $y[$n + $p] = $y[$n + $p] / $a[0];
            }
            if ($cut > 0)
                for ($n = ($wid - $cut); $n < $wid; $n++)
                    array_push($y, $x[$n + $p]);
        }
        $x = null;
        return $y;
    }

    private function fixFilterKnots($c, $x1, $x2, $nochange = 0)
    {
        $samples = &$this->waveforms[$c]->getSamples();
        $y1 = $samples[$x1];
        $y2 = $samples[$x2];
        if ($nochange == 1)
            $y1 = $y2;
        if ($nochange == 2)
            $y2 = $y1;
        $difY = $y2 - $y1;
        $difX = $x2 - $x1;
        if ($difX != 0) {
            for ($k = $x1; $k < $x2; $k++) {
                $point = ((($difY / $difX) * $k) - (($difY / $difX) * $x1)) + $y1;
                $samples[$k] = $samples[$k] - $point;
            }
        }
    }

    public static function parse($dcmFile, $tags, $meta)
    {
        $study = new ECGStudy($tags, $meta);
        $study->loadData($dcmFile);
        return $study;
    }
}
