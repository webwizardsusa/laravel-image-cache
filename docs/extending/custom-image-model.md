---
title: A custom Image model
weight: 10
description: Add columns and behaviour to Images with your own model.
---

To add columns to Images, such as alt text or a caption, extend the package's model:

```php
namespace App\Models;

use Webwizardsusa\ImageCache\Models\Image as BaseImage;

class Image extends BaseImage
{
    //
}
```

Point the config at it:

```php
'image_model' => App\Models\Image::class,
```

Add the columns to the published migration before you run it, or in a migration of your own.

From then on, `Image::store()`, the route, Variants' and Usages' `image` relation, `usedImages()` and the commands
all use your model. A class that doesn't extend `Webwizardsusa\ImageCache\Models\Image` throws an
`InvalidArgumentException` when the package reaches for it.

In your own code, `ImageCache::imageModel()` returns the configured class and `ImageCache::newImageQuery()` a query
for it.
