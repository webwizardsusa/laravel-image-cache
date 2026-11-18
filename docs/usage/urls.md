---
title: Showing Images
weight: 30
description: Get a Variant's URL, what it points at, and how to avoid N+1 queries.
---

```blade
<img src="{{ $image->url('thumb') }}" alt="">
```

To render an `<img>` with its size and a srcset, use the [image component](responsive-images.md).

`$image->url($style)` (or `ImageCache::url($image, $style)`) returns:

- **The disk URL of the Variant** when it has been generated (`Storage::url()`), so your web server, S3 or CDN serves it
  without touching PHP.
- **The package's route**, `/image-cache/{style}/{ulid}`, when it hasn't. The first request to the route generates
  the Variant and redirects to its disk URL.

So the URL for a Variant changes once it exists. Store Images (or their ids) in your records, not URLs, and ask for
the URL when you render.

## The route

| Request | Response |
|---|---|
| A registered style and an existing Image | `302` to the Variant's disk URL, after generating it if needed |
| A style that isn't registered, or no Image with that ULID | `404` |
| A private Image without a valid, unexpired signature | `403` |
| Another request is generating the same Variant, for longer than `lock.timeout` | `503` with `Retry-After` |

Requests that arrive together for the same missing Variant generate it once: the first takes a lock, and the others
wait for it (up to `lock.timeout` seconds) and are then redirected to the Variant it made. For this to hold across
servers, `lock.store` must be a cache store they share.

Set `route.middleware` if the route needs middleware of your own, such as rate limiting.

## Avoiding N+1 queries

Working out a URL needs the Image's Variants. When you render many Images, eager-load them:

```php
$articles = Article::with('heroImage.variants')->get();
```

`$image->variant($style)` returns the `Variant` row, or `null` when it hasn't been generated. It uses the loaded
`variants` relation when there is one.
