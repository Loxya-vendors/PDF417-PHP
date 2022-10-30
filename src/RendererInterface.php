<?php
declare(strict_types=1);

namespace BigFish\PDF417;

interface RendererInterface
{
    public function render(BarcodeData $data);
}
