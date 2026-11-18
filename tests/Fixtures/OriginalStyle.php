<?php

namespace Webwizardsusa\ImageCache\Tests\Fixtures;

use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\ImageStyle;

class OriginalStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'original';
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image;
    }
}
