<?php

namespace Webwizardsusa\ImageCache;

use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;

/**
 * A named set of transformations that renders a Variant of an Image. Variants are stored and found by the name the
 * class declares, never by the class name, so renaming or moving the class doesn't cut its Variants off.
 */
abstract class ImageStyle
{
    /**
     * The style's name: a slug (lowercase letters, digits, `-` and `_`) used in Variant paths and URLs.
     */
    abstract public static function name(): string;

    /**
     * Transforms the original into the Variant, such as `$image->cover(300, 300)`.
     */
    abstract public function apply(ImageInterface $image): ImageInterface;

    public function strategy(): GenerationStrategy
    {
        return GenerationStrategy::OnDemand;
    }

    /**
     * The format Variants are encoded in, or null to keep the original's.
     */
    public function format(): ?Format
    {
        return null;
    }

    /**
     * The encoding quality (0 to 100), or null for the encoder's default. Ignored by formats without one.
     */
    public function quality(): ?int
    {
        return null;
    }
}
