---
title: Private Images
weight: 40
description: Store Images that only people given a temporary link can see.
---

```php
use Webwizardsusa\ImageCache\Enums\Visibility;

$image = Image::store($request->file('scan'), Visibility::Private);

$image->url('thumb');
```

Private originals and Variants are written to the private disks (`disks.private.*`, `local` by default), and their
URLs are always temporary, lasting `private.url_ttl` minutes (5 by default):

- When the Variant exists, the URL is the disk's temporary URL (`Storage::temporaryUrl()`).
- When it doesn't, the URL is a temporary signed URL to the package's route. The route generates the Variant and
  redirects to a fresh temporary disk URL. Without a valid signature, or after it expires, the route answers `403`.

Your application decides who may see a private Image when it asks for the URL. The package's only check is the
signature: anyone holding an unexpired URL can see the Image. Make URLs for the people allowed to see it, when you
render the page for them.

## Disks

The private Variants disk must be able to make temporary URLs. `s3` can, and so can a `local` disk with `serve`
turned on (new Laravel applications have it on for `local`):

```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
],
```

If it can't, asking for a private Image's URL throws a `TemporaryUrlsNotSupportedException` that names the disk.

## Changing an Image's Visibility

Images are immutable. To make a private Image public, or the other way round, store it again with the new Visibility
and delete the old Image.
