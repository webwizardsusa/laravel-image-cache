<?php

namespace Webwizardsusa\ImageCache\Tests\Fixtures;

use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;
use Webwizardsusa\ImageCache\ImageStyle;

class FailingStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'failing';
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        throw new RuntimeException('The style failed.');
    }

    public function strategy(): GenerationStrategy
    {
        return GenerationStrategy::OnCreate;
    }
}
