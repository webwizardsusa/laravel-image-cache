# Laravel Image Cache

[![Latest version on Packagist](https://img.shields.io/packagist/v/webwizardsusa/laravel-image-cache.svg)](https://packagist.org/packages/webwizardsusa/laravel-image-cache)
[![Tests](https://github.com/webwizardsusa/laravel-image-cache/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/webwizardsusa/laravel-image-cache/actions/workflows/tests.yml)
[![Total downloads](https://img.shields.io/packagist/dt/webwizardsusa/laravel-image-cache.svg)](https://packagist.org/packages/webwizardsusa/laravel-image-cache/stats)
[![PHP version](https://img.shields.io/packagist/dependency-v/webwizardsusa/laravel-image-cache/php.svg)](https://packagist.org/packages/webwizardsusa/laravel-image-cache)
[![Laravel version](https://img.shields.io/packagist/dependency-v/webwizardsusa/laravel-image-cache/illuminate/support.svg?label=Laravel)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/webwizardsusa/laravel-image-cache.svg)](https://github.com/webwizardsusa/laravel-image-cache/blob/main/LICENSE.md)

Drupal-style image styles for Laravel. **Image Styles** are classes that transform an **Image** with Intervention
Image into a **Variant**. Variants are generated on demand (under a lock, so only once), while the Image is stored, or
on a queue, and they're tracked so deleting an Image deletes them too. Images are public or private, with temporary
URLs for private ones. Optionally, models report the Images they use, and Images nothing uses any more are pruned.

Requires PHP 8.3+, Laravel 12 or 13, and the `gd` extension (or `imagick`, or the vips driver).

```bash
composer require webwizardsusa/laravel-image-cache
php artisan vendor:publish --tag=image-cache-config
php artisan vendor:publish --tag=image-cache-migrations
php artisan migrate
```

## Usage

```php
use Intervention\Image\Interfaces\ImageInterface;
use Webwizardsusa\ImageCache\ImageStyle;

class ThumbStyle extends ImageStyle
{
    public static function name(): string
    {
        return 'thumb';
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->cover(300, 300);
    }
}
```

List it in `config/image-cache.php` (`'styles' => [ThumbStyle::class]`), then store Images and show their Variants:

```php
use Webwizardsusa\ImageCache\Models\Image;

$image = Image::store($request->file('photo'));

$image->url('thumb');
```

```blade
<x-image-cache::image :image="$image" image-style="card" :srcset="['thumb', 'card', 'banner']" alt="A cat" />
```

## Documentation

Read the full documentation in the [`docs` folder](https://github.com/webwizardsusa/laravel-image-cache/tree/main/docs).
