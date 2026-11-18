---
title: Image Styles
weight: 10
description: Define an Image Style, choose its Generation Strategy, format and quality, and register it.
---

An Image Style extends `Webwizardsusa\ImageCache\ImageStyle` and declares two things: its name, and what it does to an
image.

```php
namespace App\ImageStyles;

use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;
use Webwizardsusa\ImageCache\ImageStyle;

class BannerStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'banner';
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->scaleDown(width: 1600);
    }

    public function strategy(): GenerationStrategy
    {
        return GenerationStrategy::Queued;
    }

    public function format(): ?Format
    {
        return Format::WEBP;
    }

    public function quality(): ?int
    {
        return 80;
    }
}
```

- **`name()`** is a slug of lowercase letters, digits, `-` and `_`. It appears in Variant paths and URLs, and Variants
  are found by it, so keep it stable. Renaming or moving the class is fine.
- **`apply()`** gets the original as an Intervention image and returns the Variant. Anything Intervention can do is
  available: `cover()`, `scale()`, `crop()`, `blur()`, `place()` for watermarks, and so on.
- **`strategy()`** is `GenerationStrategy::OnDemand` by default. See [concepts](../concepts.md#generation-strategies).
- **`format()`** is `null` by default, which keeps the original's format.
- **`quality()`** is `null` by default, which uses the encoder's default. Formats without a quality ignore it.

## Registering styles

List the application's styles in `config/image-cache.php`:

```php
'styles' => [
    App\ImageStyles\ThumbStyle::class,
    App\ImageStyles\BannerStyle::class,
],
```

Packages register theirs from a service provider's `boot()` method:

```php
use Webwizardsusa\ImageCache\Facades\ImageCache;

ImageCache::register(BannerStyle::class);
```

Registering a class that doesn't extend `ImageStyle`, a name that isn't a slug, or two classes with the same name
throws an `InvalidStyleException`.

## Looking styles up

```php
ImageCache::style('banner');            // by name
ImageCache::style(BannerStyle::class);  // by class
ImageCache::has('banner');
ImageCache::styles();                   // every style, keyed by name
```

`style()` throws an `UnknownStyleException` for a name that isn't registered.

## Changing a style

Variants that already exist don't change when you change a style. Generate them again in place:

```bash
php artisan image-cache:regenerate banner
```

Or flush them, and they're generated again the next time they're requested:

```bash
php artisan image-cache:flush banner
```

See [commands](commands.md).
