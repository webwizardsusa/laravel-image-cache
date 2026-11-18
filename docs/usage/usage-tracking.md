---
title: Usage tracking
weight: 50
description: Record which records use which Images, and delete Images once nothing uses them.
---

Usage tracking is optional. Add the `TracksImageUsage` trait to a model, and say which Images it uses:

```php
use Illuminate\Database\Eloquent\Model;
use Webwizardsusa\ImageCache\Concerns\TracksImageUsage;

class Article extends Model
{
    use TracksImageUsage;

    public function referencedImages(): iterable
    {
        return $this->imagesFromAttributes('hero_image_id', 'thumbnail_image_id');
    }
}
```

`referencedImages()` returns the Images the record uses right now, as models or ids (nulls are skipped).
`imagesFromAttributes()` reads foreign key columns. For Images referenced in other ways, such as ids in a JSON column or
in HTML, return them however you find them:

```php
public function referencedImages(): iterable
{
    return [$this->hero_image_id, ...($this->gallery_image_ids ?? [])];
}
```

Every time the record is saved, its Usages are synced to what `referencedImages()` returns. When it's deleted, its
Usages are removed. A soft delete keeps them, so the Images aren't Orphaned while the record can be restored. You can
also sync by hand:

```php
$article->syncImageUsages();                // from referencedImages()
$article->syncImageUsages([$image, 42]);    // these exactly
```

`$article->usedImages()` is the record's Images as a relation, and `$image->usages()` an Image's Usages.

## Orphaned Images and pruning

When an Image loses its last Usage, its `orphaned_at` is set. When it gains a Usage again, it's cleared.
`$image->isOrphaned()` tells you whether it's set.

Schedule `image-cache:prune` to delete Images that have been Orphaned for longer than `prune.grace_hours` (24 by
default):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('image-cache:prune')->daily();
```

The grace period protects an Image that's moved from one record to another, or briefly unused while a form is edited.

An Image that has never had a Usage is never Orphaned, so the prune command never deletes Images you don't track, or
an upload that hasn't been saved onto a record yet.
