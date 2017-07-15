<?php
namespace Softneta\MedDream\Core\ECG;

class ScaledImage
{
    private $img;
    private $pxPerMillimeter;

    private $colors;

    public function __construct($width, $height, $dpi)
    {
        if (!function_exists('imagecreate'))
            throw new \Exception('GD2 extension missing');

        $this->pxPerMillimeter = $dpi / 25.4;
        $this->img = imagecreate($this->scale($width), $this->scale($height));
        $this->colors = array(
            'background' => $this->colorAllocate(255, 255, 255),
            'foreground' => $this->colorAllocate(0, 0, 0)
        );
    }

    public function addColor($name, $red, $green, $blue)
    {
        $this->colors[$name] = $this->colorAllocate($red, $green, $blue);
    }

    private function colorAllocate($red, $green, $blue)
    {
        return imagecolorallocate($this->img, $red, $green, $blue);
    }

    public function line($x1, $y1, $x2, $y2, $color = 'foreground')
    {
        return imageline($this->img,
            $this->scale($x1), $this->scale($y1),
            $this->scale($x2), $this->scale($y2),
            $this->colors[$color]);
    }

    public function dashedLine($x1, $y1, $x2, $y2, $color = 'foreground')
    {
        return imagedashedline($this->img,
            $this->scale($x1), $this->scale($y1),
            $this->scale($x2), $this->scale($y2),
            $this->colors[$color]);
    }

    public function string($font, $x, $y, $string, $color = 'foreground')
    {
        return imagestring($this->img, $font, $this->scale($x), $this->scale($y), $string, $this->colors[$color]);
    }

    private function scale($val)
    {
        return $val * $this->pxPerMillimeter;
    }

    public function getImg()
    {
        return $this->img;
    }
}
