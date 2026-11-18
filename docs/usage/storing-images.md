---
title: Storing Images
weight: 20
description: Store an Image from an upload or a local file, and delete it again.
---

## Storing

```php
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Models\Image;

$image = Image::store($request->file('photo'));

$image = Image::store(storage_path('app/import/photo.jpg'), Visibility::Private, directory: 'avatars');
```

`Image::store()` (or `ImageCache::store()`) takes an uploaded file, an `SplFileInfo`, or the path of a local file, then:

1. It checks that the file is an image Intervention can read. If it isn't, it throws an `InvalidImageException` and
   stores nothing.
2. It writes the file to the disk for its Visibility, in `originals_directory` or the directory you give, under a
   random 40-character name. The extension comes from the file's contents, never from its name.
3. It creates the Image row, as the configured [Image model](../extending/custom-image-model.md).
4. It generates the Variants of `OnCreate` styles. If one fails, the Image and its files are deleted again and the
   exception is rethrown.
5. It queues the Variants of `Queued` styles, once the surrounding transaction (if any) commits.

The Visibility defaults to `default_visibility` in the config.

## Validating uploads

`store()` rejects files that aren't images, but limits such as size and dimensions are up to your validation:

```php
$request->validate([
    'photo' => ['required', 'image', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
]);
```

## Deleting

```php
$image->delete();
```

This deletes the Image's Variants and Usages, and its original and Variant files once the transaction commits. If the
transaction rolls back, the files stay.

Images can't be edited. Store a new Image to replace the picture or change its Visibility, then delete the old one.
