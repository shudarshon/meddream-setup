<?php

class RawAdam7
{
    private static $ADAM7_LUT = array(
        'start_y' => array(0, 0, 4, 0, 2, 0, 1),
        'start_x' => array(0, 4, 0, 2, 0, 1, 0),
        'step_y' => array(8, 8, 8, 4, 4, 2, 2),
        'step_x' => array(8, 8, 4, 4, 2, 2, 1),
    );

    private $medDream;
    private $steps;

    private $uid;
    private $meta;

    public function __construct($uid, MedDreamCmd $medDream, $steps = 7)
    {
        if ($steps < 0)
            throw new RawAdam7Exception("Adam can not be completed in negative number of steps, asked for $steps");

        if ($steps % 2 === 0)
            throw new RawAdam7Exception("Adam requires odd number of steps, asked for $steps");

        $this->medDream = $medDream;
        $this->steps = $steps;

        $this->uid = $uid;
        $this->meta = $this->medDream->getMeta($this->uid);
    }

    public function __destruct()
    {
        unset($this->meta);
    }

    public function printStep($step)
    {
        if ($step < 1 || $step > $this->steps)
            throw new RawAdam7Exception("Invalid step $step, valid range is 1-{$this->steps}");
        $this->medDream->printRaw($this->uid, 0, $this->getStepOffset($step), $this->getStepSize($step - 1));
    }

    private function getStepOffset($step)
    {
        $offset = 0;
        for ($i = 0; $i < $step - 1; $i++)
            $offset += $this->getStepSize($i);
        return $offset;
    }

    private function getStepSize($step)
    {
        $start_y = self::$ADAM7_LUT['start_y'][$step];
        $start_x = self::$ADAM7_LUT['start_x'][$step];
        $step_y = self::$ADAM7_LUT['step_y'][$step];
        $step_x = self::$ADAM7_LUT['step_x'][$step];

        $width = $this->meta['columns'];
        $height = $this->meta['rows'];
        $bpp = $this->meta['bitsallocated'] / 8;

        return (ceil(($width - $start_x) / $step_x) * ceil(($height - $start_y) / $step_y)) * $bpp;
    }
}
