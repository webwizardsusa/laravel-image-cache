---
title: Commands
weight: 60
description: image-cache:flush, image-cache:regenerate and image-cache:prune.
---

## `image-cache:flush`

```bash
php artisan image-cache:flush                 # every style
php artisan image-cache:flush banner          # one style
php artisan image-cache:flush banner thumb    # several styles
php artisan image-cache:flush --pretend       # count, delete nothing
```

Deletes Variants and their files, so they're generated again the next time they're requested. Run it after changing
a style. Styles that are no longer registered can be flushed by name too, to clean up their Variants.

## `image-cache:regenerate`

```bash
php artisan image-cache:regenerate                 # every registered style
php artisan image-cache:regenerate banner thumb    # the named styles
php artisan image-cache:regenerate banner --missing
php artisan image-cache:regenerate --queue
php artisan image-cache:regenerate --pretend       # count, generate nothing
```

Generates Variants again after a style has changed, replacing their files in place, so their URLs keep working
throughout (unlike flushing, which sends them back to the route until they're requested).

- Only Variants that exist are regenerated. `--missing` also generates the Variants that don't exist yet, for every
  Image.
- `--queue` generates each one on the queue set in `image-cache.queue` instead of right away.
- Every name must be a registered style. A Variant that fails, such as one whose original is missing, is reported and
  skipped, and the command exits with a failure status once the rest are done.

## `image-cache:prune`

```bash
php artisan image-cache:prune
php artisan image-cache:prune --pretend  # list, delete nothing
```

Deletes Images that have been Orphaned for longer than `prune.grace_hours`, with their Variants and files. See
[usage tracking](usage-tracking.md).
