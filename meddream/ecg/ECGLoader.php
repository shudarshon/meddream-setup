<?php
namespace Softneta\MedDream\Core\ECG;

use Softneta\MedDream\Core\DICOM\DicomTagParser;

class ECGLoader
{
    private $dcmFile;

    public function __construct($dcmFile)
    {
        $this->dcmFile = $dcmFile;
    }

    public function load($filtered)
    {
        $tags = DicomTagParser::parseFile($this->dcmFile, 6);

        if (strtoupper($tags[0x0008][0x0060]['data']) != 'ECG')
            throw new \Exception("Not an ECG image");

        $study = self::getStudy($tags, $filtered);
        $waveforms = self::formatWaveforms($study->getWaveforms());
        return array(
            'meta' => array_merge(self::getMeta($tags), array(
                'channelCount' => $study->getNumberOfChannels(),
                'sampleCount' => count($waveforms[0]['attributes']['samples']),
                'frequency' => $study->getSamplingFrequency(),
                'minValue' => $study->getMinValue(),
                'maxValue' => $study->getMaxValue()
            )),
            'data' => $waveforms,
        );
    }

    private static function formatWaveforms($waveforms)
    {
        return array_map(function (ECGWaveform $waveform) {
            return array(
                'type' => 'waveform',
                'id' => $waveform->getId(),
                'attributes' => array(
                    'label' => $waveform->getLabel(),
                    'samples' => $waveform->getSamples()
                )
            );
        }, $waveforms);
    }

    private function getStudy($tags, $filtered, $originality = 'ORIGINAL')
    {
        $waveform = null;
        $waveformSequence = $tags[0x5400][0x0100];
        foreach ($waveformSequence[0xfffe][0xe000] as $item)
            if ((isset($item[0x0004]) && $item[0x0004]['data'] == $originality) ||
                (isset($item[0x003a]) && isset($item[0x003a][0x0004]) && $item[0x003a][0x0004]['data'] == $originality)
            ) return self::parseStudy($this->dcmFile, $tags, $item, $filtered);
        return null;
    }

    private static function parseStudy($dcmFile, $tags, $item, $filtered)
    {
        $ecg = ECGStudy::parse($dcmFile, $tags, $item);
        if ($filtered) {
            $ecg->clipSides();
            $ecg->removeBaselineDrift();
        }
        return $ecg;
    }

    private static function getMeta($tags)
    {
        $dateTime = date_parse($tags[0x0008][0x0020]['data'] . ' ' . $tags[0x0008][0x0030]['data']);
        return array(
            'studyDateTime' => self::formatDate($dateTime),
            'seriesDescription' => isset($tags[0x0008][0x103e]['data']) ? $tags[0x0008][0x103e]['data'] : null,
            'patientName' => self::formatPatientName($tags[0x0010][0x0010]['data']),
            'patientId' => $tags[0x0010][0x0020]['data'],
            'studyId' => isset($tags[0x0020][0x0010]) ? $tags[0x0020][0x0010]['data'] : null
        );
    }

    private static function formatDate($dt)
    {
        return sprintf("%d.%02d.%02d %d:%02d:%02d", $dt['year'], $dt['month'], $dt['day'], $dt['hour'], $dt['minute'], $dt['second']);
    }

    private static function formatPatientName($name)
    {
        return trim(str_replace('^', ' ', $name));
    }
}
