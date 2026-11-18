---
title: Introduction
weight: 10
description: What Laravel Image Cache does, a short example, and what it needs to run.
---

Laravel Image Cache renders stored images in named styles, the way Drupal's image styles do.

- An **Image** is an original you store with the package. It's a database row plus a file on a disk, and it never
  changes afterwards.
- An **Image Style** is a PHP class that transforms an image with [Intervention Image](https://image.intervention.io/v4),
  such as cropping it to 300×300 or converting it to WebP.
- A **Variant** is one Image rendered in one Image Style. Variants are tracked in the database, so deleting an Image
  deletes its Variants too.

Each Image Style decides when its Variants are generated: the first time one is requested, while the Image is being
stored, or on the queue. Whichever it is, a Variant that's missing when it's requested is generated then, under a lock,
so that requests arriving together generate it only once.

Images are public or private. Private Images get temporary signed URLs. Optionally, models can report which Images
they use, so Images that are no longer used anywhere are deleted automatically.

## A short example

Define an Image Style:

```php
namespace App\ImageStyles;

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

Register it in `config/image-cache.php`:

```php
'styles' => [
    App\ImageStyles\ThumbStyle::class,
],
```

Store an Image and ask for its URL:

```php
use Webwizardsusa\ImageCache\Models\Image;

$image = Image::store($request->file('photo'));

$image->url('thumb');
```

## Requirements

- PHP 8.3 or later.
- Laravel 12 or 13.
- Intervention Image 4, with the `gd` extension (the default), `imagick`, or the `intervention/image-driver-vips`
  package.
