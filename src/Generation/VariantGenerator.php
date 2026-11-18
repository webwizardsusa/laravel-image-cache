<?php

namespace Webwizardsusa\ImageCache\Generation;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use RuntimeException;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Models\Variant;

/**
 * Renders Images in Image Styles, writes the Variants to the disk for the Image's Visibility and records them.
 */
class VariantGenerator
{
    /**
     * The Variant in this style, generated first when it's missing. Generation happens under a lock, so requests that
     * arrive together generate it once: the others wait for it, and find it when they get the lock.
     *
     * @throws LockTimeoutException when the lock isn't free within `image-cache.lock.timeout` seconds
     */
    public function ensure(Image $image, string $style): Variant
    {
        $name = ImageCache::style($style)::name();

        if (($variant = $image->variant($name)) !== null) {
            return $variant;
        }

        return Cache::store(config('image-cache.lock.store'))
            ->lock("image-cache:{$image->getKey()}:{$name}", (int) config('image-cache.lock.ttl', 60))
            ->block((int) config('image-cache.lock.timeout', 10), fn (): Variant => $image->variants()->where('style', $name)->first()
                ?? $this->generate($image, $name));
    }

    /**
     * Generates the Variant in this style, replacing the one there is, without taking the lock.
     */
    public function generate(Image $image, string $style): Variant
    {
        $style = ImageCache::style($style);
        $name = $style::name();
        $contents = Storage::disk($image->disk)->get($image->path);

        if ($contents === null) {
            throw new RuntimeException("The original of Image [{$image->getKey()}] is missing from [{$image->disk}:{$image->path}].");
        }

        $rendered = $style->apply(ImageCache::imageManager()->decodeBinary($contents));
        $format = $style->format() ?? Format::create($image->mime_type);
        $encoded = $rendered->encodeUsingFormat($format, ...array_filter(['quality' => $style->quality()], fn (?int $value): bool => $value !== null));

        $disk = ImageCache::diskFor($image->visibility, 'variants');
        $path = ImageCache::variantPath($image, $name, $format);

        Storage::disk($disk)->put($path, $encoded->toString());

        $previous = $image->variants()->where('style', $name)->first();

        if ($previous !== null && ($previous->disk !== $disk || $previous->path !== $path)) {
            Storage::disk($previous->disk)->delete($previous->path);
        }

        $variant = $image->variants()->updateOrCreate(['style' => $name], [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $format->mediaType()->value,
            'width' => $rendered->width(),
            'height' => $rendered->height(),
            'size' => $encoded->size(),
        ]);

        $image->unsetRelation('variants');

        return $variant;
    }
}
