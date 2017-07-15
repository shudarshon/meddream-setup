<?php
namespace Softneta\MedDream\Core\DICOM;

class DicomTagParser
{
    private $tags = array();

    private $sequences = array();

    /**
     * @var self
     */
    private $childParser = null;

    private $lastValue = null;

    private function processTag($tag)
    {
        $this->process($tag, isset($tag['level']) ? $tag['level'] : 0);
    }

    private function process($tag, $level)
    {
        if ($level > 0) {
            if ($this->childParser == null) {
                if ($this->lastValue['data'] != null)
                    throw new \Exception("Parent node is a value and can't be converted into a sequence");
                $this->childParser = new self();
            }
            $this->childParser->process($tag, $level - 1);
        } else {
            $this->addChildren();
            $this->addTag($tag['group'], $tag['element'], array(
                'vl' => $tag['vl'],
                'offset' => $tag['offset'],
                'data' => isset($tag['data']) ? $tag['data'] : null,
            ));
        }
    }

    private function addTag($group, $element, $value)
    {
        if (!isset($this->tags[$group]))
            $this->tags[$group] = array();

        $groupArray = &$this->tags[$group];

        if (isset($groupArray[$element])) {
            if (!in_array("$group:$element", $this->sequences))
                $this->addSequence($group, $element);
            $groupArray[$element][] = &$value;
        } else
            $groupArray[$element] = &$value;

        $this->lastValue = &$value;
    }

    private function addSequence($group, $element)
    {
        $this->sequences[] = "$group:$element";
        $this->tags[$group][$element] = array($this->tags[$group][$element]);
    }

    private function getTags()
    {
        $this->addChildren();
        return $this->tags;
    }

    private function addChildren()
    {
        if ($this->childParser != null) {
            $this->lastValue = $this->childParser->getTags();
            $this->childParser = null;
        }
    }

    public static function parseFile($dcmFile, $depth = 0)
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return self::parse(meddream_get_tags(dirname(__DIR__), $dcmFile, $depth));
    }

    public static function parse($data)
    {
        if (!empty($data['error']))
            throw new \Exception('Error ' . $data['error'], $data['error']);

        $parser = new self();
        foreach ($data['tags'] as $tag)
            $parser->processTag($tag);

        return $parser->getTags();
    }
}
