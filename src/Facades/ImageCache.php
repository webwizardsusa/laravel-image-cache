<?php

namespace Webwizardsusa\ImageCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string ...$classes)
 * @method static \Webwizardsusa\ImageCache\ImageStyle style(string|\Webwizardsusa\ImageCache\ImageStyle $style)
 * @method static bool has(string|\Webwizardsusa\ImageCache\ImageStyle $style)
 * @method static array<string, \Webwizardsusa\ImageCache\ImageStyle> styles()
 * @method static class-string<\Webwizardsusa\ImageCache\Models\Image> imageModel()
 * @method static \Illuminate\Database\Eloquent\Builder<\Webwizardsusa\ImageCache\Models\Image> newImageQuery()
 * @method static \Intervention\Image\ImageManager imageManager()
 * @method static \Webwizardsusa\ImageCache\Models\Image store(\Illuminate\Http\UploadedFile|\SplFileInfo|string $file, ?\Webwizardsusa\ImageCache\Enums\Visibility $visibility = null, ?string $directory = null)
 * @method static string url(\Webwizardsusa\ImageCache\Models\Image $image, string|\Webwizardsusa\ImageCache\ImageStyle $style)
 * @method static string variantUrl(\Webwizardsusa\ImageCache\Models\Variant $variant, \Webwizardsusa\ImageCache\Enums\Visibility $visibility)
 * @method static array<int, string> srcsetStyles(array|string $set)
 * @method static array<int, array{style: string, url: string, width: int}> srcsetCandidates(\Webwizardsusa\ImageCache\Models\Image $image, array|string $set, ?int $maxWidth = null)
 * @method static string srcset(\Webwizardsusa\ImageCache\Models\Image $image, array|string $set, ?int $maxWidth = null)
 * @method static array{width: int, height: int}|null dimensions(\Webwizardsusa\ImageCache\Models\Image $image, string|\Webwizardsusa\ImageCache\ImageStyle $style)
 * @method static string diskFor(\Webwizardsusa\ImageCache\Enums\Visibility $visibility, string $kind)
 * @method static string variantPath(\Webwizardsusa\ImageCache\Models\Image $image, string $style, \Intervention\Image\Format $format)
 *
 * @see \Webwizardsusa\ImageCache\ImageCache
 */
class ImageCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Webwizardsusa\ImageCache\ImageCache::class;
    }
}
