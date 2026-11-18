---
title: Configuration
weight: 40
description: Every key in config/image-cache.php.
---

| Key | Default | What it does |
|---|---|---|
| `image_model` | `Models\Image` | The Image model. See [a custom Image model](extending/custom-image-model.md). |
| `tables.images` | `images` | The Images table. |
| `tables.variants` | `image_variants` | The Variants table. |
| `tables.usages` | `image_usages` | The Usages table. |
| `driver` | `gd` (`IMAGE_CACHE_DRIVER`) | The Intervention driver: `gd`, `imagick`, `vips`, or a driver class name. |
| `styles` | `[]` | The application's Image Style classes. |
| `disks.public.originals` | `public` | Where public originals are written. |
| `disks.public.variants` | `public` | Where public Variants are written. |
| `disks.private.originals` | `local` | Where private originals are written. |
| `disks.private.variants` | `local` | Where private Variants are written. It must make temporary URLs. |
| `default_visibility` | `public` | The Visibility of Images stored without one. |
| `originals_directory` | `images` | The directory originals are written to, unless `store()` is given one. |
| `variants_prefix` | `image-cache` | The directory Variants are written under. |
| `route.prefix` | `image-cache` | The URL prefix of the route that generates missing Variants. |
| `route.middleware` | `[]` | Middleware for that route. |
| `route.name` | `image-cache.variant` | That route's name. |
| `lock.store` | `null` | The cache store generation locks are taken on (`null` for the default). |
| `lock.timeout` | `10` | How many seconds a request waits for the lock before answering 503. |
| `lock.ttl` | `60` | How many seconds a lock is held at most. |
| `queue.connection` | `null` | The queue connection for Queued styles. |
| `queue.queue` | `null` | The queue for Queued styles. |
| `private.url_ttl` | `5` | How many minutes private Images' temporary URLs last. |
| `prune.grace_hours` | `24` | How many hours an Image stays Orphaned before `image-cache:prune` deletes it. |

Every Image and Variant records the disk it was written to, so changing the disks only affects files written from then
on.

The generation lock is only atomic across servers when `lock.store` is a store they share, such as `redis`,
`database` or `memcached`.
