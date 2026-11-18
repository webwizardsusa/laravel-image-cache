---
title: Responsive images
weight: 35
description: Build srcset attributes from Image Styles, and show Images with the image component.
---

## The image component

```blade
<x-image-cache::image :image="$post->heroImage" image-style="card" alt="A cat" class="rounded-lg" />
```

It renders an `<img>` of the Image's Variant in the `image-style`, with:

- `width` and `height`, so the page doesn't shift when the image loads: the Variant's own once it exists, otherwise
  the size the style [declares](image-styles.md#declaring-a-size);
- `loading="lazy"` and `decoding="async"`, which you can change with `loading="eager"` and `decoding="auto"`;
- every other attribute you give it, such as `alt`, `class`, `style` or `fetchpriority`.

Private Images get temporary URLs, as everywhere else.

## srcset

Give the component a list of styles, and the browser picks the one that fits:

```blade
<x-image-cache::image :image="$image" image-style="banner" :srcset="['thumb', 'card', 'banner']"
    sizes="(min-width: 768px) 50vw, 100vw" alt="A cat" />
```

> [!IMPORTANT]
> Browsers treat every candidate in a srcset as the same picture at a different size. Use styles that keep the same
> aspect ratio, such as scales to several widths, and not a mix of crops: a square thumbnail in a banner's srcset would
> be shown where the banner was expected.

`image-style` is the `src`, for browsers that don't support `srcset`. Each candidate's width is its Variant's once it
has been generated, otherwise the width its style declares. A style with neither is left out, and styles that come out
the same width (a small Image scaled down by several) are listed once.

Missing Variants are generated on demand, as usual: the browser only fetches the one candidate it picks.

Without `sizes`, a lazy image gets `sizes="auto, 100vw"`. Browsers that support `auto` pick a candidate for the size
the image is laid out at; others assume the full viewport width. An eager image needs `sizes` of its own.

### Named sets

Define sets once in `config/image-cache.php`, and use them by name:

```php
'srcsets' => [
    'hero' => ['card', 'banner'],
],
```

```blade
<x-image-cache::image :image="$image" image-style="banner" srcset="hero" alt="" />
```

### Without the component

```php
$image->srcset('hero');                       // "…/card 400w, …/banner 1200w"
$image->srcset(['thumb', 'card']);
ImageCache::srcset($image, 'hero', maxWidth: 800);
ImageCache::dimensions($image, 'card');       // ['width' => 400, 'height' => 250], or null
```

`maxWidth` leaves out wider candidates. Asking for a set that isn't configured, or a style that isn't registered,
throws.
