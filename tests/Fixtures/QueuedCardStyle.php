<?php

namespace Webwizardsusa\ImageCache\Tests\Fixtures;

use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;
use Webwizardsusa\ImageCache\ImageStyle;

class QueuedCardStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'card';
    }

    public function width(): ?int
    {
        return 300;
    }

    public function height(): ?int
    {
        return 200;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->cover(300, 200);
    }

    public function strategy(): GenerationStrategy
    {
        return GenerationStrategy::Queued;
    }
}
