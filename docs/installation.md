---
title: Installation
weight: 30
description: Install the package, publish its config and migration, and create the image cache tables.
---

## Install the package

```bash
composer require webwizardsusa/laravel-image-cache
```

The service provider and the `ImageCache` facade alias are registered through package discovery.

## Publish the config

```bash
php artisan vendor:publish --tag=image-cache-config
```

This writes `config/image-cache.php`. See the [configuration reference](configuration.md) for each key.

## Publish and run the migration

The package never runs its migration from the vendor folder. You publish it into your application, where you can
change it before running it:

```bash
php artisan vendor:publish --tag=image-cache-migrations
php artisan migrate
```

> [!IMPORTANT]
> The migration reads the table names from `image-cache.tables` when it runs, not when it's published. If you want
> table names other than `images`, `image_variants` and `image_usages`, set them in `config/image-cache.php`
> **before** you migrate, and don't change them afterwards.

The migration creates three tables:

- **`images`**: `id`, a unique `ulid`, `disk`, `path`, `visibility`, `mime_type`, `width`, `height`, `size`, `hash`,
  `orphaned_at` and timestamps.
- **`image_variants`**: `id`, `image_id`, `style`, `disk`, `path`, `mime_type`, `width`, `height`, `size` and
  timestamps. Each Image has at most one row per style.
- **`image_usages`**: `image_id` and the `usable` morph columns, with timestamps.

## Disks

Public Images go to the `public` disk by default, so run `php artisan storage:link` if you haven't already. Private
Images go to the `local` disk, which needs `serve` turned on to make temporary URLs (it is in new Laravel
applications). See [private Images](usage/private-images.md).
