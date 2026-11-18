<?php

namespace Webwizardsusa\ImageCache\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image as ImageModel;

/**
 * `<x-image-cache::image :image="$image" image-style="card" srcset="hero" alt="…" />`: an `<img>` of an Image's Variant
 * in a style, with a srcset of other styles when given, `width` and `height` to keep the layout from shifting, and lazy
 * loading by default. Other attributes, such as `alt` and `class`, are passed through.
 *
 * Without `sizes`, a lazy image with a srcset gets `sizes="auto, 100vw"`, so browsers that support it pick a
 * candidate for the size the image is shown at, and others assume the full viewport width.
 */
class Image extends Component
{
    /**
     * @param  array<string> | string | null  $srcset  style names, or the name of a set in `image-cache.srcsets`
     */
    public function __construct(
        public ImageModel $image,
        public string $imageStyle,
        public array|string|null $srcset = null,
        public ?string $sizes = null,
        public ?string $loading = 'lazy',
        public ?string $decoding = 'async',
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function imageAttributes(): array
    {
        $srcset = $this->srcset !== null && $this->srcset !== [] ? ImageCache::srcset($this->image, $this->srcset) : '';
        $dimensions = ImageCache::dimensions($this->image, $this->imageStyle);

        return array_filter([
            'src' => ImageCache::url($this->image, $this->imageStyle),
            'srcset' => $srcset,
            'sizes' => $srcset !== '' ? ($this->sizes ?? ($this->loading === 'lazy' ? 'auto, 100vw' : null)) : null,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'loading' => $this->loading,
            'decoding' => $this->decoding,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function render(): View
    {
        return view('image-cache::components.image');
    }
}
