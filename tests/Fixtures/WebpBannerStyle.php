<?php

namespace Webwizardsusa\ImageCache\Tests\Fixtures;

use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;
use Webwizardsusa\ImageCache\ImageStyle;

class WebpBannerStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'banner';
    }

    public function width(): ?int
    {
        return 400;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->scale(width: 400);
    }

    public function strategy(): GenerationStrategy
    {
        return GenerationStrategy::OnCreate;
    }

    public function format(): ?Format
    {
        return Format::WEBP;
    }

    public function quality(): ?int
    {
        return 70;
    }
}
